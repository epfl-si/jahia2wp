<?php

/*
 * Simple display of input field to open a window using a reverse proxy
 */
function epfl_magistrale_process_shortcode( $atts, $content = null ) {

ob_start();

?>

<form onsubmit="$('#vip-code-submit').click()">
   <input type="text" id="vip-personal-code" size="30">
   <span id="vip-code-submit" class="dashicons dashicons-search" style="font-size:32px;cursor:pointer" onclick="window.open('https://reverse-proxy-kis.epfl.ch/code/'+$('#vip-personal-code').val(), '_blank');"></span>
</form>


<?php

return ob_get_clean();
}

add_action( 'init', function() {
  // define the shortcode
  add_shortcode('epfl_magistrale', __NAMESPACE__ . '\epfl_magistrale_process_shortcode');
});
