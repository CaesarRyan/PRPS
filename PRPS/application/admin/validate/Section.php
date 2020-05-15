<?php
namespace app\admin\validate;

use think\Validate;

class Section extends Validate
{
    // 验证规则
    protected $rule = [
		['section_num', 'require|number', '课节序号禁止为空|课节序号禁止出现除数字外字符'],
		['section_name', 'require|max:20', '课节名称禁止为空|课节名称不超过20位'],
		['section_info', 'require|max:500', '课节信息禁止为空|课节信息不超过500位'],
		['section_audio', 'require', '课节音频禁止为空'],
    ];
}