<?php
class Menu{
	static $menu = Array(
		Array(
			'title' => 'File Archive'
			,'directive' => 'origami'
			,'minimum_role' => 'user'
			,'element' => Array(
				Array(
					'title' => 'Search'
					,'directive' => 'origami'
					,'minimum_role' => 'user'
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
	);

	static function renderMenu(){
		$html = '';

		foreach(Menu::$menu as $menu){
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