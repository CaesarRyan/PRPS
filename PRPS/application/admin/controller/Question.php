<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\Course;
use app\admin\model\Chapter;
use app\admin\model\Knowledge;
use app\admin\model\Question as mQuestion;
use app\admin\model\QueAns;
use app\admin\model\QueKnow;
use app\admin\model\StuQue;
use app\admin\model\StuTest;
use app\admin\model\Setting;

use think\Controller;
use think\Request;
use think\Db;

class Question extends Controller
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
	
	public function get_question($course_id = null, $chapter_num = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$join = [['course cou', 'cou.course_id = cha.course_id'], ['question que', 'que.chapter_id = cha.chapter_id']];
			if (empty($course_id) && empty($chapter_num)) {
				$question = Db::table('chapter')
						->alias('cha')
						->join($join)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num, que.qid, 
								que.question_name, que.question, que.input, que.output, que.template_top, que.template_bottom, que.accept, que.total, que.acc_avg_row, que.time, que.memory')
						->order('cou.course_id', 'asc')
						->order('cha.chapter_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = mQuestion::count();
			} else if (empty($course_id)) {
				$question = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cha.chapter_num', $chapter_num)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num, que.qid, 
								que.question_name, que.question, que.input, que.output, que.template_top, que.template_bottom, que.accept, que.total, que.acc_avg_row, que.time, que.memory')
						->order('cou.course_id', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cha.chapter_num', $chapter_num)
						->count();
			} else if (empty($chapter_num)) {
				$question = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num, que.qid, 
								que.question_name, que.question, que.input, que.output, que.template_top, que.template_bottom, que.accept, que.total, que.acc_avg_row, que.time, que.memory')
						->order('cha.chapter_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->count();
			} else {
				$question = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->where('cha.chapter_num', $chapter_num)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num, que.qid, 
								que.question_name, que.question, que.input, que.output, que.template_top, que.template_bottom, que.accept, que.total, que.acc_avg_row, que.time, que.memory')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->where('cha.chapter_num', $chapter_num)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $question]);
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
	
	public function modify_question() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Question');
			if ($message === true) {
				if (empty($data['time'])) {
					$data['time'] = 1000;
				}
				if (empty($data['memory'])) {
					$data['memory'] = 125;
				}
				mQuestion::where('qid', $data['qid'])
						->update(['question_name' => $data['question_name'], 'question' => $data['question'],
									'input' => $data['input'], 'output' => $data['output'], 'template_top' => $data['template_top'],
									'template_bottom' => $data['template_bottom'], 'time' => $data['time'], 'memory' => $data['memory']]);
				return array('state' => true, 'message' => '修改成功');
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function add_question() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['course_id'])) {
				$message = '课程名称禁止为空';
				return array('state' => false, 'message' => $message);
			} else if (empty($data['chapter_num'])) {
				$message = '章节序号禁止为空';
				return array('state' => false, 'message' => $message);
			}
			$message = $this->validate($data, 'Question');
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
					if (empty($data['time'])) {
						$data['time'] = 1000;
					}
					if (empty($data['memory'])) {
						$data['memory'] = 125;
					}
					mQuestion::create(['chapter_id' => $chapter_id['chapter_id'], 'question_name' => $data['question_name'], 
										'question' => $data['question'], 'input' => $data['input'], 'output' => $data['output'], 'template_top' => $data['template_top'],
										'template_bottom' =>$data['template_bottom'], 'time' => $data['time'], 'memory' => $data['memory']]);
					$qid = mQuestion::max('qid');
					return array('state' => true, 'message' => $qid);
				}
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function copy_question() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['old_qid'])) {
				$message = '要复制的问题ID禁止为空';
			} else if (empty($data['new_course_id'])) {
				$message = '新问题所在课程禁止为空';
			} else if (empty($data['new_chapter_num'])) {
				$message = '新问题所在章节禁止为空';
			} else {
				$chapter_id = Db::table('chapter')
						->alias(['chapter' => 'cha', 'course' => 'cou'])
						->join('course', 'cha.course_id = cou.course_id')
						->where('cou.course_id', $data['new_course_id'])
						->where('cha.chapter_num', $data['new_chapter_num'])
						->field('cha.chapter_id')
						->find();
				$question = mQuestion::where('qid', $data['old_qid'])
						->find();
				if (empty($question)) {
					$message = '要复制的问题不存在';
				} else if (empty($chapter_id)) {
					$message = '该课程不存在此章节';
				} else {
					mQuestion::create(['chapter_id' => $chapter_id['chapter_id'], 'question_name' => $question['question_name'], 'question' => $question['question'],
									'input' => $question['input'], 'output' => $question['output'], 'template_top' => $question['template_top'], 'template_bottom' => $question['template_bottom'],
									'accept' => $question['accept'], 'total' => $question['total'], 'time' => $question['time'], 'memory' => $question['memory']]);
					$qid = mQuestion::max('qid');
					$testcase = QueAns::where('qid', $data['old_qid'])
							->select();
					foreach ($testcase as $x) {
						QueAns::create(['qid' => $qid, 'input' => $x['input'], 'output' => $x['output']]);
					}
					return array('state' => true, 'message' => $qid);
				}
			}
			return array('state' => false, 'message' => $message);
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function del_question() {
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
						$test_id_temp = QueAns::where('qid', $data['qid'])
								->field('test_id')
								->select();
						$test_id = array();
						foreach ($test_id_temp as $x) {
							array_push($test_id, $x['test_id']);
						}
						
						Db::startTrans();
						try {
							mQuestion::where('qid', $data['qid'])
									->delete();
							QueAns::where('qid', $data['qid'])
									->delete();
							QueKnow::where('qid', $data['qid'])
									->delete();
							StuQue::where('qid', $data['qid'])
									->delete();
							StuTest::where('test_id', 'in', $test_id)
									->delete();
			
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
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	
	public function detail($qid) {
		$question = mQuestion::where('qid', $qid)
				->find();
		if (empty($question)) {
			$this->error('该问题不存在', url('admin/question/index'), -1, 2);
		} else {
			$new_message = Db::table('user_problem')
					->where('new', 'true')
					->count();
			$chapter = Chapter::where('chapter_id', $question['chapter_id'])
					->field('chapter_num, chapter_name, course_id')
					->find();
			$course = Course::where('course_id', $chapter['course_id'])
					->field('course_id, course_name, compile')
					->find();
			$class_name = UserInfo::distinct(true)
					->field('class')
					->select();
			$this->assign('new_message', $new_message);
			$this->assign('question', $question);
			$this->assign('course', $course);
			$this->assign('chapter', $chapter);
			$this->assign('class_name', $class_name);
			return $this->fetch('detail');
		}
	}
	
	public function get_knowledge($chapter_id, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$knowledge = Knowledge::where('chapter_id', $chapter_id)
					->field('kid, knowledge, know_num, difficulty')
					->order('know_num', 'asc')
					->limit($limit*($page-1), $limit)
					->select();
			$cnt = Knowledge::where('chapter_id', $chapter_id)
					->count();
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $knowledge]);
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function get_que_know($qid, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$que_know = Db::table('que_know')
					->alias(['que_know' => 'que', 'knowledge' => 'know'])
					->join('knowledge', 'que.kid = know.kid')
					->where('que.qid', $qid)
					->field('know.knowledge, know.kid, know.know_num')
					->order('know.know_num', 'asc')
					->limit($limit*($page-1), $limit)
					->select();
			$cnt = QueKnow::where('qid', $qid)
					->count();
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $que_know]);
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function add_que_know($qid, $chapter_id) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$knowledge = Knowledge::where('chapter_id', $chapter_id)
					->where('know_num', $data['know_num'])
					->field('kid')
					->find();
			if (empty($knowledge)) {
				$message = '该章节不存在此知识点';
				return array('state' => false, 'message' => $message);
			} else {
				$que_know = QueKnow::where('qid', $qid)
						->where('kid', $knowledge['kid'])
						->find();
				if (empty($que_know)) {
					$chapter = Chapter::where('chapter_id', $chapter_id)
							->field('course_id')
							->find();
					$num = QueKnow::where('qid', $qid)
							->count();
					$max_num = Setting::where('course_id', $chapter['course_id'])
							->field('most_know_num')
							->find();
					if ($num >= $max_num['most_know_num']) {
						$message = '该问题已达到知识点数量上限（' . $max_num['most_know_num'] . ')，如果需要请到重要参数设置中更改';
						return array('state' => false, 'message' => $message);
					} else {
						QueKnow::create(['qid' => $qid, 'kid' => $knowledge['kid']]);
						return array('state' => true, 'message' => '添加成功');
					}
				} else {
					$message = '该问题已存在此知识点';
					return array('state' => true, 'message' => $message);
				}
			}
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function del_que_know($qid, $chapter_id) {
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
						$knowledge = Knowledge::where('chapter_id', $chapter_id)
								->where('know_num', $data['know_num'])
								->field('kid')
								->find();
						QueKnow::where('qid', $qid)
								->where('kid', $knowledge['kid'])
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
	
	public function get_stu_que($qid, $state = null, $class_name = null, $username = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			if (empty($state)) {
				$state = array('0', '1', '2', '3', '4', '5');
			} else {
				$state = array($state);
			}
			if (empty($class_name) && empty($username)) {
				$stu_que = Db::table('stu_que')
						->alias(['stu_que' => 'que', 'user_info' => 'stu'])
						->join('user_info', 'que.userid = stu.userid')
						->where('que.qid', $qid)
						->where('que.state', 'in', $state)
						->field('stu.userid, stu.class, stu.username, stu.realname, que.time, que.state, que.score, que.answer, que.row, que.result')
						->order('que.time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = StuQue::where('qid', $qid)
						->where('state', 'in', $state)
						->count();
			} else if (empty($username)) {
				$stu_que = Db::table('stu_que')
						->alias(['stu_que' => 'que', 'user_info' => 'stu'])
						->join('user_info', 'que.userid = stu.userid')
						->where('que.qid', $qid)
						->where('stu.class', $class_name)
						->where('que.state', 'in', $state)
						->field('stu.userid, stu.class, stu.username, stu.realname, que.time, que.state, que.score, que.answer, que.row, que.result')
						->order('stu.username', 'asc')
						->order('que.time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('stu_que')
						->alias(['stu_que' => 'que', 'user_info' => 'stu'])
						->join('user_info', 'que.userid = stu.userid')
						->where('que.qid', $qid)
						->where('stu.class', $class_name)
						->where('que.state', 'in', $state)
						->count();
			} else if (empty($class_name)) {
				$stu_que = Db::table('stu_que')
						->alias(['stu_que' => 'que', 'user_info' => 'stu'])
						->join('user_info', 'que.userid = stu.userid')
						->where('que.qid', $qid)
						->where('stu.username', $username)
						->where('que.state', 'in', $state)
						->field('stu.userid, stu.class, stu.username, stu.realname, que.time, que.state, que.score, que.answer, que.row, que.result')
						->order('que.time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('stu_que')
						->alias(['stu_que' => 'que', 'user_info' => 'stu'])
						->join('user_info', 'que.userid = stu.userid')
						->where('que.qid', $qid)
						->where('stu.username', $username)
						->where('que.state', 'in', $state)
						->count();
			} else {
				$stu_que = Db::table('stu_que')
						->alias(['stu_que' => 'que', 'user_info' => 'stu'])
						->join('user_info', 'que.userid = stu.userid')
						->where('que.qid', $qid)
						->where('stu.class', $class_name)
						->where('stu.username', $username)
						->where('que.state', 'in', $state)
						->field('stu.userid, stu.class, stu.username, stu.realname, que.time, que.state, que.score, que.answer, que.row, que.result')
						->order('que.time', 'desc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('stu_que')
						->alias(['stu_que' => 'que', 'user_info' => 'stu'])
						->join('user_info', 'que.userid = stu.userid')
						->where('que.qid', $qid)
						->where('stu.class', $class_name)
						->where('stu.username', $username)
						->where('que.state', 'in', $state)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $stu_que]);
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function modify_stu_que($qid) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'StuQue');
			if ($message === true) {
				StuQue::where('qid', $qid)
						->where('userid', $data['userid'])
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
	
	public function del_stu_que($qid) {
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
						StuQue::where('qid', $qid)
								->where('userid', $data['userid'])
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
	
	public function get_que_ans($qid, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$que_ans = QueAns::where('qid', $qid)
					->field('test_id, input, output')
					->limit($limit*($page-1), $limit)
					->select();
			$cnt = QueAns::where('qid', $qid)
					->count();
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $que_ans]);
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function add_que_ans($qid) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['output'])) {
				$message = '输出禁止为空';
				return array('state' => false, 'message' => $message);
			} else {
				QueAns::create(['qid' => $qid, 'input' => $data['input'], 'output' => $data['output']]);
				return array('state' => true, 'message' => '添加成功');
			}
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function modify_que_ans() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['output'])) {
				$message = '输出禁止为空';
				return array('state' => false, 'message' => $message);
			} else {
				QueAns::where('test_id', $data['test_id'])
						->update(['input' => $data['input'], 'output' => $data['output']]);
				return array('state' => true, 'message' => '修改成功');
			}
		} else {
			return $this->error('操作禁止', url('admin/question/index'), -1, 2);
		}
	}
	
	public function del_que_ans() {
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
						QueAns::where('test_id', $data['test_id'])
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
	
	public function testcase($userid, $qid) {
		$this->assign('userid', $userid);
		$this->assign('qid', $qid);
		return $this->fetch('testcase');
	}
	
	public function get_testcase($userid, $qid, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$stu_test = Db::table('stu_test')
					->alias(['stu_test' => 'stu', 'que_ans' => 'que'])
					->join('que_ans', 'que.test_id = stu.test_id')
					->where('que.qid', $qid)
					->where('stu.userid', $userid)
					->field('stu.test_id, stu.state, stu.time, stu.memory, que.input, que.output')
					->limit($limit*($page-1), $limit)
					->select();
			$cnt = Db::table('stu_test')
					->alias(['stu_test' => 'stu', 'que_ans' => 'que'])
					->join('que_ans', 'que.test_id = stu.test_id')
					->where('que.qid', $qid)
					->where('stu.userid', $userid)
					->count();
			return json(['code' => 0, 'msg' => '', 'count' => 0, 'data' => $stu_test]);
		} else {
			return $this->error('操作禁止', url('admin/question/detail', ['qid' => $qid]), -1, 2);
		}
	}
}