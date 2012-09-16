<?php
class ImageProcessing{
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
}
?>