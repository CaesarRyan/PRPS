<?php
namespace app\index\model;

use think\Model;

class Question extends Model
{
	protected $table = 'question';
	
	public function knowledges() {
		return $this->hasMany('QueKnow', 'qid');
	}
}