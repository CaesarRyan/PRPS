<?php
namespace app\admin\validate;

use think\Validate;

class Password extends Validate
{
    // 验证规则
    protected $rule = [
		['password', 'require|min:5|max:15|confirm:repassword', '密码禁止为空|密码不能少于5位|密码不能多于15位|两次密码不一致'],
    ];
}