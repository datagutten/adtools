#!/usr/bin/env bash
sudo killall /usr/sbin/slapd
rm -R /tmp/slapd
php tests/slapd/build_config.php
mkdir /tmp/slapd
sudo slapd -f tests/slapd/slapd.conf &