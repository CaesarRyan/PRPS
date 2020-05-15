<?php
namespace app\index\controller;

use app\index\model\UserInfo;
use app\index\model\Course;
use app\index\model\StuQue;
use app\index\model\Setting;

use think\Controller;
use think\Session;
use think\Cookie;
use think\Request;
use think\Db;

class User extends Controller
{
	public function _initialize()
	{
		if (!session('username')) {
			return $this->error('您没有登录或登录过期', url('index/index/login'), -1, 2);
		}
	}
	
	public function index() {
		return $this->fetch('main_page');
	}
	
	public function problem_feedback() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Problem');
			if ($message === true) {
				Db::table('user_problem')
				    ->insert(['username' => session('username'), 'problem' => $data['problem']]);
				$message = '反馈成功，感谢您的支持';
				return array('state' => true, 'message' => $message, 'callback' => array('controller' => 'user', 'function' => 'index'));
			}
			return array('state' => false, 'message' => $message, 'callback' => null);
		} else {
			return $this->fetch('problem_feedback');
		}
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
						return array('state' => true, 'message' => $message, 'callback' => array('controller' => 'user', 'function' => 'index'));
					}
				}
			}
			return array('state' => false, 'message' => $message, 'callback' => null);
		} else {
			return $this->fetch('change_password');
		}
	}
	
	public function sign_out() {
		UserInfo::where('userid', session('userid'))
				->update(['state' => "off"]);
		Session::clear();
		Cookie::clear('cluster_');
		return $this->fetch('index/sign_out');
	}
	
	public function myinfo() {
		$course = Db::table('stu_course')
				->alias(['stu_course' => 'stu', 'course' => 'cou'])
				->join('course', 'stu.course_id = cou.course_id')
				->where('stu.userid', session('userid'))
				->field('cou.course_name, cou.course_id, cou.teacher, cou.time, cou.image, stu.score')
				->select();
		
		$stu_answer = Db::table('stu_que')
				->alias(['stu_que' => 'stu', 'question' => 'que'])
				->join('question que', 'stu.qid = que.qid')
				->where('userid', session('userid'))
				->field('stu.id, stu.qid, stu.time, stu.score, que.question_name')
				->order('time', 'desc')
				->paginate(5);
		
		$this->assign('course', $course);
		$this->assign('stu_answer', $stu_answer);
		
		return $this->fetch('myinfo');
	}
	
	public function get_homework_score($course_id) {
		$all_score = 0;
		$get_score = 0;
		$join = [['chapter cha', 'cha.chapter_id = que.chapter_id'], ['que_know qk', 'qk.qid = que.qid'], ['knowledge know', 'know.kid = qk.kid']];
		$all_know = Db::table('question')
				->alias('que')
				->join($join)
				->where('cha.course_id', $course_id)
				->field('know.difficulty')
				->select();
		foreach ($all_know as $x) {
			$all_score += $x['difficulty'];
		}
		$temp = StuQue::where('userid', session('userid'))
				->where('state', '3')
				->distinct(true)
				->field('qid')
				->select();
		$qid = array();
		foreach ($temp as $x) {
			array_push($qid, $x['qid']);
		}
		$stu_know = Db::table('question')
				->alias('que')
				->join($join)
				->where('cha.course_id', $course_id)
				->where('que.qid', 'in', $qid)
				->field('know.difficulty')
				->select();
		foreach ($stu_know as $x) {
			$get_score += $x['difficulty'];
		}
		return ($all_score == 0) ? 100 : round($get_score / $all_score, 4) * 100;
	}
	
	public function get_study_score($course_id) {
		$all_section = Db::table('chapter')
				->alias(['chapter' => 'cha', 'section' => 'sec'])
				->join('section', 'cha.chapter_id = sec.chapter_id')
				->where('cha.course_id', $course_id)
				->count();
		$stu_section = Db::table('chapter')
				->alias(['chapter' => 'cha', 'section' => 'sec', 'stu_section' => 'stu'])
				->join('section', 'cha.chapter_id = sec.chapter_id')
				->join('stu_section', 'stu.section_id = sec.section_id')
				->where('cha.course_id', $course_id)
				->where('stu.userid', session('userid'))
				->where('stu.state', '3')
				->count();
		return ($all_section == 0) ? 100 : round($stu_section / $all_section, 4) * 100;
	}
	
	public function update_course_score($course_id, $course_type) {
		if ($course_type == 'homework') {
			$score = $this->get_homework_score($course_id);
		} else if ($course_type == 'study') {
			$score = $this->get_study_score($course_id);
		} else {
			$homework_score = $this->get_homework_score($course_id);
			$study_score = $this->get_study_score($course_id);
			$proportion = Setting::where('course_id', $course_id)
					->field('homework_score_proportion')
					->find();
			$score = $homework_score * $proportion['homework_score_proportion'] + $study_score * (1 - $proportion['homework_score_proportion']);
		}
		return $score;
	}
	
	public function myprogram($id) {
		$program = StuQue::where('userid', session('userid'))
				->where('id', $id)
				->field('answer')
				->find();
		if (empty($program)) {
			$this->error('您没有提交过此题', url('index/index/login'), -1, 2);
		}
		$program['answer'] = str_replace('<', '&lt;', $program['answer']);
		$program['answer'] = str_replace('>', '&gt;', $program['answer']);
		$this->assign('program', $program['answer']);
		return $this->fetch('myprogram');
	}
	
	public function success_jump($message = null, $controller = 'user', $function = 'index') {
		$nextUrl = $controller . '/' . $function;
		$this->success($message, url($nextUrl), -1, 2);
	}
}
