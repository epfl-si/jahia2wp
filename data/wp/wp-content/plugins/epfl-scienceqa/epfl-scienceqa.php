<?php
/**
 * Plugin Name:     Science Q&A
 * Description:     Provide a shortcode to display the Science Q&A form
 * Author:          Loïc Cattani
 * Text Domain:     epfl-scienceqa
 * Domain Path:     /languages
 * Version:         0.1.0
 * Copyright:      Copyright (c) 2018 Loïc Cattani
 */


declare(strict_types=1);

define('SCIENCEQA_API_URL', 'https://qi.epfl.ch/');

require_once 'render.php';
require_once 'utils.php';
require_once 'shortcake-config.php';

/**
 * Build api URL of Science Q&A
 *
 * @param $lang: language
 * @return the api URL for latest Science Q&A
 */
function epfl_scienceqa_build_api_url( string $lang, string $qid ): string
{
	// define API URL
	$url = SCIENCEQA_API_URL . $lang . '/question/json';

	if ( $qid ) {
		$url .= '/' . $qid;
	}

	return $url;
}

/**
 * Check the required parameters
 *
 * @param $lang: language (fr or en)
 * @return True if the required parameters are right.
 */
function epfl_scienceqa_required_parameters( string $lang ): bool {
  
	// check lang
	if ( $lang !==  'fr' && $lang !== 'en' ) {
		return FALSE;
	}

	return TRUE;
}

/**
 * Check required response data
 *
 * @return True if the required parameters are right.
 */
function epfl_scienceqa_check_response_data( $scienceqa ): bool {
  
	if ( $scienceqa === NULL ) {
		return FALSE;
	}

	// check image
	if ( !is_string( $scienceqa->image ) || !substr( $scienceqa->image, 0, 4 ) === 'http' ) {
		return FALSE;
	}

    // check question
	if ( !is_string( $scienceqa->question ) ) {
		return FALSE;
	}

	// check answers
	if ( !is_object( $scienceqa->answers ) ) {
		return FALSE;
	} else {
		// check each answer is a string
		for ( $i=1; $i <= 3; $i++ ) {
			if ( !is_string( $scienceqa->answers->$i ) ) {
				return FALSE;
			}
		}
	}

	return TRUE;
}

function epfl_scienceqa_process_shortcode( $atts = [], $content = '', $tag = '' ): string {
  
	// shortcode parameters
	$atts = shortcode_atts( array(
		'lang'     => 'en',
		'qid'      => '',
	), $atts, $tag );

	// sanitize parameters
	$lang = sanitize_text_field( $atts['lang'] );
	$qid = sanitize_text_field( $atts['qid'] );

	if (epfl_scienceqa_required_parameters( $lang ) == FALSE) {
		return '';
	}

	$url = epfl_scienceqa_build_api_url( $lang, $qid );

	$items = ScienceQAUtils::get_items( $url );

	if (epfl_scienceqa_check_response_data( $items ) == FALSE) {
		return '';
	}

	// if supported delegate the rendering to the theme
	if ( has_action( 'epfl_scienceqa_action' ) ) {
		ob_start();
		try {
			do_action( 'epfl_scienceqa_action', $items );
			return ob_get_contents();
		} finally {
			ob_end_clean();
		}
	// otherwise the plugin does the rendering
	} else {
		return ScienceQARender::epfl_scienceqa_build_html( $items, $lang );
	}
}

// load .mo file for translation
function epfl_scienceqa_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-scienceqa', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}

add_action( 'plugins_loaded', 'epfl_scienceqa_load_plugin_textdomain' );

add_action( 'init', function() {
	// define the shortcode
	add_shortcode( 'epfl_scienceqa', 'epfl_scienceqa_process_shortcode' );

	// shortcake configuration
	if (function_exists( 'shortcode_ui_register_for_shortcode' ) ) {
		ScienceQAShortCakeConfig::config();
	}
});

?>
