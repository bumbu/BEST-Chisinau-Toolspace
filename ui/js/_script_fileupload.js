/**********************************************
	File upload processing

	List of used topics
	- domready				domready
	- image_box_reloaded	callback on image box reload
	- reload_image_box		command to reaload image box
	- alert					show alert message
	- upload_state
	- upload_progress
	- uploaded_file
***********************************************/

$.Topic('domready').subscribe(dragover_hook)
$.Topic('domready').subscribe(fileupload_hook)

function dragover_hook(){
	$(document).bind('dragover', function (e) {
		var dropZone = $('#file_dropzone'),
			timeout = window.dropZoneTimeout;
		if (!timeout) {
			dropZone.addClass('in');
		} else {
			clearTimeout(timeout);
		}
		if (e.target === dropZone[0]) {
			dropZone.addClass('hover');
		} else {
			dropZone.removeClass('hover');
		}
		window.dropZoneTimeout = setTimeout(function () {
			window.dropZoneTimeout = null;
			dropZone.removeClass('in hover');
		}, 100);
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
			$.Topic('upload_state').publish('started')
		}
		,progressall: function (e, data){
			var progress = parseInt(data.loaded / data.total * 100, 10)
			$.Topic('upload_progress').publish(progress)			
		}
		,done: function (e, data){
			var file_name = ''
			$.each(data.result, function (index, file){
				file_name = file.name
			});
			$.Topic('upload_state').publish('ended')
			$.Topic('uploaded_file').publish(file_name)
		}
		,fail: function(e, data){
			$.Topic('upload_state').publish('error')
		}
	});
}

$.Topic('upload_state').subscribe(block_submit_button)
$.Topic('upload_state').subscribe(progress_bar_state)
$.Topic('upload_progress').subscribe(progress_bar_state)

function block_submit_button(state){
	//TODO
}

function progress_bar_state(state){
	switch(state){
		case 'started':
			$('#progress_bar').show().find('.bar').css('width', progress + '%').html(progress + '%')
			break;
		case 'ended':
		case 'error':
			$('#progress_bar').hide()
			break;
		default:
			var progress = parseInt(progress)
				,progress = progress > 100 ? 100 : progress
				,progress = progress < 0 ? 0 : progress
			$('#progress_bar .bar').css('width', progress + '%').html(progress + '%')
			break;
	}
}


/************************************
OLD TRASH
*************************************/

// TODO: delete
function fileupload_hook2(){
	// file upload
	$('#file').fileupload({
		dataType: 'json'
		,url: LIVE_SITE+'ajax/origami/file/upload/'
		,maxChunkSize: 4000000
		,add: function (e, data) {
			var that = this;
			$.getJSON(LIVE_SITE+'ajax/origami/file/resumeUpload/', {file: data.files[0].name}, function (file){
				data.uploadedBytes = file && file.size
				$.blueimp.fileupload.prototype.options.add.call(that, e, data)
			});
		}
		,start: function(e, data){
			$('#fileupload_container').hide()
			$('#fileupload_text').hide()
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
		,fail: function(e, data){
			$('.btn-submit-form').removeAttr('disabled')
			$('#progress_bar').hide()
			
			$('#fileupload_text').show()			
			$('#fileupload_text span').html('was not')
			
			$('#fileupload_container').show()
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