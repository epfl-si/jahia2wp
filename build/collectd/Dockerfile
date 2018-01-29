FROM camptocamp/collectd:v0.2.3

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libcurl3-gnutls \
    libvarnishapi1 \
  && \
    apt-get -y autoremove && \
    apt-get clean \\
  && \
    rm -rf /var/lib/apt/lists/*

COPY /collectd.conf /etc/collectd/collectd.conf.d/
