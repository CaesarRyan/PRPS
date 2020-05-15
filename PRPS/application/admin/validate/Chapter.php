<?php
namespace app\admin\validate;

use think\Validate;

class Chapter extends Validate
{
    // 验证规则
    protected $rule = [
		['course_id', 'require', '课程名称禁止为空'],
		['chapter_name', 'require|max:20', '章节名称禁止为空|章节名称不超过20位'],
		['chapter_num', 'require|number', '章节序号禁止为空|章节序号禁止出现除数字外字符'],
    ];
}