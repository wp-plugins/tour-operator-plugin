<?php

class tourAvail extends WP_Widget {
	function tourAvail() {
		parent::WP_Widget(false, 'Tour Availability', array('description' => 'Display a two year calendar indicating which months have availability. Only visible on Tour/Hotel pages.'));	
	}
	
	function widget($args, $instance) {
		global $post;
		
		// Check that this is a "tour" and that it has a start location saved
		// at the time of writing all Tours in TourCMS must have a start location
		if(is_single() && get_query_var('post_type') == 'tour') {
			$has_sale = (int)get_post_meta($post->ID, 'tourcms_wp_has_sale', true );
			if($has_sale==1)
				$do_output = true;	
			else
				$do_output = false;			
		} else {
			$do_output = false;
		}
		
		if($do_output) {
			extract($args);
			echo $before_widget;
			$title = empty($instance['title']) ? '&nbsp;' : apply_filters('widget_title', $instance['title']);
			if ( !empty( $title ) ) { echo $before_title . $title . $after_title; };
				$year = date("Y");
				$month = date("n");
				$months = array("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec");
				$style = get_option('tourcms_wp_bookstyle');
				
				// Build Link format (if any)
				if($style!="off" && $style!="iframe") {
					$height = (get_option('tourcms_wp_bookheight')=="") ? "600" : get_option('tourcms_wp_bookheight');
					$width = (get_option('tourcms_wp_bookwidth')=="") ? "600" : get_option('tourcms_wp_bookwidth');			
					$link = get_post_meta($post->ID, 'tourcms_wp_book_url', true );
					$book_params = get_option('tourcms_wp_bookqs')=="" ? "" : get_option('tourcms_wp_bookqs');
					$link .= $book_params;
					
					if($style=="popup") {
						// Popup window
						$if_width = (int)$width - 20;
						$link .= "&if=1&ifwidth=$if_width";
						$text = '<a href="'.$link.'&month_year=[MONTHYEAR]" onclick="window.open(this, \'_blank\', \'height='.$height.',width='.$width.',statusbar=0,scrollbars=1\'); return false;">[MONTH]</a>';
					} else {
						$text = '<a href="'.$link.'&month_year=[MONTHYEAR]">[MONTH]</a>';
					}
				} else {
					$text = "[MONTH]";
				}
				
				// Render bulk of widget
			?>
				<p style="margin-bottom: 0;"><?php echo get_post_meta($post->ID, 'tourcms_wp_available', true ); ?></p>
				<style type="text/css">
					span.month {
						text-align: center; font-size: 10px; font-family: arial, helvetic, sans-serif; display: block; width: 28px; color: white; float: left; margin-left: 3px; margin-top: 2px;  -moz-border-radius: 3px; -webkit-border-radius: 3px; border-radius: 3px;
					}
					
					span.month.off {
						background: #999;
					}
					
					span.month.on {
						background: #6d9978; 
					}
				</style>
				<div><div style="width: 100px; float: left;">
					<strong><?php print $year; ?></strong><br />
					<?php
						for($i=0; $i<=11; $i++) {
							if($i<$month-1)
								print '<span class="month off">'.__( strtoupper($months[$i]), 'tourcms_wp' ).'</span> ';
							else {
								$onsale = (int)get_post_meta($post->ID, 'tourcms_wp_has_sale_'.$months[$i], true );
								if($onsale) {
									$out_month = str_pad($i + 1, 2, "0", STR_PAD_LEFT);
									$out_text = str_replace("[MONTHYEAR]", $out_month . "_" . $year, $text);
									print str_replace("[MONTH]", '<span class="month on">'.__( strtoupper($months[$i]), 'tourcms_wp' ).'</span> ', $out_text);
								} else {
									//print '<span class="month off">'.__( strtoupper($months[$i]), 'tourcms_wp' ).'</span> ';		
									$out_month = str_pad($i + 1, 2, "0", STR_PAD_LEFT);
									$out_text = str_replace("[MONTHYEAR]", $out_month . "_" . $year, $text);
									print str_replace("[MONTH]", '<span class="month off">'.__( strtoupper($months[$i]), 'tourcms_wp' ).'</span> ', $out_text);
								}
							}
						}
					?>
				</div>
				<div style="width: 100px; float: left;">
					<strong><?php print $year + 1; ?></strong><br />
					<?php
						for($i=0; $i<=11; $i++) {
							if($i<$month-1) {
								$onsale = (int)get_post_meta($post->ID, 'tourcms_wp_has_sale_'.$months[$i], true );
								if($onsale) {
									$out_month = str_pad($i + 1, 2, "0", STR_PAD_LEFT);
									$out_text = str_replace("[MONTHYEAR]", $out_month . "_" . ($year + 1), $text);
									print str_replace("[MONTH]", '<span class="month on">'.__( strtoupper($months[$i]), 'tourcms_wp' ).'</span> ', $out_text);
								} else {
									//print '<span class="month off">'.__( strtoupper($months[$i]), 'tourcms_wp' ).'</span> ';
									$out_month = str_pad($i + 1, 2, "0", STR_PAD_LEFT);
									$out_text = str_replace("[MONTHYEAR]", $out_month . "_" . ($year + 1), $text);
									print str_replace("[MONTH]", '<span class="month off">'.__( strtoupper($months[$i]), 'tourcms_wp' ).'</span> ', $out_text);
								}
							} else {
								//print '<span class="month off">'.__( strtoupper($months[$i]), 'tourcms_wp' ).'</span> ';
								$out_month = str_pad($i + 1, 2, "0", STR_PAD_LEFT);
								$out_text = str_replace("[MONTHYEAR]", $out_month . "_" . ($year + 1), $text);
								print str_replace("[MONTH]", '<span class="month off">'.__( strtoupper($months[$i]), 'tourcms_wp' ).'</span> ', $out_text);
							}
						}
					?>
				</div><br style="clear: left;"></div>
			<?php
			echo $after_widget;
		}
	}
	
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		return $instance;
	}
	
	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => 'Tour Availability') );
		$title = strip_tags($instance['title']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
		<?php
	}
}

add_action('widgets_init', create_function('', 'return register_widget("tourAvail");'));

?>