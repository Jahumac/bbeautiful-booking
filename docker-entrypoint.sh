#!/bin/bash

# Bbeautiful wrapper entrypoint.
# Starts the cron daemon (which drives the mail queue worker), then delegates
# to the upstream Easy!Appointments entrypoint which regenerates config.php
# from env vars and launches Apache.
#
# The mail worker line goes in the ROOT USER crontab (/var/spool/cron/crontabs/root)
# because /etc/cron.d is not processed by cron in this minimal Debian image.
# IMPORTANT: cron's PATH does NOT include /usr/local/bin, so the absolute
# /usr/local/bin/php path MUST be used or the job fails with "php: not found".

set -e

# (Re)install the mail queue worker cron line on every boot, so it survives
# container recreation. Write the crontab file directly (idempotent).
mkdir -p /var/spool/cron/crontabs
printf '# Bbeautiful mail queue worker\n* * * * * cd /var/www/html && /usr/local/bin/php index.php console mail_worker > /dev/null 2>&1\n' > /var/spool/cron/crontabs/root
chmod 0600 /var/spool/cron/crontabs/root

echo "[bbeautiful] starting cron for mail queue worker..."
cron 2>/dev/null || (echo "[bbeautiful] cron start skipped" && true)

echo "[bbeautiful] delegating to upstream entrypoint..."
exec /usr/local/bin/docker-entrypoint.sh "$@"
