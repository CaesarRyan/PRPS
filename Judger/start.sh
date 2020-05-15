#!/bin/bash
if [ x"$2" = x ]; then 
    echo "param error! -Homework Client Num -Study Client Num"
    exit 1
fi
curPath=$(dirname $(readlink -f "$0"))
fileName="/main.py"

homeworkServer="/Homework/Server"
homeworkClient="/Homework/Client"
python3.6 $curPath$homeworkServer$fileName &
sleep 1
for (( i=0; i<"$1"; i++ ))
do
   sudo python3.6 $curPath$homeworkClient$fileName &
done
sleep 1

studyServer="/Study/Server"
studyClient="/Study/Client"
python3.6 $curPath$studyServer$fileName &
sleep 1
for (( i=0; i<"$2"; i++ ))
do
   sudo python3.6 $curPath$studyClient$fileName &
done