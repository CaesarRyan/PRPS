<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\Course;
use app\admin\model\Chapter;
use app\admin\model\Question;
use app\admin\model\QueAns;
use app\admin\model\Section;
use app\admin\model\LoadLog;
use app\admin\model\StuCourse;
use app\admin\model\StuChapter;
use app\admin\model\StuQue;
use app\admin\model\StuSection;
use app\admin\model\StuTest;

use PHPExcel_IOFactory;

use think\Controller;
use think\Request;
use think\Db;

class Student extends Controller
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
		$course = Course::field('course_id, course_name')
				->select();
				
		$this->assign('new_message', $new_message);
		$this->assign('class_name', $class_name);
		$this->assign('course', $course);
		return $this->fetch('index');
	}
	
	public function get_stu($class = null, $username = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			if (empty($class) && empty($username)) {
				$user = UserInfo::field('userid, username, authority, recent_load, state, security, class, realname, telnumber')
						->order('class', 'asc')
						->order('username', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = UserInfo::count();
			} else if (empty($username)) {
				$user = UserInfo::field('userid, username, authority, recent_load, state, security, class, realname, telnumber')
						->where('class', $class)
						->order('username', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = UserInfo::where('class', $class)
						->count();
			} else {
				$user = UserInfo::field('userid, username, authority, recent_load, state, security, class, realname, telnumber')
						->where('username', $username)
						->select();
				$cnt = empty($user) ? 0 : 1;
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $user]);
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
	
	public function modify_student() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Student');
			if ($message === true) {
				if (empty($data['authority'])) {
					$message = '用户权限禁止为空';
				} else if (empty($data['state'])) {
					$message = '用户状态禁止为空';
				} else if (empty($data['security'])) {
					$message = '用户安全等级禁止为空';
				} else {
					if (is_numeric($data['security']) &&  $data['security'] >= 0 && $data['security'] <= 3) {
						UserInfo::where('userid', $data['userid'])
								->update(['username' => $data['username'], 'authority' => $data['authority'], 
										'realname' => $data['realname'], 'state' => $data['state'], 'class' => $data['class'], 
										'security' => $data['security'], 'telnumber' => $data['telnumber']]);
						return array('state' => true, 'message' => '修改成功');
					} else {
						$message = '安全等级在0～3之间';
					}
				}
				return array('state' => false, 'message' => $message);
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/student/index'), -1, 2);
		}
	}
	
	public function add_student() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Student');
			if ($message === true) {
				UserInfo::create(['username' => $data['username'], 'class' => $data['class'], 'realname' => $data['realname']]);
				$userid = UserInfo::max('userid');
				return array('state' => true, 'message' => $userid);
			}else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/student/index'), -1, 2);
		}
	}
	
	public function del_student() {
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
						
						// $username = UserInfo::where('userid', $data['userid'])
						// 		->field('username')
						// 		->find();
						// Db::table('user_problem')
						// 		->where('username', $username['username'])
						// 		->delete();
						
						if ($data['userid'] == session('userid')) {
							$message = '无法删除自己';
							return array('state' => false, 'message' => $message);
						}
						
						Db::startTrans();
						try {
							UserInfo::where('userid', $data['userid'])
									->delete();
							LoadLog::where('userid', $data['userid'])
									->delete();
							StuCourse::where('userid', $data['userid'])
									->delete();
							StuChapter::where('userid', $data['userid'])
									->delete();
							StuQue::where('userid', $data['userid'])
									->delete();
							StuSection::where('userid', $data['userid'])
									->delete();
							StuTest::where('userid', $data['userid'])
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
			return $this->error('操作禁止', url('admin/student/index'), -1, 2);
		}
	}
	
	public function stu_course($userid) {
		$new_message = Db::table('user_problem')
				->where('new', 'true')
				->count();
		$student = UserInfo::where('userid', $userid)
				->field('userid, username, realname, class')
				->find();
		$course = Course::field('course_id, course_name')
				->select();
					
		$this->assign('new_message', $new_message);
		$this->assign('student', $student);
		$this->assign('course', $course);
		return $this->fetch('stu_course');
	}
	
	public function get_stu_course($userid) {
		if (Request::instance()->isAjax()) {
			$stu_course = Db::table('stu_course')
					->alias(['stu_course' => 'stu', 'course' => 'cou'])
					->join('course', 'stu.course_id = cou.course_id')
					->where('stu.userid', $userid)
					->field('cou.course_id, cou.course_name, cou.teacher, cou.time, stu.score')
					->select();
			$cnt = StuCourse::where('userid', $userid)
					->count();
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $stu_course]);
		} else {
			return $this->error('操作禁止', url('admin/student/index'), -1, 2);
		}
	}
	
	public function add_stu_course($userid) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['course_id'])) {
				$message = '课程禁止为空';
				return array('state' => false, 'message' => $message);
			} else {
				$stu_course = StuCourse::where('userid', $userid)
						->where('course_id', $data['course_id'])
						->find();
				if (empty($stu_course)) {
					StuCourse::create(['userid' => $userid, 'course_id' => $data['course_id']]);
					$chapter = Chapter::where('course_id', $data['course_id'])
							->field('chapter_id')
							->select();
					$chapter_id = array();
					$stu_chapter = array();
					foreach ($chapter as $x) {
						array_push($chapter_id, $x['chapter_id']);
						array_push($stu_chapter, array('userid' => $userid, 'chapter_id' => $x['chapter_id']));
					}
					$section = Section::where('chapter_id', 'in', $chapter_id)
							->field('section_id')
							->select();
					$stu_section = array();
					foreach ($section as $x) {
						array_push($stu_section, array('userid' => $userid, 'section_id' => $x['section_id']));
					}
					$chapter = new StuChapter;
					$chapter->saveAll($stu_chapter);
					$section = new StuSection;
					$section->saveAll($stu_section);
					return array('state' => true, 'message' => '添加成功');
				} else {
					$message = '该学生已选修此课程';
					return array('state' => false, 'message' => $message);
				}
			}
		} else {
			return $this->error('操作禁止', url('admin/student/index'), -1, 2);
		}
	}
	
	public function del_stu_course($userid) {
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
						$chapter_id_temp = Chapter::where('course_id', $data['course_id'])
								->field('chapter_id')
								->select();
						$chapter_id = array();
						foreach ($chapter_id_temp as $x) {
							array_push($chapter_id, $x['chapter_id']);
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
							StuCourse::where('userid', $userid)
									->where('course_id', $data['course_id'])
									->delete();
							StuChapter::where('userid', $userid)
									->where('chapter_id', 'in', $chapter_id)
									->delete();
							StuQue::where('userid', $userid)
									->where('qid', 'in', $question_id)
									->delete();
							StuSection::where('userid', $userid)
									->where('section_id', 'in', $section_id)
									->delete();
							StuTest::where('userid', $userid)
									->where('test_id', 'in', $test_id)
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
			return $this->error('操作禁止', url('admin/student/index'), -1, 2);
		}
	}
	
	public function upload_xls() {
		if (Request::instance()->isPost()) {
			$file = request()->file('file');
			if ($file) {
				$info = $file->move(ROOT_PATH . 'public' . DS . 'uploads/xls');
				if ($info) {
					$temp = str_replace('\\', "/", $info->getSaveName());
					$path =  'uploads/xls/'. $temp;
					return array('code' => 1, 'msg' => '上传成功', 'data' => array('src' => $path));
				}
			}
			return array('code' => 0, 'msg' => $file->getError(), 'data' => array('src' => ''));
		} else {
			return $this->error('操作禁止', url('admin/student/index'), -1, 2);
		}
	}
	
	public function upload_student() {
		if (Request::instance()->isAjax()) {
			include_once EXTEND_PATH . 'PHPExcel/PHPExcel.php';
			$data = input('post.');
			if (empty($data['student_excel'])) {
				$message = '上传文件禁止为空';
				return array('state' => false, 'message' => $message);
			} else {
				try {
					$inputFileType = PHPExcel_IOFactory::identify($data['student_excel']);
					$objReader = PHPExcel_IOFactory::createReader($inputFileType);
					$objPHPExcel = $objReader->load($data['student_excel']);
				} catch(Exception $e) {
					$message = '文件解析失败';
					return array('state' => false, 'message' => $message);
				}
				$sheet = $objPHPExcel->getSheet(0);
				$highestRow = $sheet->getHighestRow();
				
				$rowData = $sheet->rangeToArray('B' . 6 . ':' . 'B' . $highestRow, NULL, TRUE, FALSE);
				$username = array();
				foreach ($rowData as $x) {
					array_push($username, $x[0]);
				}
				$temp = UserInfo::where('username', 'in', $username)
						->field('username')
						->select();
				$exist = array();
				foreach ($temp as $x) {
					array_push($exist, $x['username']);
				}
				
				$students = array();
				$username = array();
				$msg = array();
				$temp = array();
				for ($row = 6; $row <= $highestRow; $row++){
					$rowData = $sheet->rangeToArray('B' . $row . ':' . 'D' . $row, NULL, TRUE, FALSE);
					$temp['username'] = $rowData[0][0];
					$temp['realname'] = $rowData[0][1];
					$temp['class'] = $rowData[0][2];
					$message = $this->validate($temp, 'Student');
					if ($message === true) {
						if (!in_array($temp['username'], $exist)) {
							array_push($students, $temp);
							array_push($username, $temp['username']);
						} else {
							array_push($msg, $temp['username']);
						}
					} else {
						$message = '第' . ($row - 5) . '行存在不合法数据';
						return array('state' => false, 'message' => $message);
					}
				}

				$user = new UserInfo;
				$user->saveAll($students);
				$userid = UserInfo::where('username', 'in', $username)
						->field('userid')
						->select();
				if (file_exists($data['student_excel'])) {
					unlink($data['student_excel']);
				}
				
				if (!empty($students)) {
					$message = '成功导入' . count($students) . '名学生';
					if (!empty($msg)) {
						$message = $message . ',' . $msg[0] . '等' . count($msg). '名学生已存在';
					}
				} else {
					if (!empty($msg)) {
						$message = $msg[0] . '等' . count($msg). '名学生已存在';
					} else {
						$message = '导入失败';
					}
				}
				return array('state' => true, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/student/index'), -1, 2);
		}
	}
	
	public function upload_stu_course() {
		if (Request::instance()->isAjax()) {
			include_once EXTEND_PATH . 'PHPExcel/PHPExcel.php';
			$data = input('post.');
			if (empty($data['course_id'])) {
				$message = '选修课程禁止为空';
				if (file_exists($data['stu_course_excel'])) {
					unlink($data['stu_course_excel']);
				}
				return array('state' => false, 'message' => $message);
			} else if (empty($data['stu_course_excel'])) {
				$message = '上传文件禁止为空';
				return array('state' => false, 'message' => $message);
			} else {
				try {
					$inputFileType = PHPExcel_IOFactory::identify($data['stu_course_excel']);
					$objReader = PHPExcel_IOFactory::createReader($inputFileType);
					$objPHPExcel = $objReader->load($data['stu_course_excel']);
				} catch(Exception $e) {
					$message = '文件解析失败';
					return array('state' => false, 'message' => $message);
				}
				$sheet = $objPHPExcel->getSheet(0);
				$highestRow = $sheet->getHighestRow();
				// $highestColumn = $sheet->getHighestColumn();
				
				$rowData = $sheet->rangeToArray('B' . 6 . ':' . 'B' . $highestRow, NULL, TRUE, FALSE);
				$username = array();
				foreach ($rowData as $x) {
					array_push($username, $x[0]);
				}
				$user = UserInfo::where('username', 'in', $username)
						->field('userid')
						->select();
				$userid = array();
				foreach ($user as $x) {
					array_push($userid, $x['userid']);
				}
				
				$stu_has_course = StuCourse::where('userid', 'in', $userid)
						->where('course_id', $data['course_id'])
						->field('userid')
						->select();
				$exist = array();
				foreach ($stu_has_course as $x) {
					array_push($exist, $x['userid']);
				}
				
				$userid = array();
				foreach ($user as $x) {
					if (!in_array($x['userid'], $exist)) {
						array_push($userid, $x['userid']);
					}
				}

				$stu_course = array();
				foreach ($userid as $x) {
					array_push($stu_course, array('userid' => $x, 'course_id' => $data['course_id']));
				}
				$chapter = Chapter::where('course_id', $data['course_id'])
						->field('chapter_id')
						->select();
				$chapter_id = array();
				$stu_chapter = array();
				foreach ($chapter as $x) {
					array_push($chapter_id, $x['chapter_id']);
					foreach ($userid as $y) {
						array_push($stu_chapter, array('userid' => $y, 'chapter_id' => $x['chapter_id']));
					}
				}
				$section = Section::where('chapter_id', 'in', $chapter_id)
						->field('section_id')
						->select();
				$stu_section = array();
				foreach ($section as $x) {
					foreach ($userid as $y) {
						array_push($stu_section, array('userid' => $y, 'section_id' => $x['section_id']));
					}
				}
				
				$course = new StuCourse;
				$course->saveAll($stu_course);
				$chapter = new StuChapter;
				$chapter->saveAll($stu_chapter);
				$section = new StuSection;
				$section->saveAll($stu_section);
				
				if (file_exists($data['stu_course_excel'])) {
					unlink($data['stu_course_excel']);
				}
				
				$message = count($userid) . '名学生选课成功';
				if (count($user) < count($username)) {
					$message = $message . ',' . (count($username) - count($user)) . '名学生不存在，无法选课';
				}
				if (!empty($exist)) {
					$message = $message . ',' . count($exist) . '名学生已选择此门课程';
				}
				return array('state' => true, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/student/index'), -1, 2);
		}
	}
}