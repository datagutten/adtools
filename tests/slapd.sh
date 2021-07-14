#!/bin/sh

mkdir -p /tmp/slapd
ls -l .
php build_config.php

set -x
which slapd
exec slapd -f slapd.conf -d1
