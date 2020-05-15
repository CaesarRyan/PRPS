# Ubuntu安装

1. 配置Apache环境, 开启rewrite模块, 将apach2.conf里的AllowOverride后的None均改为All;  
```
sudo apt install apache2  
sudo a2enmod rewrite  
```
2. 配置PHP上传文件大小, 一般在/etc/php/目录下, 找到php.ini文件, 按照需求更改配置;  
```
file_uploads = On                      //默认也是开启的  
upload_max_filesize = 50M              //允许上传文件大小的最大值  
post_max_size = 100M                   //通过表单POST给PHP的所能接收的最大值  
max_execution_time = 600               //每个PHP页面运行的最大时间值  
max_input_time = 600                   //每个PHP页面接收数据所需的最大时间  
memory_limit = 128M                    //每个PHP页面所吃掉的最大内存
```
3. 重启Apache服务;  
```
sudo systemctl restart apache2  
```
4. 文件移动到主机目录下, 给文件添加权限;  
```
sudo chmod 777 -R PRPS/
```
		
5. 新建数据库psDB, 并导入psDB.sql, 编码方式选择utf8_general_ci;  

6. 修改配置文件`PRPS/application/database.php`, 主要'hostname', 'database', 'username', 'password'字段;  

7. 以adminadmin用户名进行注册, 实际姓名为'管理员', 注册后可以根据需要自行更改信息;  

8. 如需调试, 可开启`PRPS/application/config.php`中'app_debug'字段为'true';  


# 重写模块开启失败的解决办法
```
/application/admin/view/question/detail.html 文件下第11、15、229行,   
/application/admin/view/score/detail.html 文件下第11、113行,  
'__ROOT__' 改为 '__ROOT__/index.php'
```
