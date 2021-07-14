#!/bin/sh

mkdir -p /tmp/slapd
ls -l .
php build_config.php

set -x

exec /usr/sbin/slapd -f slapd.conf -d1
