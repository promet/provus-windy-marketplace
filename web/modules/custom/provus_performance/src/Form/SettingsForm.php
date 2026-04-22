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
    $this->config(self::SETTINGS)
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('remove_lazy', (bool) $form_state->getValue('remove_lazy'))
      ->set('set_fetchpriority', (bool) $form_state->getValue('set_fetchpriority'))
      ->set('content_anchors', $this->arrayFromLines($form_state->getValue('content_anchors')))
      ->set('skip_selectors', $this->arrayFromLines($form_state->getValue('skip_selectors')))
      ->set('font_preloads', $this->arrayFromLines($form_state->getValue('font_preloads')))
      ->set('excluded_paths', $this->arrayFromLines($form_state->getValue('excluded_paths')))
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
