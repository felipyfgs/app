#!/bin/sh
set -eu

# Chrome headed (padrao docapi) precisa de X server. DISPLAY pode vir
# predefinido no Dockerfile (:99) sem o Xvfb estar no ar — sobe se faltar.
DISPLAY_NUM="${DISPLAY#:}"
DISPLAY_NUM="${DISPLAY_NUM:-99}"
case "$DISPLAY_NUM" in
  ""|*[!0-9]*) DISPLAY_NUM=99 ;;
esac
export DISPLAY=":${DISPLAY_NUM}"

if [ ! -S "/tmp/.X11-unix/X${DISPLAY_NUM}" ]; then
  Xvfb "${DISPLAY}" -screen 0 1280x1024x24 -nolisten tcp -ac >/tmp/mei-xvfb.log 2>&1 &
  i=0
  while [ "$i" -lt 50 ]; do
    if [ -S "/tmp/.X11-unix/X${DISPLAY_NUM}" ]; then
      break
    fi
    i=$((i + 1))
    sleep 0.1
  done
fi

exec "$@"
