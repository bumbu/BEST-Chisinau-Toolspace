<?php
class UserActivity{
	/*
		user:created
	*/
	static function add($action, $element_id, $user_id = NULL, $details = ''){
		$user_activities = new Axon('user_activities');
		$user_activities->user_id = ($user_id !== NULL ? $user_id : F3::get('USER')->id);
		$user_activities->action = $action;
		$user_activities->element_id = $element_id;
		$user_activities->datetime = timeToMySQLDatetime(time());
		$user_activities->details = $details;

		$user_activities->save('id');

		return $user_activities->id;
	}
}