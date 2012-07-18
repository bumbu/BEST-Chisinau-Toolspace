Array.prototype.remove = function(from, to) {
  var rest = this.slice((to || from) + 1 || this.length);
  this.length = from < 0 ? this.length + from : from;
  return this.push.apply(this, rest);
};


/**********************
	Custom stuff
***********************/


// on DOM ready
$(function(){
	$(document).bind('drop dragover', function (e) {
		e.preventDefault();
	});
	loadHooks()
	fileupload_hook()
})

var file_details_open_id = 0
	,file_details_is_open = false

function loadHooks(){
	// form submission
	$(".submit").click(function(event){elementClick(event, this, '#form')})
	$(".sorting").click(function(event){elementClick(event, this, '#form')})
	$('.change').click(function(event){elementClick(event, this)})

	$('.change_download_old').click(function(){changeDownloadButtons(this)})

	// changing active version moved to HTML data
	// file_details_open_id

	// load file details only when asked for
	$('#fileDetails').on('show', function(){fileDetails('show')})
	$('#fileDetails').on('hide', function(){fileDetails('hide')})

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
	Image upload processing
***********************************************/

function fileupload_hook(){
	// file upload
	$('#file').fileupload({
		dataType: 'json'
		,url: LIVE_SITE+'ajax/origami/file/upload/'
		,maxChunkSize: 4000000
		,add: function (e, data) {
			var that = this;
			$.getJSON('ajax/origami/file/resumeUpload/', {file: data.files[0].name}, function (file){
				data.uploadedBytes = file && file.size
				$.blueimpUI.fileupload.prototype.options.add.call(that, e, data)
			});
		}
		,start: function(e, data){
			$('#fileupload_container').hide()
			$('#progress_bar').show()
			// disable send button
			$('.btn-submit-form').attr('disabled', 'disabled');
		}
		,progressall: function (e, data){
			var progress = parseInt(data.loaded / data.total * 100, 10);
			$('#progress_bar .bar').css('width', progress + '%').html(progress + '%')
		}
		,done: function (e, data){
			$('.btn-submit-form').removeAttr('disabled')
			$('#progress_bar').hide()
			$('#fileupload_text').show()
			// show uploaded file name
			$('#fileupload_delete').show()

			var file_name = ''
			$.each(data.result, function (index, file){
				file_name = file.name
			});
			$('#fileupload_text span').html(file_name)
			$('#file_uploaded').val(file_name)

			// try to create thumb
			fileupload_createThumb(file_name, 0)
		}
	});

	$('#fileupload_delete').click(function(event){
		event.preventDefault()
		// clear input values
		$('#file_uploaded').val('')
		$('#file_uploaded_thumb').val('')
		// show file add button
		$('#fileupload_container').show()
		// hide all other elements
		$('#fileupload_text').hide()
		$('#fileupload_delete').hide()
		$('#fileupload_thumbtext').hide()
		$('#fileupload_thumbdelete').hide()
		$('#fileupload_container_thumb').hide()
		$('#progress_bar').hide()
	})

	$('#fileupload_thumbdelete').click(function(event){
		event.preventDefault()
		// clear input value
		$('#file_uploaded_thumb').val('')
		// show thumb add button
		$('#fileupload_container_thumb').show()
		// hide all other elements
		$('#fileupload_thumbtext').hide()
		$('#fileupload_thumbdelete').hide()
		$('#progress_bar').hide()
	})

	$('#file_thumb').fileupload({
		dataType: 'json'
		,url: LIVE_SITE+'ajax/origami/file/upload/'
		,start: function(e, data){
			$('#fileupload_container_thumb').hide()
			$('#progress_bar .bar').css('width', '0%').html('0%')
			$('#progress_bar').show()
			// disable send button
			$('.btn-submit-form').attr('disabled', 'disabled')
			$('#fileupload_thumbtext').html('Uploading thumb')
		}
		,progressall: function (e, data){
			var progress = parseInt(data.loaded / data.total * 100, 10)
			$('#progress_bar .bar').css('width', progress + '%').html(progress + '%')
		}
		,done: function (e, data){
			$('#progress_bar').hide()
			$('.btn-submit-form').removeAttr('disabled')

			var file_name = ''
			$.each(data.result, function (index, file){
				file_name = file.name
			});
			$('#fileupload_thumbtext').html('Thumb' +file_name+ 'uploaded')

			// try to create thumb
			fileupload_createThumb(file_name, 1)
		}
	});
}

function fileupload_createThumb(name, remove_file){
	$.ajax({
		type: 'POST'
		,url: LIVE_SITE+'ajax/origami/file/createThumb/'
		,cache: false
		,dataType: 'json'
		,data: {'name': name, 'remove_file': remove_file}
		,error: function(jqXHR, textStatus, errorThrown){
			console.log(jqXHR, textStatus, errorThrown)
			// thumb not created
			$('.btn-submit-form').removeAttr('disabled')
			$('#fileupload_thumbtext').html('System was not able to create thumb automatically')
			$('#fileupload_container_thumb').show()
		}
		,beforeSend: function(){
			$('.btn-submit-form').attr('disabled', 'disabled')

			$('#fileupload_thumbtext').html('Trying to create thumb');
			$('#fileupload_thumbtext').show();			
		}
		,success: function(data) {
			// enable send button
			$('.btn-submit-form').removeAttr('disabled')

			if(data.response_code == '200'){
				$('#fileupload_thumbtext').html(data.response_message);
				$('#file_uploaded_thumb').val(data.message)
				$('#fileupload_thumbdelete').show()
			}else{
				$('#fileupload_thumbtext').html(data.response_message);
				// show thumb input
				$('#fileupload_container_thumb').show()
			}
		}
	});
}


/**********************************************
	Elements hooks
***********************************************/

function elementClick(event, element, submit_form){
	var element = $(element)

	if(typeof(element.data('on')) !== 'undefined'){
		$('#'+element.data('on')).val(1)
	}
	if(typeof(element.data('off')) !== 'undefined'){
		$('#'+element.data('off')).val(0)
	}
	if(typeof(element.data('change')) !== 'undefined'){
		if(typeof(element.data('changeto')) !== 'undefined'){
			$('#'+element.data('change')).val(element.data('changeto'))
		}
	}
	if(typeof(element.data('changevar')) !== 'undefined'){
		if(typeof(element.data('changeto')) !== 'undefined'){
			window[element.data('changevar')] = element.data('changeto')
		}
	}
	if(typeof(element.data('sort')) !== 'undefined' && typeof(element.data('sortby')) !== 'undefined'){
		$('#sort').val(element.data('sort'))
		$('#sort_by').val(element.data('sortby'))
	}

	if(typeof(submit_form) !== 'undefined')
		$(submit_form).submit()
}

function changeDownloadButtons(element){
	var element = $(element)
		,new_version = element.data('changeto')
		,download_old_version = $('#download_old_version')
		,last_version = download_old_version.data('last-version')
		,download_buttons =$('.download')

	if(last_version != new_version){
		download_buttons.hide()
		download_old_version.show()
		download_old_version.attr('href', download_old_version.data('href') + element.data('changeto'))
	}else{
		download_buttons.show()
		download_old_version.hide()
	}
}

function fileDetails(action){
	if(action == 'show'){
		if(!file_details_is_open){
			file_details_is_open = true

			var file_id = file_details_open_id

			$.ajax({
				type: 'POST'
				,url: LIVE_SITE+'ajax/origami/file/details/'
				,cache: false
				,dataType: 'json'
				,data: {'file_id':file_id}
				,error: function(jqXHR, textStatus, errorThrown){
					console.log(jqXHR, textStatus, errorThrown)
				}
				,beforeSend: function(){
					
				}
				,success: function(data) {
					// console.log(data)
					if(data.response_code == 200){
						$('#fileDetails').html(data.message)
					}
					loadHooks()
				}
			});
		}
	}else if(action == 'hide'){
		file_details_is_open = false
		file_details_open_id = 0
		// empty this window and place loader
		$('#fileDetails').html('<div class="loader"></div>')
	}
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
		if(element.caret().start == 0){
			var last_tag = tags_array.pop()

			element.val(last_tag.slice(0,-1) +' '+ value)
			element.caret(last_tag.length-1,last_tag.length-1)

			$('#search_tags a').last().remove()

			tags_modified = true
		}
	}

	previous_caret_position = element.caret().start
	
	var value = element.val()
		,matched_tags = value.match(/\[([a-zA-Z0-9\s]+)\]/g)
		,value_with_no_tags = $.trim(value.replace(/\[([a-zA-Z0-9\s]+)\]/g, ''))

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


/**********************************************
	File version approvement/disapprovement
***********************************************/

function fileVersionApprove(element, file_id, version){
	return fileVersionChangeState(element, file_id, version, 'approve')
}

function fileVersionDisapprove(element, file_id, version){
	return fileVersionChangeState(element, file_id, version, 'disapprove')
}

function fileVersionChangeState(element, file_id, version, state){
	$.ajax({
		type: 'POST'
		,url: LIVE_SITE+'ajax/origami/file/version/?action='+state
		,cache: false
		,dataType: 'json'
		,data: {'file_id':file_id, 'version':version}
		,error: function(jqXHR, textStatus, errorThrown){
			console.log(jqXHR, textStatus, errorThrown)
		}
		,beforeSend: function(){

		}
		,success: function(data) {
			if(data.response_code == 200){
				$(element).parent().html(data.message)
			}
		}
	});

	return false
}