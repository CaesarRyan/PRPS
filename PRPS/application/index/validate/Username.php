<?php
namespace app\index\validate;

use think\Validate;

class Username extends Validate
{
    // 验证规则
    protected $rule = [
		['username', 'require|min:10|max:10', '用户名禁止为空|用户名为10位学号|用户名为10位学号'],
    ];
}