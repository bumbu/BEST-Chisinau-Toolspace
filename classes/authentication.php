<?php
class Authentication{
	static function loginGoogle(){
		$openid = new OpenID;
		$openid->identity = 'https://www.google.com/accounts/o8/id';
		$openid->return_to = F3::get('LIVE_SITE') .'login' ;
		$openid->__set('ns.ax', 'http://openid.net/srv/ax/1.0');
		$openid->__set('ax.mode', 'fetch_request');
		$openid->__set('ax.required', 'firstname,lastname,email');
		$openid->__set('ax.type.firstname', 'http://axschema.org/namePerson/first');
		$openid->__set('ax.type.lastname', 'http://axschema.org/namePerson/last');
		$openid->__set('ax.type.email', 'http://axschema.org/contact/email');
		$openid->auth();
	}

	static function checkGoogleResponse(){
		switch(Request::get_post('openid_mode', '', 'command')){
			case 'cancel':
				// show error
				// TODO: change text, add tutorial how to overcome default deny of permision from google
				Alerts::addAlert('', 'Not logged', 'It seems that you did not accepted google log in. In order to have access to site, please log in with Google Account. If you set up default option to reject Google Login for this site, then follow <a href="#">these</a> steps to be able to login back with your credentials.');
				break;
			case 'id_res':
				// set user params
				F3::get('USER')->email = Request::sanitize($_REQUEST['openid_ext1_value_email'], 'email');
				F3::get('USER')->name =	 Request::sanitize($_REQUEST['openid_ext1_value_firstname'], 'string')
										." "
										.Request::sanitize($_REQUEST['openid_ext1_value_lastname'], 'string');
				
				if(F3::get('USER')->isLogged()){
					F3::reroute(F3::get('LIVE_SITE'));
				}else{
					if(F3::get('USER')->blocked)
						Alerts::addAlert('', 'This account is blocked', 'Please contact ' . F3::get('SUPPORT_EMAIL') . ' to solve this problem.');
					elseif(!F3::get('USER')->approved)
						Alerts::addAlert('info', 'This account is not approved', 'Please contact ' . F3::get('SUPPORT_EMAIL') . ' to approve your account.');
					else
						Alerts::addAlert('info', 'Login failed', 'Please contact ' . F3::get('SUPPORT_EMAIL') . ' to approve your account.');
				}

				break;
			default:
				Alerts::addAlert('', 'Unexpected error happened', 'Please contact ' . F3::get('SUPPORT_EMAIL') . ' to solve this problem.');
				break;
		}
	}
}