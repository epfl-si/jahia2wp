#!/bin/bash

function usage {
    echo "Usage: ${0} -s SERVICE -h HOST"
    echo "    SERVICE the service to which to route the traffic of the site"
    echo "    HOST the fqdn of the site"
    exit 1
}


while getopts "s:h:" opt; do
    case ${opt} in
        s)
            SERVICE=${OPTARG}
            ;;
        h)
            HOST=${OPTARG}
            NAME=${HOST%%.*}
            ;;
        *)
            usage
            ;;
    esac
done


cat <<EOF
apiVersion: v1
kind: Route
metadata:
  annotations:
    haproxy.router.openshift.io/balance: roundrobin
  labels:
    app: ${SERVICE}
  name: httpd-${NAME}
spec:
  host: ${HOST}
  port:
    targetPort: http
  tls:
    insecureEdgeTerminationPolicy: Redirect
    termination: edge
  to:
    kind: Service
    name: varnish-varnish
    weight: 100
  wildcardPolicy: None
EOF
