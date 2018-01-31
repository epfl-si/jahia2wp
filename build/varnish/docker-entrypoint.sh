#!/bin/sh

/usr/sbin/varnishd -F -n varnishd -s malloc,256m -f /etc/varnish/default.vcl
