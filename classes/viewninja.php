<?php
class ViewNinja extends View{
	function usersList(){
		// get list
		$users = new users;
		F3::set('users', $users->getList());

		$this->showHTMLFrame(Template::serve('_users.html'));
	}

	function userEdit(){
		// get account
		// echo Request::get_post('id', 0, 'number');
		$user = new User(Request::post_get('id', 0, 'number'), true);
		if(Request::post('post', false, 'bool'))
			$user->updateUser();

		F3::set('user', $user->getUserDetails());
		F3::set('roles', User::$roles);

		F3::set('user_stats', $user->getUserStats());

		$this->showHTMLFrame(Template::serve('_account_edit.html'));
	}
}