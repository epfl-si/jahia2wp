<?php
/*
* Plugin Name: EPFL custom editor role menu
* Plugin URI:
* Description: Must-use plugin for the EPFL website.
* Version: 1.0.4
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

// For Gutenberg only, limit and remove some elements
function add_gutenberg_custom_editor_menu() {
	wp_enqueue_script(
		'wp-gutenberg-epfl-custom-editor-menu',
		content_url() . '/mu-plugins/EPFL_gutenberg_custom_editor.js',
		array( 'wp-editor', 'wp-blocks', 'wp-i18n', 'wp-element' )
	);
}

// Gutenberg is on ?
if (function_exists( 'register_block_type' ) ) {
	add_action( 'enqueue_block_editor_assets', 'add_gutenberg_custom_editor_menu' );
}

?>
