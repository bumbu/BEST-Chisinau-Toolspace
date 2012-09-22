<?php
class Tag{
	function __construct($tag_id = 0){
		$this->tag = new Axon('tags');

		if($tag_id > 0){
			$this->tag->load($tag_id);
		}
	}

	function dry(){
		return $this->tag->dry();
	}

	function getId(){
		return $this->tag->id;
	}

	function getTitle(){
		return $this->tag->title;
	}

	function create($title){
		$this->tag = new Axon('tags');
		$this->tag->title = $title;
		$this->tag->save();
		$this->tag->id = $this->tag->_id;

		UserActivity::add_extended('tag:created', $this->tag->id);
	}

	function find($title){
		$this->tag->load("title LIKE '$title'");
		if($this->tag->dry()){
			return 0;
		}

		return $this->tag->id;
	}

	function delete(){
		if(!$this->tag->dry()){
			UserActivity::add_extended('tag:deleted', $this->tag->id);

			$this->erase();
		}
	}
}