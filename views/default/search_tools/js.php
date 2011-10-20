<?php
?>
//<script>

function widget_search_execute(widget_id, search_form){

	$results = $("#widget_search_results_" + widget_id); 
	
	$results.html('<?php echo elgg_view('ajax/loader',array('slashes' => true)); ?>');

	$search_form = $(search_form);
	
	$.ajax({
		type: 'POST',
		url: $search_form.attr("action"),
		data: $search_form.serialize(),
		success: function(data) {
			if (data) {
				$results.html(data);
			}
		}
	});
	
	return false;
}

function widget_search_toggle_type_options(elem){
	if($(elem).is(":checked")){
		$(elem).parent().parent().find("input[name='params[types][]']").attr("disabled", "disabled");
		$(elem).attr("disabled", "");
	} else {
		$(elem).parent().parent().find("input[name='params[types][]']").attr("disabled", "");
	}
}
