<?php
namespace app\index\validate;

use think\Validate;

class Register extends Validate
{
    // 验证规则
    protected $rule = [
		['username', 'require|min:10|max:10', '用户名禁止为空|用户名为10位学号|用户名为10位学号'],
		['password', 'require|min:5|max:15|confirm:repassword', '密码禁止为空|密码不能少于5位|密码不能多于15位|两次密码不一致'],
		['realname', 'require|min:2|max:5', '真实姓名禁止为空|真实姓名不少于2位|真实姓名不超过5位'],
		['phone', 'require|number|min:11|max:11', '联系电话禁止为空|联系电话禁止出现除数字外字符|联系电话为11位数字|联系电话为11位数字'],
		['question', 'require|max:50', '密保问题禁止为空|密保问题不超过50位'],
		['answer', 'require|max:50', '密保答案禁止为空|密保答案不超过50位'],
    ];
}