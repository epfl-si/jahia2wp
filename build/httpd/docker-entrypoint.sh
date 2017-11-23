#!/bin/sh

set -e

cat > /etc/apache2/conf-available/dyn-vhost.conf <<EOF
UseCanonicalName Off

SetEnvIf X-Forwarded-For "^(.*\..*\..*\..*)|(.*:.*:.*:.*:.*:.*:.*:.*)" proxied
LogFormat "%V %h %l %u %t \"%r\" %s %b" vcommon
LogFormat "%V %{X-Forwarded-For}i %l %u %t \"%r\" %s %b" vproxy
CustomLog "| /usr/bin/rotatelogs /srv/${WP_ENV}/logs/access_log.%Y%m%d 86400" vcommon env=!proxied
CustomLog "/dev/stdout" vcommon env=!proxied
CustomLog "| /usr/bin/rotatelogs /srv/${WP_ENV}/logs/access_log.%Y%m%d 86400" vproxy env=proxied
CustomLog "/dev/stdout" vproxy env=proxied
ErrorLog "| /usr/bin/rotatelogs /srv/${WP_ENV}/logs/error_log.%Y%m%d 86400"

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
