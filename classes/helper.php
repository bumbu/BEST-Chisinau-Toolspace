<?php

function pretifySize($bytes){
	$unit = 1024;
	$kilobyte = 1*$unit;
	$megabyte = $kilobyte*$unit;
	$gigabyte = $megabyte*$unit;
	$result = '';

	if($bytes >= $gigabyte){
		$result .= number_format($bytes / $gigabyte, 3, '.', '');
		$result .= ' Gb';
	}elseif($bytes >= $megabyte){
		$result .= number_format($bytes / $megabyte, 2, '.', '');
		$result .= ' Mb';
	}elseif($bytes >= $kilobyte){
		$result .= (int)($bytes / $kilobyte);
		$result .= ' Kb';
	}else{
		$result .= $bytes;
		$result .= ' Bytes';
	}

	return $result;
}

function pretifyDate($date){
	// Y-m-d H:i:s
	$time = strtotime($date);

	return date('d M Y', $time);
}

function timeToMySQLDatetime($time){
	return date('Y-m-d H:i:s', $time);
}

function getFilePath($file_id, $version_id, $name, $extension, $full = true){
	$file_path = F3::get('STORAGE');
	$file_path .= F3::get('FILES_PATH');
	$file_path .= (($file_id%100 - $file_id%10)/10) . "/" . ($file_id%10) . "/";
	if($full){
		$file_path .= formatFileVersionName($file_id, $version_id, $name);
		$file_path .= ".$extension";
	}

	return $file_path;
}

function extractExtensionFromName($name){
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

/*
	ID|_|v|VER|_ |title		// name structure
	 5|6|7| 10|11|   63		// name indexes
	 5|1|1|  3| 1|   52		// name sizes
*/
function formatFileVersionName($file_id, $version_id, $title, $prepend = true){
	$name = '';
	if($prepend){
		// add ID
		$name .= sprintf('%1$05d', $file_id%100000);
		// add _v
		$name .= '_v';
		// add version
		$name .= sprintf('%1$03d', $version_id%1000);
		// add _
		$name .= '_';
	}

	// add title
	$title = trim($title);
	$title = preg_replace('/^\d{5}_v\d{3}_/', '', $title);	// remove old id and version
	$title = preg_replace('/\.\w{1,5}$/', '', $title);	// remove extension
	$title = Request::sanitize($title, 'remove_accents');
	$title = Request::sanitize($title, 'alfanumeric_space_minus_underscore');
	$title = str_replace(' ', '_', $title);	// replace spaces
	$title = substr($title, 0, 52);	// limit size

	$name .= $title;

	return $name;
}

function getFileThumbPath($file_id, $version){
	$file = new File($file_id);
	$file_version = $file->getVersion($version);
	$file_path_no_extension = getFilePath($file->getId(), $version, $file->getFileName(), '');

	if(is_file($file_path_no_extension.'thumb.png')){
		return F3::get('LIVE_SITE') . $file_path_no_extension.'thumb.png';
	}else{
		return F3::get('PLACEHOLDER');
	}
}