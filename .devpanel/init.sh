#!/usr/bin/env bash

if [ -n "${DEBUG_SCRIPT:-}" ]; then
  set -x
fi

set -eu -o pipefail
cd "$APP_ROOT"

LOG_FILE="logs/init-$(date +%F-%T).log"
exec > >(tee "$LOG_FILE") 2>&1

TIMEFORMAT=%lR

export COMPOSER_NO_AUDIT=1
export COMPOSER_NO_BLOCKING=1
export COMPOSER_NO_SECURITY_BLOCKING=1

echo
echo "Remove root-owned files."
time sudo rm -rf lost+found || :

echo
echo "Composer install."
if [ -f composer.json ]; then
  if composer show cweagans/composer-patches ^2 &> /dev/null; then
    echo 'Update patches.lock.json.'
    time composer prl
    echo
  fi
else
  echo 'Generate composer.json.'
  time source .devpanel/composer_setup.sh
  echo
fi

time composer install --no-progress

# Directories
[ ! -d private ] && mkdir private
[ ! -d config/sync ] && mkdir -p config/sync

echo
echo "Install or update Drupal."

if [ -z "$(drush status --field=db-status)" ]; then
  echo "Install Drupal (recipe)."

  until time drush si recipes/provus_edu -y; do
    :
  done

  drush en provus_site_alert -y
  drush pmu search -y || :

  echo "Enable Automatic Updates."
  drush cset --input-format=yaml package_manager.settings additional_trusted_composer_plugins '["cweagans/composer-patches","drupal/site_template_helper"]'
  drush cset --input-format=yaml package_manager.settings include_unknown_files_in_project_root '["assets","patches.json","patches.lock.json"]'
  drush cset --input-format=yaml automatic_updates.settings unattended '{"method":"console","level":"patch"}'

  time drush ev '\Drupal::moduleHandler()->invoke("automatic_updates", "modules_installed", [[], FALSE])'
  time php web/modules/contrib/automatic_updates/auto-update

  drush cr
else
  echo "Update database."
  time drush updb -y
fi

echo
echo "Run cron."
time drush cron

echo
echo "Warm caches."
time drush cache:warm &> /dev/null || :
time .devpanel/warm || :
time .devpanel/warm /user/login || :

# Timer
INIT_DURATION=$SECONDS
printf "\nTotal elapsed time: %02d:%02d:%02d\n" \
  $(($INIT_DURATION/3600)) \
  $(($INIT_DURATION%3600/60)) \
  $(($INIT_DURATION%60))
