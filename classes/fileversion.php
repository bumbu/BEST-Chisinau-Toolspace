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

	function hasExtension($extension){
		$this->loadExtension($extension);
		return !$this->loaded_extension->dry();
	}

	function deleteExtension($extension){
		$this->loadExtension($extension);

		if(!$this->loaded_extension->dry()){
			UserActivity::add('file:extesion_deleted', $this->file->getId(), NULL, $this->file_version->version);
			// delete phisical file
			$file_path = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), $extension);
			@unlink($file_path);

			$this->loaded_extension->erase();
			$this->loaded_extension = null;
		}

	}

	function updateExtension($extension, $approved){
		$approved = $approved ? 1 : 0;
		$this->loadExtension($extension);

		$this->loaded_extension->approved = $approved;
		if($approved){
			$this->loaded_extension->approved_at = timeToMySQLDatetime(time());
			$this->loaded_extension->approved_by = F3::get('USER')->id;
		}else{
			$this->loaded_extension->approved_at = timeToMySQLDatetime(0);
			$this->loaded_extension->approved_by = 0;
		}
		$this->loaded_extension->save();
	}

	function updateExtensionFile($extension, $file){
		if($extension == ''){
			$extension = FileVersion::extractExtensionFromName($file);
		}

		$this->loadExtension($extension);

		if($this->loaded_extension->dry()){
			$this->loaded_extension->file_id = $this->file->getId();
			$this->loaded_extension->version = $this->version;
			$this->loaded_extension->extension = $extension;
			$this->loaded_extension->save();
			$this->loaded_extension->id = $this->loaded_extension->_id;
		}

		$this->loaded_extension->size = filesize($file);
		$this->loaded_extension->added_at = timeToMySQLDatetime(time());
		$this->loaded_extension->added_by = F3::get('USER')->id;
		$this->loaded_extension->save();

		$folder_path = getFilePath($this->loaded_extension->file_id, $this->version, '', '', false);
		// create dir if it does not exists
		if(!is_dir($folder_path)){
			mkdir($folder_path, 0777, true);
		}

		$file_path = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), $this->loaded_extension->extension);

		// move file
		if(rename($file_path_full, $file_path)){
			return true;
		}
		return false;

		// TODO: zip
	}

	function getExtensionNames(){
		$extension_names = [];
		$this->loadExtension();	// load all extensions
		if(!$this->loaded_extension->dry()){
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
		$file_path_no_extension = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), '');
		if(is_file($file_path_no_extension.'thumb.png'))
			@unlink($file_path_no_extension.'thumb.png');
	}

	function update($approved){
		$extension_names = $this->getExtensionNames();
		foreach($extension_names as $extension_name){
			$this->updateExtension($extension_name, $approved);
		}
	}

	function updateThumbnail($file){
		$file_path_no_extension = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), '');
		$file_path = $file_path_no_extension.'thumb.png';

		// move thumb
		if(rename($file, $file_path)){
			return true;
		}
		return false;
	}

	static function createScaledImageByConvert($upload_dir, $file_name, $options){
		$sizes = $options['max_width'].'x'.$options['max_height'];

		exec("cd $upload_dir;/usr/bin/convert -thumbnail 330x330 \"{$file_name}[0]\" \"{$file_name}.thumb.png\"");

		if(is_file($upload_dir.$file_name.'.thumb.png'))
			return true;
		else
			return false;
	}

	static function createScaledImage($upload_dir, $file_name, $options) {
		$file_path = $upload_dir.$file_name;
		$new_file_path = $upload_dir.'thumb_'.$file_name;
		list($img_width, $img_height) = @getimagesize($file_path);
		if (!$img_width || !$img_height) {
			return false;
		}
		$scale = min(
			$options['max_width'] / $img_width,
			$options['max_height'] / $img_height
		);
		if ($scale >= 1) {
			if ($file_path !== $new_file_path) {
				return copy($file_path, $new_file_path);
			}
			return true;
		}
		$new_width = $img_width * $scale;
		$new_height = $img_height * $scale;
		$new_img = @imagecreatetruecolor($new_width, $new_height);
		switch (strtolower(substr(strrchr($file_name, '.'), 1))) {
			case 'jpg':
			case 'jpeg':
				$src_img = @imagecreatefromjpeg($file_path);
				$write_image = 'imagejpeg';
				$image_quality = isset($options['jpeg_quality']) ?
					$options['jpeg_quality'] : 75;
				break;
			case 'gif':
				@imagecolortransparent($new_img, @imagecolorallocate($new_img, 0, 0, 0));
				$src_img = @imagecreatefromgif($file_path);
				$write_image = 'imagegif';
				$image_quality = null;
				break;
			case 'png':
				@imagecolortransparent($new_img, @imagecolorallocate($new_img, 0, 0, 0));
				@imagealphablending($new_img, false);
				@imagesavealpha($new_img, true);
				$src_img = @imagecreatefrompng($file_path);
				$write_image = 'imagepng';
				$image_quality = isset($options['png_quality']) ?
					$options['png_quality'] : 9;
				break;
			default:
				$src_img = null;
		}

		$write_image = 'imagepng';
		$image_quality = 9;

		$success = $src_img && @imagecopyresampled(
			$new_img,
			$src_img,
			0, 0, 0, 0,
			$new_width,
			$new_height,
			$img_width,
			$img_height
		) && $write_image($new_img, $new_file_path, $image_quality);
		// Free up memory (imagedestroy does not delete files):
		@imagedestroy($src_img);
		@imagedestroy($new_img);
		return $success;
	}

	static function extractExtensionFromName($name){
		$archive_extensions = Array('rar', 'zip', 'gzip', 'gz');

		$extension = substr(strrchr($name, '.'), 1);
		if(in_array($extension, $archive_extensions)){
			$name = substr($name, 0, strrpos($name, '.'));
			if(stristr($name, '.') !== FALSE){
				$extension = substr(strrchr($name, '.'), 1) .'.'. $extension;
			}
		}

		return $extension;
	}
}