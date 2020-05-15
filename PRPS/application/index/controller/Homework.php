<?php
namespace app\index\controller;

use app\index\model\Course;
use app\index\model\Chapter;
use app\index\model\Knowledge;
use app\index\model\Question;
use app\index\model\QueAns;
use app\index\model\StuCourse;
use app\index\model\StuChapter;
use app\index\model\StuQue;
use app\index\model\Setting;
use app\index\model\StuTest;

use think\Controller;
use think\Request;
use think\Cookie;
use think\Db;

class Homework extends Controller
{	
	public function _initialize()
	{
		if (!session('username')) {
			return $this->error('您没有登录或登录过期', url('index/index/login'), -1, 2);
		}
	}
	
	public function index() {
		$list = Course::field('course_id, course_name, teacher, time, image')
				->where('type', ['=', 'homework'], ['=', 'both'], 'or')
				->paginate(3);
		if (empty($list)) {
			$this->error('暂无课程', url('index/user/index'), -1, 2);
		} else {
			$this->assign('location', '作业系统');
			$this->assign('list', $list);
			return $this->fetch('course');
		}
	}
	
	
	
	public function chapter($course_id) {
		$course = Course::where('course_id', $course_id)
				->find();
		if (empty($course)) {
			$this->error('无此门课程', url('index/homework/index'), -1, 2);
		}
		$result = StuCourse::where('course_id', $course_id)
				->where('userid', session('userid'))
				->field('id')
				->find();
		if (empty($result)) {
			$this->error('您没有参与本门课程', url('index/homework/index'), -1, 2);
		}
		$chapter = Db::table('chapter')
				->alias(['chapter' => 'cha', 'stu_chapter' => 'stu'])
				->join('stu_chapter', 'cha.chapter_id = stu.chapter_id')
				->where('cha.course_id', $course_id)
				->where('stu.userid', session('userid'))
				->field('cha.chapter_id, cha.chapter_name, stu.state')
				->order('chapter_num', 'asc')
				->paginate(5);
		
		$join = [['stu_que stu', 'stu.qid = que.qid'], ['chapter cha', 'cha.chapter_id = que.chapter_id']];
		$recent_question = Db::table('question')
				->alias('que')
				->join($join)
				->where('stu.userid', session('userid'))
				->where('cha.course_id', $course_id)
				->field('que.qid, que.question_name')
				->order('stu.time', 'desc')
				->find();
		
		$accepted_question = StuQue::where('userid', session('userid'))
				->where('state', '3')
				->field('qid')
				->select();
		$temp = array();
		foreach ($accepted_question as $x) {
			array_push($temp, $x['qid']);
		}
		$temp = Db::table('question')
				->alias('que')
				->join($join)
				->where('stu.userid', session('userid'))
				->where('cha.course_id', $course_id)
				->where('stu.state', '<>', '3')
				->where('que.qid', 'not in', $temp)
				->field('que.qid, que.question_name')
				->order('stu.time', 'desc')
				->select();
		$max_num = Setting::where('course_id', $course_id)
				->field('max_recommend_num')
				->find();
		$recent_fail_question = array();
		$num = 0;
		foreach ($temp as $x) {
			if ($num >= $max_num['max_recommend_num']) {
				break;
			}
			if (!in_array($x, $recent_fail_question)) {
				array_push($recent_fail_question, $x);
				$num++;
			}
		}
		
		$user = controller('User');
		$score = $user->get_homework_score($course_id);
		
		$this->assign('location', '作业系统');
		$this->assign('course', $course);
		$this->assign('chapter', $chapter);
		$this->assign('recent_question', $recent_question);
		$this->assign('recent_fail_question', $recent_fail_question);
		$this->assign('score', $score);
		return $this->fetch('chapter');
	}
	
	
	
	public function cluster($chapter_id) {
		$chapter = $this->check_chapter_id($chapter_id);
		
		$flag = false;
		if (Cookie::has('chapter_id', 'cluster_')) {
			$cookie_chapter_id = Cookie::get('chapter_id', 'cluster_');
			if ($cookie_chapter_id == $chapter_id) {
				$flag = true;
				$cluster = Cookie::get('list', 'cluster_');
			}
		}
		
		$knowledge_list = Knowledge::where('chapter_id', $chapter_id)
				->field('kid, knowledge')
				->select();
		$knowledge = array();
		foreach ($knowledge_list as $x) {
			$knowledge[$x['kid']] = $x['knowledge'];
		}
		
		if ($flag == false) {
			$cluster = $this->get_cluster($chapter_id);
			$stu_answer = $this->get_stu_ans($chapter_id, $cluster);
		}
		
		$course = Course::where('course_id', $chapter['course_id'])
				->field('course_name')
				->find();
		
		$this->assign('location', '作业系统');
		$this->assign('course', $course);
		$this->assign('chapter', $chapter);
		$this->assign('cluster', $cluster);
		$this->assign('knowledge', $knowledge);
		
		return $this->fetch('cluster');
	}
	
	public function check_chapter_id($chapter_id) {
		$chapter = Chapter::where('chapter_id', $chapter_id)
				->find();
		if (empty($chapter)) {
			$this->error('无此章节', url('index/homework/index'), -1, 2);
		}
		if ($chapter['chapter_num'] > 1 && session('authority') != 'root') {
			$state = Db::table('chapter')
					->alias(['chapter' => 'cha', 'stu_chapter' => 'stu'])
					->join('stu_chapter', 'cha.chapter_id = stu.chapter_id')
					->where('cha.course_id', $chapter['course_id'])
					->where('stu.userid', session('userid'))
					->where('cha.chapter_num', $chapter['chapter_num']-1)
					->field('stu.state')
					->find();
			if (empty($state)) {
				$this->error('您没有参与本门课程', url('index/homework/index'), -1, 2);
			} else if ($state['state'] == 'false') {
				$this->error('请先通过先前章节', url('index/homework/chapter', ['course_id' => $chapter['course_id']]), -1, 2);
			}
		}
		return $chapter;
	}
	
	public function check_kid($rule, $a) {
		$len = count($a);
		for ($i = 0; $i < $len; $i++) {
			if (in_array($a[$i], $rule) == false) {
				return false;
			}
		}
		return true;
	}
	
	public function compare_equal($a, $b) {
		$len_a = count($a);
		$len_b = count($b);
		if ($len_a == $len_b) {
			for ($i = 0; $i < $len_a; $i++) {
				$flag = false;
				for ($j = 0; $j < $len_b; $j++) {
					if ($a[$i] == $b[$j]) {
						$flag = true;
					}
				}
				if ($flag == false) {
					return false;
				}
			}
		} else {
			return false;
		}
		return true;
	}
	
	public function max_kid($rule, $a) {
		$len = count($rule);
		$len_a = count($a);
		$result = [0, 0];
		for ($i = 0; $i < $len_a; $i++) {
			for ($j = 0; $j < $len; $j++) {
				if ($a[$i] == $rule[$j]) {
					if ($j > $result[1]) {
						$result[1] = $j;
						$result[0] = $i;
					}
				}
			}
		}
		return $result;
	}
	
	public function compare_level($rule, $a, $b) {
		if (empty($a)) {
			return false;
		} else if (empty($b)) {
			return true;
		} else {
			$len = count($rule);
			$a_max_kid = $this->max_kid($rule, $a);
			$b_max_kid = $this->max_kid($rule, $b);
			for ($i = 0; $i < $len; $i++) {
				if ($a_max_kid[1] > $b_max_kid[1]) {
					return true;
				} else if ($a_max_kid[1] < $b_max_kid[1]) {
					return false;
				} else {
					array_splice($a, $a_max_kid[0], 1);
					array_splice($b, $b_max_kid[0], 1);
					return $this->compare_level($rule, $a, $b);
				}
			}
		}
	}
	
	public function get_cluster($chapter_id) {
		$chapter = Chapter::where('chapter_id', $chapter_id)
				->field('course_id')
				->find();
		
		$list = Knowledge::where('chapter_id', $chapter_id)
				->field('kid, difficulty')
				->order('know_num', 'asc')
				->select();
		$difficulty = array();
		$knowledge = array();
		foreach ($list as $x) {
			array_push($knowledge, $x['kid']);
			$difficulty[$x['kid']] = $x['difficulty'];
		}
		
		$list = Question::where('chapter_id', $chapter_id)
				->field('qid')
				->select();
		if (empty($list)) {
			$this->error('此章节没有问题', url('index/homework/chapter', ['course_id' => $chapter['course_id']]), -1, 2);
		}
		$question = array();
		$flag = array();
		foreach ($list as $x) {
			$kid = array();
			foreach ($x->knowledges as $y) {
				array_push($kid, $y['kid']);
			}
			array_push($question, array($x['qid'], $kid));
			array_push($flag, true);
		}
		$len = count($question);
		for ($i = 0; $i < $len; $i++) {
			if ($this->check_kid($knowledge, $question[$i][1]) == false) {
				$this->error('数据不一致，请联系管理员(Error: 00003)', url('index/homework/index'));
			}
		}
		$list = array();
		for ($i = 0; $i < $len; $i++) {
			if ($flag[$i] == true) {
				$temp = [[$question[$i][0]], $question[$i][1]];
				$flag[$i] = false;
				for ($j = $i+1; $j < $len; $j++) {
					if ($flag[$j] == true) {
						if ($this->compare_equal($question[$i][1], $question[$j][1])) {
							array_push($temp[0], $question[$j][0]);
							$flag[$j] = false;
						}
					}
				}
				array_push($list, $temp);
			}
		}
		$len = count($list);
		for ($i = 0; $i < $len; $i++) {
			for ($j = $i+1; $j < $len; $j++) {
				if ($this->compare_level($knowledge, $list[$i][1], $list[$j][1])) {
					$temp = $list[$i];
					$list[$i] = $list[$j];
					$list[$j] = $temp;
				}
			}
		}
		$most_know_num= Setting::where('course_id', $chapter['course_id'])
				->field('most_know_num')
				->find();
		for ($i = 0; $i < $len; $i++) {
			$len_know = count($list[$i][1]);
			$result = 0;
			for ($j = 0; $j < $len_know; $j++) {
				$result += $difficulty[$list[$i][1][$j]];
			}
			$avg_difficulty = ($most_know_num['most_know_num'] == 0) ? 0 : round($result / $most_know_num['most_know_num'], 2);
			array_push($list[$i], $avg_difficulty);
		}
		return $list;
	}
	
	public function &get_stu_ans($chapter_id, &$cluster) {
		$chapter = Chapter::where('chapter_id', $chapter_id)
				->field('course_id')
				->find();
		
		$question = Question::where('chapter_id', $chapter_id)
				->field('qid')
				->select();
		$qid_list = array();
		foreach ($question as $x) {
			array_push($qid_list, $x['qid']);
		}
		$stu_answer = StuQue::where('userid', session('userid'))
				->where('qid', 'in', $qid_list)
				->field('qid, max(score) score')
				->group('qid')
				->select();
		
		$student = array();
		$len_cluster = count($cluster);
		$least_num= Setting::where('course_id', $chapter['course_id'])
				->field('least_num')
				->find();
		$flag_chapter = true;
		for ($i = 0; $i < $len_cluster; $i++) {
			$num = 0;
			$temp = array();
			$len_question = count($cluster[$i][0]);
			$len_answer = count($stu_answer);
			for ($j = 0; $j < $len_question; $j++) {
				$flag = false;
				for ($k = 0; $k < $len_answer; $k++) {
					if ($stu_answer[$k]['qid'] == $cluster[$i][0][$j]) {
						$accept = false;
						if ($stu_answer[$k]['score'] == '100') {
							$num++;
							$accept = true;
						}
						if (empty($stu_answer[$k]['score'])) {
							$stu_answer[$k]['score'] = 'zero';
						}
						array_push($temp, array('qid' => $cluster[$i][0][$j], 'score' => $stu_answer[$k]['score'], 'accept' => $accept));
						array_splice($stu_answer, $k, 1);
						$len_answer--;
						$k--;
						$flag = true;
					}
				}
				if ($flag == false) {
					array_push($temp, array('qid' => $cluster[$i][0][$j], 'accept' => false));
				}
			}
			if ($num >= $least_num['least_num'] || $num == $len_question) {
				$cluster[$i][3] = true;
			} else {
				$cluster[$i][3] = false;
				$flag_chapter = false;
			}
			array_push($student, $temp);
		}
		$stu_chapter_state = StuChapter::where('userid', session('userid'))
				->where('chapter_id', $chapter_id)
				->field('id, state')
				->find();
		if (empty($stu_chapter_state)) {
			$this->error('数据不一致，请联系管理员(Error: 00005)', url('index/homework/index'));
		}
		if ($stu_chapter_state['state'] == 'true') {
			if ($flag_chapter == false) {
				StuChapter::where('id', $stu_chapter_state['id'])
						->update(['state' => 'false']);
			}
		} else {
			if ($flag_chapter == true) {
				StuChapter::where('id', $stu_chapter_state['id'])
						->update(['state' => 'true']);
			}
		}
		Cookie::set('chapter_id', $chapter_id, ['prefix'=>'cluster_', 'expire'=>3600]);
		Cookie::set('list', $cluster, ['prefix'=>'cluster_', 'expire'=>3600]);
		Cookie::set('stu_answer', $student, ['prefix'=>'cluster_', 'expire'=>3600]);
		return $student;
	}
	
	
	
	public function question($chapter_id, $cluster_id) {
		$chapter = $this->check_chapter_id($chapter_id);
		
		$flag = false;
		if (Cookie::has('chapter_id', 'cluster_')) {
			$cookie_chapter_id = Cookie::get('chapter_id', 'cluster_');
			if ($cookie_chapter_id == $chapter_id) {
				$flag = true;
				$cluster = Cookie::get('list', 'cluster_');
				$stu_answer = Cookie::get('stu_answer', 'cluster_');
			}
		}
		if ($flag == false) {
			$cluster = $this->get_cluster($chapter_id);
			$stu_answer = $this->get_stu_ans($chapter_id, $cluster);
		}
		
		$this->check_cluster_id($chapter_id, $cluster_id, $cluster);
		
		$answer = $stu_answer[$cluster_id-1];
		
		$len_question = count($answer);
		$question = array();
		for ($i = 0; $i < $len_question; $i++) {
			array_push($question, $answer[$i]['qid']);
		}
		$question_list = Question::where('qid', 'in', $question)
				->field('qid, question_name')
				->select();
		$question = array();
		foreach ($question_list as $x) {
			$question[$x['qid']] = $x['question_name'];
		}
		
		$course = Course::where('course_id', $chapter['course_id'])
				->field('course_name')
				->find();
		
		$this->assign('location', '作业系统');
		$this->assign('course', $course);
		$this->assign('chapter', $chapter);
		$this->assign('cluster', $cluster_id);
		$this->assign('answer', $answer);
		$this->assign('question', $question);
		
		return $this->fetch('question');
	}
	
	public function check_cluster_id($chapter_id, $cluster_id, $cluster) {
		$len_cluster = count($cluster);
		if ($cluster_id > $len_cluster || $cluster_id < 1) {
			$this->error('关卡不存在', url('index/homework/cluster', ['chapter_id' => $chapter_id]), -1, 2);
		}
		if (session('authority') != 'root') {
			if ($cluster[$cluster_id-1][3] == false) {
				for ($i = 0; $i < $cluster_id-1; $i++) {
					if ($cluster[$i][3] == false) {
						$this->error('请先通过先前关卡', url('index/homework/cluster', ['chapter_id' => $chapter_id]), -1, 2);
					}
				}
			}
		}
	}
	
	
	
	public function submit($chapter_id, $cluster_id, $qid) {
		$result = $this->get_result($chapter_id, $cluster_id, $qid, 2);
		
		$question = Question::where('qid', $qid)
				->field('qid, question_name, question, input, output, accept, total, acc_avg_row, template_top, template_bottom')
				->find();
		$question['question'] = nl2br($question['question']);
		
		$stu_answer = StuQue::where('userid', session('userid'))
				->where('qid', $qid)
				->field('time, score, answer')
				->order('time', 'desc')
				->find();
		if (empty($stu_answer) == false) {
			if (empty($question['template_top']) == false) {
				$stu_answer['answer'] = str_replace(($question['template_top'] . "\r\n"), '', $stu_answer['answer']);
			}
			if (empty($question['template_bottom']) == false) {
				$stu_answer['answer'] = str_replace(("\r\n" . $question['template_bottom']), '', $stu_answer['answer']);
			}
			$stu_answer['answer'] = str_replace('<', '&lt;', $stu_answer['answer']);
			$stu_answer['answer'] = str_replace('>', '&gt;', $stu_answer['answer']);
		}
		
		if (empty($question['template_top']) && empty($question['template_bottom'])) {
			$template_flag = false;
		} else {
			$template_flag = true;
			$question['template_top'] = str_replace('<', '&lt;', $question['template_top']);
			$question['template_top'] = str_replace('>', '&gt;', $question['template_top']);
			$question['template_bottom'] = str_replace('<', '&lt;', $question['template_bottom']);
			$question['template_bottom'] = str_replace('>', '&gt;', $question['template_bottom']);
		}
		
		$all_question = Db::table('chapter')
				->alias(['chapter' => 'cha', 'question' => 'que'])
				->join('question', 'que.chapter_id = cha.chapter_id')
				->where('cha.course_id', $result['chapter']['course_id'])
				->field('que.qid')
				->select();
		$all_question_num = count($all_question);
		$all_qid = array();
		foreach ($all_question as $x) {
			array_push($all_qid, $x['qid']);
		}
		$stu_all_answer_num = StuQue::where('userid', session('userid'))
				->where('qid', 'in', $all_qid)
				->where('state', '3')
				->group('qid')
				->count();
		$all_finish = ($all_question_num == 0) ? 0 : round($stu_all_answer_num / $all_question_num, 4) * 100;
		
		$chapter_question_num = Question::where('chapter_id', $chapter_id)
				->count();
		$stu_chapter_answer_num = Db::table('question')
				->alias(['question' => 'que', 'stu_que' => 'stu'])
				->join('stu_que', 'que.qid = stu.qid')
				->where('que.chapter_id', $chapter_id)
				->where('stu.userid', session('userid'))
				->where('stu.state', '3')
				->group('que.qid')
				->count();
		$chapter_finish = ($chapter_question_num == 0) ? 0 : round($stu_chapter_answer_num / $chapter_question_num, 4) * 100;
				
		$knowledge = array();
		$knowledge_list = Knowledge::where('chapter_id', $chapter_id)
				->field('kid, knowledge')
				->select();
		foreach ($knowledge_list as $x) {
			$knowledge[$x['kid']] = $x['knowledge'];
		}
		$cluster_know = array();
		foreach ($result['cluster'][$cluster_id-1][1] as $x) {
			array_push($cluster_know, $knowledge[$x]);
		}
		
		$compiler = null;		
		if ($result['course']['compile'] == 'gcc') {
			$compiler = array('gcc 7.5.0', 'gcc 8.4.0');
			$editor_mode = 'c_cpp';
		} else if ($result['course']['compile'] == 'g++') {
			$compiler = array('g++ 7.5.0', 'g++ 8.4.0');
			$editor_mode = 'c_cpp';
		} else if ($result['course']['compile'] == 'python') {
			$compiler = array('python 3.6', 'python 2.7');
			$editor_mode = 'python';
		}
		
		$this->assign('location', '作业系统');
		$this->assign('course', $result['course']);
		$this->assign('chapter', $result['chapter']);
		$this->assign('cluster_id', $cluster_id);
		$this->assign('stu_answer', $stu_answer);
		$this->assign('cluster_know', $cluster_know);
		$this->assign('question', $question);
		$this->assign('template_flag', $template_flag);
		$this->assign('recommend_question_list', $result['recommend_question_list']);
		$this->assign('chapter_finish', $chapter_finish);
		$this->assign('all_finish', $all_finish);
		$this->assign('compiler', $compiler);
		$this->assign('editor_mode', $editor_mode);
		$this->assign('message', $result['message']);
		$this->assign('test_result', $result['test_result']);
		return $this->fetch('submit');
	}

	public function get_result($chapter_id, $cluster_id, $qid, $way) {
		$chapter = $this->check_chapter_id($chapter_id);

		$flag = false;
		if (Cookie::has('chapter_id', 'cluster_')) {
			$cookie_chapter_id = Cookie::get('chapter_id', 'cluster_');
			if ($cookie_chapter_id == $chapter_id) {
				$flag = true;
				$cluster = Cookie::get('list', 'cluster_');
				$answer = Cookie::get('stu_answer', 'cluster_');
			}
		}
		if ($flag == false) {
			$cluster = $this->get_cluster($chapter_id);
			$answer = $this->get_stu_ans($chapter_id, $cluster);
		}

		$course = Course::where('course_id', $chapter['course_id'])
				->field('course_name, compile')
				->find();

		$this->check_cluster_id($chapter_id, $cluster_id, $cluster);

		$len = count($cluster[$cluster_id-1][0]);
		$flag = false;
		for ($i = 0; $i < $len; $i++) {
			if ($cluster[$cluster_id-1][0][$i] == $qid) {
				$flag = true;
				break;
			}
		}
		if ($flag == false) {
			$this->error('该关卡中没有此问题', url('index/homework/question', ['chapter_id' => $chapter_id, 'cluster_id' => $cluster_id]), -1, 2);
		}

		$recommend_question_list = $this->get_recommend_question($qid, $chapter['course_id'], $cluster_id, $cluster, $answer);

		$result = StuQue::where('userid', session('userid'))
				->where('qid', $qid)
				->field('state, result')
				->order('id', 'desc')
				->find();
		$test_result = null;
		if (empty($result)) {
			$message = "新问题";
		} else {
			$test_id = QueAns::where('qid', $qid)
					->field('test_id')
					->select();
			if ($result['state'] == '3') {
				$message = "编译正确";
				$test = array();
				foreach ($test_id as $x) {
					array_push($test, $x['test_id']);
				}
				$test_result = StuTest::where('userid', session('userid'))
						->where('test_id', 'in', $test)
						->field('state, time, memory')
						->select();
			} else if ($result['state'] == '4') {
				$message = $result['result'];
				if (empty($message)) {
					$message = "编译正确";
					$test = array();
					foreach ($test_id as $x) {
						array_push($test, $x['test_id']);
					}
					$test_result = StuTest::where('userid', session('userid'))
							->where('test_id', 'in', $test)
							->field('state, time, memory')
							->select();
				}
			} else {
				$message = $result['result'];
			}
		}

		if ($way == 1) {
			$question = Question::where('qid', $qid)
					->field('qid, question_name')
					->find();
			
			$this->assign('location', '作业系统');
			$this->assign('course', $course);
			$this->assign('chapter', $chapter);
			$this->assign('cluster_id', $cluster_id);
			$this->assign('question', $question);
			$this->assign('message', $message);
			$this->assign('test_result', $test_result);
			$this->assign('recommend_question_list', $recommend_question_list);

			return $this->fetch('result');
		} else {
			return array('message' => $message, 'course' => $course, 'chapter' => $chapter, 'cluster' => $cluster, 
							'recommend_question_list' => $recommend_question_list, 'test_result' => $test_result);
		}
	}
	
	public function get_question_list($cluster_answer, $max_num, $qid) {
		$qid_list = array();
		foreach ($cluster_answer as $x) {
			if ($x['accept'] == false && $x['qid'] != $qid) {
				array_push($qid_list, $x['qid']);
			}
		}
		$result = Db::table('question')
				->where('qid', 'in', $qid_list)
				->field('qid, question_name')
				->limit($max_num)
				->select();
		return $result;
	}
	
	public function get_answer_info($cluster_id, $offset, $cluster_answer, $difficulty) {
		$accept_num = 0;
		$submit_num = 0;
		foreach ($cluster_answer as $x) {
			if ($x['accept'] == true) {
				$accept_num++;
			}
			if (!empty($x['score'])) {
				$submit_num++;
			}
		}
		if ($accept_num == 0 || $submit_num == 0) {
			$accept_num = ($accept_num == 0) ? $offset : $accept_num;
			$submit_num = ($submit_num == 0) ? $offset : $submit_num;
		}
		return array('cluster_id' => $cluster_id, 'accept_num' => $accept_num, 'submit_num' => $submit_num, 'difficulty' => $difficulty);
	}
	
	public function get_recommend_question($qid, $course_id, $cluster_id, $cluster, $answer) {
		$accept = StuQue::where('userid', session('userid'))
				->where('qid', $qid)
				->where('state', '3')
				->find();
		$setting = Setting::where('course_id', $course_id)
				->field('max_recommend_num, offset, weight_done_num, weight_accept, weight_difficulty')
				->find();
		$len_cluster = count($cluster);
		if (empty($accept)) {
			$current_cluster_info = $this->get_answer_info($cluster_id, $setting['offset'], $answer[$cluster_id-1], $cluster[$cluster_id-1][2]);
			if ($current_cluster_info['difficulty'] == 0) {
				return $this->get_question_list($answer[$cluster_id-1], $setting['max_recommend_num'], $qid);
			} else {
				$cluster_info = array();
				for ($i = 0; $i < $cluster_id-1; $i++) {
					if (!empty($cluster[$i][1])) {
						$flag = true;
						foreach ($cluster[$i][1] as $x) {
							if (!in_array($x, $cluster[$cluster_id-1][1])) {
								$flag = false;
								break;
							}
						}
						if ($flag == true) {
							$flag = false;
							foreach ($answer[$i] as $x) {
								if ($x['accept'] == false && $x['qid'] != $qid) {
									$flag = true;
									break;
								}
							}
							if ($flag == true) {
								$other_cluster_info = $this->get_answer_info($i+1, $setting['offset'], $answer[$i], $cluster[$i][2]);
								array_push($cluster_info, $other_cluster_info);
							}
						}
					}
				}
				if (empty($cluster_info)) {
					return $this->get_question_list($answer[$cluster_id-1], $setting['max_recommend_num'], $qid);
				} else {
					array_push($cluster_info, $current_cluster_info);
					$probability = array();
					$sum_submit = 0;
					foreach ($cluster_info as $x) {
						$sum_submit += $x['submit_num'];
					}
					$sum = 0;
					foreach ($cluster_info as $x) {
						$temp = pow(($x['submit_num'] / $sum_submit), -$setting['weight_done_num']) 
								* pow(($x['accept_num'] / $x['submit_num']), -$setting['weight_accept'])
								* pow($x['difficulty'], $setting['weight_difficulty']);
						array_push($probability, array('cluster_id' => $x['cluster_id'], 'probability' => $temp, 'que_num' => 0));
						$sum += $temp;
					}
					
					$len_probability = count($probability);
					$probability[0]['probability'] /= $sum;
					for ($i = 1; $i < $len_probability; $i++) {
						$probability[$i]['probability'] /= $sum;
						$probability[$i]['probability'] += $probability[$i-1]['probability'];
					}
					
					for ($i = 0; $i < $setting['max_recommend_num']; $i++) {
						$rand = rand(0, 1000) / 1000;
						for ($j = 0; $j < $len_probability; $j++) {
							if ($rand < $probability[$j]['probability']) {
								$probability[$j]['que_num']++;
								break;
							}
						}
					}

					$result = array();
					for ($i = $len_probability-1; $i >= 0; $i--) {
						$result = array_merge($result, $this->get_question_list($answer[$probability[$i]['cluster_id']-1], $probability[$i]['que_num'], $qid));
					}
					return $result;
				}
			}
		} else {
			$result = array();
			for ($i = $cluster_id-1; $i < $len_cluster; $i++) {
				$recommend_id = $i;
				if ($cluster[$i][3] == false) {
					break;
				}
			}
			for ($i = $recommend_id; $i >= $cluster_id-1; $i--) {
				$len_result = count($result);
				if ($len_result == $setting['max_recommend_num']) {
					break;
				} else {
					$other = $this->get_question_list($answer[$i], $setting['max_recommend_num'], $qid);
					$result = array_merge($result, $other);
				}
			}
			return $result;
		}
	}

	
	
	public function recommend($qid) {
		$question = Question::where('qid', $qid)
				->field('chapter_id')
				->find();
		if (empty($question)) {
			$this->error('该问题不存在', url('index/user/index'), -1, 2);
		}
		$chapter_id = $question['chapter_id'];
		$flag = false;
		if (Cookie::has('chapter_id', 'cluster_')) {
			$cookie_chapter_id = Cookie::get('chapter_id', 'cluster_');
			if ($cookie_chapter_id == $chapter_id) {
				$flag = true;
				$cluster = Cookie::get('list', 'cluster_');
			}
		}
		if ($flag == false) {
			$cluster = $this->get_cluster($chapter_id);
			$answer = $this->get_stu_ans($chapter_id, $cluster);
		}
		$cluster_id = $this->get_cluster_id($cluster, $qid);
		$this->redirect('index/homework/submit', ['chapter_id' => $chapter_id, 'cluster_id' => $cluster_id, 'qid' => $qid]);
	}
	
	public function get_cluster_id($cluster, $qid) {
		$len_cluster = count($cluster);
		for ($i = 0; $i < $len_cluster; $i++) {
			$len_question = count($cluster[$i][0]);
			for ($j = 0; $j < $len_question; $j++) {
				if ($cluster[$i][0][$j] == $qid) {
					return $i + 1;
				}
			}
		}
		$this->error('数据不一致，请联系管理员(Error: 00004)', url('index/homework/index'));
	}
	
	
	
	public function judge($qid, $template_flag) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$test_id = QueAns::where('qid', $qid)
					->field('test_id')
					->select();
			foreach ($test_id as $x) {
				$flag = StuTest::where('userid', session('userid'))
						->where('test_id', $x['test_id'])
						->find();
				if (empty($flag)) {
					StuTest::create(['userid' => session('userid'), 'test_id' => $x['test_id']]);
				}
			}
			// $has_submit = StuQue::where('userid', session('userid'))
			// 		->where('qid', $qid)
			// 		->field('id')
			// 		->find();
			// if (empty($has_submit)) {
			// 	$stu_test = array();
			// 	foreach ($test_id as $x) {
			// 		array_push($stu_test, array('userid' => session('userid'), 'test_id' => $x['test_id']));
			// 	}
			// 	$test = new StuTest;
			// 	$test->saveAll($stu_test);
			// }
			if ($template_flag == true) {
				$template = Question::where('qid', $qid)
						->field('template_top, template_bottom')
						->find();
				$code = $template['template_top'] . "\r\n" . $data['code'] . "\r\n" . $template['template_bottom'];
			} else {
				$code = $data['code'];
			}
			$chapter_id = Question::where('qid', $qid)
					->field('chapter_id')
					->find();
			$course_id = Chapter::where('chapter_id', $chapter_id['chapter_id'])
					->field('course_id')
					->find();
			$num = StuQue::where('userid', session('userid'))
					->where('qid', $qid)
					->count();
			$max_num = Setting::where('course_id', $course_id['course_id'])
					->field('save_max_num')
					->find();
			if ($num >= $max_num['save_max_num']) {
				$del = StuQue::where('userid', session('userid'))
						->where('qid', $qid)
						->order('time', 'asc')
						->field('id')
						->find();
				StuQue::where('id', $del['id'])
						->delete();
			}
			StuQue::create(['userid' => session('userid'), 'qid' => $qid, 'answer' => $code, 'row' => $data['row'], 'compiler' => $data['compiler']]);
			$id = StuQue::where('userid', session('userid'))
					->where('qid', $qid)
					->max('id');
			$has_accept = StuQue::where('userid', session('userid'))
					->where('qid', $qid)
					->where('state', '3')
					->find();
			
			$timer = 0;
			while (1) {
				sleep(1);
				$timer += 1;
				$result = StuQue::where('id', $id)
						->field('state, result')
						->find();
				if ($result['state'] == '3') {
					break;
				} else if ($result['state'] == '4') {
					break;
				} else if ($result['state'] == '5') {
					break;
				} else if ($result['state'] == '0') {
					if ($timer >= 5) {
						StuQue::where('id', $id)
								->update(['state' => '5', 'result' => '判题服务器超时！']);
						break;
					}
				} else if ($result['state'] == '1') {
					if ($timer >= 10) {
						StuQue::where('id', $id)
								->update(['state' => '5', 'result' => '判题机超时！']);
						break;
					}
				} else if ($timer >= 30) {
					StuQue::where('id', $id)
							->update(['state' => '5', 'result' => '判题超时！']);
					break;
				}
			}
			
			if ($result['state'] == '3') {
				if (empty($has_accept)) {
					$avg_row = Question::where('qid', $qid)
							->field('accept, acc_avg_row')
							->find();
					$row_num = round(($avg_row['accept'] * $avg_row['acc_avg_row'] + $data['row']) / ($avg_row['accept'] + 1), 2);
					Question::where('qid', $qid)
							->setInc('accept', 1);
					Question::where('qid', $qid)
							->setInc('total', 1);
					Question::where('qid', $qid)
							->update(['acc_avg_row' => $row_num]);
				}
				StuQue::where('id', $id)
						->update(['score' => 100]);
				$course = Db::table('chapter')
						->alias(['chapter' => 'cha', 'course' => 'cou'])
						->join('course', 'cha.course_id = cou.course_id')
						->where('cha.chapter_id', $chapter_id['chapter_id'])
						->field('cou.course_id, cou.type')
						->find();
				$user = controller('User');
				$score = $user->update_course_score($course['course_id'], $course['type']);
				StuCourse::where('userid', session('userid'))
						->where('course_id', $course['course_id'])
						->update(['score' => $score]);
			} else if ($result['state'] == '4') {
				if (empty($result['result'])) {
					$test = array();
					foreach ($test_id as $x) {
						array_push($test, $x['test_id']);
					}
					$temp = StuTest::where('userid', session('userid'))
							->where('test_id', 'in', $test)
							->field('state')
							->select();
					$sum = count($temp);
					$ac_num = 0;
					foreach ($temp as $x) {
						if ($x['state'] == 'Accepted') {
							$ac_num++;
						}
					}
					$score = ($sum == 0) ? 100 : round($ac_num / $sum, 2) * 100;
					StuQue::where('id', $id)
							->update(['score' => $score]);
				}
				if (empty($has_accept)) {
					Question::where('qid', $qid)
							->setInc('total', 1);
				}
			}
			if (Cookie::has('chapter_id', 'cluster_')) {
				$cookie_chapter_id = Cookie::get('chapter_id', 'cluster_');
				if ($cookie_chapter_id == $chapter_id['chapter_id']) {
					$cluster = Cookie::get('list', 'cluster_');
					$stu_answer = $this->get_stu_ans($cookie_chapter_id, $cluster);
				}
			}
			return true;
		} else {
			$this->error('Forbidden', url('index/user/index'));
		}
	}
}
