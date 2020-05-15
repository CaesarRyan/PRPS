#!/bin/bash
if [ x"$1" = x ]; then 
    echo "param error!"
    exit 1
fi
curPath=$(dirname $(readlink -f "$0"))
fileName="/main.py"
for (( i=0; i<"$1"; i++ ))
do
   sudo python3.6 $curPath$fileName &
done