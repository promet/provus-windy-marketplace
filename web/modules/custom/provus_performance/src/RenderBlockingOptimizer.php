<?php

declare(strict_types=1);

namespace Drupal\provus_performance;

/**
 * Rewrites the HTML <head> to eliminate render-blocking CSS/JS.
 *
 * Three independent transforms are applied, each controlled by its own
 * config flag:
 *
 * 1. Inline critical CSS — a configured CSS string is injected as a
 *    <style> element at the top of <head>, so above-the-fold content
 *    can render before any external stylesheet arrives.
 * 2. Async non-critical stylesheets — each <link rel="stylesheet"> whose
 *    href/tag does not match a "critical" pattern is rewritten to the
 *    preload-swap pattern with a <noscript> fallback:
 *      <link rel="preload" as="style" href="..." onload="this.rel='stylesheet'">
 *      <noscript><link rel="stylesheet" href="..."></noscript>
 * 3. Defer non-critical scripts — each external <script src="..."> in
 *    <head> that isn't already async/defer/module and doesn't match a
 *    "critical" pattern gets a defer attribute added.
 */
final class RenderBlockingOptimizer {

  /**
   * Applies the enabled render-blocking transforms to the HTML.
   */
  public function optimize(string $html, Options $options): string {
    if (!$options->anyTransformEnabled()) {
      return $html;
    }

    // Locate the single <head>…</head> block; everything we rewrite lives
    // inside it. Using preg_match with PREG_OFFSET_CAPTURE gives us the
    // inner offset so we can substr_replace() back into the full document.
    if (!preg_match('/(<head\b[^>]*>)(.*?)(<\/head>)/is', $html, $m, PREG_OFFSET_CAPTURE)) {
      return $html;
    }
    $openTag = $m[1][0];
    $inner = $m[2][0];
    $innerStart = (int) $m[2][1];
    $originalInner = $inner;

    if ($options->inlineCriticalCss !== '') {
      $inner = $this->injectCriticalCss($inner, $options->inlineCriticalCss);
    }
    if ($options->asyncStylesheets) {
      $inner = $this->asyncStylesheets($inner, $options->criticalCssPatterns);
    }
    if ($options->deferScripts) {
      $inner = $this->deferScripts($inner, $options->criticalJsPatterns, $options->scriptStrategy);
    }

    if ($inner === $originalInner) {
      return $html;
    }

    // Preserve the original <head> opening tag (it may have attributes).
    unset($openTag);
    return substr_replace($html, $inner, $innerStart, strlen($originalInner));
  }

  /**
   * Prepends a <style data-provus-critical> block to the head contents.
   */
  private function injectCriticalCss(string $head, string $css): string {
    // Avoid injecting twice if some upstream already did so.
    if (str_contains($head, 'data-provus-critical')) {
      return $head;
    }
    $style = '<style data-provus-critical>' . $css . '</style>';
    return $style . $head;
  }

  /**
   * Converts render-blocking <link rel="stylesheet"> tags to preload-swap.
   *
   * @param string $head
   *   The inner HTML of <head>.
   * @param array<int, string> $criticalPatterns
   *   Substrings that, when found in a link tag, mark it as critical and
   *   leave it alone.
   */
  private function asyncStylesheets(string $head, array $criticalPatterns): string {
    return (string) preg_replace_callback(
      '/<link\b([^>]*)>/i',
      function (array $m) use ($criticalPatterns): string {
        $tag = $m[0];
        $attrString = $m[1];
        $attrs = $this->parseAttributes($attrString);

        $rel = strtolower($attrs['rel'] ?? '');
        if ($rel !== 'stylesheet') {
          return $tag;
        }
        // Leave print-only or non-screen stylesheets alone; they are not
        // render-blocking for the primary screen media.
        $media = strtolower($attrs['media'] ?? '');
        if ($media !== '' && $media !== 'all' && $media !== 'screen' && !str_contains($media, 'screen')) {
          return $tag;
        }
        if (empty($attrs['href'])) {
          return $tag;
        }
        if ($this->matchesAny($tag, $criticalPatterns)) {
          return $tag;
        }
        // Skip tags that already carry our marker (idempotent).
        if (isset($attrs['data-provus-async'])) {
          return $tag;
        }

        $preload = $attrs;
        $preload['rel'] = 'preload';
        $preload['as'] = 'style';
        $preload['onload'] = "this.onload=null;this.rel='stylesheet'";
        $preload['data-provus-async'] = '1';

        $fallback = $attrs;
        // The <noscript> fallback restores normal blocking behavior when
        // JS is disabled. preload without the onload swap would never
        // become a stylesheet in that case.
        return $this->buildLinkTag($preload)
          . '<noscript>' . $this->buildLinkTag($fallback) . '</noscript>';
      },
      $head,
    );
  }

  /**
   * Adds defer (or async) to external <script src="…"> tags in <head>.
   *
   * @param string $head
   *   The inner HTML of <head>.
   * @param array<int, string> $criticalPatterns
   *   Substrings that, when found in a script tag, mark it as critical.
   * @param string $strategy
   *   Either "defer" (default) or "async".
   */
  private function deferScripts(string $head, array $criticalPatterns, string $strategy): string {
    $strategy = $strategy === 'async' ? 'async' : 'defer';

    return (string) preg_replace_callback(
      '/<script\b([^>]*)>/i',
      function (array $m) use ($criticalPatterns, $strategy): string {
        $tag = $m[0];
        $attrString = $m[1];
        $attrs = $this->parseAttributes($attrString);

        // Inline scripts have no src; we can't defer them without moving
        // them to an external file, so we leave them alone. Drupal inline
        // settings / drupalSettings fall into this bucket.
        if (empty($attrs['src'])) {
          return $tag;
        }
        // Already non-blocking in some form — don't stack attributes.
        if (isset($attrs['async']) || isset($attrs['defer'])) {
          return $tag;
        }
        // ES modules are deferred by spec.
        $type = strtolower($attrs['type'] ?? '');
        if ($type === 'module') {
          return $tag;
        }
        // Non-executable script blocks (JSON-LD, templates, importmaps…)
        // aren't render-blocking and shouldn't be touched.
        if ($type !== '' && $type !== 'text/javascript' && $type !== 'application/javascript') {
          return $tag;
        }
        if ($this->matchesAny($tag, $criticalPatterns)) {
          return $tag;
        }

        // Insert the attribute just before the closing ">" to preserve
        // everything else exactly as it was written.
        return rtrim(substr($tag, 0, -1)) . ' ' . $strategy . '>';
      },
      $head,
    );
  }

  /**
   * Parses attributes from the body of a single tag.
   *
   * Values are returned HTML-decoded; boolean attributes get an empty
   * string value.
   *
   * @return array<string, string>
   */
  private function parseAttributes(string $attrString): array {
    $attrs = [];
    $pattern = '/\s+([a-zA-Z_:][a-zA-Z0-9_.:-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?/';
    if (preg_match_all($pattern, ' ' . $attrString, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $m) {
        $name = strtolower($m[1]);
        $value = $m[2] ?? $m[3] ?? $m[4] ?? '';
        $attrs[$name] = htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);
      }
    }
    return $attrs;
  }

  /**
   * Builds a <link> tag from an attribute map.
   *
   * @param array<string, string> $attrs
   */
  private function buildLinkTag(array $attrs): string {
    $out = '<link';
    foreach ($attrs as $name => $value) {
      if ($value === '') {
        $out .= ' ' . $name;
        continue;
      }
      $out .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5) . '"';
    }
    return $out . '>';
  }

  /**
   * Returns true if any pattern is a (case-insensitive) substring of $tag.
   *
   * @param array<int, string> $patterns
   */
  private function matchesAny(string $tag, array $patterns): bool {
    foreach ($patterns as $pattern) {
      if ($pattern !== '' && stripos($tag, $pattern) !== false) {
        return true;
      }
    }
    return false;
  }

}
