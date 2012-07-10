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
		$user = new User(Request::get_post('id', 0, 'number'));
		if(Request::post('post', false, 'bool'))
			$user->updateUser();

		F3::set('user', $user->getUserDetails());
		F3::set('roles', User::$roles);

		$this->showHTMLFrame(Template::serve('_account_edit.html'));
	}
}