<?php
/*
* Plugin Name: EPFL block white list
* Plugin URI:
* Description: Must-use plugin for the EPFL website to define allowed blocks coming from Gutenberg or installed plugins.
* Version: 1.0.2
* Author: wwp-admin@epfl.ch
 */

function epfl_allowed_block_types( $allowed_block_types, $post ) {

    $blocks_to_add = array(
        'core/paragraph',
        'core/heading',
        'core/gallery',
        'core/classic',
        'core/rss',
        'core/table',
        'core/spacer',
        'core/separator',
        'core/shortcode',
        'core/freeform',
        'core/list',
        'core/image',
        'core/file',
        'tadv/classic-paragraph',
        'pdf-viewer-block/standard',
    );

    foreach($blocks_to_add as $block_name)
    {
        $allowed_block_types[] = $block_name;
    }


  	return $allowed_block_types;
    // return True; // if you want all natifs blocks.
}

// Gutenberg is on ?
if (function_exists( 'register_block_type' ) ) {
    // We register this filter with priority 99 to ensure it will be called after the one (if present) added in Gutenberg plugin to
    // register epfl blocks
	add_filter( 'allowed_block_types', 'epfl_allowed_block_types', 99, 2 );
}