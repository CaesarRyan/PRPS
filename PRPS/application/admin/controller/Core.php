<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\Course;

use think\Controller;
use think\Session;
use think\Cookie;
use think\Request;
use think\Db;

class Core extends Controller
{
	public function _initialize()
	{
		if (!session('authority')) {
			return $this->error('您没有登录或登录过期', url('admin/index/login'), -1, 2);
		} else if (session('authority') != 'root') {
			return $this->error('非管理员禁止访问', url('admin/index/login'), -1, 2);
		}
	}
	
	public function index() {
		$new_message = Db::table('user_problem')
				->where('new', 'true')
				->count();
		
		$course = Course::field('course_id, course_name, teacher, time, image, announcement')
				->paginate(5);
		
		$student = UserInfo::field('username, recent_load, state, security, class, realname')
				->order('class', 'asc')
				->paginate(5);
		
		$this->assign('new_message', $new_message);
		$this->assign('course', $course);
		$this->assign('student', $student);
		return $this->fetch('main_page');
	}
	
	public function change_password() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$oldpassword = ['password' => $data['oldpassword'], 'repassword' => $data['oldpassword']];
			$newpassword = ['password' => $data['password'], 'repassword' => $data['repassword']];
			$message = $this->validate($oldpassword, 'Password');
			if ($message === true) {
				$message = $this->validate($newpassword, 'Password');
				if ($message === true) {
					$user = UserInfo::where('userid', session('userid'))
							->where('password', md5($data['oldpassword']))
							->field('username')
							->find();
					if (empty($user)) {
						$message = '密码错误';
					} else {
						UserInfo::where('userid', session('userid'))
								->update(['password' => md5($data['password'])]);
						$message = '密码修改成功';
						return array('state' => true, 'message' => $message, 'callback' => array('controller' => 'core', 'function' => 'index'));
					}
				}
			}
			return array('state' => false, 'message' => $message, 'callback' => null);
		} else {
			$new_message = Db::table('user_problem')
					->where('new', 'true')
					->count();
			$this->assign('new_message', $new_message);
			return $this->fetch('change_password');
		}
	}
	
	public function get_problem_feedback() {
		$new_message = Db::table('user_problem')
				->alias(['user_problem' => 'pro', 'user_info' => 'user'])
				->join('user_info', 'pro.username = user.username')
				->where('pro.new', 'true')
				->order('pro.time', 'desc')
				->paginate(5);
		$old_message = Db::table('user_problem')
				->alias(['user_problem' => 'pro', 'user_info' => 'user'])
				->join('user_info', 'pro.username = user.username')
				->where('pro.new', 'false')
				->order('pro.time', 'desc')
				->paginate(5);
		Db::table('user_problem')
				->where('new', 'true')
				->update(['new' => "false"]);
		$this->assign('new_message', $new_message);
		$this->assign('old_message', $old_message);
		return $this->fetch('get_problem_feedback');
	}
	
	public function delete_problem_feedback() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$result = Db::table('user_problem')
					->where('id', $data['pid'])
					->delete();
			if ($result) {
				return true;
			} else {
				return false;
			}
		}
		$this->error('操作禁止', url('admin/core/index'), -1, 2);
	}
	
	public function sign_out() {
		UserInfo::where('userid', session('userid'))
				->update(['state' => "off"]);
		Session::clear();
		Cookie::clear('cluster_');
		return $this->fetch('index/sign_out');
	}
	
	public function success_jump($message = null, $controller = 'core', $function = 'index') {
		$nextUrl = $controller . '/' . $function;
		$this->success($message, url($nextUrl), -1, 2);
	}
}
