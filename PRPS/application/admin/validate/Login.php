<?php
namespace app\admin\validate;

use think\Validate;

class Login extends Validate
{
    // 验证规则
    protected $rule = [
		['username', 'require|min:5|max:20', '用户名禁止为空|用户名不少于5位|用户名不超过20位'],
		['password', 'require|min:5|max:15', '密码禁止为空|密码不能少于5位|密码不能多于15位'],
    ];
}