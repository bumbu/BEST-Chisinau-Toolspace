<?php
class Files{
	function getList($files_type = 'all'){
		$where = '';
		$order = '';
		$from = '';
		$join = '';
		$limit = '';

		switch($files_type){
			case 'approved':
				$where .= "	AND files.approved = 1";
				break;
			case 'new':
				$where .= "	AND files.approved = 0";
				break;
			case 'disapproved':
				$where .= "	AND files.approved = -1";
				break;
			// case 'mine_not_accepted':
			// 	$where .= "
			// 		AND files.all_approved = 0
			// 		AND files_versions.approved = 0
			// 		AND files_versions.added_by = ".F3::get('USER')->id."
			// 	";
			// 	break;
			case 'all':
			default:
				$where .= "";
				break;
		}


		$elements_per_page = F3::get('elements_per_page');
		$limit = 'LIMIT '.((F3::get('page')-1)*$elements_per_page).', '.$elements_per_page;

		// search string
		$search = Request::get_post('search', '', 'tags');
		preg_match_all('/\[(\w+)\]/', $search, $matched_tags);
		$search_text = trim(preg_replace('/\[\w+\]/', '', $search));

		// for filtering purpose
		$valid_tags = Array();
		if(isset($matched_tags[1]) && count($matched_tags[1])){
			$tags = array_unique($matched_tags[1]);
			// TODO: make system more flexible to accept more tags
			if(count($matched_tags[1]) > 5){
				$tags = array_slice($tags, 0, 5);
			}

			$DB_tag = new Axon('tags');
			foreach($tags as $key => $tag){
				$DB_tag->load("title LIKE '$tag'");
				if(!$DB_tag->dry()){
					$valid_tags[$DB_tag->id] = $DB_tag->title;
				}
			}

			foreach($valid_tags as $tag_id => $tag_title){
				$from .= ", files_tags AS files_tags_$tag_id";
				$where .= " AND files_tags_$tag_id.file_id = files.id";
				$where .= " AND files_tags_$tag_id.tag_id = $tag_id";
			}
		}

		F3::set('valid_tags', $valid_tags);

		// rebuild search string
		$search = '';
		if(count($valid_tags)){
			$search .= '[';
			$search .= implode('][', $valid_tags);
			$search .= ']';
		}

		if($search_text != ''){
			$where .= " AND files.title LIKE '%$search_text%'";
			$search .= ' '.$search_text;
		}

		F3::set('search', $search);

		$sort = F3::get('sort');	
		$sort_by = F3::get('sort_by');

		$sorting = " $sort_by $sort";

		$sql ="
			SELECT SQL_CALC_FOUND_ROWS files.*, files_versions.*
			FROM files, files_versions $from
			$join
			WHERE files.id = files_versions.file_id
			$where
			GROUP BY files.id
			ORDER BY $sorting, files_versions.version DESC $order
		";
		DB::sql($sql.$limit);
		$files = F3::get('DB')->result;

		// if no results for such ammount of pages
		if(count($files) == 0){
			F3::set('page', 1);
			$limit = 'LIMIT 0, '.$elements_per_page;
			DB::sql($sql.$limit);
			$files = F3::get('DB')->result;
		}

		F3::set('pagination', Pagination::getPaginationFromParameters());

		foreach ($files as $file_key => $file_value){
			$file = new File($file_value['file_id']);
			$files[$file_key]['tags'] = $file->getTags();
		}

		return $files;
	}
}