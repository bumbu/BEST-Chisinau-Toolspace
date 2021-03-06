<?php
class FileVersion{
	private $file = null;
	private $version = 0;
	private $loaded_extension = null;

	function __construct($file = null, $version = 0){
		$this->file = $file;
		$this->version = $version;

		$this->loaded_extension = new Axon('files_versions');
	}

	function dry(){
		// try to load any extension
		$this->loadExtension();
		return $this->loaded_extension->dry();
	}

	function loadExtension($extension = ''){
		if($extension == ''){
			if($this->loaded_extension->dry())
				$this->loaded_extension->load("file_id=".$this->file->getId()." AND version=".$this->version);
		}else{
			if($this->loaded_extension->dry() || $this->loaded_extension->extension != $extension){
				$this->loaded_extension->load("file_id=".$this->file->getId()." AND version=".$this->version ." AND extension='".$extension."'");
			}
		}
	}

	function getExtension($extension = ''){
		$this->loadExtension($extension);
		return $this->loaded_extension->cast();
	}

	function getExtensionById($extension_id){
		$this->loaded_extension->load('id='.(int)$extension_id);
		if(!$this->loaded_extension->dry()){
			return $this->loaded_extension->cast();
		}
		return null;
	}

	function hasExtension($extension){
		$this->loadExtension($extension);
		return !$this->loaded_extension->dry();
	}

	function deleteExtension($extension){
		$this->loadExtension($extension);

		if(!$this->loaded_extension->dry()){
			UserActivity::add_extended('extension:deleted', $this->loaded_extension->id);
		
			// delete phisical file
			$file_path = getFilePath($this->file->getId(), $this->version, $this->file->getName(), $extension);
			@unlink($file_path);

			$this->loaded_extension->erase();
			// reload extensions to be able to manipulate with that object
			$this->loadExtension();
		}

	}

	function updateExtensionFile($extension, $file, $update_data = true){
		if(!is_file($file))
			return false;

		if($extension == ''){
			$extension = extractExtensionFromName(basename($file));
		}

		$this->loadExtension($extension);

		if($update_data){
			$is_new = false;
			if($this->loaded_extension->dry()){
				$is_new = true;

				$this->loaded_extension->file_id = $this->file->getId();
				$this->loaded_extension->version = $this->version;
				$this->loaded_extension->extension = $extension;
			}

			$this->loaded_extension->size = filesize($file);
			$this->loaded_extension->added_at = timeToMySQLDatetime(time());
			$this->loaded_extension->added_by = F3::get('USER')->id;
			$this->loaded_extension->save();

			if($is_new)
				UserActivity::add_extended('extension:created', $this->loaded_extension->_id);
			else
				UserActivity::add_extended('extension:updated', $this->loaded_extension->id);
		}

		$folder_path = getFilePath($this->loaded_extension->file_id, $this->version, '', '', false);
		// create dir if it does not exists
		if(!is_dir($folder_path)){
			mkdir($folder_path, 0777, true);
		}

		$file_path = getFilePath($this->file->getId(), $this->version, $this->file->getName(), $this->loaded_extension->extension);

		// move file
		if(rename($file, $file_path)){
			return true;
		}
		return false;

		// TODO: zip
	}

	function getExtensionNames(){
		$extension_names = array();
		$this->loadExtension();	// load all extensions
		while(!$this->loaded_extension->dry()){
			$extension_names[] = $this->loaded_extension->extension;
			$this->loaded_extension->skip();
		}
		
		return $extension_names;
	}

	function delete(){
		$extension_names = $this->getExtensionNames();
		foreach($extension_names as $extension_name){
			$this->deleteExtension($extension_name);
		}

		// erase thumbnail
		$file_path_no_extension = getFilePath($this->file->getId(), $this->version, $this->file->getName(), '');
		if(is_file($file_path_no_extension.'thumb.png'))
			@unlink($file_path_no_extension.'thumb.png');
	}

	function updateThumbnail($file){
		if(!is_file($file))
			return false;

		$file_path_no_extension = getFilePath($this->file->getId(), $this->version, $this->file->getName(), '');
		$file_path = $file_path_no_extension.'thumb.png';

		// move thumb
		if(rename($file, $file_path)){
			return true;
		}
		return false;
	}
}