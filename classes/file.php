<?php
class File{
	private $id = 0;
	private $file = null;
	private $file_cast = null;

	function __construct($id = 0){
		$this->id = $id;
		$this->file = new Axon('files');
		$this->file->load('id='.$this->id);
	}

	function dry(){
		return $this->file->dry();
	}

	function getId(){
		return $this->file->id;
	}

	function getFileName(){
		return $this->file->name;
	}

	/*
		Loads all information about file
	*/
	function getFile($force_update = false){
		if($this->file_cast === null || $force_update){
			// load file details
			$this->file_cast = $this->file->cast();
			// load tags
			$this->file_cast['tags'] =  $this->getTags();
			// load versions
			$this->file_cast['versions'] =  $this->getVersions();

			$this->file_cast['editable_by_user'] = $this->editableByUser();
		}

		return $this->file_cast;
	}

	function getTags(){
		$sql = "
			SELECT tags.title
			FROM tags, files_tags
			WHERE tags.id = files_tags.tag_id
			AND files_tags.file_id = ".$this->id."
			ORDER BY tags.title
		";
		DB::sql($sql);

		$tags = Array();
		foreach(F3::get('DB')->result as $tag){
			$tags[] = $tag['title'];
		}

		return $tags;
	}

	function getVersions(){
		$sql = "
			SELECT files_versions.*
			FROM files_versions
			WHERE files_versions.file_id = ".$this->id."
			GROUP BY version
			ORDER BY version DESC
		";
		DB::sql($sql);

		$files_versions = Array();
		foreach(F3::get('DB')->result as $files_version){
			$files_versions[] = $files_version;
		}

		// load each version extensions
		for($i = 0;$i < count($files_versions); $i++){
			$fileVersion = new FileVersion($this, $files_versions[$i]['version']);
			$files_versions[$i]['extensions'] = $fileVersion->getExtensionNames();
		}

		return $files_versions;
	}

	function updateFileFromRequest(){
		// check for any delete
		$anything_deleted = false;
		if(F3::get('USER')->isAtLeast('manager')){
			$version = Request::post('version', 0, 'number');
			$extension = Request::post('extension', '', 'extension');

			if(Request::post('delete_all', false, 'bool')){
				$this->deleteAllVersions();
				$this->deleteAllTags();
				UserActivity::add('file:deleted', $this->id, NULL, $this->file->title);
				$this->file->erase();

				// TODO: explore F3::reroute(F3::get('USER')->getLastPage());
				header(F3::HTTP_Location. ':' . F3::get('USER')->getLastPage());
			}

			// check if delete version
			if(Request::post('delete_version', false, 'bool')){
				$version_handle = $this->getVersion($version);
				$version_handle->delete();
				Alerts::addAlert('info', 'Version deleted!', '');

				$anything_deleted = true;
			}

			// check if delete extension
			if(Request::post('delete_extension', false, 'bool')){
				$version_handle = $this->getVersion($version);
				$version_handle->deleteExtension($extension);
				Alerts::addAlert('info', 'Extension removed!', '');

				// check if version has extensions
				if($version_handle->dry()){
					Alerts::addAlert('warning', 'Version removed!', 'Version was deleted because there where no more extensions in it.');
				}
				$anything_deleted = true;
			}
		}

		if(!$anything_deleted){
			if($this->editableByUser()){
				$title = Request::post('title', '', 'title');
				if($this->file->title != $title){
					$this->file->title = $title;

					$name = $this->file->name;
					$this->file->name = formatFileVersionName(0,0,$title, false);

					$this->updateFileNames($name);					

					UserActivity::add('file:edited', $this->id, NULL, $this->file->title);
				}
			}

			if(F3::get('USER')->isAtLeast('manager'))
				$this->file->approved = Request::post('approved', 0, 'number');

			$tags = Request::post('tags', '', 'tags');

			if($this->file->published == 0){
				$this->file->published = 1;
				$this->updateTags($tags);
			}else{
				if($this->editableByUser()){
					$this->updateTags($tags);
				}
			}

			$this->file->save();

			Alerts::addAlert('info', 'File information updated!', '');

			if(Request::post('go_back', false, 'bool')){
				// F3::reroute(F3::get('USER')->getLastPage());
				header(F3::HTTP_Location. ':' . F3::get('USER')->getLastPage());
			}
		}
	}

	function getVersion($version){
		return new FileVersion($this, $version);
	}

	function deleteAllVersions(){
		$this->getFile();
		foreach($this->file_cast['versions'] as $version){
			$version_handle = $this->getVersion($version['version']);
			$version_handle->delete();
		}

		$this->file_cast = NULL;	// clear file_cast
	}

	function updateVersionThumbnail($version_id, $file){
		$version = $this->getVersion($version_id);
		$version->updateThumbnail($file);
	}

	/*
		return: version_id to prevent too big versoin incrementation
	*/
	function addExtension($version_id, $file){
		// check for last vesion
		if($this->file->last_version < $version_id){
			$this->file->last_version = $this->file->last_version + 1;
			$this->file->save();

			$version_id = $this->file->last_version;
		}

		$version = $this->getVersion($version_id);
		$version->updateExtensionFile(null, $file);

		return $version_id;
	}

	function updateFileNames($from){
		$this->getFile();
		foreach($this->file_cast['versions'] as $version){	// get all versions
			$fileVersion = new FileVersion($this, $version['version']);
			foreach($fileVersion->getExtensionNames() as $extension){	// get all extensions
				$old_file_no_extension = getFilePath($this->file->id, $version['version'], $from, '');
				// move files
				$fileVersion->updateExtensionFile($extension, $old_file_no_extension.$extension, false);
				// move thumbs
				$fileVersion->updateThumbnail($old_file_no_extension.'thumb.png');
			}

		}
	}

	function deleteAllTags(){
		return $this->updateTags('');
	}

	// string or array
	function updateTags($tags){
		$tags = File::matchTags($tags);

		// only uniqye tags
		$tags = array_unique($tags);

		// DB handlers
		$DB_tag = new Axon('tags');
		$DB_file_tag = new Axon('files_tags');

		// load all file tags
		$DB_file_tag->load("file_id=".$this->file->id);
		$tags_available = Array();
		while(!$DB_file_tag->dry()){
			// save values in keys for faster search
			$tags_available[$DB_file_tag->tag_id] = 1;
			$DB_file_tag->skip();
		}

		foreach($tags as $tag){
			$DB_tag->load("title LIKE '$tag'");

			if($DB_tag->dry() && !F3::get('USER')->isAtLeast('manager')){
				continue;
			}

			if($DB_tag->dry()){
				$DB_tag->title = $tag;
				$DB_tag->save();
				$DB_tag->id = $DB_tag->_id;
			}

			if(isset($tags_available[$DB_tag->id])){
				// such tag is set
				unset($tags_available[$DB_tag->id]);
			}else{
				// such tag is not set, set it here
				$DB_file_tag = new Axon('files_tags');
				$DB_file_tag->file_id = $this->file->id;
				$DB_file_tag->tag_id = $DB_tag->id;
				$DB_file_tag->save();
				UserActivity::add('file:tag_added', $this->id, NULL, $DB_tag->id);
			}
		}

		// detele remained tags
		foreach($tags_available as $tag_id => $tag){
			$DB_file_tag->load("file_id=".$this->file->id." AND tag_id=$tag_id");
			$DB_file_tag->erase();
			UserActivity::add('file:tag_removed', $this->id, NULL, $tag_id);
		}

	}

	static function matchTags($tags){
		if(!is_array($tags)){
			preg_match_all('/\[([A-Za-z0-9\-]+)\]/', $tags, $matched_tags);
			if(isset($matched_tags[1]))
				return $matched_tags[1];
			else
				return Array();
		}
	}

	function editableByUser(){
		if(F3::get('USER')->isAtLeast('manager'))
			return true;
		else{
			// user can edit file only if he is author and file is still not accepted
			if($this->file->approved == 0 && $this->file->author == F3::get('USER')->id){
				return true;
			}else
				return false;
		}		
	}

	static function getFileTitleById($id){
		$file = new Axon('files');
		$file->load('id='.$id);
		if($file->dry()){
			return 'no title';
		}else{
			return $file->title;
		}
	}

	/*
		published:
			-1	blocked/deleted
			0	not published
			1	published
	*/
	static function getLatestUnpublishedFile($user_id = 0, $create_new_file = true){
		$user_id = $user_id > 0 ? $user_id : F3::get('USER')->id;
		
		$file = new Axon('files');
		$file->load('author='.$user_id.' AND published = 0');
		if(!$file->dry()){
			return $file->id;
		}else{
			if($create_new_file){
				$file = new Axon('files');
				$file->last_version = 0;
				$file->author = $user_id;
				$file->save();
				return $file->_id;
			}else{
				return 0;
			}
		}
	}
}