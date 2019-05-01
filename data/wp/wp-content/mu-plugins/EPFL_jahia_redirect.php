<?php
/**
 * Plugin Name: Jahia redirection updater
 * Description: Update Jahia redirection (if any) in .htaccess file when a page permalink is updated
 * @version: 1.3
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
    GOAL : Go through given redirection list to see if redirection to trashed pages are commented.
            Commenting redirection of trashed pages wasn't initially done in this plugin so this
            function is just here to have a correct state for all redirections before starting
            to handle pages trashed and untrashed.

    IN   : $redirect_list   -> Array with redirections present in .htaccess file

    RET  : Updated redirection list.
*/
function jahia_redirection_comment_trashed_pages($redirect_list)
{

    /* Looping through redirections to update if necessary */
    for($i=0; $i<sizeof($redirect_list); $i++)
    {
        /* If current entry is trashed and is not commented */
        if(preg_match('/^[^#](.*)__trashed\/$/', $redirect_list[$i])===1)
        {
            /* We comment it */
            $redirect_list[$i] = '#'.$redirect_list[$i];
        }
    }

    return $redirect_list;
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

    /* If function doesn't exists, it means it can be a REST request so we don't do anything */
    if(!function_exists('get_home_path')) return;

    /* Function 'extract_from_markers' is not available anymore in Gutenberg when calling 'post_updated' filter.
    But this happens only if we have 'MainWP Child' plugin enabled... otherwise, it works... don't understand why

    So if it doesn't exists, workaround is to include file in which it is contained. */
    if(!function_exists('extract_from_markers'))
    {
        require_once(ABSPATH. 'wp-admin/includes/misc.php');
    }

    $htaccess = get_home_path().".htaccess";

    $redirect_list = extract_from_markers( $htaccess, JAHIA_REDIRECT_MARKER);

    /* If no redirection in .htaccess file, */
    if(sizeof($redirect_list)==0) return;

    /* We first comment redirections on trashed pages if any */
    $redirect_list = jahia_redirection_comment_trashed_pages($redirect_list);

    /* If permalink is still the same */
    if($post_before->post_name == $post_after->post_name) return;

    /* Looping through redirections to update if necessary */
    for($i=0; $i<sizeof($redirect_list); $i++)
    {
        /* If current entry matches */
        if(preg_match('/\/'.$post_before->post_name.'\/$/', $redirect_list[$i])===1)
        {
            /* We update slug */
            $redirect_list[$i] = preg_replace('/\/'.$post_before->post_name.'\/$/', "/".$post_after->post_name."/", $redirect_list[$i]);

            /* If page is now in trash, */
            if($post_before->post_status != 'trash' && $post_after->post_status == 'trash')
            {
                /* We comment the line so redirection won't be done on trashed page but information will still
                be available in .htaccess in case we restore the page from trash */
                if($redirect_list[$i][0] != '#')
                {
                    $redirect_list[$i] = '#'.$redirect_list[$i];
                }
            }
            /* If page was restored from trash */
            else if($post_before->post_status == 'trash' && $post_after->post_status != 'trash')
            {
                /* If line is commented, we remove comment */
                if($redirect_list[$i][0] == '#')
                {
                    $redirect_list[$i] = substr($redirect_list[$i],1);
                }
            }

            /* We can exit the loop because we found page slug and it is unique so continue looking is useless */
            break;
        }

    }

    /* .htaccess update */
    insert_with_markers( $htaccess, JAHIA_REDIRECT_MARKER, $redirect_list );
}

add_action( 'post_updated', 'update_jahia_redirections', 10, 3 );

?>