<?php

namespace Drush\Commands;

use Composer\InstalledVersions;
use Drupal\Core\Recipe\Recipe;
use Drush\Attributes as CLI;

/**
 * Drush commands used during installation.
 */
final class DrupalForgeInstallerCommands extends DrushCommands {

  protected $recipeDirectory;

  /**
   * Prepare Drush to be used while installation is in progress.
   */
  public function __construct() {
    // Tell Drush that we are in the process of installing.
    $GLOBALS['install_state']['theme'] = 'drupal_cms_installer_theme';

    // Set the contrib recipes path.
    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    $project_root = realpath($project_root);
    assert(is_string($project_root));

    $file = $project_root . DIRECTORY_SEPARATOR . 'composer.json';
    $data = file_get_contents($file);
    $data = json_decode($data, TRUE, flags: JSON_THROW_ON_ERROR);

    $directory = array_find_key(
      $data['extra']['installer-paths'] ?? [],
      fn (array $criteria): bool => in_array('type:' . Recipe::COMPOSER_PROJECT_TYPE, $criteria, TRUE),
    );
    if ($directory) {
      $directory = $project_root . DIRECTORY_SEPARATOR . $directory;
      // The general recipe directory will not have package-specific placeholders,
      // because that makes no sense.
      $directory = str_replace(['{$name}', '{$vendor}'], '', $directory);
      $this->recipeDirectory = rtrim($directory, DIRECTORY_SEPARATOR);
    }
  }

  /**
   * Command to get the contrib recipes path.
   */
  #[CLI\Command(name: 'drupalforge:contrib-recipes-path', aliases: ['contrib-recipes-path', 'crp'])]
  #[CLI\Usage(name: 'drupalforge:contrib-recipes-path', description: 'Emit the path to contrib recipes.')]
  public function getContribRecipesPath() {
    return $this->recipeDirectory;
  }

}
