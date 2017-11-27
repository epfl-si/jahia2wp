<?php
/*
 * Plugin Name: EPFL lock plugin and theme install and configuration
 * Plugin URI: 
 * Description: Must-use plugin for the EPFL website.
 * Version: 0.0.1
 * Author: wwp-admin@epfl.ch
 * */

/* Disable Plugin and Theme Update and Installation
 * https://codex.wordpress.org/Editing_wp-config.php
 */
define( 'DISALLOW_FILE_MODS', true );

/* Disable the Plugin and Theme Editor
 * https://codex.wordpress.org/Editing_wp-config.php
 */
define( 'DISALLOW_FILE_EDIT', true );

/* Disable descativation and edit plug link
 * https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
 */
add_filter( 'plugin_action_links', 'disable_plugin_deactivation', 10, 4 );
function disable_plugin_deactivation( $actions, $plugin_file, $plugin_data, $context ) {
   // Remove edit link for all
   if ( array_key_exists( 'edit', $actions ) )
     	unset( $actions['edit'] );
   // Remove deactivate link for crucial plugins
   //if ( array_key_exists( 'deactivate', $actions ) && in_array( $plugin_file, array(
   //      'mainwp-child/mainwp-child.php' ,'miniorange-saml-20-single-sign-on/login.php'
   //      )))
   
   // Remove deactivate link for crucial plugins
   if ( array_key_exists( 'deactivate', $actions ) ) 
   unset( $actions['deactivate'] );
   return $actions;
}

/* Hide plugin configuration
 * https://developer.wordpress.org/reference/hooks/admin_init/
 */
add_action( 'admin_init', 'EPFL_remove_menu_pages' );
function EPFL_remove_menu_pages() {
	remove_menu_page( 'mo_saml_settings' ); 
}


/* Hide plugin configuration
 * https://codex.wordpress.org/Plugin_API/Action_Reference/admin_menu
 */
add_action( 'admin_menu', 'EPFL_remove_admin_submenus',999 );
function EPFL_remove_admin_submenus() {
	remove_submenu_page( 'options-general.php', 'addtoany' );
	remove_submenu_page( 'options-general.php', 'mainwp_child_tab' );
	remove_submenu_page( 'options-general.php', 'epfl_accred' );
	remove_submenu_page( 'options-general.php', 'epfl_tequila' );
}
?>
