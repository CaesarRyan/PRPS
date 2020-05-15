<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\Course;
use app\admin\model\StuCourse;
use app\admin\model\StuQue;
use app\admin\model\StuSection;

use think\Controller;
use think\Request;
use think\Db;

class Score extends Controller
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
	
	public function get_stu_score($course_id = null, $username = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$join = [['user_info user', 'user.userid = sc.userid'], ['course cou', 'cou.course_id = sc.course_id']];
			if (empty($course_id) && empty($username)) {
				$score = Db::table('stu_course')
						->alias('sc')
						->join($join)
						->field('cou.course_id, cou.course_name, user.class, user.userid, user.realname, user.username, sc.score')
						->order('user.class', 'asc')
						->order('user.username', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = StuCourse::count();
			} else if (empty($username)) {
				$score = Db::table('stu_course')
						->alias('sc')
						->join($join)
						->where('cou.course_id', $course_id)
						->field('cou.course_id, cou.course_name, user.class, user.userid, user.realname, user.username, sc.score')
						->order('user.class', 'asc')
						->order('user.username', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = StuCourse::where('course_id', $course_id)
						->count();
			} else if (empty($course_id)) {
				$score = Db::table('stu_course')
						->alias('sc')
						->join($join)
						->where('user.username', $username)
						->field('cou.course_id, cou.course_name, user.class, user.userid, user.realname, user.username, sc.score')
						->order('user.class', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt =  Db::table('stu_course')
						->alias('sc')
						->join($join)
						->where('user.username', $username)
						->count();
			} else {
				$score = Db::table('stu_course')
						->alias('sc')
						->join($join)
						->where('cou.course_id', $course_id)
						->where('user.username', $username)
						->field('cou.course_id, cou.course_name, user.class, user.userid, user.realname, user.username, sc.score')
						->order('user.class', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt =  Db::table('stu_course')
						->alias('sc')
						->join($join)
						->where('cou.course_id', $course_id)
						->where('user.username', $username)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $score]);
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
	
	public function detail($course_id, $userid) {
		$course = Course::where('course_id', $course_id)
				->find();
		$user = UserInfo::where('userid', $userid)
				->find();
		if (empty($course)) {
			$this->error('该课程不存在', url('admin/score/index'), -1, 2);
		} else if (empty($user)) {
			$this->error('该学生不存在', url('admin/score/index'), -1, 2);
		} else {
			$new_message = Db::table('user_problem')
					->where('new', 'true')
					->count();
			$this->assign('new_message', $new_message);
			$this->assign('course', $course);
			$this->assign('student', $user);
			return $this->fetch('detail');
		}
	}
	
	public function get_stu_que($course_id, $userid, $state = null, $chapter_num = null, $qid = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			if (empty($state)) {
				$state = array('0', '1', '2', '3', '4', '5');
			} else {
				$state = array($state);
			}
			$join = [['stu_que sq', 'sq.qid = que.qid'], ['chapter cha', 'cha.chapter_id = que.chapter_id']];
			if (empty($chapter_num) && empty($qid)) {
				$stu_que = Db::table('question')
						->alias('que')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('sq.userid', $userid)
						->where('sq.state', 'in', $state)
						->field('cha.chapter_num, cha.chapter_name, que.qid, que.question_name, sq.time, sq.state, sq.score, sq.answer, sq.row, sq.compiler, sq.result')
						->order('cha.chapter_num', 'asc')
						->order('sq.time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('question')
						->alias('que')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('sq.userid', $userid)
						->where('sq.state', 'in', $state)
						->count();
			} else if (empty($chapter_num)) {
				$stu_que = Db::table('question')
						->alias('que')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('sq.userid', $userid)
						->where('que.qid', $qid)
						->where('sq.state', 'in', $state)
						->field('cha.chapter_num, cha.chapter_name, que.qid, que.question_name, sq.time, sq.state, sq.score, sq.answer, sq.row, sq.compiler, sq.result')
						->order('cha.chapter_num', 'asc')
						->order('sq.time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('question')
						->alias('que')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('sq.userid', $userid)
						->where('sq.state', 'in', $state)
						->where('que.qid', $qid)
						->count();
			} else if (empty($qid)) {
				$stu_que = Db::table('question')
						->alias('que')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('sq.userid', $userid)
						->where('cha.chapter_num', $chapter_num)
						->where('sq.state', 'in', $state)
						->field('cha.chapter_num, cha.chapter_name, que.qid, que.question_name, sq.time, sq.state, sq.score, sq.answer, sq.row, sq.compiler, sq.result')
						->order('sq.time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('question')
						->alias('que')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('sq.userid', $userid)
						->where('sq.state', 'in', $state)
						->where('cha.chapter_num', $chapter_num)
						->count();
			} else {
				$stu_que = Db::table('question')
						->alias('que')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('sq.userid', $userid)
						->where('sq.state', 'in', $state)
						->where('cha.chapter_num', $chapter_num)
						->where('que.qid', $qid)
						->field('cha.chapter_num, cha.chapter_name, que.qid, que.question_name, sq.time, sq.state, sq.score, sq.answer, sq.row, sq.compiler, sq.result')
						->order('sq.time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('question')
						->alias('que')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('sq.userid', $userid)
						->where('sq.state', 'in', $state)
						->where('cha.chapter_num', $chapter_num)
						->where('que.qid', $qid)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $stu_que]);
		} else {
			return $this->error('操作禁止', url('admin/score/index'), -1, 2);
		}
	}
	
	public function modify_stu_que($userid) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'StuQue');
			if ($message === true) {
				StuQue::where('qid', $data['qid'])
						->where('userid', $userid)
						->where('time', $data['time'])
						->update(['time' => $data['time'], 'state' => $data['state'], 'score' => $data['score'], 'answer' => $data['answer']]);
				return array('state' => true, 'message' => '修改成功');
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function del_stu_que($userid) {
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
						StuQue::where('qid', $data['qid'])
								->where('userid', $userid)
								->where('time', $data['time'])
								->delete();
						return array('state' => true, 'message' => '删除成功');
					} else {
						$message = '密码错误';
					}
				}
			}
			return array('state' => false, 'message' => $message);
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function get_stu_sec($course_id, $userid, $chapter_num = null, $section_num = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$join = [['stu_section ss', 'ss.section_id = sec.section_id'], ['chapter cha', 'cha.chapter_id = sec.chapter_id']];
			if (empty($chapter_num) && empty($qid)) {
				$stu_sec = Db::table('section')
						->alias('sec')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('ss.userid', $userid)
						->field('cha.chapter_num, cha.chapter_name, sec.section_id, sec.section_num, sec.section_name, ss.time, ss.accept')
						->order('cha.chapter_num', 'asc')
						->order('sec.section_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('section')
						->alias('sec')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('ss.userid', $userid)
						->count();
			} else if (empty($chapter_num)) {
				$stu_sec = Db::table('section')
						->alias('sec')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('ss.userid', $userid)
						->where('sec.section_num', $section_num)
						->field('cha.chapter_num, cha.chapter_name, sec.section_id, sec.section_num, sec.section_name, ss.time, ss.accept')
						->order('cha.chapter_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('section')
						->alias('sec')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('ss.userid', $userid)
						->where('sec.section_num', $section_num)
						->count();
			} else if (empty($section_num)) {
				$stu_sec = Db::table('section')
						->alias('sec')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('ss.userid', $userid)
						->where('cha.chapter_num', $chapter_num)
						->field('cha.chapter_num, cha.chapter_name, sec.section_id, sec.section_num, sec.section_name, ss.time, ss.accept')
						->order('sec.section_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('section')
						->alias('sec')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('ss.userid', $userid)
						->where('cha.chapter_num', $chapter_num)
						->count();
			} else {
				$stu_sec = Db::table('section')
						->alias('sec')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('ss.userid', $userid)
						->where('cha.chapter_num', $chapter_num)
						->where('sec.section_num', $section_num)
						->field('cha.chapter_num, cha.chapter_name, sec.section_id, sec.section_num, sec.section_name, ss.time, ss.accept')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('section')
						->alias('sec')
						->join($join)
						->where('cha.course_id', $course_id)
						->where('ss.userid', $userid)
						->where('cha.chapter_num', $chapter_num)
						->where('sec.section_num', $section_num)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $stu_sec]);
		} else {
			return $this->error('操作禁止', url('admin/score/index'), -1, 2);
		}
	}
	
	public function modify_stu_sec($userid) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['accept'])) {
				$message = '状态禁止为空';
				return array('state' => false, 'message' => $message);
			} else if (!is_numeric($data['time'])) {
				$message = '学习时长必须为数字';
				return array('state' => false, 'message' => $message);
			} else {
				StuSection::where('userid', $userid)
						->where('section_id', $data['section_id'])
						->update(['time' => $data['time'], 'accept' => $data['accept']]);
				return array('state' => true, 'message' => '修改成功');
			}
		} else {
			return $this->error('操作禁止', url('admin/score/index'), -1, 2);
		}
	}
}