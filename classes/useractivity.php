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

	function getUserStats($user_id){
		$stats = Array();

		// Stats
		$stats[] = Array(
			'title' => 'User stats'
			,'elements' => $this->getUserStatsStatistics($user_id)
		);

		// Account state history
		$stats[] = Array(
			'title' => 'User account history'
			,'elements' => $this->getUserAccountHistory($user_id)
		);

		// Account activity history
		$stats[] = Array(
			'title' => 'User activity history'
			,'elements' => $this->getUserActivityHistory($user_id)
		);

		return $stats;
	}

	function getUserStatsStatistics($user_id){
		$stats = Array();
		$files_versions = new Axon('files_versions');
		// Files added
		$amount = (int)$files_versions->found('added_by='.$user_id);
		$stats[] = "Added $amount files";

		// Files approved
		$amount = (int)$files_versions->found('approved_by='.$user_id);
		$stats[] = "Approved $amount files";

		// Files deleted
		$user_activities = new Axon('user_activities');
		$amount = $user_activities->found("user_id = {$user_id} AND action = 'file:deleted'");
		$stats[] = "Deleted $amount files";

		// Average added file size
		$files_versions->def('_sum', 'SUM(size)');
		$files_versions->def('_amount', 'COUNT(*)');
		$files_versions->load('added_by='.F3::get('USER')->id);
		$sum = (int)$files_versions->_sum;
		$amount = (int)$files_versions->_amount;
		$files_versions->undef('_sum');
		$files_versions->undef('_amount');

		$stats[] = "Added files average size is " . pretifySize($sum/$amount);

		// Total oqupied space
		$stats[] = "Total occupied space by added files is " . pretifySize($sum);

		return $stats;
	}

	// TODO add dates
	function getUserAccountHistory($user_id){
		$stats = Array();
		$user_activities = new Axon('user_activities');
		$user_activities->load("element_id = {$user_id} AND action LIKE 'user:%'");

		while(!$user_activities->dry()){
			$action = str_replace('user:', '', $user_activities->action);
			switch($action){
				default:
					$action_text = 'User '.str_replace('_',' ',$action);
					if($user_activities->user_id > 0)
						$action_text .= ' by '.User::getUserNameById($user_activities->user_id)." ({$user_activities->user_id})";
					if($user_activities->details != '')
						$action_text .= ' to '.$user_activities->details;
					$action_text .= ' at '.pretifyDate($user_activities->datetime);
					break;
			}
			$stats[] = $action_text;
			
			$user_activities->next();
		}		

		return $stats;
	}

	function getUserActivityHistory($user_id){
		$stats = Array();
		$user_activities = new Axon('user_activities');
		$user_activities->load("user_id = {$user_id} AND action LIKE 'file:%'");

		$index = 0;
		while(!$user_activities->dry() && $index < 15){
			$action = str_replace('file:', '', $user_activities->action);
			$action_text = '';

			switch($action){
				case 'version_created':
				case 'version_approved':
				case 'version_disapproved':
				case 'version_deleted':
					$action_text .= "Version {$user_activities->details} ".str_replace('version_', '', $action);
					$action_text .= " for file ".File::getFileTitleById($user_activities->element_id);
					if($user_activities->user_id > 0)
						$action_text .= ' by '.User::getUserNameById($user_activities->user_id)." ({$user_activities->user_id})";
					$action_text .= ' at '.pretifyDate($user_activities->datetime);
					break;
				case 'created':
				case 'deleted':
				case 'edited':
					$action_text .= "File ".File::getFileTitleById($user_activities->element_id)." ".$action;
					if($user_activities->user_id > 0)
						$action_text .= ' by '.User::getUserNameById($user_activities->user_id)." ({$user_activities->user_id})";
					$action_text .= ' at '.pretifyDate($user_activities->datetime);
					break;
				case 'tag_added':
				case 'tag_removed':
					$action_text .= "Tag {$user_activities->details} ".str_replace('tag_', '', $action);
					$action_text .= " for file ".File::getFileTitleById($user_activities->element_id);
					if($user_activities->user_id > 0)
						$action_text .= ' by '.User::getUserNameById($user_activities->user_id)." ({$user_activities->user_id})";
					$action_text .= ' at '.pretifyDate($user_activities->datetime);
					break;
				default:
					$action_text .= $action;
					break;
			}
			$stats[] = $action_text;
			
			$user_activities->next();
			$index++;
		}		

		return $stats;
	}
}