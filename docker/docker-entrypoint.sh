#!/bin/sh
set -e

if [ "${PUID}" -ne '2000' ] ||  [ "${PGID}" -ne '2000' ]; then
    chown -R ${PUID}:${PGID} ${CODE_DIR}
fi

exec "$@"
