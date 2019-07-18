<?php
/**
 * Plugin Name: EPFL Courses Search Engine plugin
 * Description: Plugin to display and search EPFL courses
 * Version: 0.1
 * Author: CAPE - Ludovic Bonivento
 * Copyright: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

require_once('include/db_init.php');

include 'include/admin_menu.php';
include 'include/courses_search_engine.php';

/******** CREATE DB AT PLUGIN ACTIVATION **********/
function plugin_activate() {
	EPFLCOURSESSEDB::init();
}

/******** DELETE DB AT PLUGIN UNINSTALL **********/
function plugin_uninstall() {
	EPFLCOURSESSEDB::dropAll();
}

/************ LOAD CSS & JS ********************/
function load_plugin_css() {
    wp_enqueue_style( 'default-css', plugins_url( '/css/default.css', __FILE__ ));
    wp_enqueue_style( 'jqcloud-css', plugins_url('/css/jqcloud.css', __FILE__ ));   
    wp_enqueue_style('autocomplete',plugins_url( '/css/auto-complete.css', __FILE__ ));    
}
add_action( 'wp_enqueue_scripts', 'load_plugin_css' );

function load_plugin_js() {
	
	wp_enqueue_script('auto-complete',plugins_url( '/js/auto-complete.js', __FILE__ ));//,array('epfl-js-jquery'));    
	wp_enqueue_script('jqcloud-js', plugins_url( '/js/jqcloud.js', __FILE__ ),array('epfl-js-jquery'));
	wp_localize_script( 'my_ajax_script', 'my_ajax_url', admin_url( 'admin-ajax.php' ) );

}
add_action('wp_enqueue_scripts','load_plugin_js');

/************ ADMIN MENU PAGE ********************/
  
// Hook for adding admin menu
add_action('admin_menu', 'epflcse_plugin_create_menu');



// action function for above hook
function epflcse_plugin_create_menu() {
    
    // Add a new top-level menu (ill-advised):
    add_menu_page(__('EPFLCoursesSE','epflcse-plugin-menu'), __('EPFLCoursesSE','epflcse-plugin-menu'), 'manage_options', 'epflcse-admin', 'load_admin_menu_page' );
}

function epflcse_settings_init() {
	//register our settings
	register_setting( 'epflcse-settings-group', 'epflcse-settings' );
	add_settings_section( 'global', __( 'Global', 'epflcse-plugin' ), 'global_callback', 'epflcse-plugin');
	add_settings_field( 'years', __( 'Years', 'epflcse-plugin' ), 'years_callback', 'epflcse-plugin', 'global');
	add_settings_field( 'section', __( 'Section', 'epflcse-plugin' ), 'section_callback', 'epflcse-plugin', 'global');
	add_settings_field( 'use_polyperspectives', __( 'Use POLY-Perspectives', 'epflcse-plugin' ), 'use_polyperspectives_callback', 'epflcse-plugin', 'global');
	add_settings_field( 'user_keywords', __( 'Use Keywords', 'epflcse-plugin' ), 'use_keywords_callback', 'epflcse-plugin', 'global');
}
// Register settings to admin_init
add_action( 'admin_init', 'epflcse_settings_init' );

// mt_toplevel_page() displays the page content for the plugin Toplevel menu
function load_admin_menu_page() {
    echo display_admin_menu();
}

/************ COURSES SEARCH ENGINE PAGE ********************/
function load_courses_se(){
	echo display_courses_se();
}
add_shortcode( 'epfl-courses-se', 'load_courses_se' );

/************ load .mo file for translation ********************/
function epflcse_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-courses-se', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'epflcse_load_plugin_textdomain' );

/************ Activation and uninstall ********************/
register_activation_hook(__FILE__, 'plugin_activate');
register_uninstall_hook( __FILE__, 'plugin_uninstall');
?>