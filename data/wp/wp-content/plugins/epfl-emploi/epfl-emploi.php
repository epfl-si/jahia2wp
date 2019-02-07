<?php
/**
 * Plugin Name: EPFL Emploi
 * Description: provides a shortcode to display job offers
 * Version: 1.0
 * Author: Lucien Chaboudez
 * Contributors:
 * License: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
 **/


function epfl_emploi_process_shortcode( $atts, $content = null ) {

    $atts = shortcode_atts( array(
        'url' => '',
        'except_positions' => '',
    ), $atts );

    $url                = $atts['url'];
    $except_positions   = $atts['except_positions'];

    if($url == '')
    {
        return '<b><font color="red">Please provide an URL</font></b>';
    }

    /* Lang to numeric id for JS code */
    $lang_to_id = array('en' => 2, 'fr' => 3);

    /* If Polylang installed */
    if(function_exists('pll_current_language'))
    {
        $lang = pll_current_language();
        /* Set in english if not in allowed languages */
        if(!array_key_exists($lang, $lang_to_id)) $lang = 'en';

    }
    else /* Polylang not installed */
    {
        $lang = 'en';
    }


    /* Including CSS file*/
    wp_enqueue_style( 'epfl_emploi_style', plugin_dir_url(__FILE__).'css/style.css' );
    wp_enqueue_script( 'epfl_emploi_filter_array_emulate', plugin_dir_url(__FILE__).'js/prototype-filter-emulate.js' );
    wp_enqueue_script( 'epfl_emploi_script', plugin_dir_url(__FILE__).'js/script.js' );

    /* We have to remove all URL parameters named 'searchPosition' to have 'searchPositionUrl' value for JS */
    $url_query = parse_url($url, PHP_URL_QUERY);

    parse_str($url_query, $parameters);

    if(array_key_exists('searchPosition', $parameters))
    {
       unset($parameters['searchPosition']);
    }

    $new_url_query = http_build_query($parameters);
    /* We replace query in original url to have 'searchPositionUrl' value for JS */
    $url_search_position = str_replace($url_query, $new_url_query, $url);


ob_start();

?>

<div class="panel-content keywords-panel form"><input id="id_keywords" name="keywords" type="text" />
<button class="themed search-button keywords-button" name="search" onclick="onSelectionChanged()"><span class="icon-search">&nbsp;</span></button></div>

<div aria-expanded="true" aria-hidden="false" aria-labelledby="toggle-1" class="list-unstyled toggle-expanded" id="toggle-pane-0">&nbsp;</div>

<div class="toolbar-emploi actu-advanced-search-toolbar ui-toolbar" data-widget="toolbar" role="toolbar">
    <button class="toolbar-item" name="search" onclick="onSelectionChanged()" role="button" tabindex="0"><?PHP echo __('Search', 'epfl-emploi'); ?></button>
    <button class="toolbar-item right" onclick="reset()"><?PHP echo __('Reset', 'epfl-emploi'); ?></button>

    <!-- URLs -->
    <input type="hidden" id="EPFLEmploiDefaultUrl" value="<?PHP echo $url; ?>">
    <input type="hidden" id="EPFLEmploiSearchPositionUrl" value="<?PHP echo $url_search_position; ?>">

    <!-- Parameters -->
    <input type="hidden" id="EPFLEmploiExceptPositions" value="<?PHP echo $except_positions; ?>">


    <!-- Lang & Translations -->
    <input type="hidden" id="EPFLEmploiLang" value="<?PHP echo $lang_to_id[$lang]; ?>">
    <input type="hidden" id="EPFLEmploiTransFunction" value="<?PHP echo esc_attr__('Function', 'epfl-emploi'); ?>">
    <input type="hidden" id="EPFLEmploiTransLocation" value="<?PHP echo esc_attr__('Location', 'epfl-emploi'); ?>">
    <input type="hidden" id="EPFLEmploiTransWorkRate" value="<?PHP echo esc_attr__('Work Rate', 'epfl-emploi'); ?>">
    <input type="hidden" id="EPFLEmploiTransEmplTerm" value="<?PHP echo esc_attr__('Term of employment', 'epfl-emploi'); ?>">


</div>

<div id="umantis_iframe">&nbsp;</div>
<script>

		jQuery(document).ready(function() {
	           var time = new Date().getTime();
			var if_height, src = '<?PHP echo $url; ?>&t='+time,
			iframe = jQuery( '<iframe src="' + src + '" name="' + document.location.href + '" width="652" height="500" frameborder="0" scrolling="no" id="job-board" ><script>setInterval(function() {window.top.postMessage(document.body.scrollHeight, "*");}, 500); <\/script><\/iframe>' ).appendTo( '#umantis_iframe' );

                        window.addEventListener('message', function(e) {

				var h = Number( e.data.replace( /.*if_height=(\d+)(?:&|$)/, '$1' ) );

				if (!isNaN( h ) && h > 0 && h !== if_height) {
					/* Height has changed, update the iframe */
					if_height = h;
					iframe.height(h);
				}

                        } , false);

		});
</script>


<?php

return ob_get_clean();
}

add_action( 'init', function() {
  // define the shortcode
  add_shortcode('epfl_emploi', 'epfl_emploi_process_shortcode');
});

// Load .mo file for translation
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'epfl-emploi', FALSE, basename( plugin_dir_path( __FILE__ )) . '/languages/');
});