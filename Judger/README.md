## 连接数据库  
修改Homework和Study目录下的setting.json文件, 'server_port'根据需求而定, 但两个文件设置不要相同;  

## 安装环境依赖，
需要gcc-7, gcc-8, g++-7, g++-8, python2.7, python3.6, libmysqlclient-dev, libseccomp-dev
```
sudo apt-get install gcc-7
sudo apt-get install gcc-8

sudo apt-get install g++-7
sudo apt-get install g++-8

sudo apt-get install python2.7
sudo apt-get install python3.6

sudo apt-get install libmysqlclient-dev
sudo apt-get install libseccomp-dev
```

## python3依赖库
```
sudo apt-get install python3-pip
pip3 install mysqlclient
```

## 编译必要库libjudger.so
进入Judger执行以下命令
```
mkdir output && mkdir build && cd build && cmake .. && sudo make && sudo make install && cd .. && sudo rm -r output/ && sudo rm -r build/
```

## 可选添加用户
```
sudo chown *** /user/lib/judger/libjudger.so
```

## 启用Judger服务器和判题机
对于Homework和Study系统, 分别进入Server和Client目录, 执行以下命令  
on为显示进程信息, &为进入后台, 不选即为直接运行, 客户端必须有sudo权限
```
python3.6 Server/main.py (on/&)
sudo python3.6 Client/main.py (on/&)
```

## 使用sh开启
进入Judger目录, sudo权限运行start.sh, 第一个参数为Homework系统判题机开启数量, 第二个参数为Study系统判题机数量
```
cd Judger/ && sudo ./start.sh 1/2/3/... 1/2/3/...
```

或者进入每个Server和Client/目录，分别执行其下的sh, Client一定要sudo权限, 且需要参数指定开启数量
```
cd Homework/Server/ && ./start.sh && cd ../Client/ && sudo ./start.sh 1/2/3/...
cd Study/Server/ && ./start.sh && cd ../Client/ && sudo ./start.sh 1/2/3/...
```

## 关闭服务器以及判题机
正则搜索禁止, sudo权限kill即可
```
pgrep -a python3.6
kill 
sudo kill
```


## 清理缓存文件
缓存文件均在Client下, 进入目录删除Code/以及TestCase/, 删除后一定要创建
```
cd Judger/Homework/Client/ && sudo rm -r Code/ && sudo rm -r TestCase && sudo mkdir Code && sudo mkdir TestCase && cd ../../../
cd Judger/Study/Client/ && sudo rm -r Code/ && sudo rm -r TestCase && sudo mkdir Code && sudo mkdir TestCase && cd ../../../
```
