#!/bin/sh

set -e

cat > /etc/apache2/conf-available/dyn-vhost.conf <<EOF
UseCanonicalName Off

LogFormat "%V %h %l %u %t \"%r\" %s %b" vcommon
CustomLog "/srv/${WP_ENV}/logs/access_log" vcommon
ErrorLog "/srv/${WP_ENV}/logs/error_log"

VirtualDocumentRoot "/srv/${WP_ENV}/%0/htdocs"

<VirtualHost *:443>
  SSLEngine on
  SSLCertificateFile "/etc/apache2/ssl/server.cert"
  SSLCertificateKeyFile "/etc/apache2/ssl/server.key"
</VirtualHost>
EOF

/bin/mkdir -p /srv/${WP_ENV}/logs
/bin/chown -R www-data: /srv

/usr/sbin/a2dissite 000-default
/usr/sbin/a2enmod ssl
/usr/sbin/a2enmod rewrite
/usr/sbin/a2enmod vhost_alias
/usr/sbin/a2enconf dyn-vhost

/usr/sbin/apache2ctl -DFOREGROUND
