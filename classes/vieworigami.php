<?php
class ViewOrigami extends View{
	function fileEdit(){
		// get file
		$file_id = Request::get_post('id', 0, 'number');
		$file = new File($file_id);

		// update file
		if(Request::post('post', false, 'bool'))
			$file->updateFileFromRequest();

		// set template file
		F3::set('file', $file->getFile());

		$this->showHTMLFrame(Template::serve('_file_edit.html'));
	}

	function fileThumb(){
		$file_id = Request::get_post('file_id', 0, 'number');
		$version = Request::get_post('version', 0, 'number');

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
				if($file_version->hasThumb()){
					$file_version_details = $file_version->cast();
					$file_path = getFilePath($file_id, $version, $file->getFileName(), $file_version_details['extension']);

					Graphics::thumb($file_path, 640, 480, false);
				}else{
					// show empty thumb
					Graphics::fakeimage(640, 480);
				}
			}
		}
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
}