#!/usr/bin/env bash

if [ -n "${DEBUG_SCRIPT:-}" ]; then
  set -x
fi

set -eu -o pipefail
cd "$APP_ROOT"

LOG_FILE="logs/init-$(date +%F-%T).log"
exec > >(tee "$LOG_FILE") 2>&1

TIMEFORMAT=%lR

# Composer environment settings
export COMPOSER_NO_AUDIT=1
export COMPOSER_NO_BLOCKING=1
export COMPOSER_NO_SECURITY_BLOCKING=1

echo
echo "== Remove root-owned files =="
time sudo rm -rf lost+found || :

echo
echo "== Start DDEV =="
time ddev start

echo
echo "== Composer Install via DDEV =="
if [ -f composer.json ]; then
  if composer show --locked cweagans/composer-patches ^2 &> /dev/null; then
    echo "Update patches.lock.json."
    time ddev composer prl
    echo
  fi
else
  echo "Generate composer.json."
  time source .devpanel/composer_setup.sh
  echo
fi

time ddev composer install --no-progress

# Create directories
if [ ! -d private ]; then
  echo
  echo "Create the private files directory."
  time mkdir private
fi

if [ ! -d config/sync ]; then
  echo
  echo "Create the config sync directory."
  time mkdir -p config/sync
fi

echo
echo "== Install or Update Drupal =="

DB_STATUS=$(ddev drush status --field=db-status || echo "")

if [ -z "$DB_STATUS" ]; then
  echo "Install Drupal via recipe."

  until time ddev drush si recipes/provus_edu -y; do
    :
  done

  echo
  echo "Enable additional module."
  time ddev drush en provus_site_alert -y

  echo
  echo "Remove search module (if needed)."
  ddev drush pmu search -y || :

  echo
  echo "Enable Automatic Updates."
  ddev drush cset --input-format=yaml package_manager.settings additional_trusted_composer_plugins '["cweagans/composer-patches","drupal/site_template_helper"]'
  ddev drush cset --input-format=yaml package_manager.settings include_unknown_files_in_project_root '["assets","patches.json","patches.lock.json"]'
  ddev drush cset --input-format=yaml automatic_updates.settings unattended '{"method":"console","level":"patch"}'

  time ddev drush ev '\Drupal::moduleHandler()->invoke("automatic_updates", "modules_installed", [[], FALSE])'
  time ddev exec php web/modules/contrib/automatic_updates/auto-update

  echo
  time ddev drush cr
else
  echo "Update database."
  time ddev drush -n updb
fi

# Cache warmup
echo
echo "Run cron."
time ddev drush cron

echo
echo "Populate caches."
time ddev drush cache:warm &> /dev/null || :
time .devpanel/warm || :
time .devpanel/warm /user/login || :

# Execution time
INIT_DURATION=$SECONDS
INIT_HOURS=$(($INIT_DURATION / 3600))
INIT_MINUTES=$(($INIT_DURATION % 3600 / 60))
INIT_SECONDS=$(($INIT_DURATION % 60))

printf "\nTotal elapsed time: %d:%02d:%02d\n" $INIT_HOURS $INIT_MINUTES $INIT_SECONDS
