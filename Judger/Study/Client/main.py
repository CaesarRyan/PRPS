import json
import MySQLdb
import socket
import threading
import subprocess
import time
import os
import sys
from time import sleep

# 全局变量类，用于保存全局变量
class GlobalVar:
    ROOT_PATH = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    FILE_PATH_CODE = os.path.dirname(os.path.abspath(__file__)) + "/Code"
    FILE_PATH_TESTCASE = os.path.dirname(os.path.abspath(__file__)) + "/TestCase"
    printFlag = False
    statue = False
    judgerjson = {}
    judgername = "Unknow"
    host = "127.0.0.1"
    port = 9906
    python3path = "/usr/bin/python3.6"
    python2path = "/usr/bin/python2.7"
    cursor = None
    db = None
    clientsocket = None

    @staticmethod 
    def initGlobalVar():
        if len(sys.argv) > 1 and sys.argv[1] == "on":
            GlobalVar.printFlag = True
        GlobalVar.statue = True
        myjsonfile = open("%s/setting.json" % GlobalVar.ROOT_PATH, 'r')
        GlobalVar.judgerjson = json.loads(myjsonfile.read())
        myjsonfile.close()

        if os.environ.get("DB_USER"):
            GlobalVar.judgerjson["db_ip"] = os.environ.get("DB_HOST")
            GlobalVar.judgerjson["db_pass"] = os.environ.get("DB_PASSWORD")
            GlobalVar.judgerjson["db_user"] = os.environ.get("DB_USER")
            GlobalVar.judgerjson["db_port"] = os.environ.get("DB_PORT")

            GlobalVar.judgerjson["server_ip"] = os.environ.get("SERVER_IP")
            GlobalVar.judgerjson["server_port"] = os.environ.get("SERVER_PORT")

            GlobalVar.judgerjson["python3_path"] = os.environ.get("PYTHON3_PATH")
            GlobalVar.judgerjson["python2_path"] = os.environ.get("PYTHON2_PATH")

        GlobalVar.judgername = socket.gethostbyname(socket.gethostname())

        GlobalVar.host = GlobalVar.judgerjson["server_ip"]
        GlobalVar.port = GlobalVar.judgerjson["server_port"]
        GlobalVar.python3path = GlobalVar.judgerjson["python3_path"]
        GlobalVar.python2path = GlobalVar.judgerjson["python2_path"]

        GlobalVar.db = MySQLdb.connect(GlobalVar.judgerjson["db_ip"], GlobalVar.judgerjson["db_user"], GlobalVar.judgerjson["db_pass"],
                            GlobalVar.judgerjson["db_database"], int(GlobalVar.judgerjson["db_port"]), charset='utf8')
        GlobalVar.cursor = GlobalVar.db.cursor()

        GlobalVar.clientsocket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        GlobalVar.clientsocket.connect((GlobalVar.host, GlobalVar.port))


class Controller:
    @staticmethod
    def getStuCode(id):
        GlobalVar.cursor.execute("SELECT compiler, code, input FROM stu_section WHERE id = '%d'" % int(id))
        stucode = GlobalVar.cursor.fetchone()
        file_in = GlobalVar.FILE_PATH_TESTCASE + "/" + GlobalVar.judgername + ".in"
        file = open(file_in, "w", encoding='utf-8')
        file.write(stucode[2])
        file.close()
        return [stucode[0], stucode[1], file_in]

    @staticmethod
    def getTimeMemory():
        GlobalVar.cursor.execute("SELECT study_timelimit, study_memorylimit FROM setting")
        result = GlobalVar.cursor.fetchone()
        return int(result[0]), int(result[1])

    @staticmethod
    def updateState(id, state):
        GlobalVar.cursor.execute("UPDATE stu_section SET state = '%s' WHERE id = '%d'" % (str(state), int(id)))
        GlobalVar.db.commit()

    @staticmethod
    def compileError(id, message):
        GlobalVar.cursor.execute("UPDATE stu_section SET state = '4', result = '%s' WHERE id = '%d'" % (message, int(id)))
        GlobalVar.db.commit()

    @staticmethod
    def systemError(id, message):
        GlobalVar.cursor.execute("UPDATE stu_section SET state = '5', result = '%s' WHERE id = '%d'" % (message, int(id)))
        GlobalVar.db.commit()

    @staticmethod
    def updateResult(id, state, result, output, time, memory):
        GlobalVar.cursor.execute("UPDATE stu_section SET state = '%s', result = '%s', output = '%s', timelimit = '%d', memory = '%d' WHERE id = '%d'" % (str(state), result, output, int(time), int(memory), int(id)))
        GlobalVar.db.commit()


class Core:
    @staticmethod
    def run(max_cpu_time,
        max_real_time,
        max_memory,
        max_stack,
        max_output_size,
        max_process_number,
        exe_path,
        input_path,
        output_path,
        error_path,
        args,
        env,
        log_path,
        seccomp_rule_name,
        uid,
        gid,
        memory_limit_check_only=0):
        str_list_vars = ["args", "env"]
        int_vars = ["max_cpu_time", "max_real_time",
                    "max_memory", "max_stack", "max_output_size",
                    "max_process_number", "uid", "gid", "memory_limit_check_only"]
        str_vars = ["exe_path", "input_path", "output_path", "error_path", "log_path"]

        proc_args = ["/usr/lib/judger/libjudger.so"]

        for var in str_list_vars:
            value = vars()[var]
            if not isinstance(value, list):
                raise ValueError("{} must be a list".format(var))
            for item in value:
                if not isinstance(item, str):
                    raise ValueError("{} item must be a string".format(var))
                proc_args.append("--{}={}".format(var, item))

        for var in int_vars:
            value = vars()[var]
            if not isinstance(value, int):
                raise ValueError("{} must be a int".format(var))
            if value != -1:
                proc_args.append("--{}={}".format(var, value))

        for var in str_vars:
            value = vars()[var]
            if not isinstance(value, str):
                raise ValueError("{} must be a string".format(var))
            proc_args.append("--{}={}".format(var, value))

        if not isinstance(seccomp_rule_name, str) and seccomp_rule_name is not None:
            raise ValueError("seccomp_rule_name must be a string or None")
        if seccomp_rule_name:
            proc_args.append("--seccomp_rule={}".format(seccomp_rule_name))

        proc = subprocess.Popen(proc_args, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        out, err = proc.communicate()
        if err:
            raise ValueError("Error occurred while calling judger: {}".format(err))
        return json.loads(out.decode("utf-8"))


def isPrint(message):
    if GlobalVar.printFlag == True:
        print(message)

# 获取系统内存，返回MB，用于判断内存是否足够用于判题，否则，等待内存足够
def getmem():
    with open('/proc/meminfo') as fd:
        for line in fd:
            if line.startswith('MemAvailable'):
                free = line.split()[1]
                fd.close()
                break
    return int(free)/1024.0

def compileC(id, compiler, code, file_name):
    file_c = file_name + ".c"
    file_out = file_name + ".out"
    file_msg = file_name + ".txt"
    file = open(file_c, "w", encoding='utf-8')
    file.write(code)
    file.close()
    if compiler == "gcc 7.5.0":
    	compiler_v = "gcc-7"
    else:
    	compiler_v = "gcc-8"
    result = os.system("timeout 10 %s %s -fmax-errors=3 -o %s -O2 -std=c11 2>%s" % (compiler_v, file_c, file_out, file_msg))
    if result:
        try:
            filece = open(file_msg, "r")
            msg = str(filece.read())
            if msg == "": 
            	msg = "Compile timeout! Maybe you define too big arrays!"
            else:
            	msg = msg.replace(file_c, "code")
            filece.close()
            Controller.compileError(id, msg)
        except:
            Controller.systemError(id, "Compiler Error!")
        GlobalVar.statue = True
        return False
    return True

def compileCPP(id, compiler, code, file_name):
    file_cpp = file_name + ".cpp"
    file_out = file_name + ".out"
    file_msg = file_name + ".txt"
    file = open(file_cpp, "w", encoding='utf-8')
    file.write(code)
    file.close()
    if compiler == "g++ 7.5.0":
    	compiler_v = "g++-7"
    else:
    	compiler_v = "g++-8"
    result = os.system("timeout 10 %s %s -fmax-errors=3 -o %s -O2 -std=c11 2>%s" % (compiler_v, file_cpp, file_out, file_msg))
    if result:
        try:
            filece = open(file_msg, "r")
            msg = str(filece.read())
            if msg == "": 
                msg = "Compile timeout! Maybe you define too big arrays!"
            else:
                msg = msg.replace(file_cpp, "code")
            filece.close()
            Controller.compileError(id, msg)
        except:
            Controller.systemError(id, "Compiler Error!")
        GlobalVar.statue = True
        return False
    return True

def compilePython(code, file_name):
    file_py = file_name + ".py"
    file = open(file_py, "w", encoding='utf-8')
    file.write(code)
    file.close()
    return True

def judgeC_CPP(timelimit, memorylimit, input_path, output_path, error_path):
    exe_file = GlobalVar.FILE_PATH_CODE + "/" + GlobalVar.judgername + ".out"
    log_path = GlobalVar.FILE_PATH_TESTCASE + "/" + GlobalVar.judgername + "judger.log"
    return Core.run(max_cpu_time = timelimit,
                    max_real_time = timelimit * 10,
                    max_memory = memorylimit * 1024 * 1024,
                    max_process_number = 200,
                    max_output_size = 32 * 1024 * 1024,
                    max_stack = 32 * 1024 * 1024,
                    exe_path = exe_file,
                    input_path = input_path,
                    output_path = output_path,
                    error_path = error_path,
                    args = [],
                    env = [],
                    log_path = log_path,
                    seccomp_rule_name = "c_cpp",
                    uid = 0,
                    gid = 0
                    )

def judgePython2(timelimit, memorylimit, input_path, output_path, error_path):
    file_py = GlobalVar.FILE_PATH_CODE + "/" + GlobalVar.judgername + ".py"
    log_path = GlobalVar.FILE_PATH_TESTCASE + "/" + GlobalVar.judgername + "judger.log"
    return Core.run(max_cpu_time = timelimit,
                    max_real_time = timelimit * 10,
                    max_memory = memorylimit * 1024 * 1024,
                    max_process_number = 200,
                    max_output_size = 32 * 1024 * 1024,
                    max_stack = 32 * 1024 * 1024,
                    exe_path = GlobalVar.python2path,
                    input_path = input_path,
                    output_path = output_path,
                    error_path = error_path,
                    args = [file_py],
                    env = [],
                    log_path = log_path,
                    seccomp_rule_name = "general",
                    uid = 0,
                    gid = 0
                    )

def judgePython3(timelimit, memorylimit, input_path, output_path, error_path):
    file_py = GlobalVar.FILE_PATH_CODE + "/" + GlobalVar.judgername + ".py"
    log_path = GlobalVar.FILE_PATH_TESTCASE + "/" + GlobalVar.judgername + "judger.log"
    return Core.run(max_cpu_time = timelimit,
                    max_real_time = timelimit * 10,
                    max_memory = memorylimit * 1024 * 1024,
                    max_process_number = 200,
                    max_output_size = 32 * 1024 * 1024,
                    max_stack = 32 * 1024 * 1024,
                    exe_path = GlobalVar.python3path,
                    input_path = input_path,
                    output_path = output_path,
                    error_path = error_path,
                    args = [file_py],
                    env = [],
                    log_path = log_path,
                    seccomp_rule_name = "general",
                    uid = 0,
                    gid = 0
                    )


def judge(id, compiler, code, input_file):
    isPrint("judging!!! stu_section_id: %d" % int(id))
    file_name_code = GlobalVar.FILE_PATH_CODE + "/" + GlobalVar.judgername
    if compiler == "gcc 7.5.0" or compiler == "gcc 8.4.0":
        if compileC(id, compiler, code, file_name_code) == False:
            return
    elif compiler == "g++ 7.5.0" or compiler == "g++ 8.4.0":
        if compileCPP(id, compiler, code, file_name_code) == False:
            return
    elif compiler == "python 2.7" or compiler == "python 3.6":
        if compilePython(code, file_name_code) == False:
            return
    else:
        Controller.systemError(id, "Unknown Language!")
        GlobalVar.statue = True
        return

    timelimit, memorylimit = Controller.getTimeMemory()
    file_name_testcase = GlobalVar.FILE_PATH_TESTCASE + "/" + GlobalVar.judgername

    try:
        waittime = 0
        while True:
            available_memory = getmem()
            if available_memory >= memorylimit / 5:
                break
            waittime = waittime + 1
            if waittime >= 10:
                Controller.systemError(id, "Memory Error!")
                GlobalVar.statue = True
                return
            sleep(1)
    except Exception as e:
        Controller.systemError(id, "Memory Error!")
        GlobalVar.statue = True
        return

    ret = []
    if compiler == "gcc 7.5.0" or compiler == "gcc 8.4.0" or compiler == "g++ 7.5.0" or compiler == "g++ 8.4.0":
        ret = judgeC_CPP(timelimit, memorylimit, input_file, "%stemp.out" % file_name_testcase, "%serror,out" % file_name_testcase)
    elif compiler == "python 2.7":
        ret = judgePython2(timelimit, memorylimit, input_file, "%stemp.out" % file_name_testcase, "%serror,out" % file_name_testcase)
    elif compiler == "python 3.6":
        ret = judgePython3(timelimit, memorylimit, input_file, "%stemp.out" % file_name_testcase, "%serror,out" % file_name_testcase)
    else:
        Controller.systemError(id, "Unknown Language!")
        GlobalVar.statue = True
        return

    test_time = ret["cpu_time"]
    test_memory = ret["memory"] / 1024 / 1024
    if ret["result"] != 0:
        if (ret["result"] == 4 and ret["exit_code"] == 127 and ret["signal"] == 0) or (ret["result"] == 4 and ret["exit_code"] == 0 and ret["signal"] == 31):
            result = "Memory Exceeded"
        else:
            if ret["result"] == 1 or ret["result"] == 2:
                result = "Time Exceeded"
            elif ret["result"] == 3:
                result = "Memory Exceeded"
            elif ret["result"] == 4:
                result = "Runtime Error"
            elif ret["result"] == 5:
                result = "System Error"
            else:
                result = "Unknown Error"
        Controller.updateResult(id, 4, result, "", test_time, test_memory)
    else:
        user_out = open("%stemp.out" % file_name_testcase, "r")
        stdout = ""
        while True:
            try:
                std = user_out.readline()
                if std == "":
                    break
                std = std.rstrip()
                stdout = stdout + std
            except:
                break
        user_out.close()
        Controller.updateResult(id, 3, "", stdout, test_time, test_memory)
        del stdout
    GlobalVar.statue = True

def reconnect():
    GlobalVar.clientsocket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    try:
        GlobalVar.db = MySQLdb.connect(GlobalVar.judgerjson["db_ip"], GlobalVar.judgerjson["db_user"], GlobalVar.judgerjson["db_pass"],
                            GlobalVar.judgerjson["db_database"], int(GlobalVar.judgerjson["db_port"]), charset='utf8')
        GlobalVar.cursor = GlobalVar.db.cursor()
        GlobalVar.clientsocket.connect((GlobalVar.host, GlobalVar.port))
        GlobalVar.statue = True
    except Exception as e:
        isPrint(e)
        isPrint("connect error!")
        pass

def MainLoop():
    while True:
        sleep(1)
        cur = 1
        try:
            data = GlobalVar.clientsocket.recv(1024)
            data = data.decode("utf-8")
            if data:
                if data == "getstatue":
                    if GlobalVar.statue == True:
                        GlobalVar.clientsocket.send("ok".encode("utf-8"))
                    else:
                        GlobalVar.clientsocket.send("notok".encode("utf-8"))
                elif data == "timeout":
                    isPrint("timeout!")
                    break
                elif data.find("judge") != -1:
                    GlobalVar.statue = False
                    tp = data.split("|")
                    cur = tp[1]
                    try:
                        data = Controller.getStuCode(tp[1])
                    except:
                        isPrint("too long no submit")
                        reconnect()
                        data = Controller.getStuCode(tp[1])

                    try:
                        Controller.updateState(tp[1], 2)

                        t = threading.Thread(target=judge, args=(tp[1], data[0], data[1], data[2]))
                        t.setDaemon(True)
                        t.start()
                    except:
                        GlobalVar.db.rollback()
                        GlobalVar.statue = True
            else:
                reconnect()
        except socket.error:
            reconnect()
        except Exception as e:
            isPrint(e)
            reconnect()

if __name__ == "__main__":
    GlobalVar.initGlobalVar()
    MainLoop()