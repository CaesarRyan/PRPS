<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\Course as mCourse;
use app\admin\model\Chapter;
use app\admin\model\Knowledge;
use app\admin\model\Question;
use app\admin\model\QueAns;
use app\admin\model\QueKnow;
use app\admin\model\Section;
use app\admin\model\SecPro;
use app\admin\model\StuChapter;
use app\admin\model\StuCourse;
use app\admin\model\StuQue;
use app\admin\model\StuSection;
use app\admin\model\StuTest;
use app\admin\model\Setting;

use think\Controller;
use think\Request;
use think\Db;

class Course extends Controller
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
	
	public function get_course($limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$course = mCourse::limit($limit*($page-1), $limit)
					->select();
			$cnt = mCourse::count();
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $course]);
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
	
	public function upload_image() {
		if (Request::instance()->isPost()) {
			$file = request()->file('file');
			if ($file) {
				$info = $file->validate(['ext'=>'jpg,png,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads/image');
				if ($info) {
					$temp = str_replace('\\', "/", $info->getSaveName());
					$path =  'uploads/image/'. $temp;
					return $result = array('code' => 1, 'msg' => '上传成功', 'data' => array('src' => $path));
				}
			}
			return array('code' => 0, 'msg' => $file->getError(), 'data' => array('src' => ''));
		} else {
			return $this->error('操作禁止', url('admin/course/index'), -1, 2);
		}
	}
	
	public function modify_course() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Course');
			if ($message === true) {
				$course = mCourse::where('course_name', $data['course_name'])
						->where('course_id', '<>', $data['course_id'])
						->field('course_id')
						->select();
				if (!empty($course)) {
					$message = '课程名称已存在';
					return array('state' => false, 'message' => $message);
				} else {
					mCourse::where('course_id', $data['course_id'])
							->update(['course_name' => $data['course_name'], 'teacher' => $data['teacher'],
									'time' => $data['time'], 'compile' => $data['compile'], 'type' => $data['type'],
									'image' => $data['image'], 'announcement' => $data['announcement']]);
					return array('state' => true, 'message' => '修改成功');
				}
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/course/index'), -1, 2);
		}
	}
	
	public function add_course() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Course');
			if ($message === true) {
				$course = mCourse::where('course_name', $data['course_name'])
						->find();
				if (empty($course)) {
					mCourse::create(['course_name' => $data['course_name'], 'teacher' => $data['teacher'],
									'time' => $data['time'], 'compile' => $data['compile'], 'type' => $data['type'],
									'image' => $data['image'], 'announcement' => $data['announcement']]);
					$course_id = mCourse::max('course_id');
					Setting::create(['course_id' => $course_id]);
					return array('state' => true, 'message' => '添加成功');
				} else {
					$message = '课程名称已存在';
					return array('state' => false, 'message' => $message);
				}
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/course/index'), -1, 2);
		}
	}
	
	public function copy_course() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['old_course_name'])) {
				$message = '要复制的课程名禁止为空';
			} else if (empty($data['new_course_name'])) {
				$message = '新课程名禁止为空';
			} else if ($data['old_course_name'] == $data['new_course_name']) {
				$message = '新课程名禁止与要复制的课程名相同';
			} else {
				$course = mCourse::where('course_name', $data['old_course_name'])
						->find();
				$new_course = mCourse::where('course_name', $data['new_course_name'])
						->field('course_id')
						->find();
				if (empty($course)) {
					$message = '要复制的课程不存在';
				} else if (!empty($new_course)) {
					$message = '新课程名称已存在';
				} else {
					Db::startTrans();
					try {
						mCourse::create(['course_name' => $data['new_course_name'], 'teacher' => $course['teacher'], 'time' => $course['time'],
										'compile' => $course['compile'], 'type' => $course['type'], 'image' => $course['image'], 'announcement' => $course['announcement']]);
						$course_id = mCourse::max('course_id');
						Setting::create(['course_id' => $course_id]);
						$chapter = Chapter::where('course_id', $course['course_id'])
								->select();
						$chapter_id_list = array();
						$kid_list = array();
						foreach ($chapter as $x) {
							Chapter::create(['course_id' => $course_id, 'chapter_name' => $x['chapter_name'], 'chapter_num' => $x['chapter_num']]);
							$chapter_id = Chapter::max('chapter_id');
							$chapter_id_list[$x['chapter_id']] = $chapter_id;
							
							$knowledge = Knowledge::where('chapter_id', $x['chapter_id'])
									->select();
							foreach ($knowledge as $y) {
								Knowledge::create(['chapter_id' => $chapter_id, 'knowledge' => $y['knowledge'], 'know_num' => $y['know_num'], 'difficulty' => $y['difficulty']]);
								$kid = Knowledge::max('kid');
								$kid_list[$y['kid']] = $kid;
							}
							
							$section = Section::where('chapter_id', $x['chapter_id'])
									->select();
							foreach ($section as $y) {
								Section::create(['chapter_id' => $chapter_id, 'section_name' => $y['section_name'], 'section_num' => $y['section_num'], 'section_info' => $y['section_info'], 'audio' => $y['audio']]);
								$section_id = Section::max('section_id');
								$sec_pro = SecPro::where('section_id', $y['section_id'])
										->select();
								foreach ($sec_pro as $z) {
									SecPro::create(['section_id' => $section_id, 'time' => $z['time'], 'program' => $z['program']]);
								}
							}
						}
						foreach ($chapter as $x) {
							$question = Question::where('chapter_id', $x['chapter_id'])
									->select();
							foreach ($question as $y) {
								Question::create(['chapter_id' => $chapter_id_list[$x['chapter_id']], 'question_name' => $y['question_name'], 'question' => $y['question'],
												'input' => $y['input'], 'output' => $y['output'], 'template_top' => $y['template_top'], 'template_bottom' => $y['template_bottom'],
												'accept' => $y['accept'], 'total' => $y['total'], 'time' => $y['time'], 'memory' => $y['memory']]);
								$qid = Question::max('qid');
								
								$testcase = QueAns::where('qid', $y['qid'])
										->select();
								foreach ($testcase as $z) {
									QueAns::create(['qid' => $qid, 'input' => $z['input'], 'output' => $z['output']]);
								}
								
								$que_know = QueKnow::where('qid', $y['qid'])
										->select();
								foreach ($que_know as $z) {
									QueKnow::create(['qid' => $qid, 'kid' => $kid_list[$z['kid']]]);
								}
							}
						}
						Db::commit();
						return array('state' => true, 'message' => '复制成功');
					} catch (\Exception $e) {
						Db::rollback();
						return array('state' => false, 'message' => '复制失败');
					}
				}
			}
			return array('state' => false, 'message' => $message);
		} else {
			return $this->error('操作禁止', url('admin/course/index'), -1, 2);
		}
	}
	
	public function del_course() {
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
						$image = mCourse::where('course_id', $data['course_id'])
								->field('image')
								->find();
						if (file_exists($image['image'])) {
							unlink($image['image']);
						}
						
						$chapter_id_temp = Chapter::where('course_id', $data['course_id'])
								->field('chapter_id')
								->select();
						$chapter_id = array();
						foreach ($chapter_id_temp as $x) {
							array_push($chapter_id, $x['chapter_id']);
						}
						$knowledge_id_temp = Knowledge::where('chapter_id', 'in', $chapter_id)
								->field('kid')
								->select();
						$knowledge_id = array();
						foreach ($knowledge_id_temp as $x) {
							array_push($knowledge_id, $x['kid']);
						}
						$question_id_temp = Question::where('chapter_id', 'in', $chapter_id)
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
						$section_id_temp = Section::where('chapter_id', 'in', $chapter_id)
								->field('section_id')
								->select();
						$section_id = array();
						foreach ($section_id_temp as $x) {
							array_push($section_id, $x['section_id']);
						}
						
						Db::startTrans();
						try {
							mCourse::where('course_id', $data['course_id'])
									->delete();
							Chapter::where('course_id', $data['course_id'])
									->delete();
							Knowledge::where('chapter_id', 'in', $chapter_id)
									->delete();
							Question::where('chapter_id', 'in', $chapter_id)
									->delete();
							QueAns::where('qid', 'in', $question_id)
									->delete();
							QueKnow::where('kid', 'in', $knowledge_id)
									->delete();
							Section::where('chapter_id', 'in', $chapter_id)
									->delete();
							SecPro::where('section_id', 'in', $section_id)
									->delete();
							StuCourse::where('course_id', $data['course_id'])
									->delete();
							StuChapter::where('chapter_id', 'in', $chapter_id)
									->delete();
							StuQue::where('qid', 'in', $question_id)
									->delete();
							StuSection::where('section_id', 'in', $section_id)
									->delete();
							StuTest::where('test_id', 'in', $test_id)
									->delete();
							Setting::where('course_id', $data['course_id'])
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
			return $this->error('操作禁止', url('admin/course/index'), -1, 2);
		}
	}
}
