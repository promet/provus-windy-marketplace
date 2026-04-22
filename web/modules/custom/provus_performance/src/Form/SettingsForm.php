<?php

declare(strict_types=1);

namespace Drupal\provus_performance\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Provus Performance.
 */
final class SettingsForm extends ConfigFormBase {

  private const SETTINGS = 'provus_performance.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'provus_performance_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::SETTINGS);

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable LCP optimization'),
      '#default_value' => (bool) $config->get('enabled'),
      '#description' => $this->t('When enabled, the first above-the-fold image will be preloaded and de-lazy-loaded on non-admin pages.'),
    ];

    $form['image'] = [
      '#type' => 'details',
      '#title' => $this->t('LCP image'),
      '#open' => TRUE,
    ];
    $form['image']['remove_lazy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove <code>loading="lazy"</code> from the LCP image'),
      '#default_value' => (bool) $config->get('remove_lazy'),
    ];
    $form['image']['set_fetchpriority'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set <code>fetchpriority="high"</code> on the LCP image'),
      '#default_value' => (bool) $config->get('set_fetchpriority'),
    ];
    $form['image']['content_anchors'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content anchors'),
      '#description' => $this->t('One per line. The first <em>image</em> found <strong>after</strong> the first matching anchor in the HTML source is treated as the LCP candidate. Typical anchors include <code>&lt;main</code>, <code>role="main"</code>, <code>class="hero</code>.'),
      '#default_value' => $this->linesFromArray($config->get('content_anchors')),
      '#rows' => 6,
    ];
    $form['image']['skip_selectors'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Skip if image tag contains'),
      '#description' => $this->t('One substring per line. Any <code>&lt;img&gt;</code> whose tag contains one of these strings is ignored when picking the LCP candidate. Use this to skip logos, icons, and tracking pixels.'),
      '#default_value' => $this->linesFromArray($config->get('skip_selectors')),
      '#rows' => 6,
    ];

    $form['fonts'] = [
      '#type' => 'details',
      '#title' => $this->t('Font preloads'),
      '#open' => TRUE,
    ];
    $form['fonts']['font_preloads'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Font URLs to preload'),
      '#description' => $this->t('One absolute or root-relative URL per line. Typically the 1-2 <code>.woff2</code> files used for above-the-fold text. Each emits <code>&lt;link rel="preload" as="font" crossorigin&gt;</code>.'),
      '#default_value' => $this->linesFromArray($config->get('font_preloads')),
      '#rows' => 4,
      '#placeholder' => "/themes/contrib/provus_edu_theme/fonts/inter-variable.woff2",
    ];

    $form['render_blocking'] = [
      '#type' => 'details',
      '#title' => $this->t('Render-blocking CSS & JS'),
      '#description' => $this->t('Eliminate render-blocking requests that delay First Contentful Paint. Enable each transform independently.'),
      '#open' => TRUE,
    ];
    $form['render_blocking']['async_stylesheets'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load non-critical stylesheets asynchronously'),
      '#default_value' => (bool) $config->get('async_stylesheets'),
      '#description' => $this->t('Rewrites <code>&lt;link rel="stylesheet"&gt;</code> in <code>&lt;head&gt;</code> to <code>&lt;link rel="preload" as="style" onload="this.rel=\'stylesheet\'"&gt;</code> with a <code>&lt;noscript&gt;</code> fallback. Use the "critical" list below to exempt blocking stylesheets that must arrive synchronously.'),
    ];
    $form['render_blocking']['critical_css_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Critical CSS — do not async'),
      '#description' => $this->t('One substring per line. Any <code>&lt;link rel="stylesheet"&gt;</code> whose tag contains one of these strings is left as a render-blocking request. Useful for the small above-the-fold stylesheet that must load synchronously.'),
      '#default_value' => $this->linesFromArray($config->get('critical_css_patterns')),
      '#rows' => 4,
      '#states' => [
        'visible' => [':input[name="async_stylesheets"]' => ['checked' => TRUE]],
      ],
    ];
    $form['render_blocking']['defer_scripts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Defer non-critical scripts in <code>&lt;head&gt;</code>'),
      '#default_value' => (bool) $config->get('defer_scripts'),
      '#description' => $this->t('Adds <code>defer</code> (or <code>async</code>) to each external <code>&lt;script src&gt;</code> that isn\'t already async/defer/type="module". Inline scripts and <code>drupalSettings</code> are not touched.'),
    ];
    $form['render_blocking']['script_strategy'] = [
      '#type' => 'radios',
      '#title' => $this->t('Script loading strategy'),
      '#options' => [
        'defer' => $this->t('<strong>defer</strong> — download in parallel, execute in order after HTML parsing (recommended)'),
        'async' => $this->t('<strong>async</strong> — download in parallel, execute as soon as downloaded (order not guaranteed)'),
      ],
      '#default_value' => ((string) $config->get('script_strategy')) ?: 'defer',
      '#states' => [
        'visible' => [':input[name="defer_scripts"]' => ['checked' => TRUE]],
      ],
    ];
    $form['render_blocking']['critical_js_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Critical JS — do not defer/async'),
      '#description' => $this->t('One substring per line. Any <code>&lt;script&gt;</code> whose tag contains one of these strings is left alone.'),
      '#default_value' => $this->linesFromArray($config->get('critical_js_patterns')),
      '#rows' => 4,
      '#states' => [
        'visible' => [':input[name="defer_scripts"]' => ['checked' => TRUE]],
      ],
    ];
    $form['render_blocking']['inline_critical_css'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Inline critical CSS'),
      '#description' => $this->t('CSS pasted here is injected as <code>&lt;style&gt;</code> at the top of <code>&lt;head&gt;</code> so the browser can render above-the-fold content without waiting for external stylesheets. Generate this with tools like <a href=":critical" target="_blank" rel="noopener">Critical</a> or Drupal\'s Advanced CSS/JS Aggregation module and paste the result here.', [
        ':critical' => 'https://github.com/addyosmani/critical',
      ]),
      '#default_value' => (string) $config->get('inline_critical_css'),
      '#rows' => 8,
      '#attributes' => ['spellcheck' => 'false'],
    ];

    $form['scope'] = [
      '#type' => 'details',
      '#title' => $this->t('Scope'),
      '#open' => FALSE,
    ];
    $form['scope']['excluded_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Excluded paths'),
      '#description' => $this->t('One pattern per line. <code>*</code> is a wildcard. Admin routes are always excluded.'),
      '#default_value' => $this->linesFromArray($config->get('excluded_paths')),
      '#rows' => 8,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $strategy = (string) $form_state->getValue('script_strategy');
    if ($strategy !== 'async') {
      $strategy = 'defer';
    }

    $this->config(self::SETTINGS)
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('remove_lazy', (bool) $form_state->getValue('remove_lazy'))
      ->set('set_fetchpriority', (bool) $form_state->getValue('set_fetchpriority'))
      ->set('content_anchors', $this->arrayFromLines($form_state->getValue('content_anchors')))
      ->set('skip_selectors', $this->arrayFromLines($form_state->getValue('skip_selectors')))
      ->set('font_preloads', $this->arrayFromLines($form_state->getValue('font_preloads')))
      ->set('excluded_paths', $this->arrayFromLines($form_state->getValue('excluded_paths')))
      ->set('async_stylesheets', (bool) $form_state->getValue('async_stylesheets'))
      ->set('defer_scripts', (bool) $form_state->getValue('defer_scripts'))
      ->set('script_strategy', $strategy)
      ->set('inline_critical_css', (string) $form_state->getValue('inline_critical_css'))
      ->set('critical_css_patterns', $this->arrayFromLines($form_state->getValue('critical_css_patterns')))
      ->set('critical_js_patterns', $this->arrayFromLines($form_state->getValue('critical_js_patterns')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Converts a stored array of lines to a textarea-friendly string.
   */
  private function linesFromArray($value): string {
    if (!is_array($value)) {
      return '';
    }
    return implode("\n", array_map('strval', $value));
  }

  /**
   * Converts a textarea string into a trimmed array of non-empty lines.
   *
   * @return array<int, string>
   */
  private function arrayFromLines($value): array {
    if (!is_string($value)) {
      return [];
    }
    $lines = preg_split('/\r?\n/', $value) ?: [];
    $lines = array_map('trim', $lines);
    return array_values(array_filter($lines, static fn ($l) => $l !== ''));
  }

}
