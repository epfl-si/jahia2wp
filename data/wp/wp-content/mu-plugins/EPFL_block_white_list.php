<?php
/*
* Plugin Name: EPFL block white list
* Plugin URI:
* Description: Must-use plugin for the EPFL website to define allowed blocks
* Version: 1.0.0
* Author: wwp-admin@epfl.ch
 */

function epfl_allowed_block_types( $allowed_block_types, $post ) {
    $blocks = array(
        'epfl/news',
        'epfl/memento',
        'epfl/cover',
        'epfl/cover-dynamic',
        'epfl/toggle',
        'epfl/quote',
        'epfl/people',
        'epfl/map',
        'epfl/introduction',
        'epfl/hero',
        'epfl/google-forms',
        'epfl/video',
        'epfl/scheduler',
        'epfl/tableau',
        'epfl/page-teaser',
        'epfl/custom-teaser',
        'epfl/custom-highlight',
        'epfl/page-highlight',
        'epfl/post-teaser',
        'epfl/post-highlight',
        'epfl/infoscience-search',
        'epfl/social-feed',
        'epfl/contact',
        'epfl/caption-cards',
        'epfl/card',
        'epfl/definition-list',
        'epfl/links-group',
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

  	return $blocks;
    // return True; // if you want all natifs blocks.
}

// Gutenberg is on ?
if (function_exists( 'register_block_type' ) ) {
	add_filter( 'allowed_block_types', 'epfl_allowed_block_types', 10, 2 );
}