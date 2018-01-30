FROM debian:stretch

RUN apt-get update && apt-get install -y --no-install-recommends \
    varnish \
  && \
    apt-get -y autoremove \
  && \
    apt-get clean

COPY default.vcl /etc/varnish
COPY docker-entrypoint.sh /
RUN chmod a+x /docker-entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["/docker-entrypoint.sh"]
