FROM debian:stretch

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash-completion \
    curl \
    git \
    htop \
    less \
    make \
    mysql-client \
    openssh-server \
    tree \
    php7.0 \
    php7.0-cli \
    php7.0-curl \
    php7.0-mysql \
    python3 \
    python3-pip \
    python3-virtualenv \
    virtualenv \
    screen \
    unzip \
    vim && \
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
# https://github.com/wp-cli/wp-cli/releases
# https://github.com/diggy/polylang-cli/releases
# https://github.com/cortneyray/wp-cli-polylang/releases
COPY ./wp-packages/wp-cli-1.4.1.phar /usr/local/bin/wp
COPY ./wp-packages/polylang-cli-1.0.0-prealpha.5 /tmp/polylang-cli-1.0.0-prealpha.5
COPY ./wp-packages/wp-cli-polylang-1.0.5 /tmp/wp-cli-polylang-1.0.5
RUN chmod a+x /usr/local/bin/wp && \
    su www-data -c "/usr/local/bin/wp package install /tmp/polylang-cli-1.0.0-prealpha.5" && \
    su www-data -c "/usr/local/bin/wp package install /tmp/wp-cli-polylang-1.0.5"

# this line should come after the installation of other packages
# otherwise, it creates /var/www/.wp-cli with incorrect permission
# and makes the other installations fail
COPY ./config.yml /var/www/.wp-cli/config.yml

EXPOSE 22
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["/usr/sbin/sshd", "-D"]
