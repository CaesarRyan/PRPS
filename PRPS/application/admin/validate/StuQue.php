<?php
namespace app\admin\validate;

use think\Validate;

class StuQue extends Validate
{
    // 验证规则
    protected $rule = [
		['state', 'require', '状态禁止为空'],
		['score', 'require|number|between:0, 100', '分数禁止为空|分数禁止出现除数字外字符|分数在0～100之间'],
		['answer', 'require|max:1000', '答案禁止为空|答案不超过1000位'],
    ];
}