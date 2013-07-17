#!/bin/sh
CONFIGFILE=/etc/sphinxsearch/sphinx.conf
PORT=2222
HOSTLIST='Liste des hosts séparés par un espace genre john@carter.net sphinx@sdb.myhosting.net'

# DONT EDIT UNDER THIS LINE


DATESTART=`date +%T_%N`
echo =============== Status index $CONFIGFILE $DATESTART ===========================
for HOST in $HOSTLIST ; do
   echo  ======== $HOST =======
   ssh -p 2222 $HOST cat $CONFIGFILE | grep 'index idx' | cut -d ' ' -f 2-
done
echo press enter
read test

