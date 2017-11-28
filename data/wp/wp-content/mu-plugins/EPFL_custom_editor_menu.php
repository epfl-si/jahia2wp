<?php
/*
* Plugin Name: EPFL custom editor role menu 
* Plugin URI: 
* Description: Must-use plugin for the EPFL website.
* Version: 0.0.1
* Author: wwp-admin@epfl.ch
 */


// Allow editors to see Appearance menu
$role_object = get_role( 'editor' );
$role_object->add_cap( 'edit_theme_options' );
function hide_menu() {
	// Hide theme selection page
	remove_submenu_page( 'themes.php', 'themes.php' );
 
	// Hide customize page
	global $submenu;
	unset($submenu['themes.php'][6]);
}
 
add_action('admin_head', 'hide_menu');
?>
