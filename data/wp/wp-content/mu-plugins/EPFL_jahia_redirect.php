<?php
/**
 * Plugin Name: Jahia redirection updater
 * Description: Update Jahia redirection (if any) in .htaccess file when a page permalink is updated
 * @version: 1.0
 * @copyright: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

define('JAHIA_REDIRECT_MARKER', 'Jahia-Page-Redirect');

/**
 * Helper to debug the code
 * @param $var: variable to display
 */
function epfl_jahia_redirect_debug( $var ) {
    print "<pre>";
    var_dump( $var );
    print "</pre>";
}

/*
    GOAL : Update .htaccess redirection file

    IN : $post_id       -> ID of modified post
    IN : $post_after    -> Object with post updated information
    IN : $post_before   -> Object with post information before update

    REFERENCES :
    https://codex.wordpress.org/Plugin_API/Action_Reference/post_updated
    https://developer.wordpress.org/reference/functions/insert_with_markers/
    https://developer.wordpress.org/reference/functions/extract_from_markers/
*/
function update_jahia_redirections($post_id, $post_after, $post_before){

    /* If permalink is still the same */
    if($post_before->post_name == $post_after->post_name) return;

    $htaccess = get_home_path().".htaccess";

    $redirect_list = extract_from_markers( $htaccess, JAHIA_REDIRECT_MARKER);

    /* If no redirection in .htaccess file, */
    if(sizeof($redirect_list)==0) return;

    /* Looping through redirections to update if necessary */
    for($i=0; $i<sizeof($redirect_list); $i++)
    {
        $redirect_list[$i] = preg_replace('/\/'.$post_before->post_name.'\/$/', "/".$post_after->post_name."/", $redirect_list[$i]);
    }

    /* .htaccess update */
    insert_with_markers( $htaccess, JAHIA_REDIRECT_MARKER, $redirect_list );
}

add_action( 'post_updated', 'update_jahia_redirections', 10, 3 );

?>