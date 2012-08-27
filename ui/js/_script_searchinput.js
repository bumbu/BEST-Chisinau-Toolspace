$.Topic('domready').subscribe(bindSearchInputs)

function bindSearchInputs(){
	// Search input
	if($("input#search").length > 0){
		$("#search_tags").on('click', 'i', function(event){
			removeTag(this, event)
		})

		$("input#search").autocomplete({
			minLength: 1
			,source: function( request, response ) {
				lastXhr = $.ajax({
					url: LIVE_SITE+"ajax/origami/tag/suggestions/"
					,dataType: 'json'
					,type: 'POST'
					,data: {'term': request.term, 'tags_array': tags_array.join('')}
					,success: function( data, status, xhr ){
						response( data );
					}
				});
			}
			,close: function(event, ui){
				$("input#search").keyup()
			}
			,focus: function(event, ui){
				// prevent adding value to input while moving between different results
				event.preventDefault()
			}
		}).keyup(function(event){
			hookSearchInput(this, event)
		}).keyup()

		$('#search_submit').click(function(event){
			pushTagsBackToInput()
		})
	}

	// tags hooks
	$('button.tag').click(function(event){hookedTagClick(this, event)})
}

/**********************************************
	Search bar and tags
***********************************************/

var tags_array = []
	,previous_caret_position

function hookSearchInput(element, event){
	var element = $(element)
		,value = element.val()
		,tags_modified = false

	if(previous_caret_position == 0 && (event.which == 8 || event.which == 37)){
		if(element.caret().start == 0 && tags_array.length > 0){
			var last_tag = tags_array.pop()

			element.val(last_tag.slice(0,-1) +' '+ value)
			element.caret(last_tag.length-1,last_tag.length-1)

			$('#search_tags a').last().remove()

			tags_modified = true
		}
	}

	previous_caret_position = element.caret().start
	
	var value = element.val()
		,matched_tags = value.match(/\[([a-zA-Z0-9\s\-]+)\]/g)
		,value_with_no_tags = $.trim(value.replace(/\[([a-zA-Z0-9\s\-]+)\]/g, ''))

	if(matched_tags)
		for(var i = 0; i < matched_tags.length; i++){
			if(addTag(matched_tags[i]))
				tags_modified = true
		}

	if(value != value_with_no_tags){
		var new_caret_position = previous_caret_position - tags_array[tags_array.length-1].length

		element.val(value_with_no_tags)
		element.caret(new_caret_position, new_caret_position)
	}

	if(tags_modified){
		searchResize()
	}

}

function addTag(tag){
	if(tags_array.indexOf(tag) == -1){
		// ad new tag
		tags_array.push(tag)
		//create DOM element
		$('#search_tags').append('<a class="btn btn-mini">'+tag.slice(1,-1)+' <i class="icon-remove"></i></a>')

		return true
	}
	return false
}

function removeTag(_element, event){
	event.preventDefault()
	var element
		,element_text

	if(typeof(_element) === 'string'){
		element_text = _element
		// search for element
		$('#search_tags a').each(function(){
			if($(this).text().trim() == element_text){
				element = $(this)
				return
			}
		})
		element_text = "[" + element_text + "]"
		element.remove()
	}else{
		element = $(_element)
		element_text = "[" + element.parent().text().trim() + "]"
		element.parent().remove()
	}	

	tags_array.remove(tags_array.indexOf(element_text))	
	
	searchResize()

	// remove all highlights at all tags
}

function searchResize(){
	var input = $("#search")
		,tags = $("#search_tags")
		,input_padding = input.css("padding-left").replace("px", "")

	input.width(input.width() - tags.width() - 3 + Number(input_padding))
	input.css("padding-left", tags.width() + 3)
}

function pushTagsBackToInput(){
	var val = $("input#search").val()
	$("input#search").val(val + tags_array.join(""))
}

function hookedTagClick(element, event){
	var element = $(element)
		,tag_text_without_brackets = element.text().trim()
		,tag_text = "[" + tag_text_without_brackets + "]"

	if(element.hasClass('btn-primary')){
		// remove tag from list
		removeTag(tag_text_without_brackets, event)
		$('#search_submit').click()
	}else{
		// add tag to list
		addTag(tag_text)
		$('#search_submit').click()
	}

	searchResize()
}
