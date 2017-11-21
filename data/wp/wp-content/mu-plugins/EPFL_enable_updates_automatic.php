<?php
/*
 * Plugin Name: EPFL Enable automatic update
 * Plugin URI: 
 * Description: Must-use plugin for the EPFL website.
 * Version: 0.0.1
 * Author: wwp-admin@epfl.ch
 * */

/* Allow Automatic Updates
 * http://codex.wordpress.org/Configuring_Automatic_Background_Updates
 */


add_filter( 'allow_dev_auto_core_updates', '__return_true' );          // Enable development updates 
add_filter( 'allow_minor_auto_core_updates', '__return_true' );        // Enable minor updates
add_filter( 'allow_major_auto_core_updates', '__return_true' );        // Enable major updates
add_filter( 'auto_update_plugin', '__return_true' );                   // Enable plugin updates
add_filter( 'auto_update_theme', '__return_true' );                    // Enable theme updates 

?>
