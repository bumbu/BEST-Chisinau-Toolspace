/**********************************************
	File upload processing

	List of used topics
	- domready				domready
	- image_box_reloaded	callback on image box reload
	- reload_image_box		command to reaload image box
	- alert					show alert message
	- upload_state
		state
			:started
			:progress
				progress
			:ended
				file
			:error
	- image_log
	- thumbnail_creation
		state
				file
			:started
			:success
				text
				thumbnail
			:failed
				text
***********************************************/

$.Topic('domready').subscribe(dragover_hook)
$.Topic('domready').subscribe(fileupload_hook)
$.Topic('domready').subscribe(thumbnail_action_hook)

$.Topic('upload_state').subscribe(file_dropzone_state)
$.Topic('upload_state').subscribe(block_submit_button)
$.Topic('upload_state').subscribe(progress_bar_state)
$.Topic('upload_state').subscribe(piecon_state)
$.Topic('upload_state').subscribe(create_thumbnail)

$.Topic('reload_image_box').subscribe(reload_image_box)

$.Topic('image_box_reloaded').subscribe(fileupload_hook)
$.Topic('image_box_reloaded').subscribe(thumbnail_action_hook)

$.Topic('image_log').subscribe(image_log)

$.Topic('thumbnail_creation').subscribe(thumbnail_creation)

$.Topic('save_image').subscribe(save_image)

function dragover_hook(){
	$(document).bind('dragover', function (e) {
		var drop_zone = $('#file_dropzone')
			,drop_zone_parent = drop_zone.parent()
			timeout = window.dropZoneTimeout;

		if (!timeout) {
			drop_zone.addClass('in')
			drop_zone_parent.addClass('display')
		} else {
			clearTimeout(timeout);
		}
		if (e.target === drop_zone[0] || $(e.target).closest('#file_dropzone').length > 0) {
			drop_zone.addClass('hover')
		} else {
			drop_zone.removeClass('hover');
		}
		window.dropZoneTimeout = setTimeout(function () {
			window.dropZoneTimeout = null;
			drop_zone.removeClass('in hover')
			drop_zone_parent.removeClass('display')
		}, 300);
	});
}

function fileupload_hook(){
	// file upload
	$('#file').fileupload({
		dataType: 'json'
		,dropZone: $('#file_dropzone')
		,url: LIVE_SITE+'ajax/origami/file/upload/'
		,maxChunkSize: 4000000
		,add: function (e, data) {
			var _this = this;
			$.getJSON(LIVE_SITE+'ajax/origami/file/resumeUpload/', {file: data.files[0].name}, function (file){
				data.uploadedBytes = file && file.size
				$.blueimp.fileupload.prototype.options.add.call(_this, e, data)
			});
		}
		,start: function(e, data){
			$.Topic('upload_state').publish({state:'started'})
		}
		,progressall: function (e, data){
			var progress = parseInt(data.loaded / data.total * 100, 10)
			$.Topic('upload_state').publish({state:'progress', 'progress': progress})			
		}
		,done: function (e, data){
			var file_name = ''
			$.each(data.result, function (index, file){
				file_name = file.name
			});
			
			$.Topic('upload_state').publish({state:'ended', file: file_name})
		}
		,fail: function(e, data){
			// if file allready uploaded
			if(data.errorThrown == 'uploadedBytes'){
				var file_name = ''
				$.each(data.files, function (index, file){
					file_name = file.name
				});
				
				$.Topic('upload_state').publish({state:'ended', file: file_name})
			}else{
				$.Topic('upload_state').publish({state:'error'})
				$.Topic('alert').publish({type: 'error', title: 'File not uploaded', text: 'Try one more time'})
				$.Topic('reload_image_box').publish('')
			}
		}
	});
}

function thumbnail_action_hook(){
	var $thumbnail = $('#thumbnail')
		,$thumbnail_action = $('#thumbnail_action')

	$thumbnail_action.find('button').click(function(e){
		var $button = $(this)
			,params = {file: $thumbnail_action.data('file')}

		if($button.hasClass('btn-success')){
			params['thumbnail'] = $thumbnail_action.data('thumbnail')
		}else if($button.hasClass('btn-danger')){
			// nothing
		}

		$.Topic('save_image').publish(params)

		$thumbnail.hide()
		$thumbnail_action.hide()
	})
}

function file_dropzone_state(result){
	switch(result.state){
		case 'started':
			$("#file_dropzone").css('display', 'block').parent().addClass('uploading')
			break
	}	
}

function block_submit_button(result){
	//TODO
}

function progress_bar_state(result){
	switch(result.state){
		case 'started':
			$('#initial_text').hide()
			$.Topic('image_log').publish('Uploading file')
			$('#progress_bar').show().find('.bar').css('width', progress + '%').html(progress + '%')
			break
		case 'ended':
			$('#progress_bar').hide()
			break
		case 'error':
			$('#progress_bar').hide()
			$.Topic('image_log').publish('Uploading file failed')
			break
		case 'progress':
			var progress = parseInt(result.progress)
			$('#progress_bar .bar').css('width', progress + '%').html(progress + '%')
			break
	}
}

function piecon_state(result){
	switch(result.state){
		case 'progress':
			Piecon.setProgress(parseInt(result.progress))
			break
		case 'ended':
			Piecon.reset()
			break
	}
}

function reload_image_box(params){
	$.ajax({
		type: 'GET'
		,url : LIVE_SITE+'ajax/origami/file/filesPartial/'
		,data: params
		,dataType: 'json'
		,success: function(data){
			$('#file_images').html(data.message)
			$.Topic('image_box_reloaded').publish('')
			
			var $extension = $('#extension')
			if($extension.val() == ''){
				// extract extension from file name
				$extension.val(params.file.split('.').pop())				
			}
		}
		,error: function(jqXHR, textStatus, errorThrown){
			console.log(jqXHR, textStatus, errorThrown)
			$.Topic('alert').publish({type: 'error', title: 'Something went wrong', text: 'Please, reload the page.'})
		}
	})
}

function create_thumbnail(result){
	if(result.state == 'ended'){
		$.ajax({
			type: 'POST'
			,url : LIVE_SITE+'ajax/origami/file/createThumb/'
			,data: {name: result.file}
			,dataType: 'json'
			,start: function(){
				$.Topic('thumbnail_creation').publish({state:'started'})
			}
			,success: function(data){
				if(parseInt(data.response_code) == 200){
					$.Topic('thumbnail_creation').publish({state:'success', file:result.file, thumbnail:data.message, text:data.response_message})
				}else{
					$.Topic('thumbnail_creation').publish({state:'failed', file:result.file, text:data.response_message})
				}
				
			}
			,error: function(jqXHR, textStatus, errorThrown){
				// console.log(jqXHR, textStatus, errorThrown)
				$.Topic('thumbnail_creation').publish({state:'failed', file:result.file, text: textStatus})
			}
		})
	}
}

function image_log(message){
	var image_log = $('#image_log')
	if(image_log.is(':empty'))
		image_log.html(message).show()
	else
		image_log.html(image_log.html()+'<br>'+message).show()
}

function thumbnail_creation(result){
	switch(result.state){
		case 'started':
			$.Topic('image_log').publish('Trying to create thumbnail')
			break
		case 'success':
			$.Topic('image_log').publish('Thumbnail was created')
			// show thumbnail
			$('#thumbnail').show().children('img').attr('src', TEMP_FOLDER + result.thumbnail)
			$('#thumbnail_action').show()
			// populate thumbnail_action with data
			$('#thumbnail_action').data('file', result.file).data('thumbnail', result.thumbnail)
			break
		case 'failed':
			$.Topic('image_log').publish('Thumbnail was not created')
			$.Topic('save_image').publish({file:result.file})
			break
	}
}

function save_image(result){
	var version = $('#version').val()
		,extension = $('#extension').val()
		,id = $('#id').val()
		,params = {'version':version, 'extension':extension, 'id':id}

	$.ajax({
		type: 'POST'
		,url : LIVE_SITE+'ajax/origami/file/addFile/'
		,data: $.extend(params, result)
		,dataType: 'json'
		,start: function(){
			$.Topic('image_log').publish('Saving file')
		}
		,success: function(data){			
			$.Topic('reload_image_box').publish(params)
		}
		,error: function(jqXHR, textStatus, errorThrown){
			$.Topic('image_log').publish(textStatus)
		}
	})
}