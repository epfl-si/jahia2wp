<?php
/**
 * Plugin Name: EPFL
 * Description: Provides many epfl shortcodes 
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

require_once 'lib/utils.php';
require_once 'shortcodes/epfl-news/epfl-news.php';
require_once 'shortcodes/epfl-memento/epfl-memento.php';
require_once 'shortcodes/epfl-toggle/epfl-toggle.php';
require_once 'shortcodes/epfl-cover/epfl-cover.php';
require_once 'shortcodes/epfl-links-group/epfl-links-group.php';
require_once 'shortcodes/epfl-card/epfl-card.php';
require_once 'shortcodes/epfl-video/epfl-video.php';
require_once 'shortcodes/epfl-quote/epfl-quote.php';
require_once 'shortcodes/epfl-map/epfl-map.php';
require_once 'shortcodes/epfl-infoscience-search/epfl-infoscience-search.php';
require_once 'shortcodes/epfl-scheduler/epfl-scheduler.php';
require_once 'shortcodes/epfl-xml/epfl-xml.php';
require_once 'shortcodes/epfl-faq/epfl-faq.php';

// load .mo file for translation
function epfl_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'epfl_load_plugin_textdomain' );

?>