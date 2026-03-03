# Installation
Clone https://github.com/promet/provus-windy-marketplace
Run the following commands
ddev config --project-type=drupal11 --docroot=web
ddev composer install
ddev start
ddev drush si recipes/provus_windy_college