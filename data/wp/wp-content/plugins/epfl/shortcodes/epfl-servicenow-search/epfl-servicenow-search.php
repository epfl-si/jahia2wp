<?php

/*
 * Simple display of input field to do a search in ServiceNow (in a new tab)
 */
function epfl_service_now_search_process_shortcode( $atts, $content = null ) {
?>

<form onsubmit="$('#snow-search').click()">
   <input type="text" id="snow-keyword" size="30">
   <span id="snow-search" class="dashicons dashicons-search" style="font-size:32px;cursor:pointer" onclick="window.open('https://support.epfl.ch/help?id=search&spa=1&q='+$('#snow-keyword').val(), '_blank');"></span>
</form>


<?php
}

add_action( 'init', function() {
  // define the shortcode
  add_shortcode('epfl_servicenow_search', __NAMESPACE__ . '\epfl_service_now_search_process_shortcode');
});