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