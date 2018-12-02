#!/bin/sh

#
# desktop-client.sh
#
# FreshSplash
#

sleep 5
ping -q -c 1 -W 1 8.8.8.8 >/dev/null || exit

WALLPAPER="/opt/login-background.jpg"
API="FreshSplashServer.com/api.php?date="
TIME=$( date '+%Y-%m-%dT%H:%M:%SZ' )

curl --silent "$API$TIME" -H 'Host: arc.msn.com' -H 'Connection: keep-alive' -H 'Cache-Control: max-age=0' > "$WALLPAPER"
