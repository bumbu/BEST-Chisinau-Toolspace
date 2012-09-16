Array.prototype.remove = function(from, to) {
	var rest = this.slice((to || from) + 1 || this.length);
	this.length = from < 0 ? this.length + from : from;
	return this.push.apply(this, rest);
};

/**********************
	Observer
***********************/

var topics = {};
$.Topic = function( id ) {
	var callbacks,
		topic = id && topics[ id ];
	if ( !topic ) {
		callbacks = jQuery.Callbacks();
		topic = {
			publish: callbacks.fire,
			subscribe: callbacks.add,
			unsubscribe: callbacks.remove
		};
		if ( id ) {
			topics[ id ] = topic;
		}
	}
	return topic;
};

/**********************
	Custom stuff
***********************/


// on DOM ready
$(function(){
	$(document).bind('drop dragover', function (e) {
		e.preventDefault();
	});

	$.Topic('domready').publish('')

	loadHooks()
	fileupload_hook()
})

var file_details_open_id = 0
	,file_details_is_open = false

function loadHooks(){
	// form submission
	$('body').on('click', '.submit', function(event){elementClick(event, this, '#form')})
	$('body').on('click', '.sorting', function(event){elementClick(event, this, '#form')})
	$('.change').on('click', function(event){elementClick(event, this)})
	$('body').on('click', '.change', function(event){elementClick(event, this)})

	// load file details only when asked for
	$('#fileDetails').on('show', function(){fileDetails('show')})
	$('#fileDetails').on('hide', function(){fileDetails('hide')})
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
	if(typeof(element.data('call')) !== 'undefined'){
		var call = element.data('call')
		if(typeof(window[call]) === 'function')
			window[call]()
	}
	if(typeof(element.data('activeclass')) !== 'undefined'){
		//if btn-group radio
		if(element.parent().data('toggle') == 'buttons-radio'){
			element.siblings().each(function(index, value){
				$value = $(value)
				if($value.data('activeclass')){
					$value.removeClass($value.data('activeclass'))
				}
			})
		}
		element.addClass(element.data('activeclass'))
	}
	if(element.data('is')){
		var params = element.data('params')
			,download_link = $('#download_link')
			,changeto = element.data('changeto')

		switch(element.data('is')){
			case 'extension':
				var version_id = element.closest('.tab-pane').attr('id').replace('v','')
					,nav_tab = $('.nav-tabs a[data-changeto="'+version_id+'"]')

					nav_tab.data('params', $.extend(nav_tab.data('params'), {extension: element.data('changeto')}))

					download_link.attr('href', $.url(download_link.attr('href'), true).param('extension', changeto).toString())
				break
			case 'version':
				$('#extension').val(params.extension)
				if(download_link.length > 0){
					// update download link url
					download_link.attr('href', $.url(download_link.attr('href'), true).param('version', changeto).param('extension', params.extension).toString())
				}
				break
			case 'add-file-button':
				$('#file_dropzone').addClass('hover').parent().addClass('display')
				$('#file').click()
				break
		}
	}

	if(typeof(submit_form) !== 'undefined')
		$(submit_form).submit()
}

function fileDetails(action){
	if(action == 'show'){
		if(!file_details_is_open){
			file_details_is_open = true

			var file_id = file_details_open_id

			$.ajax({
				type: 'GET'
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
	Alerts
***********************************************/
$.Topic('alert').subscribe(show_alert)

function show_alert(params){
	params.type = params.type || ''
	params.title = params.title || params.type
	params.text = params.text || ''

	var alert = '<div class="alert '+params.type+'"><a class="close" data-dismiss="alert" href="#">×</a><h4 class="alert-heading">'+params.title+'</h4>'+params.text+'</div>'
	$('#alerts').append($(alert))
}