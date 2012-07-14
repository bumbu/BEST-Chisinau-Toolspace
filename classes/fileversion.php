<?php
class FileVersion{
	private $file = null;
	private $version = 0;
	private $file_version = null;

	function __construct($file = null, $version = 0){
		$this->file = $file;
		$this->version = $version;

		$this->file_version = new Axon('files_versions');
		$this->file_version->load("file_id=".$this->file->getId()." AND version=".$this->version);

		$this->file_version->file_id = $this->file->getId();
		$this->file_version->version = $version;
	}

	function dry(){
		return $this->file_version->dry();
	}

	function cast(){
		return $this->file_version->cast();
	}

	function hasThumb(){
		return $this->file_version->has_thumb;
	}

	function getExtension(){
		return $this->file_version->extension;
	}
	
	function getExtensionThumb(){
		return $this->file_version->extension_thumb;
	}

	function delete(){
		if(!$this->file_version->dry()){
			UserActivity::add('file:version_deleted', $this->file->getId(), NULL, $this->file_version->version);
			// delete phisical file
			$file_path = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), $this->file_version->extension);
			@unlink($file_path);

			// if thumb, delete thumb
			if($this->file_version->has_thumb){
				$file_path_no_extension = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), '');
				@unlink($file_path_no_extension.'thumb.'.$this->file_version->extension_thumb);
			}

			// delete DB data
			$this->file_version->erase();
		}
	}

	function updateVersion($uploaded_file, $thumb, $approved){
		$was_dry = $this->file_version->id == 0;

		$this->file_version->approved = $approved;
		if($approved){
			$this->file_version->approved_at = timeToMySQLDatetime(time());
			$this->file_version->approved_by = F3::get('USER')->id;
		}else{
			$this->file_version->approved_at = timeToMySQLDatetime(0);
			$this->file_version->approved_by = 0;
		}

		if($uploaded_file && $uploaded_file !== NULL){
			$this->file_version->size = $uploaded_file['size'];
			$this->file_version->mime_type = $uploaded_file['type'];
			$this->file_version->added_at = timeToMySQLDatetime(time());
			$this->file_version->added_by = F3::get('USER')->id;

			// working with file
			$this->file_version->extension = substr(strrchr($uploaded_file['name'], '.'), 1);

			$folder_path = getFilePath($this->file_version->file_id, $this->version, '', '', false);
			// create dir if it does not exists
			if(!is_dir($folder_path)){
				mkdir($folder_path, 0777, true);
			}

			$file_path = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), $this->file_version->extension);

			// move file
			copy($uploaded_file['tmp_name'], $file_path);

			$this->file_version->has_thumb = 0;
			// create thumb from thumb provided image
			if($thumb !== NULL){
				// move file
				copy($thumb['tmp_name'], F3::get('TEMP').$thumb['name']);
				$thumb_extension = $this->createThumb($thumb['name'], F3::get('TEMP').$thumb['name']);
				if($thumb_extension !== false){
					$this->file_version->has_thumb = 1;
					$this->file_version->extension_thumb = $thumb_extension;
				}
			}

			// create thumb from file
			if(!$this->file_version->has_thumb){
				$thumb_extension = $this->createThumb($uploaded_file['name']);
				if($thumb_extension !== false){
					$this->file_version->has_thumb = 1;
					$this->file_version->extension_thumb = $thumb_extension;
				}
			}

			if($thumb !== NULL){
				// remove temp thumb
				@unlink(F3::get('TEMP').$thumb['name']);		
			}

			// TODO: zip
		}

		$this->file_version->save();

		if($was_dry){
			UserActivity::add('file:version_created', $this->file->getId(), NULL, $this->file_version->version);
		}else{
			if(!$approved)
				UserActivity::add('file:version_disapproved', $this->file->getId(), NULL, $this->file_version->version);
		}

		if($approved)
			UserActivity::add('file:version_approved', $this->file->getId(), NULL, $this->file_version->version);
	}

	function createThumb($uploaded_file_name, $uploaded_location = NULL){
		if(!$uploaded_location)
			$file_path = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), $this->file_version->extension);
		else
			$file_path = $uploaded_location;
		
		$file_path_no_extension = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), '');
		$file_path_thumb = $file_path_no_extension.'thumb.'. $this->file_version->extension;
				
		// check if it is image
		preg_match('/\.(gif|jp[e]*g|png)$/',$uploaded_file_name,$extension);
		if($extension){
			// crate thumb
			ob_start();
			Graphics::thumb($file_path, 330, 330, false);
			$thumb_contents = ob_get_contents();
			ob_end_clean();

			F3::putFile($file_path_thumb, $thumb_contents);

			return $extension[1];	// return without point
		}else{
			// try to use ImageMagic from exec
			$output = Array();
			exec("convert -thumbnail 330x330 \"{$file_path}[0]\" {$file_path_no_extension}thumb.png", $out);
			if(count($output)){
				foreach($output as $output_error){
					Alerts::addAlert('block', 'Thumb not magically-created!', 'It may be because of unsuported file format');
				}
			}
			
			if(file_exists($file_path_no_extension.'thumb.png')){
				return 'png';
			}
		}
		
		return false;
	}
}