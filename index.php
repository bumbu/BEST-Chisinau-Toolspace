<?php

require __DIR__.'/lib/base.php';
require __DIR__.'/classes/helper.php';
require __DIR__.'/specific.cfg.php';

/********************************
 *			SETTINGS
 ********************************/

F3::set('CACHE',FALSE);
F3::set('DEBUG',1);
// F3::set('THROTTLE',3000);
F3::set('AUTOLOAD','classes/');
F3::set('MAX_DOWNLOAD_SPEED',1024);	// Kb/s

F3::set('VERSION','0.03');
F3::set('UI','ui/');
F3::set('STORAGE','cloud/');
F3::set('FILES_PATH','files/');

F3::set('USER',new User);
F3::set('ELEMENTS_PER_PAGE_AVAILABLE', Array(10, 25, 50));
F3::set('PLACEHOLDER', F3::get('LIVE_SITE').F3::get('UI').'images/placeholder.png');

/********************************
 *			ROUTER
 ********************************/

$directives = Array('ajax', 'cloud', 'origami', 'ninja');

F3::route('GET;POST /', 'View->showMainPage');
F3::route('GET;POST /login', function(){
	$view = new View(false);
	$view->showLogIn();
});
F3::route('GET;POST /logout', 'View->showLogOut');

F3::route('GET;POST /@directive/@entity/@action', function(){
	mirrorClassView(F3::get('PARAMS[directive]'), F3::get('PARAMS[entity]'), F3::get('PARAMS[action]'));
});

F3::route('GET;POST /ajax/@directive/@entity/@action', function(){
	mirrorClassView(F3::get('PARAMS[directive]'), F3::get('PARAMS[entity]'), F3::get('PARAMS[action]'), true);
});

function mirrorClassView($directive, $entity, $action, $is_ajax = false){
	GLOBAL $directives;

	$ajax_prefix = '';
	if($is_ajax)
		$ajax_prefix = 'ajax_';

	// check for login
	if(!F3::get('USER')->isLogged()){
		F3::reroute(F3::get('LIVE_SITE').'login');
	}

	if(in_array($directive, $directives)){
		// check for user rights
		// if(!F3::get('USER')->isAtLeast(Menu::$directive_permisions[$directive]))
		if(!Menu::hasAccessToPage($directive, $entity, $action)){
			F3::error(403);
		}

		$directive_view = 'View' . ucfirst($directive);
		$view = new $directive_view();

		$directive_action = $ajax_prefix . $entity . ucfirst($action);

		if(method_exists($view, $directive_action)){
			$view->$directive_action();
		}else{
			F3::error(404);
		}
	}else{
		F3::error(404);
	}
}


/********************************
 *			RUN
 ********************************/
F3::run();