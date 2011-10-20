<?php 

function search_tools_init(){
	if(is_plugin_enabled("search")){
		// extend CSS
		elgg_extend_view("css", "search_tools/css");
		elgg_extend_view("js/initialise_elgg", "search_tools/js");
		
		// search widget
		add_widget_type("search", elgg_echo("widgets:search:title"), elgg_echo("widgets:search:description"), "profile,dashboard,index,groups", true);
		register_page_handler("widget_search_execute", "widget_search_execute_page_handler");
		
		// user search
		register_plugin_hook('search', 'user', 'search_tools_search_users_hook');
		unregister_plugin_hook('search', 'user', 'search_users_hook'); // unregister default search behaviour

		// object search
		register_plugin_hook('search', 'object', 'search_tools_search_objects_hook');
		unregister_plugin_hook('search', 'object', 'search_objects_hook'); // unregister default search behaviour
	}
}


function widget_search_execute_page_handler(){
	$query = get_input("q");
	
	$widget_guid = get_input("widget_guid");
	if(!empty($widget_guid)){
		$widget = get_entity($widget_guid);
	}
	
	if(!empty($query) && !empty($widget)){
		
		$configured_types = $widget->getMetadata("types");
		if(empty($configured_types)){
			$configured_types = array();
		} elseif(!is_array($configured_types)){
			$configured_types = array($configured_types);
		}
		
		// $search_type == all || entities || trigger plugin hook
		$search_type = get_input('search_type', 'all');
		
		// @todo there is a bug in get_input that makes variables have slashes sometimes.
		// @todo is there an example query to demonstrate ^
		// XSS protection is more important that searching for HTML.
		$query = stripslashes(get_input('q', get_input('tag', '')));
		
		// @todo - create function for sanitization of strings for display in 1.8
		// encode <,>,&, quotes and characters above 127
		if (function_exists('mb_convert_encoding')) {
			$display_query = mb_convert_encoding($query, 'HTML-ENTITIES', 'UTF-8');
			$display_query = htmlspecialchars($display_query, ENT_QUOTES, 'UTF-8', false);
		} else {
			// we list mb_string as a requirement, why do we check if the function exists?
			$display_query = htmlentities($display_query, ENT_QUOTES, 'UTF-8', false);
		}
		
		// check that we have an actual query - bail early if none
		if (!$query) {
			$title = sprintf(elgg_echo('search:results'), "\"$display_query\"");
			
			$body  = elgg_view_title(elgg_echo('search:search_error'));
			$body .= elgg_view('page_elements/contentwrapper', array('body' => elgg_echo('search:no_query')));
		
			$layout = elgg_view_layout('two_column_left_sidebar', '', $body);
			page_draw($title, $layout);
		
			return;
		}
		
		// get limit and offset.  override if on search dashboard, where only 2
		// of each most recent entity types will be shown.
		$limit = ($search_type == 'all') ? 2 : get_input('limit', 10);
		$offset = ($search_type == 'all') ? 0 : get_input('offset', 0);
		
		$entity_type = get_input('entity_type', ELGG_ENTITIES_ANY_VALUE);
		$entity_subtype = get_input('entity_subtype', ELGG_ENTITIES_ANY_VALUE);
		$owner_guid = get_input('owner_guid', ELGG_ENTITIES_ANY_VALUE);
		if($owner_guid !== ELGG_ENTITIES_ANY_VALUE){
			$owner_guid = (int) $owner_guid ; 
		}
		
		$container_guid = get_input('container_guid', ELGG_ENTITIES_ANY_VALUE);
		if($container_guid !== ELGG_ENTITIES_ANY_VALUE){
			$container_guid = (int) $container_guid ; 
		}
		
		if($widget->getOwnerEntity() instanceof ElggGroup){
			$container_guid = $widget->getOwner();
		}
		
		$sort = get_input('sort');
		switch ($sort) {
			case 'relevance':
			case 'created':
			case 'updated':
			case 'action_on':
			case 'alpha':
				break;
		
			default:
				$sort = 'relevance';
				break;
		}
		
		$order = get_input('sort', 'desc');
		if ($order != 'asc' && $order != 'desc') {
			$order = 'desc';
		}
		
		// set up search params
		$params = array(
			'query' => $query,
			'offset' => $offset,
			'limit' => $limit,
			'sort' => $sort,
			'order' => $order,
			'search_type' => $search_type,
			'type' => $entity_type,
			'subtype' => $entity_subtype,
			'owner_guid' => $owner_guid,
			'container_guid' => $container_guid,
			'pagination' => ($search_type == 'all') ? FALSE : TRUE,
			'widget_search_guid' => $widget_guid
		);
		
		$types = get_registered_entity_types();
		
		$custom_types = trigger_plugin_hook('search_types', 'get_types', $params, array());
		
		// start the actual search
		$results_html = '';
		
		if ($search_type == 'all' || $search_type == 'entities') {
			// to pass the correct current search type to the views
			$current_params = $params;
			$current_params['search_type'] = 'entities';
		
			// foreach through types.
			// if a plugin returns FALSE for subtype ignore it.
			// if a plugin returns NULL or '' for subtype, pass to generic type search function.
			// if still NULL or '' or empty(array()) no results found. (== don't show??)
			foreach ($types as $type => $subtypes) {
				if ($search_type != 'all' && $entity_type != $type) {
					continue;
				}
				
				if (is_array($subtypes) && count($subtypes)) {
					foreach ($subtypes as $subtype) {
						
						if(!empty($configured_types) && !in_array("$type:$subtype", $configured_types)){
							continue;
						}
						
						// no need to search if we're not interested in these results
						// @todo when using index table, allow search to get full count.
						if ($search_type != 'all' && $entity_subtype != $subtype) {
							continue;
						}
						
						$current_params['subtype'] = $subtype;
						$current_params['type'] = $type;
		
						$results = trigger_plugin_hook('search', "$type:$subtype", $current_params, NULL);
						
						
						if ($results === FALSE) {
							// someone is saying not to display these types in searches.
							continue;
						} elseif (is_array($results) && !count($results)) {
							// no results, but results searched in hook.
						} elseif (!$results) {
							// no results and not hooked.  use default type search.
							// don't change the params here, since it's really a different subtype.
							// Will be passed to elgg_get_entities().
							$results = trigger_plugin_hook('search', $type, $current_params, array());
							
						}
							
						if (is_array($results['entities']) && $results['count']) {
							if ($view = search_get_search_view($current_params, 'listing')) {
								$results_html .= elgg_view($view, array('results' => $results, 'params' => $current_params));
							}
						}
					}
				}
		
				
				if(!empty($configured_types) && !in_array("$type", $configured_types)){
					continue;
				}
				
				// pull in default type entities with no subtypes
				$current_params['type'] = $type;
				$current_params['subtype'] = ELGG_ENTITIES_NO_VALUE;
		
				$results = trigger_plugin_hook('search', $type, $current_params, array());
				if ($results === FALSE) {
					// someone is saying not to display these types in searches.
					continue;
				}
				
				if (is_array($results['entities']) && $results['count']) {
					if ($view = search_get_search_view($current_params, 'listing')) {
						$results_html .= elgg_view($view, array('results' => $results, 'params' => $current_params));
					}
				}
				
			}
		}
		
		// call custom searches
		if ($search_type != 'entities' || $search_type == 'all') {
			if (is_array($custom_types)) {
				foreach ($custom_types as $type) {
					if($type !== "tags"){
						// only support tag search (as tag search can listen to container_guid)
						continue;
					}
					if($widget->tag_filter){
						// only support tag search if not filtered in object search
						continue;
					}
					
					if ($search_type != 'all' && $search_type != $type) {
						continue;
					}
					
					if(!empty($configured_types)){
						foreach($configured_types as $configured_type_combination){
							list($configured_type, $configured_subtype) = explode(":", $configured_type_combination);
							
							if(empty($configured_subtype)){
								unset($configured_subtype);
							}
							
							if(!isset($params["type_subtype_pairs"])){
								$params["type_subtype_pairs"] = array();
							}
							
							if(!empty($configured_type)){
								if($configured_type === "object"){
									if(!isset($params["type_subtype_pairs"]["object"])){
										$params["type_subtype_pairs"]["object"] = array();
									}
									$params["type_subtype_pairs"]["object"][] = $configured_subtype;
								} else {
									$params["type_subtype_pairs"][$configured_type] = $configured_subtype;
								}
							}
						}
					}
					
					$current_params = $params;
					$current_params['search_type'] = $type;
					// custom search types have no subtype.
					unset($current_params['subtype']);
		
					$results = trigger_plugin_hook('search', $type, $current_params, array());
		
					if ($results === FALSE) {
						// someone is saying not to display these types in searches.
						continue;
					}
		
					if (is_array($results['entities']) && $results['count']) {
						if ($view = search_get_search_view($current_params, 'listing')) {
							$results_html .= elgg_view($view, array('results' => $results, 'params' => $current_params));
						}
					}
				}
			}
		}
		
		// highlight search terms
		$searched_words = search_remove_ignored_words($display_query, 'array');
		$highlighted_query = search_highlight_words($searched_words, $display_query);
	}
	
	if (!$results_html) {
		$body = elgg_view('page_elements/contentwrapper', array('body' => elgg_echo('search:no_results')));
	} else {
		$body = $results_html;
	}
	
	echo $body;
	
	exit();
}

function search_tools_search_objects_hook($hook, $type, $value, $params) {
	global $CONFIG;

	$join = "JOIN {$CONFIG->dbprefix}objects_entity oe ON e.guid = oe.guid";
	$params['joins'] = array($join);
	$fields = array('title', 'description');
	
	$where = search_get_where_sql('oe', $fields, $params, FALSE);

	$params['wheres'] = array($where);
	$params['count'] = TRUE;
	
	if($params["subtype"] === "page"){
		$params["subtype"] = array("page", "page_top");	
	}
	
	// extra filter for search from widget
	$widget_guid = (int) get_input("widget_search_guid", $params["widget_search_guid"]);
	
	if(!empty($widget_guid)){
		$widget = get_entity($widget_guid);
		if($widget instanceof ElggWidget){
			if($widget->tag_filter){
				$tags = string_to_tag_array($widget->tag_filter);

				$filtered_tags = array();
				foreach($tags as $tag){
					$clean_tag = sanitize_string($tag);
					if(!empty($clean_tag)){
						$filtered_tags[] = $clean_tag;
					}
				}
				
				if(!empty($filtered_tags)){
					// add extra search criteria
					$params["metadata_names"] = array("tags");
					$params["metadata_values"] = $filtered_tags;
				}
			}
		}
	}
	
	$count = elgg_get_entities_from_metadata($params);
	
	// no need to continue if nothing here.
	if (!$count) {
		return array('entities' => array(), 'count' => $count);
	}
	
	$params['count'] = FALSE;
	$entities = elgg_get_entities_from_metadata($params);

	// add the volatile data for why these entities have been returned.
	foreach ($entities as $entity) {
		$title = search_get_highlighted_relevant_substrings($entity->title, $params['query']);
		$entity->setVolatileData('search_matched_title', $title);

		$desc = search_get_highlighted_relevant_substrings($entity->description, $params['query']);
		$entity->setVolatileData('search_matched_description', $desc);
	}
	
	return array(
		'entities' => $entities,
		'count' => $count,
	);
}

function search_tools_search_users_hook($hook, $type, $value, $params) {
	global $CONFIG;

	if(isset($params["container_guid"])){
		$entity = get_entity($params["container_guid"]);
	}
	
	if($entity instanceof ElggGroup) {
		// check for group membership relation
		$params["relationship"] = "member";
		$params["relationship_guid"] = $params["container_guid"];
		$params["inverse_relationship"] = TRUE;
	} else {
		$params["relationship"] = "member_of_site";
		$params["relationship_guid"] = $CONFIG->site_guid;
		$params["inverse_relationship"] = TRUE;
	}
	
	unset($params["container_guid"]); // no need for this
		
	$query = sanitise_string($params['query']);

	$join = "JOIN {$CONFIG->dbprefix}users_entity ue ON e.guid = ue.guid";
	$params['joins'] = array($join);

	$fields = array('username', 'name');
	$where = search_get_where_sql('ue', $fields, $params, FALSE);
	
	$params['wheres'] = array($where);

	// override subtype -- All users should be returned regardless of subtype.
	$params['subtype'] = ELGG_ENTITIES_ANY_VALUE;

	$params['count'] = TRUE;
	$count = elgg_get_entities_from_relationship($params);

	// no need to continue if nothing here.
	if (!$count) {
		return array('entities' => array(), 'count' => $count);
	}
	
	$params['count'] = FALSE;
	$entities = elgg_get_entities_from_relationship($params);

	// add the volatile data for why these entities have been returned.
	foreach ($entities as $entity) {
		$username = search_get_highlighted_relevant_substrings($entity->username, $query);
		$entity->setVolatileData('search_matched_title', $username);

		$name = search_get_highlighted_relevant_substrings($entity->name, $query);
		$entity->setVolatileData('search_matched_description', $name);
	}

	return array(
		'entities' => $entities,
		'count' => $count,
	);
	
}

register_elgg_event_handler("init", "system", "search_tools_init");