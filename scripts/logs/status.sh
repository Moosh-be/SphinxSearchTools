#!/bin/sh


CONFIGFILE=/etc/sphinxsearch/sphinx.conf

echo les sources
grep -e '^source ' $CONFIGFILE | sort

echo les indexs

grep -e '^index ' $CONFIGFILE | sort

echo les répertoires

grep -e 'path ' $CONFIGFILE | sort
grep -e '.log$' $CONFIGFILE | sort

QUERYLOG=` grep -e ' query_log ' $CONFIGFILE | cut -d'=' -f 2-`

TODAYINSPHINXLOG=`date '+%b %d'`

echo etude du log des call
echo $QUERYLOG

#QUERYLOG=/var/log/sphinxsearch/query.log 

echo requetes par index
sudo cat $QUERYLOG | cut -d ']' -f 3 | cut -d '[' -f 2- | sort | uniq -c | sort -r

echo requetes par heure
sudo cat $QUERYLOG  | cut -d ']' -f 1 | cut -d '[' -f 2- | cut -d ' ' -f 5 | cut -d ':' -f 1  | sort | uniq -c | sort -r


echo top search today
sudo grep $TODAYINSPHINXLOG  $QUERYLOG | sed 's/\(.*\)[^\]]\(.*\)$/\2/' | sort | uniq -c | sort -nr | head 
