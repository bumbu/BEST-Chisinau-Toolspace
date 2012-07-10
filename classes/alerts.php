<?php
class Alerts{
	static function addAlert($type = 'block', $title = '', $text = ''){
		$alerts = F3::get('alerts');
		switch($type){
			case 'error':
			case 'alert-error':
				$type = 'alert-error';
				break;
			case 'success':
			case 'alert-success':
				$type = 'alert-success';
				break;
			case 'info':
			case 'alert-info':
				$type = 'alert-info';
				break;
			case 'alert':
			case 'block':
			case 'alert-block':
			default :
				$type = 'alert-block';
				break;
		}

		$alert = Array(
			'type' => $type
			,'title' => $title
			,'text' => $text
		);

		if(is_array($alerts)){
			$alerts[] = $alert;
		}else{
			$alerts = Array($alert);
		}

		F3::set('alerts', $alerts);
	}
}