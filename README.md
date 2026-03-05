# About

This repository is a sandbox for building and testing the **`provus_edu`** Drupal recipe.

Reference implementation (Drupal 10):
- https://windy.provusdemo.com/


# Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/promet/provus-windy-marketplace
   cd provus-windy-marketplace
   ```

2. Set up DDEV and install dependencies:

    ```bash
    ddev config --project-type=drupal11 --docroot=web
    ddev composer install
    ddev start
    ```

3. Install Drupal using the `provus_edu` recipe:

    ```
    ddev drush si recipes/provus_edu -y
    ```

# Importing / Exporting Recipe Work

Use this workflow to export changes from the site into the recipe directory, then reinstall to validate.

1. Export the site into a local destination:

    ```
    ddev drush site:export --destination=./provus_edu
    ```

2. Move the exported recipe into the recipes directory:

    ```
    mv ./provus_edu ./recipes/provus_edu
    ```

3. Reinstall using the updated recipe:

    ```
    ddev drush si recipes/provus_edu -y
    ```


---

Credit

Promet Source