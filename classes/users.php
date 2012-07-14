<?php
class Users{
	function getList(){
		$elements_per_page = F3::get('elements_per_page');
		$limit = 'LIMIT '.((F3::get('page')-1)*$elements_per_page).', '.$elements_per_page;

		$sql ="
			SELECT SQL_CALC_FOUND_ROWS users.*
			FROM users
			ORDER BY find_in_set(users.role, '".implode(',', array_reverse(array_keys(User::$roles)))."'), users.id ASC
		";
		DB::sql($sql.$limit);
		$users = F3::get('DB')->result;

		// if no results for such ammount of pages
		if(count($users) == 0){
			F3::set('page', 1);

			$limit = 'LIMIT 0, '.$elements_per_page;
			DB::sql($sql.$limit);
			$users = F3::get('DB')->result;
		}

		F3::set('pagination', Pagination::getPaginationFromParameters());

		return $users;
	}
}