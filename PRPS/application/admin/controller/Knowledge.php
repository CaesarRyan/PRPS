<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\Course;
use app\admin\model\Knowledge as mKnowledge;
use app\admin\model\QueKnow;

use think\Controller;
use think\Request;
use think\Db;

class Knowledge extends Controller
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
		$course = Course::field('course_id, course_name')
				->select();
		
		$this->assign('new_message', $new_message);
		$this->assign('course', $course);
		return $this->fetch('index');
	}
	
	public function get_knowledge($course_id = null, $chapter_num = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$join = [['course cou', 'cou.course_id = cha.course_id'], ['knowledge know', 'know.chapter_id = cha.chapter_id']];
			if (empty($course_id) && empty($chapter_num)) {
				$knowledge = Db::table('chapter')
						->alias('cha')
						->join($join)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num,
								know.kid, know.knowledge, know.know_num, know.difficulty')
						->order('cou.course_id', 'asc')
						->order('cha.chapter_num', 'asc')
						->order('know.know_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = mKnowledge::count();
			} else if (empty($course_id)) {
				$knowledge = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cha.chapter_num', $chapter_num)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num,
								know.kid, know.knowledge, know.know_num, know.difficulty')
						->order('cou.course_id', 'asc')
						->order('know.know_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cha.chapter_num', $chapter_num)
						->count();
			} else if (empty($chapter_num)) {
				$knowledge = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num,
								know.kid, know.knowledge, know.know_num, know.difficulty')
						->order('cha.chapter_num', 'asc')
						->order('know.know_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->count();
			} else {
				$knowledge = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->where('cha.chapter_num', $chapter_num)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num,
								know.kid, know.knowledge, know.know_num, know.difficulty')
						->order('know.know_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->where('cha.chapter_num', $chapter_num)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $knowledge]);
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
	
	public function modify_knowledge() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Knowledge');
			if ($message === true) {
				$chapter_id = mKnowledge::where('kid', $data['kid'])
						->field('chapter_id')
						->find();
				$max_num = mKnowledge::where('chapter_id', $chapter_id['chapter_id'])
						->max('know_num');
				if ($data['know_num'] > $max_num || $data['know_num'] < 1) {
					$message = '知识点序号在1～' . $max_num . '之间';
					return array('state' => false, 'message' => $message);
				} else {
					if ($data['last_num'] > $data['know_num']) {
						mKnowledge::where('chapter_id', $chapter_id['chapter_id'])
								->where('know_num', '>=', $data['know_num'])
								->where('know_num', '<', $data['last_num'])
								->setInc('know_num', 1);
					} else {
						mKnowledge::where('chapter_id', $chapter_id['chapter_id'])
								->where('know_num', '>', $data['last_num'])
								->where('know_num', '<=', $data['know_num'])
								->setInc('know_num', -1);
					}
					mKnowledge::where('kid', $data['kid'])
							->update(['knowledge' => $data['knowledge'], 'difficulty' => $data['difficulty'], 'know_num' => $data['know_num']]);
					return array('state' => true, 'message' => '修改成功');
				}
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/knowledge/index'), -1, 2);
		}
	}
	
	public function add_knowledge() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['course_id'])) {
				$message = '课程名称禁止为空';
				return array('state' => false, 'message' => $message);
			} else if (empty($data['chapter_num'])) {
				$message = '章节序号禁止为空';
				return array('state' => false, 'message' => $message);
			}
			$message = $this->validate($data, 'Knowledge');
			if ($message === true) {
				$chapter_id = Db::table('chapter')
						->alias(['chapter' => 'cha', 'course' => 'cou'])
						->join('course', 'cha.course_id = cou.course_id')
						->where('cou.course_id', $data['course_id'])
						->where('cha.chapter_num', $data['chapter_num'])
						->field('cha.chapter_id')
						->find();
				if (empty($chapter_id)) {
					$message = '该课程不存在此章节';
					return array('state' => false, 'message' => $message);
				} else {
					$max_num = mKnowledge::where('chapter_id', $chapter_id['chapter_id'])
							->max('know_num');
					if ($data['know_num'] > $max_num + 1) {
						$message = '添加的章节序号在1～' . ($max_num + 1) . '之间';
						return array('state' => false, 'message' => $message);
					} else {
						mKnowledge::where('chapter_id', $chapter_id['chapter_id'])
								->where('know_num', '>=', $data['know_num'])
								->setInc('know_num', 1);
						
						mKnowledge::create(['chapter_id' => $chapter_id['chapter_id'], 'knowledge' => $data['knowledge'], 
										'know_num' => $data['know_num'], 'difficulty' => $data['difficulty']]);
						return array('state' => true, 'message' => '添加成功');
					}
				}
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/knowledge/index'), -1, 2);
		}
	}
	
	public function del_knowledge() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$password = array('password' => $data['password'], 'repassword' => $data['password']);
			$message = $this->validate($password, 'Password');
			if ($message === true) {
				$user = UserInfo::where('userid', session('userid'))
						->where('authority', 'root')
						->field('password')
						->find();
				if (empty($user)) {
					$message = '用户不存在或权限不足';
				} else {
					if (md5($data['password']) == $user['password']) {
						$chapter_id = mKnowledge::where('kid', $data['kid'])
								->field('chapter_id, know_num')
								->find();
						
						Db::startTrans();
						try {
							mKnowledge::where('kid', $data['kid'])
									->delete();
							QueKnow::where('kid', $data['kid'])
									->delete();
									
							mKnowledge::where('chapter_id', $chapter_id['chapter_id'])
									->where('know_num', '>', $chapter_id['know_num'])
									->setInc('know_num', -1);
							
							Db::commit();
							return array('state' => true, 'message' => '删除成功');
						} catch (\Exception $e) {
							Db::rollback();
							return array('state' => false, 'message' => '删除失败');
						}
					} else {
						$message = '密码错误';
					}
				}
			}
			return array('state' => false, 'message' => $message);
		} else {
			return $this->error('操作禁止', url('admin/knowledge/index'), -1, 2);
		}
	}
}