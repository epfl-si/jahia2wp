FROM debian:stretch

RUN apt-get -y update && apt-get install -y --no-install-recommends \
    bash-completion \
    curl \
    composer \
    git \
    gnupg \
    htop \
    less \
    lsb-release \
    make \
    mysql-client \
    openssh-server \
    tree \
    python3 \
    python3-pip \
    python3-virtualenv \
    virtualenv \
    screen \
    software-properties-common \
    unzip \
    vim \
  && \
    apt-get -y autoremove && \
    apt-get clean

RUN curl -sL https://deb.nodesource.com/setup_10.x | bash -
RUN curl https://packages.sury.org/php/apt.gpg > /etc/apt/trusted.gpg.d/php.gpg
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
RUN apt-get -y update
# The following goodies have dependencies above this line, and
# thus must be installed in another round of apt-get
RUN apt-get -y install nodejs \
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


RUN mkdir /var/run/sshd && \
    sed -ri 's/UsePAM yes/UsePAM no/g' /etc/ssh/sshd_config && \
    sed -ri 's/AcceptEnv LANG LC_\*/AcceptEnv LANG LC_\* WP_ENV/g' /etc/ssh/sshd_config && \
    sed -ri 's/#UsePrivilegeSeparation sandbox/UsePrivilegeSeparation no/g'  /etc/ssh/sshd_config && \
    sed -ri 's/#?PermitRootLogin\s+.*/PermitRootLogin yes/g' /etc/ssh/sshd_config && \
    mkdir /tmp/openshift && \
    cd /tmp/openshift && \
    curl -L -O https://github.com/openshift/origin/releases/download/v3.6.1/openshift-origin-client-tools-v3.6.1-008f2d5-linux-64bit.tar.gz && \
    tar -xf openshift-origin-client-tools-v3.6.1-008f2d5-linux-64bit.tar.gz && \
    mv */oc /usr/local/bin && \
    cd / && \
    rm -rf /tmp/openshift && \
    mkdir /root/.ssh

COPY ./bash_profile /var/www/.bash_profile
COPY ./docker-entrypoint.sh /

# setup access rights
RUN sed -ir 's#www-data.*:/usr/sbin/nologin#www-data:x:33:33:www-data:/var/www:/bin/bash#' /etc/passwd && \
    mkdir -p /var/www/.ssh && \
    chown -R www-data: /var/www && \
    chmod 700 /var/www/.ssh

# install wp-cli packages, downloaded from
# Do not use "Code -> Releases" page to download ZIP files. Use "Code -> Download" option on "Code" tab.
# https://github.com/diggy/polylang-cli
# https://github.com/cortneyray/wp-cli-polylang

COPY ./wp-packages/wp-cli-1.4.1.phar /usr/local/bin/wp
COPY ./wp-packages/polylang-cli-master.zip /tmp/polylang-cli-master.zip
COPY ./wp-packages/wp-cli-polylang-master.zip /tmp/wp-cli-polylang-master.zip

RUN chmod a+x /usr/local/bin/wp && \
    su www-data -c "/usr/local/bin/wp package install /tmp/polylang-cli-master.zip" && \
    su www-data -c "/usr/local/bin/wp package install /tmp/wp-cli-polylang-master.zip"



# this line should come after the installation of other packages
# otherwise, it creates /var/www/.wp-cli with incorrect permission
# and makes the other installations fail
COPY ./config.yml /var/www/.wp-cli/config.yml

EXPOSE 22
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["/usr/sbin/sshd", "-D"]
