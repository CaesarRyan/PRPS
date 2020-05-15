<?php
namespace app\admin\validate;

use think\Validate;

class Question extends Validate
{
    // 验证规则
    protected $rule = [
		['question_name', 'require|max:20', '问题名称禁止为空|问题名称不超过20位'],
		['question', 'require|max:500', '问题内容禁止为空|问题内容不超过500位'],
		['input', 'max:500', '输入样例不超过500位'],
		['output', 'require|max:500', '输出样例禁止为空|输出样例不超过500位'],
		['template_top', 'max:1000', '模版顶部不超过1000位'],
		['template_bottom', 'max:1000', '模版底部不超过1000位'],
		['time', 'number|between:100,2000', '时间禁止出现除数字外其他字符|时间在100～2000之间'],
		['memory', 'number|between:10,300', '内存禁止出现除数字外其他字符|内存在10～300之间'],
    ];
}