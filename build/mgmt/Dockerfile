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
    curl -k -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod a+x /usr/local/bin/wp && \
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

RUN sed -ir 's#www-data.*:/usr/sbin/nologin#www-data:x:33:33:www-data:/var/www:/bin/bash#' /etc/passwd
RUN mkdir -p /var/www/.ssh
RUN chown -R www-data: /var/www
RUN chmod 700 /var/www/.ssh
COPY ./config.yml /var/www/.wp-cli/config.yml

RUN su www-data -c "/usr/local/bin/wp package install git@github.com:diggy/polylang-cli.git"
RUN su www-data -c "/usr/local/bin/wp package install git@github.com:cortneyray/wp-cli-polylang.git"

EXPOSE 22
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["/usr/sbin/sshd", "-D"]
