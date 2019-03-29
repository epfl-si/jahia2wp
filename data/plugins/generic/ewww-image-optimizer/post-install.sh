#!/bin/bash

# Building path using given WordPress install path
SRC_BIN_PATH="${1}/wp-content/plugins/ewww-image-optimizer/binaries/"
TRGT_BIN_PATH="${1}/wp-content/ewww/"

# Copy a bin file from source path to target path
function copyBin
{
    cp "${SRC_BIN_PATH}${1}-linux" "${TRGT_BIN_PATH}${1}"
    chmod a+x "${TRGT_BIN_PATH}${1}"
}

# Creating target dir if not exists
if [ ! -e ${TRGT_BIN_PATH} ]
then
    mkdir ${TRGT_BIN_PATH}
fi

# Copying files
copyBin gifsicle
copyBin jpegtran
copyBin optipng
