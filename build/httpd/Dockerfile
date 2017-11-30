FROM debian:stretch

RUN apt-get update && apt-get install -y --no-install-recommends \
    apache2 \
    ca-certificates \
    libapache2-mod-php7.0 \
    libapache2-mod-security2 \
    modsecurity-crs \
    php7.0 \
    php7.0-gd \
    php7.0-curl \
    php7.0-mysql \
    php7.0-mbstring \
    php7.0-xml \
  && \
    apt-get -y autoremove && \
    apt-get clean

COPY apache2.conf /etc/apache2/
COPY restrict-wp-admin.conf /etc/apache2/conf-enabled/
COPY modsecurity.conf /etc/modsecurity/
COPY docker-entrypoint.sh /
RUN chmod a+x /docker-entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["/docker-entrypoint.sh"]
