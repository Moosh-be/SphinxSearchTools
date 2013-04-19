#!/bin/sh


CONFIGFILE=/etc/sphinxsearch/sphinx.conf

echo les sources
grep -e '^source ' $CONFIGFILE | sort

echo les indexs

grep -e '^index ' $CONFIGFILE | sort

echo les répertoires

grep -e '^path ' $CONFIGFILE | sort

TODAYINSPHINXLOG=`date '+%b %d'`

QUERYLOG=/var/log/sphinxsearch/query.log 

echo requetes par index
cat $QUERYLOG | cut -d ']' -f 3 | cut -d '[' -f 2- | sort | uniq -c | sort -r

echo requetes par heure
cat $QUERYLOG  | cut -d ']' -f 1 | cut -d '[' -f 2- | cut -d ' ' -f 5 | cut -d ':' -f 1  | sort | uniq -c | sort -r


echo top search today
grep $TODAYINSPHINXLOG  /var/log/sphinxsearch/query.log | sed 's/\(.*\)[^\]]\(.*\)$/\2/' | sort | uniq -c | sort -nr | head 
