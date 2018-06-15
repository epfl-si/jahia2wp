FROM debian:stretch

RUN apt-get update && apt-get install -y --no-install-recommends \
    apache2 \
    curl \
    ca-certificates \
    libapache2-mod-security2 \
    modsecurity-crs \
    vim \
  && \
    apt-get -y autoremove && \
    apt-get clean

RUN curl https://packages.sury.org/php/apt.gpg > /etc/apt/trusted.gpg.d/php.gpg
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
# The following goodies have dependencies above this line, and
# thus must be installed in another round of apt-get
RUN  apt-get -y update && \
  apt-get -y install nodejs \
    libapache2-mod-php7.1 \
    php7.1 \
    php7.1-gd \
    php7.1-cli \
    php7.1-curl \
    php7.1-mysql \
    php7.1-zip \
    php7.1-xml \
  && \
    apt-get -y autoremove && \
    apt-get clean

# directory for custom error pages
RUN mkdir /var/www/error

COPY apache2.conf /etc/apache2/
COPY ports.conf /etc/apache2/
COPY restrict-wp-admin.conf /etc/apache2/conf-enabled/
COPY error-pages.conf /etc/apache2/conf-enabled/
COPY 403 /var/www/error/
COPY modsecurity.conf /etc/modsecurity/
COPY modsecurity-whitelist.conf /etc/modsecurity/
COPY docker-entrypoint.sh /
RUN chmod a+x /docker-entrypoint.sh

EXPOSE 8080 8443

ENTRYPOINT ["/docker-entrypoint.sh"]
