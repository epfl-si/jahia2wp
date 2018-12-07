#!/usr/bin/env bash
# Description : Just to display a graphical logstalgia window for each active access log for subdomains POD
#               When executed, tries to find access log file(s) for current day and starts a logstalgia window for
#               each one.
#               Before it starts new logstalgia windows, it kill the "old" ones.
#               If logstalgia windows are started on day X, they won't work anymore on day Y because log file(s)
#               is/are not the same. So it means, this script has to be executed again.
#
#               Hint: hit Q key on logstalgia window to have more information.
#
# Author      : Lucien Chaboudez

# Config
REMOTE_FOLDER="/srv/subdomains/logs/"
TMP_FILE="/tmp/logstalgia"
SCREEN_VERTICAL_MARGIN=45
SCREEN_HORIZONTAL_MARGIN=90

currentDate=`date "+%Y%m%d"`

# --- CHECKING PREREQUISITES
echo -n "Checking if logstalgia exists... "
if [ "`which logstalgia`" == "" ]
then
    echo "missing!"
    exit
fi
echo "done"


# --- KILLING OLD PROCESSES
echo -n "Killing existing processes... "
for pid in `ps -a | grep logstalgia | grep -v grep | awk '{print $1}'`
do
    kill -9 ${pid}
done
echo "done"


# --- GETTING LOG FILES LIST
ssh -p 32222 -q -o StrictHostKeyChecking=no www-data@ssh-wwp.epfl.ch "ls -alh ${REMOTE_FOLDER}access* | grep ${currentDate}" | awk '{print $NF } '  > ${TMP_FILE}


# --- ESTIMATING OPTIMAL LOGSTALGIA WINDOWS SIZE
echo -n "Calculating windows size... "

# Getting NB access log files
nbWindows=`cat ${TMP_FILE}| wc -l`

# Screen size - Ex: 1920x1080
screenFullSize=`xdpyinfo | grep dimensions | sed -r 's/^[^0-9]*([0-9]+x[0-9]+).*$/\1/'`

windowHeight=`echo ${screenFullSize} | awk -F"x" '{print $2-'$SCREEN_VERTICAL_MARGIN'}'`
windowWidth=`echo ${screenFullSize} | awk -F"x" '{print (int(($1-'$SCREEN_HORIZONTAL_MARGIN') / '$nbWindows'))}'`

windowSize="${windowWidth}x${windowHeight}"

echo "done (${windowSize})"


# --- STARTING LOGSTALGIA PROCESSES
echo -n "Starting new processes... "

# Looping through access log files
for accessLog in `cat ${TMP_FILE}`
do
    # Starting logstalgia for current access log file
    ssh -p 32222 -q -o StrictHostKeyChecking=no www-data@ssh-wwp.epfl.ch "tail -f ${accessLog}" | logstalgia --sync -${windowSize} -x -r 25 --paddle-mode vhost --font-size 10 --glow-multiplier 1.1 --glow-intensity 0.3 --disable-progress  &

done
echo "done"


# --- CLEANING
rm ${TMP_FILE}