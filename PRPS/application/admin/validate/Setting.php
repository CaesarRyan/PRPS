<?php
namespace app\admin\validate;

use think\Validate;

class Setting extends Validate
{
    // 验证规则
    protected $rule = [
		['save_max_num', 'require|number|between:0,10', '每个学生的每个问题保存的最大数量禁止为空|每个学生的每个问题保存的最大数量禁止出现除数字外字符|每个学生的每个问题保存的最大数量在0～10之间'],
		['least_num', 'require|number|between:0,10', '每个簇通过最少数量禁止为空|每个簇通过最少数量禁止出现除数字外字符|每个簇通过最少数量在0～10之间'],
		['most_know_num', 'require|number|between:0,5', '问题所包含知识点最多数量禁止为空|问题所包含知识点最多数量禁止出现除数字外字符|问题所包含知识点最多数量在0～5之间'],
		['homework_score_proportion', 'require|number|between:0,1', '作业课程分数占比禁止为空|作业课程分数占比禁止出现除数字外字符|作业课程分数占比在0～1之间'],
		['max_recommend_num', 'require|number|between:0,10', '最大的推荐数量禁止为空|最大的推荐数量禁止出现除数字外字符|最大的推荐数量在0～10之间'],
		['offset', 'require|number', '补偿量禁止为空|补偿量禁止出现除数字外字符'],
		['weight_done_num', 'require|number', '做题数权重禁止为空|做题数权重禁止出现除数字外字符'],
		['weight_accept', 'require|number', '通过率权重禁止为空|通过率权重禁止出现除数字外字符'],
		['weight_difficulty', 'require|number', '难度权重禁止为空|难度权重禁止出现除数字外字符'],
		['study_timelimit', 'require|number|between:100,2000', '学习系统用户程序时间限制禁止为空|学习系统用户程序时间限制禁止出现除数字外字符|学习系统用户程序时间限制在100～2000之间'],
		['study_memorylimit', 'require|number|between:10,300', '学习系统用户程序内存限制禁止为空|学习系统用户程序内存限制禁止出现除数字外字符|学习系统用户程序内存限制在10～300之间'],
    ];
}