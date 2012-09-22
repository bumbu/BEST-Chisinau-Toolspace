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

	function getName(){
		return $this->file->name;
	}

	function getTitle(){
		return $this->file->title;
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
		$file_tag = new FileTag($this->id);
		$tag_ids = $file_tag->getTagsId();

		$tags_titles = Array();

		foreach($tag_ids as $tag_id){
			$tag = new Tag($tag_id);
			$tags_titles[] = $tag->getTitle();
		}

		return $tags_titles;
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
				UserActivity::add_extended('file:deleted', $this->id);
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
				$is_new = !$this->file->published;
				$file_changed = false;

				$title = Request::post('title', '', 'title');
				if($this->file->title != $title){
					$this->file->title = $title;

					$name = $this->file->name;
					$this->file->name = formatFileVersionName(0,0,$title, false);

					$this->updateFileNames($name);	

					$file_changed = true;				
				}

				$this->file->published = 1;

				$tags = Request::post('tags_tags', '', 'tags');
				$this->updateTags($tags);

				if(F3::get('USER')->isAtLeast('manager')){
					$approved = Request::post('approved', 0, 'number');
					if($this->file->approved != $approved){
						$this->file->approved = $approved;
						$file_changed = true;
					}
				}

				if($is_new || $file_changed){
					$this->file->save();
					
					UserActivity::add_extended('file:'.($is_new?'created':'updated'), $this->id);
				}

				Alerts::addAlert('info', 'File information updated!', '');
			}			

			if(Request::post('go_back', false, 'bool')){
				// TODO F3::reroute(F3::get('USER')->getLastPage());
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
		$DB_tag = new Tag();
		$file_tag = new FileTag($this->file->id);

		// load all file tags (save values in keys for faster search)
		$tags_available = array_fill_keys($file_tag->getTagsId(), true);

		foreach($tags as $tag){
			$DB_tag->find($tag);

			if($DB_tag->dry() && !F3::get('USER')->isAtLeast('manager')){
				continue;
			}

			if($DB_tag->dry()){
				$DB_tag->create($tag);
			}

			if(isset($tags_available[$DB_tag->getId()])){
				// such tag is set
				unset($tags_available[$DB_tag->getId()]);
			}else{
				$file_tag->create($DB_tag->getId());
			}
		}

		// detele remained tags
		foreach($tags_available as $tag_id => $tag){
			$file_tag->delete($tag_id);
		}

	}

	static function matchTags($tags){
		if(!is_array($tags)){
			preg_match_all('/([\w\s\-]+)(\,)/', $tags, $matched_tags);
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