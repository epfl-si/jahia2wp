<?php
/*
 * Plugin Name: EPFL disable all automatic updates.
 * Plugin URI: 
 * Description: Must-use plugin for the EPFL website.
 * Version: 0.0.1
 * Author: wwp-admin@epfl.ch
 * */

/* Disable all automatic updates.
 *
 * http://codex.wordpress.org/Configuring_Automatic_Background_Updates
 */

// disable WordPress Core minor updates
add_filter( 'allow_minor_auto_core_updates', '__return_false' );

// disable WordPress Core major updates
add_filter( 'allow_major_auto_core_updates', '__return_false' );

// disable plugins updates
add_filter( 'auto_update_plugin', '__return_false' );

// disable themes updates
add_filter( 'auto_update_theme', '__return_false' );

// disable transalations updates
add_filter( 'auto_update_translation', '__return_false' );

?>
