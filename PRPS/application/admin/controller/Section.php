<?php
namespace app\admin\controller;

use app\admin\model\UserInfo;
use app\admin\model\Course;
use app\admin\model\Chapter;
use app\admin\model\Section as mSection;
use app\admin\model\StuCourse;
use app\admin\model\StuSection;
use app\admin\model\SecPro;

use think\Controller;
use think\Request;
use think\Db;

class Section extends Controller
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
	
	public function get_section($course_id = null, $chapter_num = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$join = [['course cou', 'cou.course_id = cha.course_id'], ['section sec', 'sec.chapter_id = cha.chapter_id']];
			if (empty($course_id) && empty($chapter_num)) {
				$section = Db::table('chapter')
						->alias('cha')
						->join($join)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num, sec.section_id, 
								sec.section_name, sec.section_info, sec.section_num, sec.audio')
						->order('cou.course_id', 'asc')
						->order('cha.chapter_num', 'asc')
						->order('sec.section_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = mSection::count();
			} else if (empty($course_id)) {
				$section = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cha.chapter_num', $chapter_num)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num, sec.section_id,
								sec.section_name, sec.section_info, sec.section_num, sec.audio')
						->order('cou.course_id', 'asc')
						->order('sec.section_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cha.chapter_num', $chapter_num)
						->count();
			} else if (empty($chapter_num)) {
				$section = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num, sec.section_id,
								sec.section_name, sec.section_info, sec.section_num, sec.audio')
						->order('cha.chapter_num', 'asc')
						->order('sec.section_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->count();
			} else {
				$section = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->where('cha.chapter_num', $chapter_num)
						->field('cou.course_name, cha.chapter_name, cha.chapter_num, sec.section_id,
								sec.section_name, sec.section_info, sec.section_num, sec.audio')
						->order('sec.section_num', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('chapter')
						->alias('cha')
						->join($join)
						->where('cou.course_id', $course_id)
						->where('cha.chapter_num', $chapter_num)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $section]);
		} else {
			return $this->error('操作禁止', url('admin/core/index'), -1, 2);
		}
	}
	
	public function upload_audio() {
		if (Request::instance()->isPost()) {
			$file = request()->file('file');
			if ($file) {
				$info = $file->move(ROOT_PATH . 'public' . DS . 'uploads/audio');
				if ($info) {
					$temp = str_replace('\\', "/", $info->getSaveName());
					$path =  'uploads/audio/'. $temp;
					return array('code' => 1, 'msg' => '上传成功', 'data' => array('src' => $path));
				}
			}
			return array('code' => 0, 'msg' => $file->getError(), 'data' => array('src' => ''));
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function modify_section() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			$message = $this->validate($data, 'Section');
			if ($message === true) {
				$chapter_id = mSection::where('section_id', $data['section_id'])
						->field('chapter_id')
						->find();
				$max_num = mSection::where('chapter_id', $chapter_id['chapter_id'])
						->max('section_num');
				if ($data['section_num'] > $max_num || $data['section_num'] < 1) {
					$message = '课节序号在1～' . $max_num . '之间';
					return array('state' => false, 'message' => $message);
				} else {
					if ($data['last_num'] > $data['section_num']) {
						mSection::where('chapter_id', $chapter_id['chapter_id'])
								->where('section_num', '>=', $data['section_num'])
								->where('section_num', '<', $data['last_num'])
								->setInc('section_num', 1);
					} else {
						mSection::where('chapter_id', $chapter_id['chapter_id'])
								->where('section_num', '>', $data['last_num'])
								->where('section_num', '<=', $data['section_num'])
								->setInc('section_num', -1);
					}
					mSection::where('section_id', $data['section_id'])
							->update(['section_num' => $data['section_num'], 'section_name' => $data['section_name'],
										'section_info' => $data['section_info'], 'audio' => $data['section_audio']]);
					return array('state' => true, 'message' => '修改成功');
				}
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function add_section() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['course_id'])) {
				$message = '课程名称禁止为空';
				return array('state' => false, 'message' => $message);
			} else if (empty($data['chapter_num'])) {
				$message = '章节序号禁止为空';
				return array('state' => false, 'message' => $message);
			}
			$message = $this->validate($data, 'Section');
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
					$max_num = mSection::where('chapter_id', $chapter_id['chapter_id'])
							->max('section_num');
					if ($data['section_num'] > $max_num + 1) {
						$message = '添加的课节序号在1～' . ($max_num + 1) . '之间';
						return array('state' => false, 'message' => $message);
					} else {
						mSection::where('chapter_id', $chapter_id['chapter_id'])
								->where('section_num', '>=', $data['section_num'])
								->setInc('section_num', 1);
						
						mSection::create(['chapter_id' => $chapter_id['chapter_id'], 'section_name' => $data['section_name'], 
										'section_num' => $data['section_num'], 'section_info' => $data['section_info'], 'audio' => $data['section_audio']]);
						$section_id = mSection::max('section_id');
						
						$user = StuCourse::where('course_id', $data['course_id'])
								->field('userid')
								->select();
						$stu_section = array();
						foreach ($user as $x) {
							array_push($stu_section, array('userid' => $x['userid'], 'section_id' => $section_id));
						}
						$section = new StuSection;
						$section->saveAll($stu_section);
						
						return array('state' => true, 'message' => $section_id);
					}
				}
			} else {
				return array('state' => false, 'message' => $message);
			}
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function copy_section() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['old_section_id'])) {
				$message = '要复制的课节ID禁止为空';
			} else if (empty($data['new_course_id'])) {
				$message = '新课节所在课程禁止为空';
			} else if (empty($data['new_chapter_num'])) {
				$message = '新课节所在章节禁止为空';
			} else if (empty($data['new_section_num'])) {
				$message = '新课节序号禁止为空';
			} else {
				$chapter_id = Db::table('chapter')
						->alias(['chapter' => 'cha', 'course' => 'cou'])
						->join('course', 'cha.course_id = cou.course_id')
						->where('cou.course_id', $data['new_course_id'])
						->where('cha.chapter_num', $data['new_chapter_num'])
						->field('cha.chapter_id')
						->find();
				$section = mSection::where('section_id', $data['old_section_id'])
						->find();
				if (empty($section)) {
					$message = '要复制的课节不存在';
				} else if (empty($chapter_id)) {
					$message = '该课程不存在此章节';
				} else {
					$max_num = mSection::where('chapter_id', $chapter_id['chapter_id'])
							->max('section_num');
					if ($data['new_section_num'] > $max_num + 1) {
						$message = '添加的课节序号在1～' . ($max_num + 1) . '之间';
					} else {
						mSection::where('chapter_id', $chapter_id['chapter_id'])
								->where('section_num', '>=', $data['new_section_num'])
								->setInc('section_num', 1);
						mSection::create(['chapter_id' => $chapter_id['chapter_id'], 'section_name' => $section['section_name'],
										'section_num' => $data['new_section_num'], 'section_info' => $section['section_info'], 'audio' => $section['audio']]);
						$section_id = mSection::max('section_id');
						$user = StuCourse::where('course_id', $data['new_course_id'])
								->field('userid')
								->select();
						$stu_section = array();
						foreach ($user as $x) {
							array_push($stu_section, array('userid' => $x['userid'], 'section_id' => $section_id));
						}
						$section = new StuSection;
						$section->saveAll($stu_section);
						
						$sec_pro = SecPro::where('section_id', $data['old_section_id'])
								->select();
						foreach ($sec_pro as $x) {
							SecPro::create(['section_id' => $section_id, 'time' => $x['time'], 'program' => $x['program']]);
						}
						return array('state' => true, 'message' => $section_id);
					}
				}
			}
			return array('state' => false, 'message' => $message);
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function del_section() {
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
						$audio = mSection::where('section_id', $data['section_id'])
								->field('audio')
								->find();
						if (file_exists($audio['audio'])) {
							unlink($audio['audio']);
						}
						
						$chapter_id = mSection::where('section_id', $data['section_id'])
								->field('chapter_id, section_num')
								->find();
						
						Db::startTrans();
						try {
							mSection::where('section_id', $data['section_id'])
									->delete();
							SecPro::where('section_id', $data['section_id'])
									->delete();
							StuSection::where('section_id', $data['section_id'])
									->delete();
							
							mSection::where('chapter_id', $chapter_id['chapter_id'])
									->where('section_num', '>', $chapter_id['section_num'])
									->setInc('section_num', -1);
			
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
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function detail($section_id) {
		$section = mSection::where('section_id', $section_id)
				->find();
		if (empty($section)) {
			$this->error('该课节不存在', url('admin/section/index'), -1, 2);
		} else {
			$new_message = Db::table('user_problem')
					->where('new', 'true')
					->count();
			$chapter = Chapter::where('chapter_id', $section['chapter_id'])
					->field('chapter_num, chapter_name, course_id')
					->find();
			$course = Course::where('course_id', $chapter['course_id'])
					->field('course_name')
					->find();
			$class_name = UserInfo::distinct(true)
					->field('class')
					->select();
			$this->assign('new_message', $new_message);
			$this->assign('section', $section);
			$this->assign('course', $course);
			$this->assign('chapter', $chapter);
			$this->assign('class_name', $class_name);
			return $this->fetch('detail');
		}
	}
	
	public function get_sec_pro($section_id, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			$sec_pro = SecPro::where('section_id', $section_id)
					->field('id, time, program')
					->order('time', 'asc')
					->limit($limit*($page-1), $limit)
					->select();
			$cnt = SecPro::where('section_id', $section_id)
					->count();
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $sec_pro]);
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function modify_sec_pro() {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['time'])) {
				$message = '显示时间禁止为空';
				return array('state' => false, 'message' => $message);
			} else if (empty($data['program'])) {
				$message = '显示代码禁止为空';
				return array('state' => false, 'message' => $message);
			} else {
				$time = SecPro::where('time', $data['time'])
						->where('id', '<>', $data['id'])
						->select();
				if (empty($time)) {
					SecPro::where('id', $data['id'])
							->update(['time' => $data['time'], 'program' => $data['program']]);
					return array('state' => true, 'message' => '添加成功');
				} else {
					$message = '该显示时间已存在';
					return array('state' => false, 'message' => $message);
				}
			}
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function add_sec_pro($section_id) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if (empty($data['time'])) {
				$message = '显示时间禁止为空';
				return array('state' => false, 'message' => $message);
			} else if (empty($data['program'])) {
				$message = '显示代码禁止为空';
				return array('state' => false, 'message' => $message);
			} else {
				$time = SecPro::where('time', $data['time'])
						->where('section_id', $section_id)
						->select();
				if (empty($time)) {
					SecPro::create(['section_id' => $section_id, 'time' => $data['time'], 'program' => $data['program']]);
					return array('state' => true, 'message' => '添加成功');
				} else {
					$message = '该时间已有显示的代码';
					return array('state' => false, 'message' => $message);
				}
			}
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function del_sec_pro() {
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
						SecPro::where('id', $data['id'])
								->delete();
						return array('state' => true, 'message' => '删除成功');
					} else {
						$message = '密码错误';
					}
				}
			}
			return array('state' => false, 'message' => $message);
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function get_stu_sec($section_id, $class_name = null, $username = null, $limit = 10, $page = 1) {
		if (Request::instance()->isAjax()) {
			if (empty($class_name) && empty($username)) {
				$stu_sec = Db::table('stu_section')
						->alias(['stu_section' => 'sec', 'user_info' => 'stu'])
						->join('user_info', 'sec.userid = stu.userid')
						->where('sec.section_id', $section_id)
						->field('stu.userid, stu.class, stu.username, stu.realname, sec.time, sec.accept')
						->order('stu.class', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = StuSection::where('section_id', $section_id)
						->count();
			} else if (empty($username)) {
				$stu_sec = Db::table('stu_section')
						->alias(['stu_section' => 'sec', 'user_info' => 'stu'])
						->join('user_info', 'sec.userid = stu.userid')
						->where('sec.section_id', $section_id)
						->where('stu.class', $class_name)
						->field('stu.userid, stu.class, stu.username, stu.realname, sec.time, sec.accept')
						->order('stu.class', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('stu_section')
						->alias(['stu_section' => 'sec', 'user_info' => 'stu'])
						->join('user_info', 'sec.userid = stu.userid')
						->where('sec.section_id', $section_id)
						->where('stu.class', $class_name)
						->count();
			} else if (empty($class_name)) {
				$stu_sec = Db::table('stu_section')
						->alias(['stu_section' => 'sec', 'user_info' => 'stu'])
						->join('user_info', 'sec.userid = stu.userid')
						->where('sec.section_id', $section_id)
						->where('stu.username', $username)
						->field('stu.userid, stu.class, stu.username, stu.realname, sec.time, sec.accept')
						->order('stu.class', 'asc')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('stu_section')
						->alias(['stu_section' => 'sec', 'user_info' => 'stu'])
						->join('user_info', 'sec.userid = stu.userid')
						->where('sec.section_id', $section_id)
						->where('stu.username', $username)
						->count();
			} else {
				$stu_sec = Db::table('stu_section')
						->alias(['stu_section' => 'sec', 'user_info' => 'stu'])
						->join('user_info', 'sec.userid = stu.userid')
						->where('sec.section_id', $section_id)
						->where('stu.class', $class_name)
						->where('stu.username', $username)
						->field('stu.userid, stu.class, stu.username, stu.realname, sec.time, sec.accept')
						->limit($limit*($page-1), $limit)
						->select();
				$cnt = Db::table('stu_section')
						->alias(['stu_section' => 'sec', 'user_info' => 'stu'])
						->join('user_info', 'sec.userid = stu.userid')
						->where('sec.section_id', $section_id)
						->where('stu.class', $class_name)
						->where('stu.username', $username)
						->count();
			}
			return json(['code' => 0, 'msg' => '', 'count' => $cnt, 'data' => $stu_sec]);
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
	
	public function modify_stu_sec($section_id) {
		if (Request::instance()->isAjax()) {
			$data = input('post.');
			if ($data['time'] == null) {
				$message = '学习时长禁止为空';
			} else if (!is_numeric($data['time'])) {
				$message = '学习时长禁止出现除数字外字符';
			} else if (empty($data['accept'])) {
				$message = '学习状态禁止为空';
			} else {
				StuSection::where('section_id', $section_id)
						->where('userid', $data['userid'])
						->update(['time' => $data['time'], 'accept' => $data['accept']]);
				return array('state' => true, 'message' => '修改成功');
			}
			return array('state' => false, 'message' => $message);
		} else {
			return $this->error('操作禁止', url('admin/section/index'), -1, 2);
		}
	}
}