<?php

declare(strict_types=1);

namespace Drupal\hubspot_embed\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;

/**
 * Hook implementations for the HubSpot Embed module.
 */
class HubspotEmbedHooks {

  /**
   * HubSpot portal (hub) ID.
   */
  const PORTAL_ID = '22569384';

  public function __construct(
    protected AdminContext $adminContext,
  ) {}

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    // Only load tracking on the public-facing site, not admin pages.
    if ($this->adminContext->isAdminRoute()) {
      return;
    }

    $attachments['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => '',
        '#attributes' => [
          'type' => 'text/javascript',
          'id' => 'hs-script-loader',
          'async' => 'async',
          'defer' => 'defer',
          'src' => '//js.hs-scripts.com/' . self::PORTAL_ID . '.js',
        ],
      ],
      'hubspot_embed_script_loader',
    ];
  }

}
