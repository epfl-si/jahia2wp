<?php
/*
 * Plugin Name: EPFL lock plugin and theme install and configuration
 * Plugin URI: 
 * Description: Must-use plugin for the EPFL website.
 * Version: 0.0.2
 * Author: wwp-admin@epfl.ch
 * */

/* Disable Plugin and Theme Update and Installation.
 *
 * Note : this will also set DISALLOW_FILE_EDIT to true.
 *
 * https://codex.wordpress.org/Editing_wp-config.php
 */
//Plus utils? pour le moment car coupe également ls automatic update des plugins et des traductions
//define( 'DISALLOW_FILE_MODS', true );

/* Disable descativation and edit plug link
 * https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
 *
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
*/
/* Hide plugin configuration
 * https://developer.wordpress.org/reference/hooks/admin_init/

add_action( 'admin_init', 'EPFL_remove_menu_pages' );
function EPFL_remove_menu_pages() {
   remove_menu_page( 'plugins.php' );
}
/* Hide apparence editor menu
 *
 
function EPFL_remove_menu_editor() {
   remove_action('admin_menu', '_add_themes_utility_last', 101);
}
add_action('_admin_menu', 'EPFL_remove_menu_editor', 1);
*/

/* Hide plugin bulk deactivate action
 * https://codex.wordpress.org/Plugin_API/Filter_Reference/bulk_actions
add_filter('bulk_actions-plugins','my_custom_bulk_actions');
function my_custom_bulk_actions($actions){
       unset( $actions[ 'deactivate-selected' ] );
           return $actions;
}
*/

/*
 * Add capabilites to editor to manage options and export
 */
function EPFL_add_editor_caps() {
		$role = get_role( 'editor' );
			$role->add_cap( 'manage_options' ); 
			$role->add_cap( 'export' ); 
}
add_action( 'admin_init', 'EPFL_add_editor_caps');

/* Hide apparence backround and header menu
 * 
 */
add_action( 'after_setup_theme','EPFL_remove_background_header_options', 100 );
function EPFL_remove_background_header_options() {    
   remove_theme_support( 'custom-header');
   remove_theme_support( 'custom-background');
}

/* Hide plugin configuration
 * https://codex.wordpress.org/Plugin_API/Action_Reference/admin_menu
 */
add_action( 'admin_menu', 'EPFL_remove_admin_submenus',999 );
function EPFL_remove_admin_submenus() {
   remove_submenu_page( 'options-general.php', 'options-general.php' );
   remove_submenu_page( 'options-general.php', 'options-permalink.php' );
   remove_submenu_page( 'options-general.php', 'mainwp_child_tab' );
   remove_submenu_page( 'options-general.php', 'epfl_accred' );
   remove_submenu_page( 'options-general.php', 'epfl_tequila' );
}


/* Hide customize menu in admin bar
 * 
 */
add_action( 'wp_before_admin_bar_render', 'EPFL_remove_customize_admin_bar_render' ); 
function EPFL_remove_customize_admin_bar_render()
{
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('customize');
}

/* Remove 'At a glance' and 'Welcome' widgets from Dashboard (because contains link to themes page)
 *
 */
add_action( 'admin_init','EPFL_remove_theme_reference_widget', 100 );
function EPFL_remove_theme_reference_widget() {
    remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
    remove_action('welcome_panel', 'wp_welcome_panel');
}


?>
