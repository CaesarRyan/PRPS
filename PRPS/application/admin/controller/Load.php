<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\LoadLog;

use think\Controller;
use think\Request;
use think\Db;

class Load extends Controller
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
		$class_name = UserInfo::distinct(true)
				->field('class')
				->select();
				
		$this->assign('new_message', $new_message);
		$this->assign('class_name', $class_name);
		return $this->fetch('index');
	}
	
	public function get_load_log($class = null, $username = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			if (empty($class) && empty($username)) {
				$log = LoadLog::order('time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = LoadLog::count();
			} else if (empty($username)) {
				$user = UserInfo::where('class', $class)
						->field('userid')
						->select();
				$userid = array();
				foreach ($user as $x) {
					array_push($userid, $x['userid']);
				}
				$log = LoadLog::where('userid', 'in', $userid)
						->order('time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = LoadLog::where('userid', 'in', $userid)
						->count();
			} else {
				$log = LoadLog::where('username', $username)
						->order('time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = LoadLog::where('username', $username)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $log]);
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
	
	public function del_load() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			LoadLog::where('id', $data['id'])
					->delete();
			return array('state' => true, 'message' => '删除成功');
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
}