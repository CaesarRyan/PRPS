<?php
namespace app\admin\validate;

use think\Validate;

class Knowledge extends Validate
{
    // 验证规则
    protected $rule = [
		['knowledge', 'require|max:20', '知识点内容禁止为空|知识点内容不超过20位'],
		['know_num', 'require|number', '知识点序号禁止为空|知识点序号禁止出现除数字外字符'],
		['difficulty', 'require|number|between:1,5', '知识点难度禁止为空|知识点难度禁止出现除数字外字符|知识点难度在1～5之间'],
    ];
}