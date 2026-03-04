# About

This is a testing ground for creating provus_edu recipe.

Refer to this site: https://windy.provusdemo.com/ (D10)

# Installation
```
Clone https://github.com/promet/provus-windy-marketplace
Run the following commands
ddev config --project-type=drupal11 --docroot=web
ddev composer install
ddev start
ddev drush si recipes/provus_edu -y
```

# Importing work
```
ddev drush site:export --destination=./recipes/provus_edu
mv ./recipes/provus_edu to ../recipes
ddev drush si recipes/provus_edu -y
```

Credit:

Promet Source
