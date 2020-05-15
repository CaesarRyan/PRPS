#!/bin/bash
if [ x"$1" = x ]; then 
    echo "param error!"
    exit 1
fi
curPath=$(dirname $(readlink -f "$0"))
server="/Server"
client="/Client"
fileName="/main.py"
python3.6 $curPath$server$fileName &
sleep 1
for (( i=0; i<"$1"; i++ ))
do
   sudo python3.6 $curPath$client$fileName &
done