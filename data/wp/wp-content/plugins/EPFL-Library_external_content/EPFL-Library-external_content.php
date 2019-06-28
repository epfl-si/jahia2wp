<?php

/*
Plugin Name: EPFL Library External content shortcode
Plugin URI:
Description: provides a shortcode to transmit parameters to specific Library APP
    and get external content from this external source according to the
    transmitted parameters.
Version: 1.0
Author: Raphaël REY & Sylvain VUILLEUMIER
Author URI: https://people.epfl.ch/raphael.rey
Author URI: https://people.epfl.ch/sylvain.vuilleumier
License: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

/*
USAGE: [epfl_library_external_content url="xxx"]
Required parameter:
- url: url source of the external content

Optional parameters :
- script_url: url of an additional js script (required if script_name)
- script_name: name of the script in order to be able to call it (required if script_url)
- css_url: url of an additional css stylesheet (required if css_name)
- css_name: name of the css stylesheet (required if css_url)

The plugin will transmit the arguments of the current url to the external content url.

*/

// function epfl_library_external_content_log($message) {
//
//     if (WP_DEBUG === true) {
//         if (is_array($message) || is_object($message)) {
//             error_log(print_r($message, true));
//         } else {
//             error_log($message);
//         }
//     }
// }

function external_content_urlExists($url)
{
    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);

    $response = curl_exec($handle);
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

    if ($httpCode >= 200 && $httpCode <= 400) {
        return true;
    } else {
        return false;
    }
    curl_close($handle);
}

function epfl_library_external_content_process_shortcode($attributes, $content = null)
{
    extract(shortcode_atts(array(
                'url' => '',
                'script_name' => '',
                'script_url' => '',
                'css_name' => '',
                'css_url' => ''
    ), $attributes));

    if (url == ''){
      $error = new WP_Error('URL missing', 'The url parameter is missing', $url);
      // epfl_library_external_content_log($error);
      return 'ERROR: url parameter empty.';
    }
    // Add optional css
    if ($css_name != '' and $css_url != ''){
        wp_enqueue_style($css_name, $css_url);
    }

    // Add optional script
    if ($script_name != '' and $script_url != ''){
        wp_enqueue_script($script_name, $script_url);
    }

    // Test the final concatened url
    if (external_content_urlExists($url)) {
        $response = wp_remote_get($url . '?' . $_SERVER['QUERY_STRING']);
        $page = $response['body'];
        return $page;
    } else {
        $error = new WP_Error('not found', 'The page cannot be displayed', $url);
        return 'ERROR: page not found.'
        // epfl_library_external_content_log($error);
    }
}

add_shortcode('epfl_library_external_content', 'epfl_library_external_content_process_shortcode');
