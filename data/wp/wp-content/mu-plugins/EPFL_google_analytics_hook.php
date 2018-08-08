<?php
/*
 * Plugin Name: EPFL Google Analytics connector
 * Plugin URI:
 * Description: Must-use plugin for the EPFL website.
 * Version: 1.0
 * Author: wwp-admin@epfl.ch
 * */

/* Hook that add the Google Analytics header to all pages
 *
 * https://premium.wpmudev.org/blog/create-google-analytics-plugin/
 */

function google_analytics_connector_render() {

    # TODO: get a right value / strategy for the fallback
    if (!defined('GA_TRACKING_ID')) {
        define('GA_TRACKING_ID', 'UA-20398423-1');
    }

    $GA_hook = '<!-- Global Site Tag (gtag.js) - Google Analytics -->';
    $GA_hook .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . GA_TRACKING_ID . '"></script>';
    $GA_hook .= '<script>';
    $GA_hook .= '    window.dataLayer = window.dataLayer || [];';
    $GA_hook .= '    function gtag(){dataLayer.push(arguments);}';
    $GA_hook .= '    gtag("js", new Date());';
    $GA_hook .= '    gtag("config", "' . GA_TRACKING_ID . '", { "anonymize_ip": true });';

    $GA_hook .= '</script>';

    return $GA_hook;
}

add_action('wp_head', 'google_analytics_connector_render', 10);
?>
