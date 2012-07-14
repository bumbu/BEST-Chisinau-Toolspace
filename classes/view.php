<?php
class View{
	function __construct($check_for_login = true){
		if($check_for_login && !F3::get('USER')->isLogged()){
			F3::reroute(F3::get('LIVE_SITE').'login');
		}

		// some basic stuff
		$page = Request::get_post('page', 1, 'number');
		F3::set('page', $page);

		$elements_per_page = Request::cookie_get_post('elements_per_page', 10, 'number');
		if(!in_array($elements_per_page, F3::get('ELEMENTS_PER_PAGE_AVAILABLE'))){
			$elements_per_page = current(F3::get('ELEMENTS_PER_PAGE_AVAILABLE'));
		}
		F3::set('elements_per_page', $elements_per_page);
	}

	function showMainPage(){
		$this->showHTMLFrame('welcome<br>find your own way');
	}

	function showLogIn(){
		if(Request::post('login_google', false, 'bool')){
			Authentication::loginGoogle();
		}elseif(Request::get_post('openid_mode', false, 'isset')){
			Authentication::checkGoogleResponse();
		}

		F3::set('template_head_menu', '');
		F3::set('template_footer', '');
		$this->showHTMLFrame(Template::serve('_login.html'));
	}

	function showLogOut(){
		session_unset();
		F3::reroute(F3::get('LIVE_SITE').'login');
	}

	function showHTMLFrame($container = false){
		// header
		if(!F3::exists('template_head_menu'))
			F3::set('template_head_menu', Template::serve('__head_menu.html'));

		// container
		if($container !== false && !F3::exists('template_container'))
			F3::set('template_container', $container);

		// footer
		if(!F3::exists('template_footer'))
			F3::set('template_footer', Template::serve('__footer.html'));

		// frame
		echo Template::serve('frame.html');
	}

	function showAJAXResponse(){
		// response code
		if(!F3::exists('response_code'))
			F3::set('response_code', '200');
		// response message
		if(!F3::exists('response_message'))
			F3::set('response_message', 'ok');
		// message
		if(!F3::exists('message'))
			F3::set('message', '');

		echo Template::serve('response.json');
	}


}