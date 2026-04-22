<?php

declare(strict_types=1);

namespace Drupal\provus_performance;

/**
 * Value object for render-blocking optimizer options.
 *
 * Kept separate from config so the optimizer can be unit-tested without
 * a Drupal kernel, and so the subscriber can assemble options from
 * multiple config keys in one place.
 */
final class Options {

  /**
   * @param string $inlineCriticalCss
   *   Raw CSS to inline in a <style> block at the top of <head>. Empty
   *   string disables this transform.
   * @param bool $asyncStylesheets
   *   Whether to convert non-critical stylesheets to the preload-swap
   *   pattern.
   * @param bool $deferScripts
   *   Whether to add defer/async to non-critical external scripts in
   *   <head>.
   * @param array<int, string> $criticalCssPatterns
   *   Substrings that mark a <link> tag as critical (skipped by
   *   asyncStylesheets).
   * @param array<int, string> $criticalJsPatterns
   *   Substrings that mark a <script> tag as critical (skipped by
   *   deferScripts).
   * @param string $scriptStrategy
   *   Either "defer" or "async" — the attribute to add to non-critical
   *   scripts.
   */
  public function __construct(
    public readonly string $inlineCriticalCss = '',
    public readonly bool $asyncStylesheets = false,
    public readonly bool $deferScripts = false,
    public readonly array $criticalCssPatterns = [],
    public readonly array $criticalJsPatterns = [],
    public readonly string $scriptStrategy = 'defer',
  ) {}

  /**
   * True when at least one transform is switched on.
   */
  public function anyTransformEnabled(): bool {
    return $this->inlineCriticalCss !== '' || $this->asyncStylesheets || $this->deferScripts;
  }

}
