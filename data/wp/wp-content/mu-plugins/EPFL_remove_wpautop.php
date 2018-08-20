<?php
/*
 * Plugin Name: EPFL remove wpautop from WP
 * Plugin URI: 
 * Description: Must-use plugin for the EPFL 2018 website.
 * Version: 1.0
 * Author: wwp-admin@epfl.ch
 * */
remove_filter( 'the_content', 'wpautop' );
remove_filter( 'the_excerpt', 'wpautop' );
?>
