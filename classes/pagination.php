<?php
class Pagination{
	static function getPaginationFromParameters($quantity = -1, $page = -1, $elements_per_page = -1){
		if($quantity == -1){
			DB::sql("SELECT FOUND_ROWS()");
			$quantity = F3::get('DB')->result;

			if(isset($quantity[0]) && isset($quantity[0]['FOUND_ROWS()'])){
				$quantity = $quantity[0]['FOUND_ROWS()'];
			}else{
				$quantity = 0;
			}
		}

		if($page == -1){
			if(F3::exists('page'))
				$page = F3::get('page');
			else
				$page = 1;
		}	

		if($elements_per_page == -1){
			if(F3::exists('elements_per_page'))
				$elements_per_page = F3::get('elements_per_page');
			else
				$elements_per_page = 10;
		}

		return Pagination::getPagination($quantity, $page, $elements_per_page);
	}

	static function getPagination($quantity, $page = 1, $elements_per_page = 10){
		$max_visible_elements = 9;
		$max_visible_elements_half = floor($max_visible_elements/2);
		$pages = ceil($quantity/$elements_per_page);

		if($pages <= $max_visible_elements){
			$pages_from = 1;
			$pages_to = $pages;
		}else{
			if($page <= $max_visible_elements_half){
				$pages_from = 1;
				$pages_to = $max_visible_elements;
			}elseif($page >= $pages - $max_visible_elements_half){
				$pages_from = $pages - $max_visible_elements + 1;
				$pages_to = $pages;
			}else{
				$pages_from = $page - $max_visible_elements_half;
				$pages_to = $page + $max_visible_elements_half;
			}
		}

		/*HTML*/
		$html = '<ul>';

		if($page <= 1)
			$html .= '<li class="disabled"><a href="#" ><i class="icon-hand-left"></i></a></li>';
		else
			$html .= '<li><a href="#" class="submit" data-change="page" data-changeto="'.($page-1).'">&laquo;</a></li>';
		

		for($i = $pages_from; $i <= $pages_to; $i++){
			$html .= '<li'.($i == $page ? ' class="active"' : '').'><a href="#" class="submit" data-change="page" data-changeto="'.$i.'">'.$i.'</a></li>';
		}

		if($page >=$pages)
			$html .= '<li class="disabled"><a href="#">&raquo;</a></li>';
		else
			$html .= '<li><a href="#" class="submit" data-change="page" data-changeto="'.($page+1).'"><i class="icon-hand-right"></i></a></li>';

		$html .= '</ul>';

		return $html;
	}
}