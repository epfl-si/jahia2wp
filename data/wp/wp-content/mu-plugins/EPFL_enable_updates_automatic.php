<?php
/*
 * Plugin Name: EPFL enable all automatic updates.
 * Plugin URI: 
 * Description: Must-use plugin for the EPFL website.
 * Version: 0.0.1
 * Author: wwp-admin@epfl.ch
 * */

/* Enable all automatic updates.
 *
 * http://codex.wordpress.org/Configuring_Automatic_Background_Updates
 */

// enable WordPress Core minor updates
add_filter( 'allow_minor_auto_core_updates', '__return_true' );

// enable WordPress Core majour updates
add_filter( 'allow_major_auto_core_updates', '__return_true' );

// enable plugins updates
add_filter( 'auto_update_plugin', '__return_true' );

// enable themes updates
add_filter( 'auto_update_theme', '__return_true' );

?>
