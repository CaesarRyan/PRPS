<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\Course;
use app\admin\model\Chapter as mChapter;
use app\admin\model\Knowledge;
use app\admin\model\Question;
use app\admin\model\QueAns;
use app\admin\model\QueKnow;
use app\admin\model\Section;
use app\admin\model\SecPro;
use app\admin\model\StuCourse;
use app\admin\model\StuChapter;
use app\admin\model\StuQue;
use app\admin\model\StuSection;
use app\admin\model\StuTest;

use think\Controller;
use think\Request;
use think\Db;

class Chapter extends Controller
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
	
	public function get_chapter($course_id = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			if (empty($course_id)) {
				$chapter = Db::table('chapter')
						->alias(['chapter' => 'cha', 'course' => 'cou'])
						->join('course', 'cha.course_id = cou.course_id')
						->field('cou.course_name, cou.course_id, cha.chapter_id, cha.chapter_name, cha.chapter_num')
						->order('cou.course_id', 'asc')
						->order('cha.chapter_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = mChapter::count();
			} else {
				$chapter = Db::table('chapter')
						->alias(['chapter' => 'cha', 'course' => 'cou'])
						->join('course', 'cha.course_id = cou.course_id')
						->where('cou.course_id', $course_id)
						->field('cou.course_name, cou.course_id, cha.chapter_id, cha.chapter_name, cha.chapter_num')
						->order('cha.chapter_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = mChapter::where('course_id', $course_id)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $chapter]);
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
	
	public function modify_chapter() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Chapter');
			if ($message === true) {
				$max_num = mChapter::where('course_id', $data['course_id'])
						->max('chapter_num');
				if ($data['chapter_num'] > $max_num || $data['chapter_num'] < 1) {
					$message = '章节序号在1～' . $max_num . '之间';
					return array('state' => false, 'message' => $message);
				} else {
					if ($data['last_num'] > $data['chapter_num']) {
						mChapter::where('course_id', $data['course_id'])
								->where('chapter_num', '>=', $data['chapter_num'])
								->where('chapter_num', '<', $data['last_num'])
								->setInc('chapter_num', 1);
					} else {
						mChapter::where('course_id', $data['course_id'])
								->where('chapter_num', '>', $data['last_num'])
								->where('chapter_num', '<=', $data['chapter_num'])
								->setInc('chapter_num', -1);
					}
					mChapter::where('chapter_id', $data['chapter_id'])
							->update(['chapter_num' => $data['chapter_num'], 'chapter_name' => $data['chapter_name']]);
					return array('state' => true, 'message' => '修改成功');
				}
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/chapter/index'), -1, 2);
		}
	}
	
	public function add_chapter() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Chapter');
			if ($message === true) {
				$max_num = mChapter::where('course_id', $data['course_id'])
						->max('chapter_num');
				if ($data['chapter_num'] > $max_num + 1) {
					$message = '添加的章节序号在1～' . ($max_num + 1) . '之间';
					return array('state' => false, 'message' => $message);
				} else {
					mChapter::where('course_id', $data['course_id'])
							->where('chapter_num', '>=', $data['chapter_num'])
							->setInc('chapter_num', 1);
					
					mChapter::create(['course_id' => $data['course_id'], 'chapter_num' => $data['chapter_num'], 
									'chapter_name' => $data['chapter_name']]);
									
					$chapter_id = mChapter::max('chapter_id');
					$user = StuCourse::where('course_id', $data['course_id'])
							->field('userid')
							->select();
					$stu_chapter = array();
					foreach ($user as $x) {
						array_push($stu_chapter, array('userid' => $x['userid'], 'chapter_id' => $chapter_id));
					}
					$chapter = new StuChapter;
					$chapter->saveAll($stu_chapter);
					
					return array('state' => true, 'message' => '添加成功');
				}
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/chapter/index'), -1, 2);
		}
	}
	
	public function del_chapter() {
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
						$knowledge_id_temp = Knowledge::where('chapter_id', $data['chapter_id'])
								->field('kid')
								->select();
						$knowledge_id = array();
						foreach ($knowledge_id_temp as $x) {
							array_push($knowledge_id, $x['kid']);
						}
						$question_id_temp = Question::where('chapter_id', $data['chapter_id'])
								->field('qid')
								->select();
						$question_id = array();
						foreach ($question_id_temp as $x) {
							array_push($question_id, $x['qid']);
						}
						$test_id_temp = QueAns::where('qid', 'in', $question_id)
								->field('test_id')
								->select();
						$test_id = array();
						foreach ($test_id_temp as $x) {
							array_push($test_id, $x['test_id']);
						}
						$section_id_temp = Section::where('chapter_id', $data['chapter_id'])
								->field('section_id')
								->select();
						$section_id = array();
						foreach ($section_id_temp as $x) {
							array_push($section_id, $x['section_id']);
						}
						$course_id = mChapter::where('chapter_id', $data['chapter_id'])
								->field('course_id, chapter_num')
								->find();
						
						Db::startTrans();
						try {
							mChapter::where('chapter_id', $data['chapter_id'])
									->delete();
							Knowledge::where('chapter_id', $data['chapter_id'])
									->delete();
							Question::where('chapter_id', $data['chapter_id'])
									->delete();
							QueAns::where('qid', 'in', $question_id)
									->delete();
							QueKnow::where('kid', 'in', $knowledge_id)
									->delete();
							Section::where('chapter_id', $data['chapter_id'])
									->delete();
							SecPro::where('section_id', 'in', $section_id)
									->delete();
							StuChapter::where('chapter_id', $data['chapter_id'])
									->delete();
							StuQue::where('qid', 'in', $question_id)
									->delete();
							StuSection::where('section_id', 'in', $section_id)
									->delete();
							StuTest::where('test_id', 'in', $test_id)
									->delete();
									
							mChapter::where('course_id', $course_id['course_id'])
									->where('chapter_num', '>', $course_id['chapter_num'])
									->setInc('chapter_num', -1);
							
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
			return $this->error('操作禁止', url('admin/chapter/index'), -1, 2);
		}
	}
}