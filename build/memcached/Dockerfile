FROM wordpress:apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libmemcached-dev \
        tidy \
        csstidy \
        bash-completion \
        curl \
        git \
        htop \
        less \
        make \
        mysql-client \
        tree \
        screen \
        unzip \
        vim && \
    curl -k -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod a+x /usr/local/bin/wp && \
    apt-get clean && \
	mkdir -p /usr/src/php/ext

RUN curl -o memcached.tgz -SL http://pecl.php.net/get/memcached-2.2.0.tgz \
    && tar -xf memcached.tgz -C /usr/src/php/ext/ \
    && rm memcached.tgz \
    && mv /usr/src/php/ext/memcached-2.2.0 /usr/src/php/ext/memcached

RUN curl -o memcache.tgz -SL http://pecl.php.net/get/memcache-3.0.8.tgz \
    && tar -xf memcache.tgz -C /usr/src/php/ext/ \
    && rm memcache.tgz \
    && mv /usr/src/php/ext/memcache-3.0.8 /usr/src/php/ext/memcache

RUN curl -o zip.tgz -SL http://pecl.php.net/get/zip-1.13.1.tgz \
    && tar -xf zip.tgz -C /usr/src/php/ext/ \
    && rm zip.tgz \
    && mv /usr/src/php/ext/zip-1.13.1 /usr/src/php/ext/zip

RUN docker-php-ext-install memcached \
    && docker-php-ext-install memcache \
    && docker-php-ext-install zip

COPY ./var/object-cache.php /var/object-cache.php
COPY ./bin/activate_cache.sh /usr/local/bin/
COPY ./bin/install_wp.sh /usr/local/bin/install_wp.sh
