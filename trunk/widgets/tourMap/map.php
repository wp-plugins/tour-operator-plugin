<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<?php
			isset($_GET['apikey']) ? $apikey = htmlspecialchars($_GET['apikey']) : $apikey = '';
			
			isset($_GET['width']) ? $width = intval($_GET['width']) : $width = '200';
			
			isset($_GET['height']) ? $height = intval($_GET['height']) : $height = '200';
			
			isset($_GET['zoomlevel']) ? $zoomlevel = intval($_GET['zoomlevel']) : $latlng = '5';
			
			if((int)$zoomlevel > 18)
				$zoomlevel = 18;
			
			isset($_GET['latlng']) ? $latlng = htmlspecialchars($_GET['latlng']) : $latlng = '0,0';
		?>
		
		<script type="text/javascript" src="http://www.google.com/jsapi?key=<?php echo $apikey; ?>"></script>
		
		<script type="text/javascript">
			google.load("maps", "2", {"other_params":"sensor=false"});
			
			// Call this function when the page has been loaded
			function initialize() {
				// Get long/lat from TourCMS
				var startPoint = new google.maps.LatLng(<?php echo $latlng; ?>);
											  		
				// Create a new Google Map
				var map = new google.maps.Map2(document.getElementById("map"));
				map.setUIToDefault();
				
				// Start point
				// Start Icon
				var startIcon = new GIcon(G_DEFAULT_ICON);
				startIcon.image = "images/marker-dot.png";
				
				// Start Marker Options
				startMarkerOptions = { icon:startIcon, clickable:false };
							    
				// Build and plot Start Point
				map.addOverlay(new google.maps.Marker(startPoint, startMarkerOptions));
				
				map.setCenter(startPoint, <?php echo $zoomlevel; ?>);
			}
						  
						  
						    		  
			google.setOnLoadCallback(initialize);
		</script>
		
		<style type="text/css"> 
			body {
				margin: 0;
			}
			#map { 
		 		width: <?php echo $width; ?>px; height: <?php echo $height; ?>px; margin: 0; padding: 0;
		 	} 
		</style> 
	</head>
	<body>
		<div id="map"></div>
	</body>
</html>