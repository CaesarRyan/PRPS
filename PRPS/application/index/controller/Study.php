<?php
namespace app\index\controller;

use app\index\model\Course;
use app\index\model\StuCourse;
use app\index\model\StuSection;
use app\index\model\SecPro;

use think\Controller;
use think\Request;
use think\Db;

class Study extends Controller
{
	public function _initialize()
	{
		if (!session('username')) {
			return $this->error('您没有登录或登录过期', url('index/index/login'), -1, 2);
		}
	}
	
	public function index() {
		$list = Course::field('course_id, course_name, teacher, time, image')
				->where('type', ['=', 'study'], ['=', 'both'], 'or')
				->paginate(3);
		if (empty($list)) {
			$this->error('暂无课程', url('index/user/index'), -1, 2);
		} else {
			$this->assign('location', '学习系统');
			$this->assign('list', $list);
			return $this->fetch('course');
		}
	}
	
	public function chapter($course_id) {
		$course = Course::where('course_id', $course_id)
				->find();
		if (empty($course)) {
			$this->error('无此门课程', url('index/study/index'), -1, 2);
		}
		if (session('authority') != 'root') {
			$result = StuCourse::where('course_id', $course_id)
					->where('userid', session('userid'))
					->field('id')
					->find();
			if (empty($result)) {
				$this->error('您没有参与本门课程', url('index/study/index'), -1, 2);
			}
		}
		$join = [['chapter cha', 'cha.chapter_id = sec.chapter_id'], ['stu_section stu', 'stu.section_id = sec.section_id']];
		$chapter = Db::table('section')
				->alias('sec')
				->join($join)
				->where('cha.course_id', $course_id)
				->where('stu.userid', session('userid'))
				->field('cha.chapter_id, cha.chapter_name, cha.chapter_num, sec.section_id, sec.section_name, stu.accept, stu.time')
				->order(['cha.chapter_num'=> 'asc', 'sec.section_num' => 'asc'])
				->select();
		if (empty($chapter)) {
			$this->error('该课程暂无章节', url('index/study/index'), -1, 2);
		}
		$chapter_section = array();
		$len_chapter = count($chapter);
		$start = $chapter[0]['chapter_num'];
		$temp1['chapter_id'] = $chapter[0]['chapter_id'];
		$temp1['chapter_name'] = $chapter[0]['chapter_name'];
		$temp1['section'] = array();
		$temp2['section_id'] = $chapter[0]['section_id'];
		$temp2['section_name']= $chapter[0]['section_name'];
		$temp2['accept'] = $chapter[0]['accept'];
		array_push($temp1['section'], $temp2);
		for ($i = 1; $i < $len_chapter; $i++) {
			if ($chapter[$i]['chapter_num'] > $start) {
				array_push($chapter_section, $temp1);
				$start = $chapter[$i]['chapter_num'];
				$temp1['chapter_id'] = $chapter[$i]['chapter_id'];
				$temp1['chapter_name'] = $chapter[$i]['chapter_name'];
				$temp1['section'] = array();
			}
			$temp2['section_id'] = $chapter[$i]['section_id'];
			$temp2['section_name']= $chapter[$i]['section_name'];
			$temp2['accept'] = $chapter[$i]['accept'];
			array_push($temp1['section'], $temp2);
		}
		array_push($chapter_section, $temp1);
		
		$section_time = 0;
		for ($i = 0; $i < $len_chapter; $i++) {
			$section_time += $chapter[$i]['time'];
		}
		$time[0] = ($section_time - $section_time % 3600) / 3600;
		$time[1] = ($section_time % 3600 - $section_time % 3600 % 60) / 60;
		$time[2] = $section_time % 3600 % 60;
		
		$user = controller('User');
		$score = $user->get_study_score($course_id);
		
		$this->assign('location', '学习系统');
		$this->assign('course', $course);
		$this->assign('chapter', $chapter_section);
		$this->assign('time', $time);
		$this->assign('score', $score);
		
		return $this->fetch('chapter');
	}
	
	public function detail($course_id, $section_id) {
		$result = StuCourse::where('course_id', $course_id)
				->where('userid', session('userid'))
				->field('id')
				->find();
		if (empty($result)) {
			$this->error('您没有参与本门课程', url('index/study/index'), -1, 2);
		}
		$join = [['chapter cha', 'cha.chapter_id = sec.chapter_id'], ['stu_section stu', 'stu.section_id = sec.section_id']];
		$section = Db::table('section')
				->alias('sec')
				->join($join)
				->where('cha.course_id', $course_id)
				->where('stu.userid', session('userid'))
				->where('stu.section_id', $section_id)
				->find();
		if (empty($section)) {
			$this->error('本门课程不存在此课节', url('index/study/chapter', ['course_id' => $course_id]), -1, 2);
		}
		
		$course = Course::where('course_id', $section['course_id'])
				->field('course_name, compile')
				->find();
				
		$accept = StuSection::where('userid', session('userid'))
				->where('section_id', $section_id)
				->field('accept')
				->find();
		
		$compiler = null;
		if ($course['compile'] == 'gcc') {
			$compiler = array('gcc 7.5.0', 'gcc 8.4.0');
			$editor_mode = 'c_cpp';
		} else if ($course['compile'] == 'g++') {
			$compiler = array('g++ 7.5.0', 'g++ 8.4.0');
			$editor_mode = 'c_cpp';
		} else if ($course['compile'] == 'python') {
			$compiler = array('python 3.6', 'python 2.7');
			$editor_mode = 'python';
		}
		
		$this->assign('location', '学习系统');
		$this->assign('section', $section);
		$this->assign('course_name', $course['course_name']);
		$this->assign('accept', $accept['accept']);
		$this->assign('compiler', $compiler);
		$this->assign('editor_mode', $editor_mode);
		
		return $this->fetch('detail');
	}
	
	public function program() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$section_program = SecPro::where('section_id', $data['section_id'])
					->field('time, program')
					->order('time', 'asc')
					->select();
			$time = array();
			$program = array();
			if (empty($section_program) == false) {
				$len_program = count($section_program);
				for ($i = 0; $i < $len_program; $i++) {
					array_push($time, $section_program[$i]['time']);
					array_push($program, $section_program[$i]['program']);
				}
			}
			return array('program' => $program, 'time' => $time);
		} else {
			$this->error('禁止访问', url('index/study/index'), -1, 2);
		}
	}
	
	public function save_time() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$last_state = StuSection::where('userid', session('userid'))
					->where('section_id', $data['section_id'])
					->field('accept')
					->find();
			if ($last_state['accept'] == 'false' && $data['accept'] == 'true') {
				$course = Course::where('course_id', $data['course_id'])
						->field('type')
						->find();
				$user = controller('User');
				$score = $user->update_course_score($data['course_id'], $course['type']);
				StuCourse::where('userid', session('userid'))
						->where('course_id', $data['course_id'])
						->update(['score' => $score]);
			}
			StuSection::where('userid', session('userid'))
					->where('section_id', $data['section_id'])
					->update(['time' => $data['end_time'], 'accept' => $data['accept']]);
		} else {
			$this->error('禁止访问', url('index/study/index'), -1, 2);
		}
	}
	
	public function judge($section_id) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$id = StuSection::where('userid', session('userid'))
					->where('section_id', $section_id)
					->field('id')
					->find();
			StuSection::where('id', $id['id'])
					->update(['judge' => 'true', 'code' => $data['code'], 'compiler' => $data['compiler'], 'input' => $data['input']]);
			$timer = 0;
			while (1) {
				sleep(1);
				$timer += 1;
				$result = StuSection::where('id', $id['id'])
						->field('judge, state')
						->find();
				if ($result['judge'] == 'true') {
					if ($timer >= 5) {
						StuSection::where('id', $id['id'])
								->update(['judge' => 'false', 'state' => '5', 'result' => '判题服务器超时！']);
						break;
					}
				} else {
					if ($result['state'] == '3') {
						break;
					} else if ($result['state'] == '4') {
						break;
					} else if ($result['state'] == '5') {
						break;
					} else if ($result['state'] == '1') {
						if ($timer >= 10) {
							StuSection::where('id', $id['id'])
									->update(['state' => '5', 'result' => '判题机超时！']);
							break;
						}
					} else if ($timer >= 30) {
						StuSection::where('id', $id['id'])
								->update(['state' => '5', 'result' => '判题超时！']);
						break;
					}
				}
			}
			$info = StuSection::where('id', $id['id'])
					->field('state, result, output, timelimit, memory')
					->find();
			if (empty($info['result'])) {
				$compile_result = '编译正确';
			} else {
				$compile_result = $info['result'];
			}
			return array('compile_result' => $compile_result, 'timelimit' => $info['timelimit'], 'memory' => $info['memory'], 'output' => $info['output']);
		} else {
			$this->error('Forbidden', url('index/user/index'));
		}
	}
}
