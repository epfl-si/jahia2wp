<?php

// For Gutenberg only, limit and remove some elements
function add_gutenberg_custom_editor_menu() {
	wp_enqueue_script(
		'wp-gutenberg-epfl-custom-editor-menu',
		content_url() . '/mu-plugins/epfl-custom-editor-menu/epfl-custom-editor-menu.js',
		array( 'wp-editor', 'wp-blocks', 'wp-i18n', 'wp-element' )
	);
}

// Gutenberg is on ?
if (function_exists( 'register_block_type' ) ) {
	add_action( 'enqueue_block_editor_assets', 'add_gutenberg_custom_editor_menu' );
}

?>
