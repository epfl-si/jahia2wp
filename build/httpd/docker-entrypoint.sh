#!/bin/sh

set -e

cat > /etc/apache2/conf-available/dyn-vhost.conf <<EOF
UseCanonicalName Off

RemoteIPHeader X-Forwarded-For
RemoteIPInternalProxy 172.31.0.0/16 10.180.21.0/24 127.0.0.0/8

LogFormat "%V %a %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-agent}i\" %T %D" vcommon
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
/bin/chown www-data: /srv/${WP_ENV}
/bin/chown www-data: /srv/${WP_ENV}/logs
/bin/chown www-data: /srv/${WP_ENV}/jahia2wp

/bin/mkdir -p /etc/apache2/ssl
/usr/bin/openssl req -x509 -sha256 -nodes -days 3650 -newkey rsa:4096 -keyout /etc/apache2/ssl/server.key -out /etc/apache2/ssl/server.cert -subj "/C=CH/ST=Vaud/L=Lausanne/O=Ecole Polytechnique Federale de Lausanne (EPFL)/CN=*.epfl.ch"

/bin/mkdir -p /var/www/html/probes/ready
echo "OK" > /var/www/html/probes/ready/index.html

# Change max upload size for http requests
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 300M/" /etc/php/7.0/apache2/php.ini
sed -i "s/post_max_size = .*/post_max_size = 300M/" /etc/php/7.0/apache2/php.ini
# Change max upload size for CLI requests
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 300M/" /etc/php/7.0/cli/php.ini
sed -i "s/post_max_size = .*/post_max_size = 300M/" /etc/php/7.0/cli/php.ini

/usr/sbin/a2dissite 000-default
/usr/sbin/a2enmod ssl
/usr/sbin/a2enmod rewrite
/usr/sbin/a2enmod vhost_alias
/usr/sbin/a2enmod status
/usr/sbin/a2enmod remoteip
/usr/sbin/a2enconf dyn-vhost

/usr/sbin/apache2ctl -DFOREGROUND
