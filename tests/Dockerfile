FROM phusion/baseimage:0.11

RUN pwd
# Disable SSH
# RUN rm -rf /etc/service/sshd /etc/my_init.d/00_regen_ssh_host_keys.sh

# Use baseimage-docker's init system.
CMD ["/sbin/my_init"]

# Configure apt
RUN apt-get -y update

# Install slapd
RUN LC_ALL=C DEBIAN_FRONTEND=noninteractive apt-get install -y slapd php-cli

EXPOSE 389

RUN pwd
# RUN mkdir /etc/service/slapd
ADD slapd.sh /etc/service/slapd/run
RUN chmod +x /etc/service/slapd/run

COPY slapd /etc/service/slapd

RUN ls -l /etc/service/slapd

# To store config outside the container, mount /etc/ldap/slapd.d as a data volume.
# To store data outside the container, mount /var/lib/ldap as a data volume.
VOLUME /etc/ldap/slapd.d /var/lib/ldap

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

