<?php
namespace app\index\validate;

use think\Validate;

class Problem extends Validate
{
    // 验证规则
    protected $rule = [
		['problem', 'require|min:5|max:200', '反馈问题禁止为空|反馈问题不少于5个字|反馈问题不多于200个字'],
    ];
}