<?php
namespace app\admin\validate;

use think\Validate;

class Course extends Validate
{
    // 验证规则
    protected $rule = [
		['course_name', 'require|max:20', '课程名称禁止为空|课程名称不超过20位'],
		['teacher', 'require|max:5', '任课教师禁止为空|任课教师不超过5位'],
		['time', 'require|max:20', '授课时间禁止为空|授课时间不超过20位'],
		['compile', 'require', '编译语言禁止为空'],
		['type', 'require', '课程类型禁止为空'],
		['announcement', 'require|max:100', '课程公告禁止为空|课程公告不超过100位'],
		['image', 'require', '课程图标禁止为空'],
    ];
}