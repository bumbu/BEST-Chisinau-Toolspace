<?php
class ViewOrigami extends View{
	function fileEdit(){
		// get file
		$file_id = Request::get_post('id', 0, 'number');

		//if it is new file, create (or select from DB last not saved) it and redirect to edit file page
		if($file_id == 0){
			// search for last unpublished file, or create one
			$file_id = File::getLatestUnpublishedFile();
			// TODO: path should not be hardcoded
			F3::reroute(F3::get('LIVE_SITE').'origami/file/edit/?id='.$file_id);
		}
		$file = new File($file_id);

		// update file
		if(Request::post('post', false, 'bool'))
			$file->updateFileFromRequest();

		// set template file
		F3::set('file', $file->getFile());

		$this->showHTMLFrame(Template::serve('_file_edit.html'));
	}

	function fileDownload(){
		$file_id = Request::get_post('file_id', 0, 'number');
		$version = Request::get_post('version', 0, 'number');
		$prepend_version = Request::get_post('prepend_version', false, 'bool');

		// get file name
		$file = new File($file_id);
		if($file->dry()){
			// TODO: show error
			return;
		}else{
			$file_version = $file->getVersion($version);
			if($file_version->dry()){
				// TODO: show error
			}else{
				$file_version_details = $file_version->cast();
				$file_path = getFilePath($file_id, $version, $file->getFileName(), $file_version_details['extension']);

				$filename = formatFileVersionName($file_id, $version, $file->getFileName(), $prepend_version).'.'.$file_version_details['extension'];

				if (!F3::send($file_path, F3::get('MAX_DOWNLOAD_SPEED'), true, ' attachment; filename="'.$filename.'"'))
					// Generate an HTTP 404
					F3::http404();
			}
		}
	}

	function fileUpload(){

	}

	function searchList(){
		if(F3::get('USER')->isAtLeast('manager')){
			$files_types = Array(
				'all' => 'All files'
				,'accepted' => 'Accepted files'
				,'accepted_fully' => 'Fully accepted files'
				,'accepted_partially' => 'Partially accepted files'
				,'accepted_not' => 'Not accepted files'
			);
		}else{
			$files_types = Array(
				'accepted' => 'Accepted files'
				,'mine_not_accepted' => 'My not accepted files'
			);
		}
		
		F3::set('files_types', $files_types);

		$files_type = Request::get_post('files_type', 'accepted', 'command');
		if(!in_array($files_type, array_keys($files_types))){
			$files_type = 'all';
		}
		F3::set('files_type', $files_type);

		$sort = Request::post_get('sort', 'desc', 'command');
		if(!in_array($sort, Array('desc', 'asc')))
			$sort = 'desc';
		F3::set('sort', $sort);

		$sort_by = Request::post_get('sort_by', 'file_id', 'command');
		if(!in_array($sort_by, Array('file_id', 'title', 'size', 'added_at', 'added_by')))
			$sort_by = 'file_id';
		F3::set('sort_by', $sort_by);

		// get files list
		$files = new Files;
		F3::set('files_list', $files->getList($files_type));

		$this->showHTMLFrame(Template::serve('_search_list.html'));

		F3::get('USER')->saveAsLastPage();
	}

	function ajax_fileDetails(){
		$file_id = Request::get_post('file_id', 0, 'number');
		$file = new File($file_id);

		F3::set('file', $file->getFile());
		F3::set('tab_active', true);
		F3::set('message', json_encode(Template::serve('__file_details.html')));

		$this->showAJAXResponse();
	}


	function ajax_fileVersion(){
		$file_id = Request::get_post('file_id', 0, 'number');
		$version = Request::get_post('version', 0, 'number');
		$action = Request::get_post('action', 0, 'command');

		$file = new File($file_id);

		F3::set('file', $file->getFile());
		F3::set('version', $file->getVersion($version)->cast());

		switch($action){
			case 'approve':
				$file->updateVersion($version, 1);
				F3::set('message', json_encode(Template::serve('__file_edit_approved.html')));
				break;
			case 'disapprove':
				$file->updateVersion($version, 0);
				F3::set('message', json_encode(Template::serve('__file_edit_disapproved.html')));
				break;
		}

		$this->showAJAXResponse();
	}

	function ajax_fileUpload(){
		$upload_handler = new Upload;
		$upload_handler->post();
	}

	function ajax_fileResumeUpload(){
		$upload_handler = new Upload;
		$upload_handler->get();
	}

	function ajax_fileCreateThumb(){
		$file_name = Request::post('name', '', 'string');
		$upload_dir = dirname($_SERVER['SCRIPT_FILENAME']).'/'.F3::get('TEMP');
		$options = Array(
			'upload_dir' => $upload_dir
			,'max_width' => 330
			,'max_height' => 330
		);

		if(ImageProcessing::createScaledImageByConvert($upload_dir, $file_name, $options)){
			F3::set('message', json_encode($file_name.'.thumb.png'));
			F3::set('response_message', 'Thumb created');
		}elseif(ImageProcessing::createScaledImage($upload_dir, $file_name, $options)){
			F3::set('message', json_encode('thumb_'.$file_name));
			F3::set('response_message', 'Thumb created');
		}else{
			F3::set('message', json_encode(''));
			F3::set('response_message', 'Thumb wasn\'t automatically created');
			F3::set('response_code', '417');
		}

		$this->showAJAXResponse();
	}

	function ajax_fileAddFile(){
		$file_id = Request::post('id', 0, 'number');
		$version = Request::post('version', 0, 'number');
		$file_name = Request::post('file', '');
		$thumbnail = Request::post('thumbnail', '');
		$upload_dir = dirname($_SERVER['SCRIPT_FILENAME']).'/'.F3::get('TEMP');

		$file = new File($file_id);

		if($file != '' && is_file($upload_dir . $file_name)){
			$file->addExtension($version, $upload_dir . $file_name);
		}

		if($thumbnail != '' && is_file($upload_dir . $thumbnail)){
			$file->updateVersionThumbnail($version, $upload_dir . $thumbnail);
		}

		$this->showAJAXResponse(json_encode('Version updated'));
	}

	// TODO: move model to class, here should be only view part
	function ajax_tagSuggestions(){
		$term = Request::get_post('term', '', 'tags');
		$term = str_replace('[', '', $term);
		$term = str_replace(']', '', $term);
		$tags_array = Request::get_post('tags_array', '', 'tags');
		$tags_output = Array();
		$max_tags_count = 10;

		$available_tags = File::matchTags($tags_array);

		$tags = new Axon('tags');
		$tags->load("title LIKE '%$term%' AND title NOT IN ('".implode("','", $available_tags)."')");
		while(!$tags->dry() && $max_tags_count > 0){
			$tags_output[] = '['.$tags->title.']';

			$tags->next();
			$max_tags_count--;
		}

		echo json_encode($tags_output);
	}

	function ajax_fileFilesPartial(){
		$id = Request::get_post('id', 0, 'number');
		$version = Request::get_post('version', 0, 'number');
		$extension = Request::get_post('extension', '', 'extension');

		$file = new File($id);

		// set template file
		F3::set('file', $file->getFile());
		F3::set('active_version', $version);
		F3::set('active_extension', $extension);

		$this->showAJAXResponse(json_encode(Template::serve('__file_images.html')));
	}
}