<?php
class FileTag{
	private $file_id;
	function __construct($file_id = 0){
		$this->file_id = $file_id;

		if($file_id > 0){
			$this->load($file_id);
		}else{
			$this->file_tag = new Axon('files_tags');
		}
	}

	function load($file_id){
		$this->file_id = $file_id;

		$this->file_tag = new Axon('files_tags');
		$this->file_tag->load("file_id=".$file_id);
	}

	function getTagsId(){
		$ids = array();
		while(!$this->file_tag->dry()){
			$ids[] = $this->file_tag->tag_id;
			$this->file_tag->skip();
		}

		return $ids;
	}

	function create($tag_id){
		$this->file_tag->reset();
		$this->file_tag->file_id = $this->file_id;
		$this->file_tag->tag_id = $tag_id;
		$this->file_tag->save();

		UserActivity::add_extended('filetag:created', $this->file_tag->cast());
	}

	function delete($tag_id){
		$this->file_tag->load("file_id=".$this->file_id." AND tag_id=".$tag_id);
		if(!$this->file_tag->dry()){
			UserActivity::add_extended('filetag:deleted', $this->file_tag->cast());
			$this->file_tag->erase();
		}
	}
}