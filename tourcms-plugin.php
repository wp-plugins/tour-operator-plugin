<?php
	/*
	Plugin Name: TourCMS
	Plugin URI: http://www.tourcms.com/support/webdesign/wordpress/
	Description: Integrate WordPress with TourCMS to aid creating specialist Tour, Activity and Accommodation Operator websites.
	Version: 1.0.9
	Author: TourCMS
	Author URI: http://www.tourcms.com
	*/
	
	// Create custom post types and taxonomies
	add_action( 'init', 'tourcms_init' );
	// Check/refresh cache
	add_action('template_redirect', 'tourcms_wp_refresh_cache');
	
	// Register settings
	add_action('admin_init', 'tourcms_wp_register');
	
	// Add a config menu to the Admin area
	add_action('admin_menu', 'tourcms_wp_adminmenu');
	
	// Save post
	add_action( 'save_post', 'tourcms_wp_save_tour', 1, 2);
	
	// Add any standard booking engines
	add_action('tourcms_wp_book', 'tourcms_wp_dobook');
	add_action('tourcms_wp_price', 'tourcms_wp_doprice');
	
	// Add a "Settings" link to the menu
	//add_filter( 'plugin_row_meta', 'set_plugin_meta', 10, 2 );
	$plugin = plugin_basename(__FILE__); 
	add_filter("plugin_action_links_$plugin", 'tourcms_wp_plugin_settings_link' );

	// Add settings link on plugin page
	function tourcms_wp_plugin_settings_link($links) { 
	  $settings_link = '<a href="options-general.php?page=tourcms_wp">Settings</a>'; 
	  array_push($links, $settings_link); 
	  return $links; 
	}
	
	function tourcms_init() {
		$allow_non_tourcms = get_option('tourcms_wp_allow_non_tourcms');
		
		if ( !is_admin() )
			wp_enqueue_script('jquery');
		
		if($allow_non_tourcms == 1) {
			register_post_type( 'tour',
				array(
					'label' => 'Tours/Hotels',
					'singular_label' => 'Tour/Hotel',
					'labels' => array("add_new_item" => "New Tour/Hotel", "edit_item" => "Edit Tour/Hotel", "view_item" => "View Tour/Hotel", "search_items" => "Search Tours/Hotels", "not_found" => "No Tours/Hotels found", "not_found_in_trash" => "No Tours/Hotels found in Trash"),
					'rewrite' => array("slug" => "tours"),
					'supports' => array('page-attributes', 'title', 'editor', 'author', 'excerpt', 'thumbnail', 'custom-fields'),
					'menu_position' => 20,
					'show_in_nav_menus' => true,
					'public' => true
				)
			);
		} else {
			register_post_type( 'tour',
				array(
					'label' => 'Tours/Hotels',
					'singular_label' => 'Tour/Hotel',
					'labels' => array("add_new_item" => "New Tour/Hotel", "edit_item" => "Edit Tour/Hotel", "view_item" => "View Tour/Hotel", "search_items" => "Search Tours/Hotels", "not_found" => "No Tours/Hotels found", "not_found_in_trash" => "No Tours/Hotels found in Trash"),
					'rewrite' => array("slug" => "tours"),
					'supports' => array('page-attributes', 'title', 'editor', 'author', 'excerpt', 'thumbnail'),
					'menu_position' => 20,
					'show_in_nav_menus' => true,
					'public' => true
				)
			);
		}
		
		register_taxonomy('product-type', array('tour'), array(
		  'label' => _x( 'Product types', 'taxonomy general name' ),
		  'singular_label' => _x( 'Product type', 'taxonomy singular name' ),
		  'public' => true,
		  'query_var' => true,
		  'rewrite' => array( 'slug' => 'tours-by-type' )
		));
		
		register_taxonomy('location', array('tour'), array(
		  'hierarchical' => true,
		  'label' => _x( 'Locations', 'taxonomy general name' ),
		  'singular_label' => _x( 'Location', 'taxonomy singular name' ),
		  'public' => true,
		  'query_var' => true,
		  'rewrite' => array( 'slug' => 'tours-by-location' )
		));
	}
	
	function set_plugin_meta($links, $file) {
	 $plugin = 'tourcms_wp';
		return array_merge(
				$links,
				array( sprintf( '<a href="options-general.php?page=%s">%s</a>', $plugin, __('Settings') ) )
			);
	 
		return $links;
	}
	
	// Admin menu page details
	function tourcms_wp_adminmenu() {
		add_submenu_page('options-general.php', 'TourCMS Plugin', 'TourCMS Plugin', 8, 'tourcms_wp', 'tourcms_wp_optionspage');	
	}
	
	// Whitelist options
	function tourcms_wp_register() {
		register_setting('tourcms_wp_settings', 'tourcms_wp_marketplace', 'intval');
		register_setting('tourcms_wp_settings', 'tourcms_wp_channel', 'intval'); 
		register_setting('tourcms_wp_settings', 'tourcms_wp_apikey');		
		register_setting('tourcms_wp_settings', 'tourcms_wp_bookstyle'); 
		register_setting('tourcms_wp_settings', 'tourcms_wp_bookheight', 'intval'); 
		register_setting('tourcms_wp_settings', 'tourcms_wp_bookwidth', 'intval'); 
		register_setting('tourcms_wp_settings', 'tourcms_wp_bookqs'); 
		register_setting('tourcms_wp_settings', 'tourcms_wp_booktext'); 
		register_setting('tourcms_wp_settings', 'tourcms_wp_update_frequency');
		register_setting('tourcms_wp_settings', 'tourcms_wp_allow_non_tourcms','intval');
		register_setting('tourcms_wp_settings', 'tourcms_wp_vidheight', 'intval'); 
		register_setting('tourcms_wp_settings', 'tourcms_wp_vidwidth', 'intval'); 
		register_setting('tourcms_wp_settings', 'tourcms_wp_vidresponsive'); 
		
		// Add custom meta box
		if ( function_exists( 'add_meta_box' ) ) {
			add_meta_box( 'tourcms_wp', 'TourCMS', 'tourcms_tour_edit' , 'tour', 'advanced', 'high' );
		}
	}
	
	
	// When the user is editing a Tour/Hotel we will display a box to let them select a TourCMS product to
	// link to, if this Tour/Hotel has been edited previously we'll also show cached TourCMS data
	function tourcms_tour_edit() {
				global $post;
				$marketplace_account_id = get_option('tourcms_wp_marketplace');
				$channel_id = get_option('tourcms_wp_channel');
				$api_private_key = get_option('tourcms_wp_apikey');
				$allow_non_tourcms = get_option('tourcms_wp_allow_non_tourcms');
				
				wp_nonce_field( 'tourcms_wp', 'tourcms_wp_wpnonce', false, true );
				
				if($marketplace_account_id===false || $channel_id===false || $api_private_key===false) 
					$configured = false;
				else
					$configured = true;
		
				
				// Output if allowed
				if ( $configured ) { 
					require_once 'tourcms.php';
					$tourcms = new TourCMS($marketplace_account_id, $api_private_key, 'simplexml');
					$results = $tourcms->list_tours($channel_id);
					$curval = get_post_meta( $post->ID, 'tourcms_wp_tourid', true );
					?>	

							<div class="form-field form-required">
								
								<?php
									if($results->error!="OK") {
										print "<p>Unable to link this Tour/Hotel with a product in TourCMS at this time, the following error message was returned:</p>";
										print "<p>".$results->error."</p>";
										print '<p>You can find <a href="http://www.tourcms.com/support/api/mp/error_messages.php" target="_blank">explanations of these error messages</a>, view the <a href="http://www.tourcms.com/support/webdesign/wordpress/installation.php" target="_blank">plugin installation instructions</a> or <a href="http://www.tourcms.com/company/contact.php" target="_blank">contact us</a> if you need some help.</p>';
									} else {
									// Plain text field
									echo '<p>&nbsp;</p><label for="tourcms_wp_tourid">Tour</label>';
									?>
									<select name="tourcms_wp_tourid">
										<!--option value="0">Do not associate with a TourCMS Tour/Hotel</option-->
										<?php
											if($allow_non_tourcms == 1) {
												print '<option value="0">Do not associate with a TourCMS Tour/Hotel</option>';
											} 
										
											foreach($results->tour as $tour) {
												print '<option value="'.$tour->tour_id.'"';
												if($tour->tour_id==$curval)
													print ' selected="selected"';
												print '>'.$tour->tour_name.'</option>';
											}
										?>
									</select>
									<?php if($curval>0) : ?>
									<p><?php 
									(get_option('tourcms_wp_update_frequency')=="") ? $tourcms_wp_update_frequency = 14400 : $tourcms_wp_update_frequency = intval(get_option('tourcms_wp_update_frequency'));
									
									if($tourcms_wp_update_frequency>1) {
										$hours = $tourcms_wp_update_frequency / 3600;
										if($hours > 1)
											$hours = $hours." hours";
										else
											$hours = "hour";
										echo "The following data is refreshed from TourCMS each time you save this Tour/Hotel plus automatically every $hours.";
									} else {
										echo "The following data is refreshed from TourCMS each time you save this Tour/Hotel.";
									}
									?><br /></p>
									<table class="widefat">
										<thead>
											<tr>
												<th style="width: 175px;">Field</th>
												<th>Value</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td class="row-title" title="[last_updated]">Last updated</td>
												<td class="desc" style="overflow: hidden"><?php 
														$last_updated = get_post_meta( $post->ID, 'tourcms_wp_last_updated', true ); 
														
														$time_since_update = time() - $last_updated;
														
														echo tourcms_wp_convtime($time_since_update)." ago";
														
														//echo date("r", $last_updated);
														

													?></td>
											</tr>
											
											<tr class="alternate">
												<td class="row-title" title="[tour_name]">Tour name</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_tour_name', true ); ?></td>
											</tr>
											<tr>
												<td class="row-title" title="[tour_id]">Tour ID</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_tour_id', true ); ?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[tour_code]">Tour code</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_tour_code', true ); ?></td>
											</tr>
											<tr>
												<td class="row-title">Priority</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_priority', true ); ?> (<?php echo get_post_meta( $post->ID, 'tourcms_wp_priority_num', true ); ?>)</td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[has_sale]">On sale?</td>
												<td class="desc"><?php 
													if((int)get_post_meta( $post->ID, 'tourcms_wp_has_sale', true )==1) {
														echo "Yes (1) - ";
														$months = array("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec");
														$monthstr = "";
														foreach($months as $month) {
															if((int)get_post_meta( $post->ID, 'tourcms_wp_has_sale_'.$month, true )==1) {
																$monthstr .= ucwords($month).", ";
															}
														}
														print substr($monthstr, 0, strlen($monthstr)-2).".";
													} else
														echo "No (0)";
													 ?></td>
											</tr>
											<tr>
												<td class="row-title" title="[book_url]">Book URL</td>
												<td class="desc" style="overflow: hidden;"><?php 
														$book_url = get_post_meta( $post->ID, 'tourcms_wp_book_url', true ); 
														if(strlen($book_url)>43) {
															$book_url = substr($book_url, 0, 40)."...";
															echo '<a href="'.get_post_meta( $post->ID, 'tourcms_wp_book_url', true ).'" target="_blank" title="'.get_post_meta( $post->ID, 'tourcms_wp_book_url', true ).'">'.$book_url.'</a>';
														} else 
															echo '<a href="'.get_post_meta( $post->ID, 'tourcms_wp_book_url', true ).'" target="_blank" title="'.get_post_meta( $post->ID, 'tourcms_wp_book_url', true ).'">'.get_post_meta( $post->ID, 'tourcms_wp_book_url', true ).'</a>';
													?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[from_price_display]">From price (display)</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_from_price_display', true ); ?></td>
											</tr>
											<tr>
												<td class="row-title" title="[from_price]">From price</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_from_price', true ); ?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[sale_currency]">Sale currency</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_sale_currency', true ); ?></td>
											</tr>
											<tr>
												<td class="row-title" title="[location]">Primary location</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_location', true ); ?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[geocode_start]">Geocode start (Long, Lat)</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_geocode_start', true ); ?> <a href="http://maps.google.com/?q=<?php echo get_post_meta( $post->ID, 'tourcms_wp_geocode_start', true ); ?>" target="_blank" title="View on Google Maps">&raquo;</a></td>
											</tr>
											<tr>
												<td class="row-title" title="[geocode_end]">Geocode end (Long, Lat)</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_geocode_end', true ); ?> <a href="http://maps.google.com/?q=<?php echo get_post_meta( $post->ID, 'tourcms_wp_geocode_end', true ); ?>" target="_blank" title="View on Google Maps">&raquo;</a></td>
											</tr>
											<tr class="alternate">
												<td class="row-title">All geocodes</td>
												<td class="desc"><?php 
												
													$tourcms_wp_geocode_all = get_post_meta( $post->ID, 'tourcms_wp_geocode_all', true );
													
													
													if($tourcms_wp_geocode_all != '') {
													
														$points = json_decode($tourcms_wp_geocode_all);
														
														
														foreach($points as $key => $point) {
															print "[" . $key . "] " 
																. $point->label
																. " <a href='http://maps.google.com/?q="
																. $point->geocode
																. "' target='_blank' title='View on Google Maps'>&raquo;</a>"
																. "<br />";
																
														}
												
													}
												?></td>
											</tr>
											<tr>
												<td class="row-title" title="[duration_desc]">Duration description</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_duration_desc', true ); ?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[available]">Available</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_available', true ); ?></td>
											</tr>
											<tr>
												<td class="row-title">Images</td>
												<td class="desc"><?php 
													for($i=0; $i<=10; $i++) {
														$img_src = get_post_meta( $post->ID, 'tourcms_wp_image_url_'.$i, true );
														if($img_src != "") {
														?>
														
														<img src="<?php echo $img_src;  ?>" title="<?php echo get_post_meta( $post->ID, 'tourcms_wp_image_desc_'.$i, true ); ?>" style="height: 100px;" />
														<?php
														}
													}
												?></td>
											</tr>
											
											<tr class="alternate">
												<td class="row-title" title="[vid_embed]">Video</td>
												<td class="desc">
													<?php
														$vid_url = get_post_meta( $post->ID, 'tourcms_wp_video_url_0', true ); 
														if(!empty($vid_url)) {
															?>
															<a href="<?php echo get_post_meta( $post->ID, 'tourcms_wp_video_url_0', true ); ?>" target="_blank"><?php echo get_post_meta( $post->ID, 'tourcms_wp_video_url_0', true ); ?></a>
															<?php
														}
													?>
												</td>
											</tr>
											
											<tr>
												<td class="row-title" title="[document_link]">Document</td>
												<td class="desc"><?php
													$vid_url = get_post_meta( $post->ID, 'tourcms_wp_document_url_0', true ); 
													if(!empty($vid_url)) {
														?>
														<a href="<?php echo get_post_meta( $post->ID, 'tourcms_wp_document_url_0', true ); ?>" target="_blank"><?php echo get_post_meta( $post->ID, 'tourcms_wp_document_desc_0', true ); ?></a>
														<?php
													}
												?></td>
											</tr>
											
											<tr class="alternate">
												<td class="row-title" title="[summary]">Summary</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_summary', true ); ?></td>
											</tr>
											
											<tr>
												<td class="row-title" title="[essential]">Essential</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_essential', true ); ?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[rest]">Restrictions</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_rest', true ); ?></td>
											</tr>
											
											<tr>
												<td class="row-title" title="[pick]">Pick up / Drop off</td>
												<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'tourcms_wp_pick', true ))); ?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[inc]">Includes</td>
												<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'tourcms_wp_inc', true ))); ?></td>
											</tr>
											<tr>
												<td class="row-title" title="[ex]">Excludes</td>
												<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'tourcms_wp_ex', true ))); ?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[extras]">Extras / upgrades</td>
												<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'tourcms_wp_extras', true ))); ?></td>
											</tr>
											<tr>
												<td class="row-title" title="[itinerary]">Itinerary</td>
												<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'tourcms_wp_itinerary', true ))); ?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[exp]">Experience</td>
												<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'tourcms_wp_exp', true ))); ?></td>
											</tr>
											<tr>
												<td class="row-title" title="[redeem]">Redemption Instructions</td>
												<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'tourcms_wp_redeem', true ))); ?></td>
											</tr>
											<tr class="alternate">
												<td class="row-title" title="[shortdesc]">Short Description</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_shortdesc', true ); ?></td>
											</tr>
											
											<tr>
												<td class="row-title" title="[longdesc]">Long Description</td>
												<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'tourcms_wp_longdesc', true ))); ?></td>
											</tr>
											
											<tr class="alternate">
												<td class="row-title">Suitable for solo</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_suitable_for_solo', true ); ?></td>
											</tr>
											
											<tr>
												<td class="row-title">Suitable for couples</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_suitable_for_couples', true ); ?></td>
											</tr>
											
											<tr class="alternate">
												<td class="row-title">Suitable for children</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_suitable_for_children', true ); ?></td>
											</tr>
											
											<tr>
												<td class="row-title">Suitable for groups</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_suitable_for_groups', true ); ?></td>
											</tr>
											
											<tr class="alternate">
												<td class="row-title">Suitable for business</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_suitable_for_business', true ); ?></td>
											</tr>
											
											<tr>
												<td class="row-title">Suitable for wheelchairs</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_suitable_for_wheelchairs', true ); ?></td>
											</tr>
											
											<tr class="alternate">
												<td class="row-title">Languages spoken</td>
												<td class="desc"><?php echo get_post_meta( $post->ID, 'tourcms_wp_languages_spoken', true ); ?></td>
											</tr>
											
											
											<tr>
												<td class="row-title">
													Alternative tours
												</td>
												<td>
													<?php
													$at_xml = get_post_meta( $post->ID, 'tourcms_wp_alternative_tours', true);
													if($at_xml != '') {
													$alternative_tours = simplexml_load_string($at_xml);
													
													foreach($alternative_tours->tour as $alternative_tour) {
														?>
<a href="<?php echo $alternative_tour->tour_url; ?>" target="_blank"><?php echo $alternative_tour->tour_name_long; ?></a> (<?php echo $alternative_tour->tour_id; ?>)<br />
														<?php
													} } ?>
												</td>
											</tr>
										</tbody>
									</table>
									<?php else : ?>
									<p>Additional fields will be displayed here once you have saved this Tour/Hotel.</p>
									<?php endif ?>
									<?php
									}
							?>
									</div>
					<?php
				} else { ?>
						<div class="form-field form-required">
						<p>You must configure the <a href="options-general.php?page=tourcms_wp">TourCMS Plugin Settings</a> before you can link this Tour/Hotel to a product.</p>
						</div>
					<?php
				} ?>


		<?php
	}
	
	// Save custom meta when page is saved
	function tourcms_wp_save_tour( $post_id, $post ) {		
		// Check nonce and permissions
		if (!wp_verify_nonce( $_POST[ 'tourcms_wp_wpnonce' ], 'tourcms_wp'))
			return;
		if (!current_user_can( 'edit_post', $post_id ))
			return;
		if ($post->post_type != 'page' && $post->post_type != 'tour')
			return;
		
		// Save Tour ID
		$tour_id = intval($_POST['tourcms_wp_tourid']);		
		tourcms_wp_refresh_info($post_id, $tour_id);
	}
	
	// Check cache freshness, update if expired
	function tourcms_wp_refresh_cache() {
		if(is_single() && get_query_var('post_type') == 'tour') {
			// Post details
			global $post;
			$last_updated = get_post_meta( $post->ID, 'tourcms_wp_last_updated', true );
			$tour_id = get_post_meta( $post->ID, 'tourcms_wp_tourid', true );
			// Cache update frequency
			(get_option('tourcms_wp_update_frequency')=="") ? $tourcms_wp_update_frequency = 14400 : $tourcms_wp_update_frequency = intval(get_option('tourcms_wp_update_frequency'));
			// Calculate next update time
			$next_update = (int)$last_updated + (int)$tourcms_wp_update_frequency;
			
			// Only update if cache is expired
			if(($tourcms_wp_update_frequency!=-1) && ($next_update <= time())) 
				tourcms_wp_refresh_info($post->ID, $tour_id);
		}
	}
	
	// Updates TourCMS information on a particular Tour/Hotel, called either when
	// editing in WordPress or when being viewed with a stale cache
	function tourcms_wp_refresh_info($post_id, $tour_id) {
			
			update_post_meta( $post_id, 'tourcms_wp_tourid', $tour_id);
			
			// Load TourCMS plugin settings
			$marketplace_account_id = get_option('tourcms_wp_marketplace');
			$channel_id = get_option('tourcms_wp_channel');
			$api_private_key = get_option('tourcms_wp_apikey');
			if($marketplace_account_id===false || $channel_id===false || $api_private_key===false) 
				$configured = false;
			else
				$configured = true;
				
			if($configured) {
				
				// Query API
				require_once 'tourcms.php';
				$tourcms = new TourCMS($marketplace_account_id, $api_private_key, 'simplexml');
				$results = $tourcms->show_tour($tour_id, $channel_id);
				
				
				// If there's any sort of error, return
				if($results->error != "OK")
					return;
	
				// Update main fields
				$tour = $results->tour;
				
				// Mandatory fields
				update_post_meta( $post_id, 'tourcms_wp_last_updated', time());
				update_post_meta( $post_id, 'tourcms_wp_book_url', (string)$tour->book_url);
				update_post_meta( $post_id, 'tourcms_wp_from_price', (string)$tour->from_price);
				update_post_meta( $post_id, 'tourcms_wp_from_price_display', (string)$tour->from_price_display);
				update_post_meta( $post_id, 'tourcms_wp_sale_currency', (string)$tour->sale_currency);
				update_post_meta( $post_id, 'tourcms_wp_geocode_start', (string)$tour->geocode_start);
				update_post_meta( $post_id, 'tourcms_wp_geocode_end', (string)$tour->geocode_end);
				
				// All geocode points
				$points[] = array(
					'geocode' => (string)$tour->geocode_start,
					'can_start_end_here' => 1,
					'label' =>  __( 'Start', 'tourcms_wp' )
				);
				
				if(!empty($tour->geocode_midpoints)) {
					foreach($tour->geocode_midpoints->midpoint as $point) {
						$points[] = array(
							'geocode' => (string)$point->geocode,
							'can_start_end_here' => (string)$point->can_start_end_here,
							'label' => (string)$point->label
						);
					}
				}
					
				$points[] = array(
					'geocode' => (string)$tour->geocode_end,
					'can_start_end_here' => 1,
					'label' =>  __( 'End', 'tourcms_wp' )
				);
				
				update_post_meta( $post_id, 'tourcms_wp_geocode_all', json_encode($points));	
				// End all geocode points
				
				update_post_meta( $post_id, 'tourcms_wp_duration_desc', (string)$tour->duration_desc);
				update_post_meta( $post_id, 'tourcms_wp_available', (string)$tour->available);	
				update_post_meta( $post_id, 'tourcms_wp_has_sale', (string)$tour->has_sale);
				update_post_meta( $post_id, 'tourcms_wp_tour_id', (int)$tour->tour_id);	
				update_post_meta( $post_id, 'tourcms_wp_tour_name', (string)$tour->tour_name_long);	
				update_post_meta( $post_id, 'tourcms_wp_location', (string)$tour->location);	
				update_post_meta( $post_id, 'tourcms_wp_summary', (string)$tour->summary);	
				update_post_meta( $post_id, 'tourcms_wp_shortdesc', (string)$tour->shortdesc);	
				update_post_meta( $post_id, 'tourcms_wp_priority', (string)$tour->priority);	
				switch ((string)$tour->priority) {
				    case "HIGH":
				        update_post_meta( $post_id, 'tourcms_wp_priority_num', "A");
				        break;
				    case "LOW":
				        update_post_meta( $post_id, 'tourcms_wp_priority_num', "C");
				        break;
				    default:
				    	// MEDIUM
				        update_post_meta( $post_id, 'tourcms_wp_priority_num', "B");
				        break;
				}
				
				
				update_post_meta( $post_id, 'tourcms_wp_has_sale_jan', (string)$tour->has_sale_jan);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_feb', (string)$tour->has_sale_feb);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_mar', (string)$tour->has_sale_mar);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_apr', (string)$tour->has_sale_apr);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_may', (string)$tour->has_sale_may);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_jun', (string)$tour->has_sale_jun);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_jul', (string)$tour->has_sale_jul);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_aug', (string)$tour->has_sale_aug);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_sep', (string)$tour->has_sale_sep);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_oct', (string)$tour->has_sale_oct);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_nov', (string)$tour->has_sale_nov);
				update_post_meta( $post_id, 'tourcms_wp_has_sale_dec', (string)$tour->has_sale_dec);
				
				// Number only fields
				update_post_meta( $post_id, 'tourcms_wp_grade', (string)$tour->grade);
				update_post_meta( $post_id, 'tourcms_wp_accomrating', (string)$tour->accomrating);
				update_post_meta( $post_id, 'tourcms_wp_product_type', (string)$tour->product_type);
				update_post_meta( $post_id, 'tourcms_wp_tourleader_type', (string)$tour->tourleader_type);
				
				// Suitable for
				update_post_meta( $post_id, 'tourcms_wp_suitable_for_solo', (string)$tour->suitable_for_solo);
				update_post_meta( $post_id, 'tourcms_wp_suitable_for_couples', (string)$tour->suitable_for_couples);
				update_post_meta( $post_id, 'tourcms_wp_suitable_for_children', (string)$tour->suitable_for_children);
				update_post_meta( $post_id, 'tourcms_wp_suitable_for_groups', (string)$tour->suitable_for_groups);
				update_post_meta( $post_id, 'tourcms_wp_suitable_for_students', (string)$tour->suitable_for_students);
				update_post_meta( $post_id, 'tourcms_wp_suitable_for_business', (string)$tour->suitable_for_business);
				update_post_meta( $post_id, 'tourcms_wp_suitable_for_wheelchairs', (string)$tour->suitable_for_wheelchairs);
			
				// Languages spoken
				update_post_meta( $post_id, 'tourcms_wp_languages_spoken', (string)$tour->languages_spoken);
				
				// Optional fields
				if(isset($tour->tour_code))
					update_post_meta( $post_id, 'tourcms_wp_tour_code', (string)$tour->tour_code);	
				else
					update_post_meta( $post_id, 'tourcms_wp_tour_code', '');	 
				
				if(isset($tour->inc_ex))
					update_post_meta( $post_id, 'tourcms_wp_inc_ex', (string)wpautop($tour->inc_ex));	
				else
					update_post_meta( $post_id, 'tourcms_wp_inc_ex', '');

				if(isset($tour->inc))
					update_post_meta( $post_id, 'tourcms_wp_inc', (string)wpautop($tour->inc));	
				else
					update_post_meta( $post_id, 'tourcms_wp_inc', '');
				
				if(isset($tour->ex))
					update_post_meta( $post_id, 'tourcms_wp_ex', (string)wpautop($tour->ex));	
				else
					update_post_meta( $post_id, 'tourcms_wp_ex', '');
				
						
				if(isset($tour->essential))
					update_post_meta( $post_id, 'tourcms_wp_essential', (string)wpautop($tour->essential));	
				else
					update_post_meta( $post_id, 'tourcms_wp_essential', '');	
					
				if(isset($tour->rest))
					update_post_meta( $post_id, 'tourcms_wp_rest', (string)wpautop($tour->rest));
				else
					update_post_meta( $post_id, 'tourcms_wp_rest', '');
				
				if(isset($tour->redeem))
					update_post_meta( $post_id, 'tourcms_wp_redeem', (string)wpautop($tour->redeem));
				else
					update_post_meta( $post_id, 'tourcms_wp_redeem', '');
				
				if(isset($tour->longdesc))
					update_post_meta( $post_id, 'tourcms_wp_longdesc', (string)wpautop($tour->longdesc));
				else
					update_post_meta( $post_id, 'tourcms_wp_longdesc', '');
				
				if(isset($tour->itinerary))
					update_post_meta( $post_id, 'tourcms_wp_itinerary', (string)wpautop($tour->itinerary));
				else
					update_post_meta( $post_id, 'tourcms_wp_itinerary', '');
					
				
				if(isset($tour->exp))
					update_post_meta( $post_id, 'tourcms_wp_exp', (string)wpautop($tour->exp));
				else
					update_post_meta( $post_id, 'tourcms_wp_exp', '');
					
				
				if(isset($tour->pick))
					update_post_meta( $post_id, 'tourcms_wp_pick', (string)$tour->pick);
				else
					update_post_meta( $post_id, 'tourcms_wp_pick', '');
					
				if(isset($tour->extras))
					update_post_meta( $post_id, 'tourcms_wp_extras', (string)wpautop($tour->extras));
				else
					update_post_meta( $post_id, 'tourcms_wp_extras', '');
				
				// Custom fields
				// Currently checks for both of the following
				//	"custom_fields" in the root (current)
				// "custom_fields" under "tour" node (proper, might be fixed in future API update
				
				if (isset($tour->custom_fields->field[0])) {
					foreach ($tour->custom_fields->field as $custom_field) {
					
						$field_name = (string)$custom_field->name;
						$field_value = (string)$custom_field->value;
					
						update_post_meta( $post_id, 'tourcms_wp_custom_' .$field_name , $field_value);
					}
				} elseif (isset($results->custom_fields->field[0])) {
					foreach ($results->custom_fields->field as $custom_field) {
					
						$field_name = (string)$custom_field->name;
						$field_value = (string)$custom_field->value;
					
						update_post_meta( $post_id, 'tourcms_wp_custom_' .$field_name , $field_value);
					}
				} 
				
				// Video
				if(!empty($tour->videos->video[0]->video_id)) {
					$vid = $tour->videos->video[0];
					update_post_meta( $post_id, 'tourcms_wp_video_id_0' , (string)$vid->video_id);
					update_post_meta( $post_id, 'tourcms_wp_video_service_0' , (string)$vid->video_service);
					update_post_meta( $post_id, 'tourcms_wp_video_url_0' , (string)$vid->video_url);
				} else {
					update_post_meta( $post_id, 'tourcms_wp_video_id_0' , '');
					update_post_meta( $post_id, 'tourcms_wp_video_service_0' , '');
					update_post_meta( $post_id, 'tourcms_wp_video_url_0' , '');
				}
				
				// Document
				if(!empty($tour->documents->document[0]->document_url)) {
					$doc = $tour->documents->document[0];
					update_post_meta( $post_id, 'tourcms_wp_document_desc_0' , (string)$doc->document_description);
					update_post_meta( $post_id, 'tourcms_wp_document_url_0' , (string)$doc->document_url);
				} else {
					update_post_meta( $post_id, 'tourcms_wp_document_desc_0' , '');
					update_post_meta( $post_id, 'tourcms_wp_document_url_0' , '');
				}
				
				// Alternative tours
				if(!empty($tour->alternative_tours)) {
					$alternative_tours = $tour->alternative_tours;
					$alternative_tours_xml = $alternative_tours->asXml();
					update_post_meta ($post_id, 'tourcms_wp_alternative_tours', $alternative_tours_xml);
				} else {
					update_post_meta ($post_id, 'tourcms_wp_alternative_tours', '');
				}
				
				
				// Update images
				for($i=0;$i<=10;$i++) {
					if(isset($tour->images->image[$i]->url)) {
						update_post_meta( $post_id, 'tourcms_wp_image_url_'.$i, (string)$tour->images->image[$i]->url);
						update_post_meta( $post_id, 'tourcms_wp_image_desc_'.$i, (string)$tour->images->image[$i]->image_desc);
						
						if(isset($tour->images->image[$i]->url_thumbnail)) {
							update_post_meta( $post_id, 'tourcms_wp_image_url_thumbnail_'.$i, (string)$tour->images->image[$i]->url_thumbnail);
						} else {
							delete_post_meta( $post_id, 'tourcms_wp_image_url_thumbnail_'.$i);
						}
						
						/*
						$attachment = array
						 (
						 'post_mime_type' => 'image/jpeg',
						 'guid' => (string)$tour->images->image[$i]->url,
						 'post_parent' => $post_id,
						 'post_title' => (string)$tour->images->image[$i]->image_desc,
						 'post_content' => '',
						 'post_status' => 'publish'
						 );
						 
						wp_insert_attachment($attachment, false, $post_id); */
	
					} else {
						delete_post_meta( $post_id, 'tourcms_wp_image_url_'.$i);
						delete_post_meta( $post_id, 'tourcms_wp_image_desc_'.$i);
						delete_post_meta( $post_id, 'tourcms_wp_image_url_thumbnail_'.$i);
					}
				}
			}
	}
	
	// Generate HTML for the menu page
	function tourcms_wp_optionspage() {
		?>
			<div class="wrap">
				<div id="icon-options-general" class="icon32"><br /></div>
				<h2>TourCMS Plugin Settings</h2>
				<form method="post" action="options.php">
					<?php settings_fields('tourcms_wp_settings'); ?>
					<h3>API Settings</h3>
					<p>You can find your settings by logging into TourCMS then heading to <strong>Configuration &amp; Setup</strong> &gt; <strong>API</strong> &gt; <strong>XML API</strong>.</p>
					<input type="hidden" name="tourcms_wp_marketplace" value="0" />
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								<label for="tourcms_wp_channel">Channel ID</label>
							</th>
							<td>
								<input type="text" name="tourcms_wp_channel" size="6" value="<?php echo get_option('tourcms_wp_channel'); ?>" autocomplete="false" /> <!--span class="description">Set this to 0 if you are a Marketplace Partner</span-->
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="tourcms_wp_apikey">API Key</label>
							</th>
							<td>
								<input type="password" name="tourcms_wp_apikey" value="<?php echo get_option('tourcms_wp_apikey'); ?>"  autocomplete="false" />
							</td>
						</tr>
					</table>
					<h3>Booking Engine Settings</h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								Display style<br />
								<span class="description"><a href="http://www.tourcms.com/support/setup/booking_engine/iframe_or_popup.php" target="_blank">What's this?</a></span>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text">
										<span>Booking Engine display style</span>
									</legend>
									<?php
										(get_option('tourcms_wp_bookstyle')=="") ? $tourcms_wp_bookstyle = "popup" : $tourcms_wp_bookstyle = get_option('tourcms_wp_bookstyle');
									?>
									<label title="off"><input type="radio" name="tourcms_wp_bookstyle" value="off" <?php echo ($tourcms_wp_bookstyle=="off" ? 'checked="checked"' : null); ?>/> Booking Engine Off</label><br />
									<label title="link"><input type="radio" name="tourcms_wp_bookstyle" value="link" <?php echo ($tourcms_wp_bookstyle=="link" ? 'checked="checked"' : null); ?>/> Standard Link</label><br />
									<label title="popup"><input type="radio" name="tourcms_wp_bookstyle" value="popup" <?php echo ($tourcms_wp_bookstyle=="popup" ? 'checked="checked"' : null); ?>/> Popup Window</label><br />
									<label title="iframe"><input type="radio" name="tourcms_wp_bookstyle" value="iframe" <?php echo ($tourcms_wp_bookstyle=="iframe" ? 'checked="checked"' : null); ?>/> Iframe</label>
								</fieldset>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="tourcms_wp_booktext">Text</label>
							</th>
							<td>
								<input type="text" name="tourcms_wp_booktext" value="<?php echo (get_option('tourcms_wp_booktext')=="") ? __( 'Book Online', 'tourcms_wp' ) : get_option('tourcms_wp_booktext'); ?>" placeholder='e.g. "Book Online"' />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Height<br />
								<span class="description">(Iframe &amp; Popup Window)</span>
							</th>
							<td>
								<input type="text" size="4" name="tourcms_wp_bookheight" value="<?php echo (get_option('tourcms_wp_bookheight')=="") ? "700" : get_option('tourcms_wp_bookheight'); ?>" placeholder='e.g. "700"' /> <span class="description">px</span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Width<br />
								<span class="description">(Popup Window only)</span>
							</th>
							<td>
								<input type="text" size="4" name="tourcms_wp_bookwidth" value="<?php echo (get_option('tourcms_wp_bookwidth')=="") ? "700" : get_option('tourcms_wp_bookwidth'); ?>" /> <span class="description">px</span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Extra Query String Parameters<br />
								<span class="description"><a href="http://www.tourcms.com/support/setup/booking_engine/integration_parameters.php" target="_blank">What's this?</a></span>
							</th>
							<td>
								<input type="text" size="30" name="tourcms_wp_bookqs" value="<?php echo (get_option('tourcms_wp_bookqs')=="") ? "" : get_option('tourcms_wp_bookqs'); ?>" placeholder='e.g. "&people=0&month_year=12_2012"' /> <span class="description">Probably leave this blank</span>
							</td>
						</tr>					
					</table>
					
					<h3>Video Embedding defaults</h3>
					<table class="form-table">
					<tr valign="top">
						<th scope="row">
							Height
						</th>
						<td>
							<input type="text" size="4" name="tourcms_wp_vidheight" value="<?php echo (get_option('tourcms_wp_vidheight')=="") ? "342" : get_option('tourcms_wp_vidheight'); ?>" placeholder='e.g. "342"' /> <span class="description">px</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							Width
						</th>
						<td>
							<input type="text" size="4" name="tourcms_wp_vidwidth" value="<?php echo (get_option('tourcms_wp_vidwidth')=="") ? "608" : get_option('tourcms_wp_vidwidth'); ?>" placeholder='e.g. "608"'  /> <span class="description">px</span>
						</td>
					</tr>
					<?php
						(get_option('tourcms_wp_vidresponsive')=="") ? $tourcms_wp_vidresponsive = "yes" : $tourcms_wp_vidresponsive = get_option('tourcms_wp_vidresponsive');
					?>
					<tr valign="top">
						<th scope="row">
							Responsive<br />
							<span class="description">If your theme resizes for mobile devices, leaving this on will allow videos to resize with your theme.</span>
						</th>
						<td>
							<label title="off"><input type="radio" name="tourcms_wp_vidresponsive" value="yes" <?php echo ($tourcms_wp_vidresponsive=="yes" ? 'checked="checked"' : null); ?>/> On</label><br />
							<label title="link"><input type="radio" name="tourcms_wp_vidresponsive" value="no" <?php echo ($tourcms_wp_vidresponsive=="no" ? 'checked="checked"' : null); ?>/> Off</label>
						</td>
					</tr>
					</table>
					
					
					<h3>Cache Settings</h3>
					<p>When you save a Tour/Hotel inside WordPress the plugin will get the latest information on that product from TourCMS. It's also possible to update that information automatically if a Tour/Hotel is viewed on your site and hasn't been updated in a while.</p>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								Update TourCMS info
							</th>
							<td>
								<?php
									(get_option('tourcms_wp_update_frequency')=="") ? $tourcms_wp_update_frequency = 14400 : $tourcms_wp_update_frequency = intval(get_option('tourcms_wp_update_frequency'));
								?>
								<select name="tourcms_wp_update_frequency">
									<option value="-1"<?php $tourcms_wp_update_frequency==-1 ? print ' selected="selected"' : null; ?>>Only when I edit the Tour/Hotel in WordPress</option>
									<option value="86400"<?php $tourcms_wp_update_frequency==86400 ? print ' selected="selected"' : null; ?>>After 24 hours</option>
									<option value="14400"<?php $tourcms_wp_update_frequency==14400 ? print ' selected="selected"' : null; ?>>After 4 hours [Default]</option>
									<option value="3600"<?php $tourcms_wp_update_frequency==3600 ? print ' selected="selected"' : null; ?>>After 1 hour</option>
									<option value="0"<?php $tourcms_wp_update_frequency==0 ? print ' selected="selected"' : null; ?>>Constantly (Don't cache)</option>
								</select>
							</td>
						</tr>
					</table>
					
					<!--h3>** Experimental ** - Allow Tours/Hotels that are not linked to a Tour/Hotel in TourCMS</h3>
					<p>When this setting is enabled you will be able to create new Tours/Hotels in WordPress that are not associated with a Tour/Hotel in TourCMS. In addition this will expose the standard WordPress interface for editing the custom information that is cached from TourCMS - allowing this data to be populated for Tours/Hotels that are not stored in TourCMS.</p>
					<p style="font-weight: bold;">This is an expirmental feature, the interface is not particularly polished and this is recommended for advanced users only. </p>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								Enable (not recommended)
							</th>
							<td>
								<?php
									(get_option('tourcms_wp_allow_non_tourcms')=="") ? $tourcms_wp_allow_non_tourcms = 0 : $tourcms_wp_allow_non_tourcms = intval(get_option('tourcms_wp_allow_non_tourcms'));
								?>
								<input type="checkbox" name="tourcms_wp_allow_non_tourcms" value="1" <?php 
									$tourcms_wp_allow_non_tourcms==1 ? print ' checked="checked"' : null;
								?> />
							</td>
						</tr>
					</table-->
					
					<p class="submit">
						<input class="button-primary" type="submit" value="Save Changes" name="Submit" />
					</p>
				</form>
			</div>
		<?php
	}
	
	// Booking engine hook function
	function tourcms_wp_book() {
		do_action('tourcms_wp_book');
	}
	
	// From price hook function
	function tourcms_wp_price() {
		do_action('tourcms_wp_price');
	}
	
	// Print out the booking engine
	function tourcms_wp_dobook($url = "", $t = "") {
		global $post;
		$continue = false;
		
		if($url == "") {
			if(is_single() && get_query_var('post_type') == 'tour') {
				$book_url = get_post_meta( $post->ID, 'tourcms_wp_book_url', true );
				if($book_url<>"")
					$continue = true;
			}
		} else {
			$book_url = $url;
			$continue = true;
		}

		if($continue) {
			// Get our settings / defaults
			$book_style = get_option('tourcms_wp_bookstyle')=="" ? "link" : get_option('tourcms_wp_bookstyle');
			if($t == "") {
				$book_text = get_option('tourcms_wp_booktext')=="" ? __( 'Book Online', 'tourcms_wp' ) : get_option('tourcms_wp_booktext');
			} else {
				$book_text = $t;
			}
			$book_height = get_option('tourcms_wp_bookheight')=="" ? "600" : get_option('tourcms_wp_bookheight');
			$book_width = get_option('tourcms_wp_bookwidth')=="" ? "600" : get_option('tourcms_wp_bookwidth');
			$book_params = get_option('tourcms_wp_bookqs')=="" ? "" : get_option('tourcms_wp_bookqs');
			$book_url .= $book_params;
		
			// Render the booking engine based on the book_style
			if($book_style=="link") {
				// Standard link
				?>
				<p class="booklink"><a href="<?php echo $book_url; ?>"><?php echo $book_text; ?></a></p>
				<?php
			} else if ($book_style=="popup") {
				// Popup window
				$book_height = (int)$book_height;
				$book_width = (int)$book_width;
				$if_width = $book_width - 20;
				$book_url .= "&if=1&ifwidth=$if_width";
				?>
				<p class="booklink"><a href="<?php echo $book_url; ?>" onclick="window.open(this, '_blank', 'height=<?php echo $book_height; ?>,width=<?php echo $book_width ; ?>,statusbar=0,scrollbars=1'); return false;"><?php echo $book_text; ?></a></p>
				<?php
			} else if ($book_style=="iframe") {
				// Iframe
				$book_height = (int)$book_height;
				$book_width = (int)$book_width;
				?>
				<iframe class="bookframe" src="" style="width: 100%; height: <?php echo $book_height; ?>px;"></iframe>
				
				<script type="text/javascript">
					jQuery(document).ready(function() {
						var tcmsbookframe = jQuery('.bookframe');
						var tcmsbookwidth = tcmsbookframe.width() - 20;
						
						var tcmsbookurl = "<?php echo $book_url; ?>&if=1&ifwidth=" + tcmsbookwidth;
						
						tcmsbookframe.attr('src', tcmsbookurl); 
					});
				</script>
				<?php
			}
		}
	}
	
	// Print out the actual from price
	function tourcms_wp_doprice() {
			global $post;
	
			$from_price = get_post_meta( $post->ID, 'tourcms_wp_from_price_display', true );

			if($from_price<>"") {
				echo "<span class='fromprice'>".__( 'from', 'tourcms_wp' )." ".$from_price."</span>";
			}
	}
	
	// Generic TourCMS Shortcode handler
	function tourcms_wp_shortcode($atts, $content, $code) {
		global $post;
		
		//error_log($tag);
		// Support tourcms_ prefixed shortcodes
		// added due to conflicts with other plugins/themes
		if(substr($code, 0, 8) == 'tourcms_') {
			$code = str_replace('tourcms_', '', $code);
		}
		
		//error_log($tag);
		
		$text = get_post_meta( $post->ID, 'tourcms_wp_'.$code, true );
		
		if($code=="from_price")
			$text = round(get_post_meta( $post->ID, 'tourcms_wp_'.$code, true ));
			
		return $text;
	}
	
	// Custom fields TourCMS shortcode handler
	// TODO: Merge with generic
	// tourcms_wp_custom_
	function tourcms_wp_custom_shortcode( $atts, $content = null ) {
	   global $post;
	   extract( shortcode_atts( array(
	      'tag' => ''
	      ), $atts ) );
	      
	      $text = "";
	      
		if($tag<>"")
			return get_post_meta( $post->ID, 'tourcms_wp_custom_'.$tag, true );

		return $text;
	}
	
	add_shortcode('tour_code', 'tourcms_wp_shortcode');
	add_shortcode('tour_id', 'tourcms_wp_shortcode');
	add_shortcode('has_sale', 'tourcms_wp_shortcode');
	add_shortcode('book_url', 'tourcms_wp_shortcode');
	add_shortcode('from_price', 'tourcms_wp_shortcode');
	add_shortcode('from_price_display', 'tourcms_wp_shortcode');
	add_shortcode('sale_currency', 'tourcms_wp_shortcode');
	add_shortcode('geocode_start', 'tourcms_wp_shortcode');
	add_shortcode('geocode_end', 'tourcms_wp_shortcode');
	add_shortcode('duration_desc', 'tourcms_wp_shortcode');
	add_shortcode('available', 'tourcms_wp_shortcode');
	add_shortcode('inc_ex', 'tourcms_wp_shortcode');
	add_shortcode('inc', 'tourcms_wp_shortcode');
	add_shortcode('ex', 'tourcms_wp_shortcode');
	add_shortcode('essential', 'tourcms_wp_shortcode');
	add_shortcode('rest', 'tourcms_wp_shortcode');
	add_shortcode('redeem', 'tourcms_wp_shortcode');
	add_shortcode('tour_name', 'tourcms_wp_shortcode');
	add_shortcode('location', 'tourcms_wp_shortcode');
	add_shortcode('summary', 'tourcms_wp_shortcode');
	add_shortcode('shortdesc', 'tourcms_wp_shortcode');
	add_shortcode('longdesc', 'tourcms_wp_shortcode');
	add_shortcode('itinerary', 'tourcms_wp_shortcode');
	add_shortcode('exp', 'tourcms_wp_shortcode');
	add_shortcode('pick', 'tourcms_wp_shortcode');
	add_shortcode('extras', 'tourcms_wp_shortcode');
	
	
	add_shortcode('tourcms_custom', 'tourcms_wp_custom_shortcode');
	
	
	// Support tourcms_ prefixed shortcodes
	// added due to conflicts with other plugins/themes
	add_shortcode('tourcms_tour_code', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_tour_id', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_has_sale', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_book_url', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_from_price', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_from_price_display', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_sale_currency', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_geocode_start', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_geocode_end', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_duration_desc', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_available', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_inc_ex', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_inc', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_ex', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_essential', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_rest', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_redeem', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_tour_name', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_location', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_summary', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_shortdesc', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_longdesc', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_itinerary', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_exp', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_pick', 'tourcms_wp_shortcode');
	add_shortcode('tourcms_extras', 'tourcms_wp_shortcode');
	
	// Generate a hyperlink to the booking engine
	function tourcms_wp_booklink($atts, $content, $code) {
		global $post;
		extract( shortcode_atts( array(
		      'style' => 'standard',
		      'height' => (get_option('tourcms_wp_bookheight')=="") ? "600" : get_option('tourcms_wp_bookheight'),
		      'width' => (get_option('tourcms_wp_bookwidth')=="") ? "600" : get_option('tourcms_wp_bookwidth')
		      ), $atts ) );    
		
		
		
		$link = get_post_meta( $post->ID, 'tourcms_wp_book_url', true );		

		if($style=="popup") {
			// Popup window
			$if_width = (int)$width - 20;
			$link .= "&if=1&ifwidth=$if_width";
			$text = '<a href="'.$link.'" onclick="window.open(this, \'_blank\', \'height='.$height.',width='.$width.',statusbar=0,scrollbars=1\'); return false;">'.$content.'</a>';
		} else {
			$text = '<a href="'.$link.'">'.$content.'</a>';
		}

		return $text;
	}
	add_shortcode('book_link', 'tourcms_wp_booklink');
	add_shortcode('tourcms_book_link', 'tourcms_wp_booklink');
	
	// Embed code for video
		function tourcms_wp_video_embed($atts, $content, $code) {
		
			global $post;
			extract( shortcode_atts( array(
			      'height' => (get_option('tourcms_wp_vidheight')=="") ? "342" : get_option('tourcms_wp_vidheight'),
			      'width' => (get_option('tourcms_wp_vidwidth')=="") ? "608" : get_option('tourcms_wp_vidwidth'),
			      'responsive' => (get_option('tourcms_wp_vidresponsive')=="") ? "yes" : get_option('tourcms_wp_vidresponsive')
			      ), $atts ) );    
			
			$video_id = get_post_meta( $post->ID, 'tourcms_wp_video_id_0', true );		
			$video_service = get_post_meta( $post->ID, 'tourcms_wp_video_service_0', true );		
			
			include_once('video_embed/video_embed.php');
			
			$video = new VideoEmbed();
			$video_options = array(
			            "width" => $width,
			            "height" => $height
			        );
			
			$return = $video->get_embed($video_id, $video_service, $video_options);
		
			if($responsive == "yes" && !empty($return)) {
				$return = "<div class='tcms-video-container'>
	$return
</div>
<style type='text/css'>
	.tcms-video-container {     position: relative;     padding-bottom: 56.25%;     padding-top: 30px; height: 0; overflow: hidden; }
	.tcms-video-container iframe {     position: absolute;     top: 0;     left: 0;     width: 100%;     height: 100%; }
</style>";
			}
		
			return $return;
		}
	add_shortcode('vid_embed', 'tourcms_wp_video_embed');
	add_shortcode('tourcms_vid_embed', 'tourcms_wp_video_embed');
	
	// Link code for documents
		function tourcms_wp_doc_link($atts, $content, $code) {
		
			global $post;
			extract( shortcode_atts( array(
			      'target' => '_blank'
			      ), $atts ) );    
			
			$document_url = get_post_meta( $post->ID, 'tourcms_wp_document_url_0', true );		
			$document_desc = get_post_meta( $post->ID, 'tourcms_wp_document_desc_0', true );		
			
			$text = $document_desc;
			
			if(!empty($content))
				$text = $content;
		
			return "<a href='$document_url' target='$target'>$text</a>";
		}
	add_shortcode('doc_link', 'tourcms_wp_doc_link');
	add_shortcode('tourcms_doc_link', 'tourcms_wp_doc_link');
	
	function tourcms_wp_convtime($seconds)
		{
		    $ret = "";
		
		    $hours = intval(intval($seconds) / 3600);
		    if($hours > 0)
		    {
		        $ret .= "$hours hours ";
		    }
	
		    $minutes = (intval($seconds) / 60)%60;
		    
		    if (function_exists('bcmod')) {
		        $minutes = bcmod((intval($seconds) / 60),60);
		    } else {
		        $minutes = (intval($seconds) / 60)%60;
		    }
		    
		    if($hours > 0 || $minutes > 0)
		    {
		        $ret .= "$minutes minutes ";
		    }
		  
		    //$seconds = bcmod(intval($seconds),60);
		    //$ret .= "$seconds seconds";
		
			if($ret =="")
				$ret .= "Seconds";
		
		    return $ret;
		}
	
	
	
	// Include Map Widget
	require_once 'widgets/tourMap/tourMap.php';
	// Include Availability Widget
	require_once 'widgets/tourAvail/tourAvail.php';
?>