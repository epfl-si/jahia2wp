#!/bin/sh

set -e

TODAY=$(date +%Y%m%d)

# Delete the file older than DAYS
find /srv/*/logs \( -name access_log.* -o -name error_log.* \) -mtime +${DAYS} -delete || echo "Failed to purge log files"

# Compress the files
find /srv/*/logs \( -name access_log.* -o -name error_log.* \) ! -name *.${TODAY} ! -name *.gz -exec gzip {} \; || echo "Failed to compress log files"

exit 0
