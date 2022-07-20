#!/bin/bash

find /var/www/SF5/kutxa/public/uploads/KutxaDigitala/* -mtime +30 -exec rm -rf {} \;
#find /var/www/SF5/kutxa/public/uploads/* -mtime +10 -ls
