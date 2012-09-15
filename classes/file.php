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
	function getFile(){
		if($this->file_cast === null){
			// load file details
			$this->file_cast = $this->file->cast();
			// load tags
			$this->file_cast['tags'] =  $this->getTags();
			// load versions
			$this->file_cast['versions'] =  $this->getVersions();

			// set last active version
			if(count($this->file_cast['versions'])){
				$this->file_cast['last_active_version'] = $this->file_cast['versions'][count($this->file_cast['versions'])-1]['version'];
				foreach($this->file_cast['versions'] as $version){
					if($version['approved'] == 1){
						$this->file_cast['last_active_version'] = $version['version'];
						break;
					}
				}
			}

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
		$where = '';
		if(!F3::get('USER')->isAtLeast('manager'))
			$where = " AND 
				 ( files_versions.approved = 1
				 	OR
				 	(files_versions.approved = 0 AND files_versions.added_by = ".F3::get('USER')->id.")

				 )";
	
		// TODO check if it works
		$sql = "
			SELECT files_versions.*
			FROM files_versions
			WHERE files_versions.file_id = ".$this->id."
			$where
			ORDER BY version DESC
		";
		DB::sql($sql);

		$files_versions = Array();
		foreach(F3::get('DB')->result as $files_version){
			$files_versions[] = $files_version;
		}

		return $files_versions;
	}

	function updateFileFromRequest(){
		// check if any delete
		if(F3::get('USER')->isAtLeast('manager')){
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
				$this->deleteVersion(Request::post('version', 0, 'number'));
				Alerts::addAlert('info', 'Version deleted!', '');
			}
		}

		if($this->file->published == 0){	// new file
			// check for automatic approvement
			$approved = F3::get('USER')->isAtLeast('manager') ? 1 : 0;

			// update file information
			$this->file->title = Request::post('title', '', 'title');
			$this->file->name = formatFileVersionName(0,0,Request::post('title', '', ''), false);
			$this->file->published = 1;
			$this->file->any_approved = $approved;
			$this->file->all_approved = $approved;
			$this->file->save();
			// $this->id = $this->file->id = $this->file->_id;

			$this->updateFileNames('', $this->file->name);

			$this->updateTags(Request::post('tags', '', 'tags'));
		}else{
			// update file title
			if($this->editableByUser()){
				$title = Request::post('title', '', 'title');
				if($this->file->title != $title){
					$this->file->title = $title;

					$name = $this->file->name;
					$this->file->name = formatFileVersionName(0,0,$title, false);

					$this->updateFileNames($name);					

					UserActivity::add('file:edited', $this->id, NULL, $this->file->title);
					$this->file->save();
				}
			}

			if($this->editableByUser())
				$this->updateTags(Request::post('tags', '', 'tags'));
		}

		Alerts::addAlert('info', 'File information updated!', '');

		// check for batch approve/disapprove
		if(!$this->file->dry()){
			if(Request::post('approve_all', false, 'bool') || Request::post('disapprove_all', false, 'bool')){
				$approve = Request::post('approve_all', false, 'bool') ? 1 : 0;
				// update file details
				$this->file->any_approved = $approve;
				$this->file->all_approved = $approve;
				$this->file->save();

				// update versions
				$sql = "UPDATE files_versions SET approved=".$approve." WHERE file_id=".$this->file->id;
				DB::sql($sql);
			}
		}
	}

	function getVersion($version){
		return new FileVersion($this, $version);
	}

	function deleteVersion($version){
		$file_version = new FileVersion($this, $version);
		$file_version->delete();
	}

	function deleteAllVersions(){
		$this->getFile();
		foreach($this->file_cast['versions'] as $version){
			$this->deleteVersion($version['version']);
		}

		$this->file_cast = NULL;	// clear file_cast
	}

	// TODO
	function updateVersion($version, $approved = 0){
		$this->createVersion(NULL, $version, $approved);
		$file_version = new Axon('files_versions');

		if($approved){
			$this->file->any_approved = 1;

			// check for any not approved
			$file_version->load('file_id='.$this->id.' AND approved=0');
			if($file_version->dry()){
				$this->file->all_approved = 1;
			}
		}else{
			if($this->file->any_approved){
				$file_version->load('file_id='.$this->id.' AND approved=1');
				if($file_version->dry()){
					$this->file->any_approved = 0;
				}
			}

			$this->file->all_approved = 0;
		}

		$this->file->save();
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
		if(!count($tags))
			return;

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
			if($this->file->any_approved == 0){
				foreach($this->getVersions() as $version){
					if($version['added_by'] != F3::get('USER')->id)
						return false;
				}
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