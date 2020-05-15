<?php
namespace app\index\controller;

use app\index\model\UserInfo;
use app\index\model\LoadLog;

use think\Controller;
use think\Session;
use think\Request;

class Index extends Controller
{
	public function index() {
		return $this->success('欢迎使用', url('index/index/login'), -1, 2);
	}
	
	public function login()
	{
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Login');
			if ($message === true) {
				$current_time = time();
				$user = UserInfo::where('username', $data['username'])
						->field('userid, password, authority, state, security, realname')
						->find();
				if (empty($user)) {
					$message = '用户不存在';
					return array('state' => false, 'message' => $message, 'callback' => null);
				} else if ($user['security'] <= 0) {
					$message = '账号被冻结，请联系管理员';
					return array('state' => false, 'message' => $message, 'callback' => null);
				} else {
					if ($user['state'] == 'frozen') {
						$last_time = LoadLog::where('userid', $user['userid'])
								->where('state', 'fail')
								->field('time')
								->order('time', 'desc')
								->find();
						if (empty($last_time)) {
							$message = '数据不一致，请联系管理员(Error: 00001)';
							return array('state' => false, 'message' => $message, 'callback' => null);
						} else {
							$wait_time = $current_time - strtotime($last_time['time']);
							if ($wait_time > 30 * 60) {
								UserInfo::where('userid', $user['userid'])
										->update(['state' => "off"]);
							} else {
								$message = '账号已锁定，请等待';
								$left_time = 30 * 60 - $wait_time;
								if ($left_time < 60) {
									$message = $message . $left_time . '秒';
								} else {
									$message = $message . (($left_time - $left_time % 60) / 60) . '分' . ($left_time % 60). '秒';
								}
								return array('state' => false, 'message' => $message, 'callback' => null);
							}
						}
					} else if ($user['state'] == 'unregistered') {
						$message = '请先注册';
						return array('state' => false, 'message' => $message, 'callback' => null);
					}
				}
				$cnt = LoadLog::where('userid', $user['userid'])
						->count();
				if ($cnt >= 20) {
					$del = LoadLog::where('userid', $user['userid'])
							->field('id')
							->order('time', 'asc')
							->find();
					LoadLog::where('id', $del['id'])
							->delete();
				}
				$password = md5($data['password']);
				if ($user['password'] == $password) {
					$newlog = LoadLog::create(['userid' => $user['userid'], 'username' => $data['username'], 'time' => date('Y-m-d H:i:s', $current_time), 'state' => 'succes']);
					if (session('username') == $data['username']) {
						$message = '您已经在线了';
						return array('state' => true, 'message' => $message, 'callback' => array('controller' => 'user', 'function' => 'index'));
					} else {
						Session::set('username', $data['username']);
						Session::set('userid', $user['userid']);
						Session::set('authority', $user['authority']);
						Session::set('realname', $user['realname']);
						UserInfo::where('userid', $user['userid'])
								->update(['state' => "on", 'recent_load' => date('Y-m-d H:i:s', $current_time)]);
						$message = '登录成功';
						return array('state' => true, 'message' => $message, 'callback' => array('controller' => 'user', 'function' => 'index'));
					}
				} else {
					$num = 5;
					$set_time = $current_time - 15 * 60;
					$succes_time = LoadLog::where('userid', $user['userid'])
							->where('state', 'succes')
							->field('time')
							->order('time', 'desc')
							->find();
					if (!empty($succes_time)) {
						if (strtotime($succes_time['time']) > $set_time) {
							$set_time = strtotime($succes_time['time']);
						}
					}
					$log = LoadLog::where('userid', $user['userid'])
							->where('state', 'fail')
							->whereTime('time', 'between', [date('Y-m-d H:i:s', $set_time), date('Y-m-d H:i:s', $current_time)])
							->field('id')
							->select();
					$newlog = LoadLog::create(['userid' => $user['userid'], 'username' => $data['username'], 'time' => date('Y-m-d H:i:s', $current_time), 'state' => 'fail']);
					$left_num = $num - count($log) - 1;
					if ($left_num <= 0) {
						UserInfo::where('userid', $user['userid'])
								->update(['state' => "frozen", 'security' => $user['security']-1]);
						$message = '账号锁定，请30分钟后再试';
					} else {
						$message = '密码错误，还有' . $left_num . '次机会';
					}
				}
			}
			return array('state' => false, 'message' => $message, 'callback' => null);
		} else {
			return $this->fetch('login');
		}
	}
	
	public function register()
	{
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Register');
			if ($message === true) {
				$user = UserInfo::where('username', $data['username'])
						->where('realname', $data['realname'])
						->field('userid, state')
						->find();
				if (empty($user)) {
					$message = '学号与真实姓名不符或未参与课程';
					return array('state' => false, 'message' => $message, 'callback' => null);
				} else {
					if ($user['state'] == 'unregistered') {
						$password = md5($data['password']);
						UserInfo::where('userid', $user['userid'])
								->update(['state' => "off", 'password' => $password, 'question' => $data['question'], 
											'answer' => $data['answer'], 'telnumber' => $data['phone']]);
						$message = '注册成功，请登录';
					} else {
						$message = '已经注册，请直接登录';
					}
					return array('state' => true, 'message' => $message, 'callback' => array('controller' => 'index', 'function' => 'login'));
				}
			}
			return array('state' => false, 'message' => $message, 'callback' => null);
		} else {
			return $this->fetch('register');
		}
	}
	
	public function get_user_question() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Username');
			if ($message === true) {
				$user = UserInfo::where('username', $data['username'])
						->field('question, state, security, authority')
						->find();
				if (empty($user)) {
					$message = '用户不存在';
				} else {
					if ($user['authority'] == 'root') {
						$message = '超级用户禁止找回密码';
					} else if ($user['state'] == 'unregistered') {
						$message = '请先注册';
					} else if ($user['security'] <= 0) {
						$message = '账号被冻结，请联系管理员';
					} else {
						$message = $user['question'];
						return array('state' => true, 'message' => $message);
					}
				}
			}
			return array('state' => false, 'message' => $message);
		} else {
			return $this->fetch('get_back_password');
		}
	}
	
	public function get_back_password() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$data_password = ['password' => $data['password'], 'repassword' => $data['repassword']];
			$message = $this->validate($data, 'Password');
			if ($message === true) {
				$user = UserInfo::where('username', $data['username'])
						->field('userid, security, answer, authority')
						->find();
				if (empty($user)) {
					$message = '数据不一致，请联系管理员(Error: 00002)';
				} else {
					if ($user['authority'] == 'root') {
						$message = '超级用户禁止找回密码';
					} else if ($user['security'] <= 0) {
						$message = '账号被冻结，请联系管理员';
					} else {
						if ($data['answer'] == $user['answer']) {
							$password = md5($data['password']);
							UserInfo::where('userid', $user['userid'])
									->update(['password' => $password]);
							$message = '修改成功，请登录';
							return array('state' => true, 'message' => $message, 'callback' => array('controller' => 'index', 'function' => 'login'));
						} else {
							$security = $user['security'] - 1;
							UserInfo::where('userid', $user['userid'])
									->update(['security' => $security]);
							if ($security <= 0) {
								$message = '账号被冻结，请联系管理员';
							} else {
								$message = '答案错误，还有' . $security . '次机会';
							}	
						}
					}
				}
			}
			return array('state' => false, 'message' => $message, 'callback' => null);
		} else {
			return $this->fetch('get_back_password');
		}
	}
	
	public function success_jump($message = null, $controller = 'index', $function = 'index') {
		$nextUrl = $controller . '/' . $function;
		$this->success($message, url($nextUrl), -1, 2);
	}
}
