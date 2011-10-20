<?php 

	$widget = $vars["entity"];
	$options = array();
	
	$types = get_registered_entity_types();
	
	$selected_options = $widget->getMetadata("types");
	if(empty($selected_options)){
		$selected_options = array(0);
	} elseif(!is_array($selected_options)){
		$selected_options = array($selected_options);
	}
	
	$owner = $widget->getOwnerEntity();
	
	if($owner instanceof ElggGroup){
		unset($types["group"]);
	}
	
	$options[elgg_echo("all")] = 0;
	foreach ($types as $type => $subtypes) {
		// @todo when using index table, can include result counts on each of these.
		if (is_array($subtypes) && count($subtypes)) {
			foreach ($subtypes as $subtype) {
				$option = elgg_echo("item:$type:$subtype");
				$value = "$type:$subtype";
				$options[$option] = $value; 
			}
		} else {
			$option = elgg_echo("item:$type");
			$value = "$type";
			$options[$option] = $value;
		}
	}	
	
	foreach($options as $option => $value){
		$checked = "";
		
		if(in_array($value, $selected_options, TRUE)){
			$checked = " checked='checked'";
		}
		
		$disabled = "";
		if(in_array(0, $selected_options, TRUE) && $value !== 0){
			$disabled = " disabled='disabled'";
		}

		$onclick = "";
		if($value === 0){
			$onclick = " onclick='widget_search_toggle_type_options(this);'";
		}
		
		echo "<label>";
		echo "<input class='input-checkboxes'" . $checked . $onclick . $disabled . " type='checkbox' name='params[types][]' value='" . $value . "' />";
		echo $option;
		echo "</label><br />";
	}
	echo elgg_view("input/hidden", array("internalname" => "params[types][]", "value" => 0));
	