<?php
class Menu{
	static $directive_permisions = Array(
		'origami' => 'visitor'
		,'ninja' => 'manager'
		,'cloud' => 'administrator'
	);

	static $menu = Array(
		Array(
			'title' => 'File Archive'
			,'directive' => 'origami'
			,'minimum_role' => 'visitor'
			,'show' => true
			,'element' => Array(
				Array(
					'title' => 'Search'
					,'directive' => 'origami'
					,'minimum_role' => 'visitor'
					,'element' => 'search/list/'
				)
				,Array(
					'title' => 'Add new file'
					,'directive' => 'origami'
					,'minimum_role' => 'user'
					,'element' => 'file/edit/'
				)
				,Array(
					'title' => 'User accounts'
					,'directive' => 'ninja'
					,'minimum_role' => 'manager'
					,'element' => 'users/list/'
				)

			)
		)
/*		,Array(
			'title' => 'Wiki'
			,'directive' => 'origami'
			,'minimum_role' => 'user'
			,'element' => 'wiki'
		)*/
/*		,Array(
			'title' => 'internal links'
			,'directive' => 'origami'
			,'minimum_role' => 'visitor'
			,'show' => false
			,'element' => Array(
				Array(
					'title' => 'File edit'
					,'directive' => 'origami'
					,'minimum_role' => 'user'
					,'element' => 'file/edit/'
				)
			)
		)*/
	);

	static function hasAccessToPage($directive, $entity, $action){
		// check directive
		if(!F3::get('USER')->isAtLeast(Menu::$directive_permisions[$directive]))
			return false;

		// check for menu element
		$path = "$entity/$action/";
		foreach(Menu::$menu as $menu){
			if(is_array($menu['element'])){
				foreach($menu['element'] as $element){
					if($element['element'] == $path){
						return F3::get('USER')->isAtLeast($element['minimum_role']);
					}
				}
			}else{
				if($menu['element'] == $path){
					return F3::get('USER')->isAtLeast($menu['minimum_role']);
				}
			}
		}

		return true;
	}

	static function renderMenu(){
		$html = '';

		foreach(Menu::$menu as $menu){
			if(isset($menu['show']) && $menu['show'] === true)
				if(F3::get('USER')->isAtLeast($menu['minimum_role'])){
					$directive_previous = '';
					if(is_array($menu['element'])){
						$html .= '<li class="dropdown">';
							$html .= '<a href="#" class="dropdown-toggle" data-toggle="dropdown">'.$menu['title'].' <b class="caret"></b></a>';
							$html .= '<ul class="dropdown-menu">';
							foreach($menu['element'] as $element){
								if(F3::get('USER')->isAtLeast($element['minimum_role'])){
									// add submenu dividers
									if($directive_previous != $element['directive']){
										$html .= '<li class="nav-header">'.$element['directive'].'</li>';
										$directive_previous = $element['directive'];
									}
									$html .= '<li><a href="'.F3::get('LIVE_SITE').$element['directive'].'/'.$element['element'].'">'.$element['title'].'</a></li>';
								}
							}
							$html .= '</ul>';
						$html .= '</li>';
					}else{
						$html .= '<li><a href="'.F3::get('LIVE_SITE').$element['directive'].'/'.$menu['element'].'">'.$menu['title'].'</a></li>';
					}
				}
		}

		return $html;
	}
}