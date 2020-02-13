<?php
/*
* Plugin Name: EPFL block white list
* Plugin URI:
* Description: Must-use plugin for the EPFL website to define allowed blocks coming from Gutenberg or installed plugins.
* Version: 1.0.4
* Author: wwp-admin@epfl.ch
 */

function epfl_allowed_block_types( $allowed_block_types, $post ) {

    /* List of blocks allowed only in Posts
    NOTES:
    - A block cannot be in both list at the same time.
    - For EPFL blocks allowed in Posts, please have a look a wp-epfl-gutenberg plugin (plugin.php) file*/
    $post_only_blocks = array('core/gallery',
        'core/heading',
        'core/image',
        'core/file',
        'core/list',
        'core/spacer',
        'core/separator',
        'pdf-viewer-block/standard',
        'tadv/classic-paragraph');

    $rest_of_allowed_blocks = array(
        'core/gallery',
        'core/classic',
        'core/rss',
        'core/table',
        'core/shortcode',
        'core/freeform',
        'enlighter/codeblock'
    );

    // In all cases post only blocks are allowed
    $allowed_block_types = array_merge($allowed_block_types, $post_only_blocks);

    // If we're not editing a post, we all rest of allowed blocks.
    if($post->post_type != 'post')
    {
        $allowed_block_types = array_merge($allowed_block_types, $rest_of_allowed_blocks);
    }

    // We ensure there's no duplicate
  	return array_unique($allowed_block_types);
    // return True; // if you want all natifs blocks.
}

// Gutenberg is on ?
if (function_exists( 'register_block_type' ) ) {
    // We register this filter with priority 99 to ensure it will be called after the one (if present) added in Gutenberg plugin to
    // register epfl blocks
	add_filter( 'allowed_block_types', 'epfl_allowed_block_types', 99, 2 );
}