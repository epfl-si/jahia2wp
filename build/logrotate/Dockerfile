FROM debian:stretch

RUN apt-get update && apt-get install -y --no-install-recommends \
    gzip \
  && \
    apt-get -y autoremove && \
    apt-get clean

COPY rotate.sh /
RUN chmod a+x /rotate.sh

ENTRYPOINT ["/rotate.sh"]
