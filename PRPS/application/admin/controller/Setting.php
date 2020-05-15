<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\Setting as mSetting;

use think\Controller;
use think\Request;
use think\Db;

class Setting extends Controller
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
		
		$this->assign('new_message', $new_message);
		return $this->fetch('index');
	}
	
	public function get_setting($limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$setting = Db::table('setting')
					->alias(['setting' => 'set', 'course' => 'cou'])
					->join('course', 'set.course_id = cou.course_id')
					->select();
			$cnt = mSetting::count();
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $setting]);
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
	
	public function modify_setting() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Setting');
			if ($message === true) {
				if ($data['offset'] <= 0) {
					$message = '补偿量必须大于0';
				} else {
					mSetting::where('course_id', $data['course_id'])
						->update(['least_num' => $data['least_num'], 'save_max_num' => $data['save_max_num'], 'most_know_num' => $data['most_know_num'],
									'homework_score_proportion' => $data['homework_score_proportion'],
									'max_recommend_num' => $data['max_recommend_num'], 'offset' => $data['offset'],
									'weight_done_num' => $data['weight_done_num'], 'weight_accept' => $data['weight_accept'],
									'weight_difficulty' => $data['weight_difficulty'], 'study_timelimit' => $data['study_timelimit'],
									'study_memorylimit' => $data['study_memorylimit']]);
					return array('state' => true, 'message' => '修改成功');
				}
			}
			return array('state' => false, 'message' => $message);
		} else {
			return $this->error('操作禁止', url('admin/setting/index'), -1, 2);
		}
	}
}