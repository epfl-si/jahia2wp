#!/bin/sh

set -e

cat > /etc/apache2/conf-available/dyn-vhost.conf <<EOF
UseCanonicalName Off

RemoteIPHeader X-Forwarded-For
RemoteIPInternalProxy 172.31.0.0/16 10.180.21.0/24 127.0.0.0/8

LogFormat "%V %a %l %u %t \"%r\" %s %b %{ms}T" vcommon
CustomLog "| /usr/bin/rotatelogs /srv/${WP_ENV}/logs/access_log.$(hostname).%Y%m%d 86400" vcommon
CustomLog "/dev/stdout" vcommon

ErrorLog "| /usr/bin/rotatelogs /srv/${WP_ENV}/logs/error_log.$(hostname).%Y%m%d 86400"

VirtualDocumentRoot "/srv/${WP_ENV}/%0/htdocs"

<VirtualHost *:8443>
  SSLEngine on
  SSLCertificateFile "/etc/apache2/ssl/server.cert"
  SSLCertificateKeyFile "/etc/apache2/ssl/server.key"
</VirtualHost>
EOF

/bin/mkdir -p /srv/${WP_ENV}/logs
/bin/chown -R www-data: /srv/${WP_ENV}

/bin/mkdir -p /etc/apache2/ssl
/usr/bin/openssl req -x509 -sha256 -nodes -days 3650 -newkey rsa:4096 -keyout /etc/apache2/ssl/server.key -out /etc/apache2/ssl/server.cert -subj "/C=CH/ST=Vaud/L=Lausanne/O=Ecole Polytechnique Federale de Lausanne (EPFL)/CN=*.epfl.ch"

/usr/sbin/a2dissite 000-default
/usr/sbin/a2enmod ssl
/usr/sbin/a2enmod rewrite
/usr/sbin/a2enmod vhost_alias
/usr/sbin/a2enmod status
/usr/sbin/a2enmod remoteip
/usr/sbin/a2enconf dyn-vhost

echo "ready" > /tmp/status

/usr/sbin/apache2ctl -DFOREGROUND
