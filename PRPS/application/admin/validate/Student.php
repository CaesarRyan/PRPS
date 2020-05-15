<?php
namespace app\admin\validate;

use think\Validate;

class Student extends Validate
{
    // 验证规则
    protected $rule = [
		['username', 'require|min:10|max:10', '学生学号禁止为空|学生学号为10位数字|学生学号为10位数字'],
		['class', 'max:20', '班级名称不超过20位'],
		['realname', 'require|max:20', '学生姓名禁止为空|学生姓名不超过20位'],
    ];
}