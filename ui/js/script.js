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
	loadHooks()
	// inp()

/*	$('#fileupload').fileupload({
		url: '/origami/file/upload/'
		,dataType: 'json'
		,formData: {file_id:0}
		,done: function (e, data) {
			$.each(data.result, function (index, file) {
				$('<p/>').text(file.name).appendTo(document.body);
			});
		}
	})*/
	
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
		hook_removing_tags()

		$("input#search").keyup(function(event){
			hook_search_input(this, event)
		}).keyup()

		$('#search_submit').click(function(event){
			pushTagsBackToInput()
		})
	}
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

function hook_search_input(element, event){
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
		,matched_tags = value.match(/\[([a-zA-Z\s]+)\]/g)
		,value_with_no_tags = $.trim(value.replace(/\[([a-zA-Z\s]+)\]/g, ''))

	if(matched_tags)
		for(var i = 0; i < matched_tags.length; i++){
			if(tags_array.indexOf(matched_tags[i]) == -1){
				// ad new tag
				tags_array.push(matched_tags[i])

				//create DOM element
				$('#search_tags').append('<a class="btn btn-mini">'+matched_tags[i].slice(1,-1)+' <i class="icon-remove"></i></a>')

				tags_modified = true
			}
		}

	if(value != value_with_no_tags){
		var new_caret_position = previous_caret_position - tags_array[tags_array.length-1].length

		element.val(value_with_no_tags)
		element.caret(new_caret_position, new_caret_position)
	}

	if(tags_modified){
		search_resize()
	}
}

function search_resize(){
	var input = $("#search")
		,tags = $("#search_tags")
		,input_padding = input.css("padding-left").replace("px", "")

	input.width(input.width() - tags.width() - 3 + Number(input_padding))
	input.css("padding-left", tags.width() + 3)
}

function hook_removing_tags(){
	$("#search_tags").on('click', 'i', function(event){
		event.preventDefault()

		var tag_text = "[" + $(this).parent().text().trim() + "]"
		tags_array.remove(tags_array.indexOf(tag_text))
		
		$(this).parent().remove()
		search_resize()
	});
}

function pushTagsBackToInput(){
	var val = $("input#search").val()
	$("input#search").val(val + tags_array.join(""))
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