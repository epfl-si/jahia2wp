<?php
/*
* Plugin Name: EPFL custom editor role menu
* Plugin URI:
* Description: Must-use plugin for the EPFL website.
* Version: 1.0.3
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

function my_plugin_allowed_block_types( $allowed_block_types, $post ) {

    /* Recovering white list from option */
    $generic_blocks = get_option('epfl:gutenberg:generic-blocks', '');
    $generic_blocks = (trim($generic_blocks) == '')? array() : explode(",", $generic_blocks);

    $specific_blocks = get_option('epfl:gutenberg:specific-blocks', '');
    $specific_blocks = (trim($specific_blocks) == '')? array() : explode(",", $specific_blocks);

    /* Merging generic and specific and removing duplicates if any */
    $blocks = array_unique( array_merge($generic_blocks, $specific_blocks));

  	return (sizeof($blocks)==0)? True : $blocks;
    // return True; // if you want all natifs blocks.
}

// Gutenberg is on ?
if (function_exists( 'register_block_type' ) ) {
	add_action( 'enqueue_block_editor_assets', 'add_gutenberg_custom_editor_menu' );
	add_filter( 'allowed_block_types', 'my_plugin_allowed_block_types', 10, 2 );
}

?>
