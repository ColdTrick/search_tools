<?php 

	global $CONFIG;

	$widget = $vars["entity"];
	
	$form_body = "<table class='widget-search-table'>";
	$form_body .= "<tr><td>";
	
		
	$form_body .= elgg_view("input/text", array("internalname" => "q", "value" => $query));
	
	$form_body .= "</td><td>";
	
	$form_body .= elgg_view("input/hidden", array("internalname" => "widget_guid", "value" => $widget->guid));
	$form_body .= elgg_view("input/submit", array("value" => elgg_echo("search")));
	
	$form_body .= "</td></tr>";
	$form_body .= "</table>";
	
	$form = elgg_view("input/form", array("body" => $form_body, "action" => $vars["url"] . "pg/widget_search_execute", "js" => "onsubmit=\"widget_search_execute('" . $widget->getGUID() . "', this);return false;\""));

	echo elgg_view("page_elements/contentwrapper", array("body" => $form));
	
	echo "<div id='widget_search_results_" . $widget->getGUID() . "'><div>";
