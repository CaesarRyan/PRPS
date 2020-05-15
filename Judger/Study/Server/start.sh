#!/bin/bash
curPath=$(dirname $(readlink -f "$0"))
fileName="/main.py"
python3.6 $curPath$fileName &