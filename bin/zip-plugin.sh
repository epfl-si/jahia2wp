#!/bin/bash

# Zip the plugin having the given name, e.g.:
#
# zip-plugin.sh epfl-news
#
# will update the zip content at ../data/plugins/generic/epfl-news/v1/epfl-news.zip

# variable check
if [ -z "$1" ]
then
  echo "Please give the name of the plugin to zip, e.g. 'epfl-news'"
  exit
fi

# paths we will use (use $PWD to have the absolute paths because we will do a 'cd' later)
ZIP_FILE=$PWD/../data/plugins/generic/$1/v1/$1.zip
PLUGIN_DIR=$PWD/../data/wp/wp-content/plugins/$1

# check if the plugin directory exists
if [ ! -d ${PLUGIN_DIR} ]
then
 echo "ERROR: plugin directory ${PLUGIN_DIR} doesn't exist"
 exit
fi

# cleanup
rm -f ${ZIP_FILE}

# do the zipping
# we need to cd into the plugin directory otherwise we have all the relative paths
# in the zip (../data/wp/wp-content/...)
cd ${PLUGIN_DIR}/..
zip -r ${ZIP_FILE} $1
