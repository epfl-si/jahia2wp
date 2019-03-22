<?php
/*
* Plugin Name: EPFL Disable Comments
* Plugin URI:
* Description: Must-use plugin to disable comments
* Version: 0.1
* Author: Lucien Chaboudez (https://people.epfl.ch/lucien.chaboudez)
 */

// Remove "comment" shortcut from admin menu (on the left)
function epfl_dis_com_remove_menu()
{
   remove_menu_page( 'edit-comments.php' );
}
add_action( 'admin_init', 'epfl_dis_com_remove_menu' );


// Disable widget showing last comments
function epfl_dis_com_disable_rc_widget()
{
    unregister_widget( 'WP_Widget_Recent_Comments' );
}
add_action( 'widgets_init', 'epfl_dis_com_disable_rc_widget' );


function epfl_dis_com_filter_wp_headers( $headers )
{
    unset( $headers['X-Pingback'] );
    return $headers;
}
add_filter( 'wp_headers', 'epfl_dis_com_filter_wp_headers');


function epfl_dis_com_filter_query()
{
    if( is_comment_feed() )
    {
        wp_die( __( 'Comments are closed.' ), '', array( 'response' => 403 ) );
    }
}
add_action( 'template_redirect', 'epfl_dis_com_filter_query', 9 );


// Remove "comment" icon from admin bar
function epfl_dis_com_filter_admin_bar()
{
    if( is_admin_bar_showing() )
    {
        remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
    }
}
add_action( 'template_redirect', 'epfl_dis_com_filter_admin_bar' );
add_action( 'admin_init', 'epfl_dis_com_filter_admin_bar' );


// Deactivate comment form on all elements (posts, medias, ...)
function epfl_dis_com_on_all( $open, $post_id ) {
    return false;
}
add_filter( 'comments_open', 'epfl_dis_com_on_all', 10 , 2 );



wp_deregister_script( 'comment-reply' );
remove_action( 'wp_head', 'feed_links_extra', 3 );