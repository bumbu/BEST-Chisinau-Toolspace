<?php
class User{
	static $roles = Array(
		'user' => Array(
			'level' => 1
			,'title' => 'User'
		)
		,'manager' => Array(
			'level' => 2
			,'title' => 'Manager'
		)
		,'administrator' => Array(
			'level' => 3
			,'title' => 'Administrator'
		)
	);

	private $user;
	private $logged_user;

	function __construct($user_id = 0, $new_user = false){
		$this->user = new Axon('users');
		$this->logged_user = ($user_id == 0 && !$new_user);

		if($this->logged_user){
			// TODO: try to save sessions in DB
			// F3::get('DB')->session();
			session_set_cookie_params(86400);
			session_start();
			
			if(isset($_SESSION['user']) && isset($_SESSION['user']['id'])){
				$this->user->load("id=".((int)$_SESSION['user']['id']));
			}
		}else{
			$this->user->load("id='$user_id'");
		}
	}

	public function __set($name, $value){
		switch($name){
			case 'email':
				$this->user->load("email='$value'");
				if($this->user->dry()){
					$this->user->email = $value;
					$this->user->save('id');
					// reload
					$this->user->load("id={$this->user->_id}");

					// save to activities
					UserActivity::add('user:created', $this->user->id, 0);
				}
				break;
			// case 'id':
			case 'name':
			// case 'password':
			case 'role':
			case 'approved':
			case 'blocked':
				$this->user->$name = $value;
				$this->user->save();
				break;
			case '':
				break;
			default:
				break;
		}

		if($this->logged_user){
			// save to session
			$_SESSION['user'] = $this->user->cast();
		}
	}

	public function __get($name){
		switch($name){
			case 'id':
			case 'email':
			case 'name':
			// case 'password':
			case 'role':
			case 'approved':
			case 'blocked':
				if(!$this->user->dry()){
					return $this->user->$name;
				}
				break;
			default:
				break;
		}

		return null;
	}

	function getUserDetails(){
		return $this->user->cast();
	}

	function isLogged(){
		if($this->user->id > 0 && $this->user->approved == 1 && $this->user->blocked == 0)
			return true;
		else
			return false;
	}

	function updateUser(){
		$was_dry = $this->user->dry();

		$requested_role = Request::post('role', 'user', 'command');
		if(!User::roleExists($requested_role)){
			$requested_role = 'user';
		}

		// check for rights to create different user roles
		if(F3::get('USER')->isMoreThen($requested_role)){
			if($this->user->dry() || (!$this->user->dry() && F3::get('USER')->isMoreThen($this->user->role))){
				if($this->user->role != $requested_role){
					$this->user->role = $requested_role;
					UserActivity::add('user:role_changed', $this->user->id, NULL, $requested_role);
				}
			}
		}

		$email = Request::post('email', '', 'email');
		if(stristr($email, '@') === false)
			$email .= '@gmail.com';
		if($this->user->email != $email){
			$this->user->email = $email;
			UserActivity::add('user:email_changed', $this->user->id, NULL, $email);
		}

		// TODO: check email for validity
		if($this->user->email == ''){
			Alerts::addAlert('error', 'Invalid email!', 'Add valid email.');
			return;
		}

		// approving
		$approved = Request::post('approved', 0, 'number');
		if($approved != $this->user->approved){
			if($approved)
				UserActivity::add('user:approved', $this->user->id);
			else
				UserActivity::add('user:disapproved', $this->user->id);
		}
		$this->user->approved = $approved;
		
		// blocking
		$blocked = Request::post('blocked', 0, 'number');
		if($blocked != $this->user->blocked){
			if($blocked)
				UserActivity::add('user:blocked', $this->user->id);
			else
				UserActivity::add('user:unblocked', $this->user->id);
		}
		$this->user->blocked = $blocked;

		$this->user->save();

		if($was_dry){
			$this->user->id = $this->user->_id;
			$this->user->load("id={$this->user->id}");
			UserActivity::add('user:created', $this->user->id);
		}

	}

	function saveAsLastPage(){
		$_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
	}

	function getLastPage(){
		if(isset($_SESSION['last_page']) && $_SESSION['last_page'] != '')
			return $_SESSION['last_page'];
		else
			return F3::get('LIVE_SITE');
	}

	/*
		User stats
	*/

	function getUserStats(){
		$useractivity = new UserActivity;
		return $useractivity->getUserStats($this->user->id);
	}

	/*
		User rights
	*/

	function is($role){
		if(!$this->isLogged())
			return false;
		return User::$roles[$this->user->role]['level'] == User::$roles[$role]['level'];
	}

	function isMoreThen($role){
		if(!$this->isLogged())
			return false;
		return User::$roles[$this->user->role]['level'] > User::$roles[$role]['level'];
	}

	function isAtLeast($role){
		if(!$this->isLogged())
			return false;
		return User::$roles[$this->user->role]['level'] >= User::$roles[$role]['level'];
	}


	/*
		Common users functions
	*/
		
	static function getUserNameById($id){
		$user = new Axon('users');
		$user->load('id='.$id);
		if($user->dry()){
			return 'no name';
		}else{
			return $user->name;
		}
	}

	static function roleExists($role){
		return in_array($role, array_keys(User::$roles));
	}
}