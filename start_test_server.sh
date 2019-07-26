#!/usr/bin/env bash
mkdir /tmp/slapd
sudo killall /usr/sbin/slapd
sudo slapd -f tests/slapd/slapd_standard.conf &
sleep 3
ldapadd -h localhost:389 -D cn=admin,dc=example,dc=com -w test -f tests/slapd/base.ldif
ldapadd -c -h localhost:389 -D cn=admin,dc=example,dc=com -w test -f tests/slapd/adtools_test_data.ldif