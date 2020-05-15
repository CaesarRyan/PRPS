import MySQLdb
from queue import Queue
import socket
import json
from time import sleep
import threading
import os
import sys

printFlag = False
if len(sys.argv) > 1 and sys.argv[1] == "on":
    printFlag = True

mutex = threading.Lock()  # queue mutex

queue = Queue() # 全局判题列表
myjsonfile = open("%s/setting.json" % os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'r')
judgerjson = json.loads(myjsonfile.read())

if os.environ.get("DB_USER"):
    judgerjson["db_ip"] = os.environ.get("DB_HOST")
    judgerjson["db_pass"] = os.environ.get("DB_PASSWORD")
    judgerjson["db_user"] = os.environ.get("DB_USER")
    judgerjson["db_port"] = os.environ.get("DB_PORT")

try:
    db = MySQLdb.connect(judgerjson["db_ip"], judgerjson["db_user"], judgerjson["db_pass"],
                         judgerjson["db_database"], int(judgerjson["db_port"]), charset='utf8')
except Exception as e:
    isPrint(e)
    exit(1)


def isPrint(message):
    global printFlag
    if printFlag == True:
        print(message)
 
# 获取未判题列表，放入到全局队列中
def getSubmition():
    global queue, mutex, db

    cursor = db.cursor()
    while True:
        sleep(1)
        if mutex.acquire():
            cursor.execute(
                "SELECT id FROM stu_section WHERE judge = 'true'")
            data = cursor.fetchall()
            try:
                for d in data:
                    queue.put(d[0])
                    cursor.execute(
                        "UPDATE stu_section SET judge = 'false', state = '1' WHERE id = '%d'" % d[0])
                db.commit()
            except:
                db.rollback()
            mutex.release()
    db.close()


# 处理每个判题机的逻辑
def deal_client(newSocket: socket, addr):
    global mutex, queue
    statue = False
    cursor = db.cursor()
    falsetime = 0
    while True:
        sleep(2) # 每隔两秒取两次
        if mutex.acquire(): # 获取队列锁
            try:
                if statue == True and queue.empty() is not True:
                    id = queue.get() # 如果可以判题，那就发送判题命令  
                    newSocket.send(("judge|%d" % id).encode("utf-8"))
                    statue = False 
                else:
                    newSocket.send("getstatue".encode("utf-8"))
                    data = newSocket.recv(1024)
                    recv_data = data.decode('utf-8')
                    if recv_data == "ok": 
                        falsetime = 0
                        statue = True
                    else:
                        falsetime = falsetime + 1
                        statue = False
                        if falsetime >= 180: # 计算一下未准备好的时间，如果超过120s，发送销毁重启命令
                            newSocket.send("timeout".encode("utf-8"))
                            isPrint("%s timeout!" % adder)
                            newSocket.close()
                            mutex.release()
                            return
                    isPrint("%s %s" % (addr, statue))
            except socket.error:
                newSocket.close()
                mutex.release()
                return
            except:
                isPrint("error!")
                mutex.release()
                return
            mutex.release()


server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
server.bind(("", judgerjson["server_port"]))
server.listen(20)
print("server is running!")

t = threading.Thread(target=getSubmition, args=()) # 用一个线程去跑
t.setDaemon(True)
t.start()

# 循环监听
while True:
    newSocket, addr = server.accept()
    isPrint("client [%s] is connected!" % str(addr))
    client = threading.Thread(target=deal_client, args=(newSocket, addr))
    client.setDaemon(True)
    client.start()
