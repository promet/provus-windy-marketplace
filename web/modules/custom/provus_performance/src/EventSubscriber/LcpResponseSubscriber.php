<?php

declare(strict_types=1);

namespace Drupal\provus_performance\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rewrites HTML responses to improve LCP.
 *
 * Detects the first above-the-fold <img> in the rendered page, injects a
 * <link rel="preload" as="image"> into <head> (using imagesrcset/imagesizes
 * for responsive images), removes loading="lazy" from that image, and adds
 * fetchpriority="high". Also injects optional font preloads.
 */
final class LcpResponseSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AdminContext $adminContext,
    private readonly RouteMatchInterface $routeMatch,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run late so other subscribers (e.g., big_pipe placeholders) have already
    // finalized the HTML, but before PageCache middleware stores the response.
    return [
      KernelEvents::RESPONSE => ['onResponse', -256],
    ];
  }

  /**
   * Rewrites the response to inject LCP preload hints.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $config = $this->configFactory->get('provus_performance.settings');
    if (!$config->get('enabled')) {
      return;
    }

    $response = $event->getResponse();
    if (!$response instanceof HtmlResponse) {
      return;
    }
    if ($response->getStatusCode() !== 200) {
      return;
    }
    if ($this->adminContext->isAdminRoute($this->routeMatch->getRouteObject())) {
      return;
    }

    $request = $event->getRequest();
    $path = $request->getPathInfo();
    foreach ((array) $config->get('excluded_paths') as $pattern) {
      if ($this->pathMatches($path, (string) $pattern)) {
        return;
      }
    }

    $content = $response->getContent();
    if ($content === '' || $content === false) {
      return;
    }
    // Guard: only process HTML documents.
    if (stripos($content, '<html') === false || stripos($content, '<head') === false) {
      return;
    }

    $modified = $this->rewrite($content, $config);
    if ($modified !== null && $modified !== $content) {
      $response->setContent($modified);
    }
  }

  /**
   * Performs the HTML rewrite.
   *
   * @param string $html
   *   The original HTML response body.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   *
   * @return string|null
   *   The rewritten HTML, or null if nothing could be changed.
   */
  private function rewrite(string $html, $config): ?string {
    $bodyPos = stripos($html, '<body');
    if ($bodyPos === false) {
      return null;
    }

    // Narrow the search window to the first anchor that appears after <body>.
    $searchFrom = $bodyPos;
    foreach ((array) $config->get('content_anchors') as $anchor) {
      $anchor = (string) $anchor;
      if ($anchor === '') {
        continue;
      }
      $pos = stripos($html, $anchor, $bodyPos);
      if ($pos !== false && $pos > $searchFrom) {
        $searchFrom = $pos;
        break;
      }
    }

    $skipSelectors = array_filter(array_map('strval', (array) $config->get('skip_selectors')));
    $imgMatch = $this->findLcpImage($html, $searchFrom, $skipSelectors);
    // Fall back to searching from <body> if the narrowed window had no match.
    if ($imgMatch === null && $searchFrom !== $bodyPos) {
      $imgMatch = $this->findLcpImage($html, $bodyPos, $skipSelectors);
    }

    $headInjection = '';

    if ($imgMatch !== null) {
      [$imgTag, $imgPos, $attrs] = $imgMatch;

      $newAttrs = $attrs;
      if ($config->get('remove_lazy')) {
        unset($newAttrs['loading']);
      }
      if ($config->get('set_fetchpriority')) {
        $newAttrs['fetchpriority'] = 'high';
      }
      // Decoding async hints the browser to decode off the main thread.
      if (!isset($newAttrs['decoding'])) {
        $newAttrs['decoding'] = 'async';
      }

      $newImgTag = $this->buildImgTag($newAttrs, str_ends_with(rtrim($imgTag), '/>'));
      if ($newImgTag !== $imgTag) {
        $html = substr_replace($html, $newImgTag, $imgPos, strlen($imgTag));
      }

      $headInjection .= $this->buildImagePreload($attrs);
    }

    $headInjection .= $this->buildFontPreloads((array) $config->get('font_preloads'));

    if ($headInjection === '') {
      return $html;
    }

    // Inject right after <head ...> opening tag (only the first occurrence).
    $injected = preg_replace(
      '/(<head\b[^>]*>)/i',
      '$1' . $this->escapeReplacement($headInjection),
      $html,
      1,
      $count,
    );
    if ($injected === null || $count === 0) {
      $this->logger->warning('Failed to inject LCP preload tags into <head>.');
      return $html;
    }

    return $injected;
  }

  /**
   * Finds the first eligible <img> after the given offset.
   *
   * @return array{0:string,1:int,2:array<string,string>}|null
   *   A tuple of [img tag, offset, parsed attributes], or null if none found.
   */
  private function findLcpImage(string $html, int $offset, array $skipSelectors): ?array {
    $pattern = '/<img\b[^>]*>/i';
    $searchOffset = $offset;

    // Walk forward through matches until we find one that isn't excluded.
    while (preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE, $searchOffset)) {
      $tag = $m[0][0];
      $pos = (int) $m[0][1];

      if ($this->isInsideNoscript($html, $pos) || $this->isInsideComment($html, $pos)) {
        $searchOffset = $pos + strlen($tag);
        continue;
      }

      $excluded = false;
      foreach ($skipSelectors as $needle) {
        if ($needle !== '' && stripos($tag, $needle) !== false) {
          $excluded = true;
          break;
        }
      }
      if ($excluded) {
        $searchOffset = $pos + strlen($tag);
        continue;
      }

      $attrs = $this->parseAttributes($tag);
      if (empty($attrs['src']) && empty($attrs['srcset'])) {
        $searchOffset = $pos + strlen($tag);
        continue;
      }
      // Ignore tiny images (tracking pixels, spacers).
      if (isset($attrs['width']) && is_numeric($attrs['width']) && (int) $attrs['width'] > 0 && (int) $attrs['width'] < 64) {
        $searchOffset = $pos + strlen($tag);
        continue;
      }

      return [$tag, $pos, $attrs];
    }

    return null;
  }

  /**
   * Parses HTML attributes from a single tag string.
   *
   * Supports double-quoted, single-quoted, and unquoted values, plus
   * boolean attributes.
   *
   * @return array<string, string>
   *   Lower-cased attribute names mapped to decoded values.
   */
  private function parseAttributes(string $tag): array {
    $attrs = [];
    // Strip leading "<img" and trailing ">" / "/>" so we only scan attributes.
    $inner = preg_replace('/^<img\b/i', '', $tag);
    $inner = rtrim((string) $inner);
    $inner = preg_replace('/\/?>$/', '', $inner) ?? '';

    $pattern = '/\s+([a-zA-Z_:][a-zA-Z0-9_.:-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?/';
    if (preg_match_all($pattern, ' ' . $inner, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $m) {
        $name = strtolower($m[1]);
        $value = $m[2] ?? $m[3] ?? $m[4] ?? '';
        $attrs[$name] = htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);
      }
    }

    return $attrs;
  }

  /**
   * Builds an <img> tag from an attribute map.
   */
  private function buildImgTag(array $attrs, bool $selfClosing): string {
    $out = '<img';
    foreach ($attrs as $name => $value) {
      if ($value === '' && in_array($name, ['disabled', 'ismap'], true)) {
        $out .= ' ' . $name;
        continue;
      }
      $out .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5) . '"';
    }
    $out .= $selfClosing ? ' />' : '>';
    return $out;
  }

  /**
   * Builds a <link rel="preload" as="image"> tag for the LCP image.
   */
  private function buildImagePreload(array $attrs): string {
    $link = '<link rel="preload" as="image"';
    // Prefer a real src when available; imagesrcset/imagesizes provide
    // responsive hints the browser uses to pick the best candidate.
    if (!empty($attrs['src']) && !str_starts_with($attrs['src'], 'data:')) {
      $link .= ' href="' . htmlspecialchars($attrs['src'], ENT_QUOTES | ENT_HTML5) . '"';
    }
    if (!empty($attrs['srcset'])) {
      $link .= ' imagesrcset="' . htmlspecialchars($attrs['srcset'], ENT_QUOTES | ENT_HTML5) . '"';
    }
    if (!empty($attrs['sizes'])) {
      $link .= ' imagesizes="' . htmlspecialchars($attrs['sizes'], ENT_QUOTES | ENT_HTML5) . '"';
    }
    $link .= ' fetchpriority="high">';
    return $link;
  }

  /**
   * Builds <link rel="preload" as="font"> tags for configured font URLs.
   */
  private function buildFontPreloads(array $urls): string {
    $out = '';
    foreach ($urls as $url) {
      $url = trim((string) $url);
      if ($url === '') {
        continue;
      }
      $type = 'font/woff2';
      if (str_ends_with(strtolower($url), '.woff')) {
        $type = 'font/woff';
      }
      elseif (str_ends_with(strtolower($url), '.ttf')) {
        $type = 'font/ttf';
      }
      elseif (str_ends_with(strtolower($url), '.otf')) {
        $type = 'font/otf';
      }
      $out .= '<link rel="preload" as="font" type="' . $type
        . '" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5) . '" crossorigin>';
    }
    return $out;
  }

  /**
   * Checks whether a position falls inside a <noscript> block.
   */
  private function isInsideNoscript(string $html, int $pos): bool {
    $open = stripos($html, '<noscript', max(0, $pos - 4096));
    if ($open === false || $open > $pos) {
      return false;
    }
    $close = stripos($html, '</noscript>', $open);
    return $close !== false && $close > $pos;
  }

  /**
   * Checks whether a position falls inside an HTML comment.
   */
  private function isInsideComment(string $html, int $pos): bool {
    $open = strrpos(substr($html, 0, $pos), '<!--');
    if ($open === false) {
      return false;
    }
    $close = strpos($html, '-->', $open);
    return $close !== false && $close > $pos;
  }

  /**
   * Matches a path against a simple wildcard pattern (only "*" supported).
   */
  private function pathMatches(string $path, string $pattern): bool {
    if ($pattern === '') {
      return false;
    }
    $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
    return (bool) preg_match($regex, $path);
  }

  /**
   * Escapes a string so it is safe to use as the replacement argument for
   * preg_replace (i.e. backslashes and $ references are literalized).
   */
  private function escapeReplacement(string $value): string {
    return strtr($value, ['\\' => '\\\\', '$' => '\\$']);
  }

}
