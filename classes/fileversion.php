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

	function updateVersion($files, $approved){
		$uploaded_file;$thumb;
		$was_dry = $this->file_version->id == 0;

		$this->file_version->approved = $approved;
		if($approved){
			$this->file_version->approved_at = timeToMySQLDatetime(time());
			$this->file_version->approved_by = F3::get('USER')->id;
		}else{
			$this->file_version->approved_at = timeToMySQLDatetime(0);
			$this->file_version->approved_by = 0;
		}

		if($files !== NULL && isset($files['file'])){
			$file_path_full = $files['file']['path'].$files['file']['name'];

			$this->file_version->size = filesize($file_path_full);
			$this->file_version->added_at = timeToMySQLDatetime(time());
			$this->file_version->added_by = F3::get('USER')->id;

			// working with file
			$this->file_version->extension = FileVersion::extractExtensionFromName($files['file']['name']);

			$folder_path = getFilePath($this->file_version->file_id, $this->version, '', '', false);
			// create dir if it does not exists
			if(!is_dir($folder_path)){
				mkdir($folder_path, 0777, true);
			}

			$file_path = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), $this->file_version->extension);

			// move file
			rename($file_path_full, $file_path);

			// save thumb
			$this->file_version->has_thumb = 0;
			if(isset($files['thumb'])){
				$file_path_thumb_no_extension = getFilePath($this->file->getId(), $this->version, $this->file->getFileName(), '');
				$file_thumb_extension = FileVersion::extractExtensionFromName($files['thumb']['name']);
				$file_path_thumb = $file_path_thumb_no_extension.'thumb.'. $file_thumb_extension;

				// move thumb
				if(rename($files['thumb']['path'].$files['thumb']['name'], $file_path_thumb)){
					$this->file_version->has_thumb = 1;
					$this->file_version->extension_thumb = $file_thumb_extension;
				}

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
			@exec("convert -thumbnail 330x330 \"{$file_path}[0]\" {$file_path_no_extension}thumb.png", $out);
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

	static function createScaledImageByConvert($upload_dir, $file_name, $options){
		$file_path = $upload_dir.$file_name;
		$new_file_path = $file_path.'.thumb.png';
		$sizes = $options['max_width'].'x'.$options['max_height'];

		@exec("convert -thumbnail $sizes \"{$file_path}[0]\" {$new_file_path}", $out);

		if(is_file($new_file_path))
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
		return substr(strrchr($name, '.'), 1);
	}
}