#!/bin/sh


CONFIGFILE=/etc/sphinxsearch/sphinx.conf

grep -e '^index ' $CONFIGFILE
grep -e '^source ' $CONFIGFILE
grep -e '^path ' $CONFIGFILE


QUERYLOG=/var/log/sphinxsearch/query.log 

echo requetes par index
cat $QUERYLOG | cut -d ']' -f 3 | cut -d '[' -f 2- | sort | uniq -c | sort -r

echo requetes par heure
cat $QUERYLOG  | cut -d ']' -f 1 | cut -d '[' -f 2- | cut -d ' ' -f 5 | cut -d ':' -f 1  | sort | uniq -c | sort -r

