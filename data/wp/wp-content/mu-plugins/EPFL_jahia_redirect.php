<?php
/**
 * Plugin Name: Jahia redirection updater
 * Description: Update Jahia redirection (if any) in .htaccess file when a page permalink is updated
 * @version: 1.8
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
    GOAL: Returns full slug to page. This means having the WordPress install folder (if any)
            with the path to page including its parent.

    IN  : $page -> Object containing the page for which we want full slug    
*/
function jahia_redirection_get_page_full_slug($page)
{
    /* Building page slug, ex: avengers/thor */
    $page_slug = $page->post_name;
    while($page->post_parent != '0')
    {
        $page = get_page($page->post_parent);
        $page_slug = $page->post_name . '/'. $page_slug;
    }
    
    $parsed = parse_url(get_option('siteurl'));

    /* Generating relative path to page from root folder, ex: /marvel/avengers/thor
    This is the new path to access the page */
    return $parsed['path'].'/'.$page_slug;
}



/*
    GOAL: Go through redirection list and comment the one have saved page slug as source.
        This is done to avoid :
        - wrong redirection (we want one existing page and we're redirected to another)
        - infinite loops between several redirections

    IN  : $redirect_list        -> Array with redirections present in .htaccess file
    IN  : $after_page_full_slug -> Full slug (including install dir) to access page after saving
 */
function jahia_redirection_comment_wrong_redirect($redirect_list, $after_page_full_slug)
{

    /* Looping through redirections to check if we have one with the new path as source */
    for($i=0; $i<sizeof($redirect_list); $i++)
    {
        list($redirect, $code, $source, $target) = explode(" ", $redirect_list[$i]);

        /* If we found the new path as a redirection source */
        if($source == $after_page_full_slug)
        {
            /* If line is not already commented */
            if($redirect_list[$i][0] != '#')
            {
                /* We comment it */
                $redirect_list[$i] = '#'.$redirect_list[$i].
                                    ' # This line is commented to avoid infinite loop or wrong redirect because a page using this source has been created/modified.';
            }
            /* Source can only be present one time in redirection list so we can skip others redirection */
            break;
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

    /* In the past, we were using get_home_path() func to have path to .htaccess file. BUT, with WordPress symlinking
      funcionality, get_home_path() returns path to WordPress images files = /wp/
      So, to fix this, we access .htaccess file using WP_CONTENT_DIR which is defined in wp-config.php file. We just
       have to remove 'wp-content'  */
    $htaccess = str_replace("wp-content", ".htaccess", WP_CONTENT_DIR);

    $redirect_list = extract_from_markers( $htaccess, JAHIA_REDIRECT_MARKER);

    /* If no redirection in .htaccess file, */
    if(sizeof($redirect_list)==0) return;

    $before_page_full_slug = jahia_redirection_get_page_full_slug($post_before);
    $after_page_full_slug = jahia_redirection_get_page_full_slug($post_after);

    /* We first comment redirections on trashed pages if any */
    $redirect_list = jahia_redirection_comment_trashed_pages($redirect_list);

    /* Trying to remove wrong redirections and infinite loop. This code is not in the for loop below because it has to 
    be executed event if saved page slug doesn't change */
    $redirect_list = jahia_redirection_comment_wrong_redirect($redirect_list, $after_page_full_slug);

    /* If permalink is different */
    if($before_page_full_slug != $after_page_full_slug)
    {

        /* Looping through redirections to update if necessary */
        for($i=0; $i<sizeof($redirect_list); $i++)
        {
            /* If current entry matches */
            if(preg_match('#'.$before_page_full_slug.'/$#', $redirect_list[$i])===1)
            {
                
                /* We create new .htaccess line */
                $new_line = preg_replace('#'.$before_page_full_slug.'/$#', $after_page_full_slug."/", $redirect_list[$i]);

                list($redirect, $code, $source, $target) = explode(" ", $new_line);
                /* if source and target are the same (we have to add / at the end of source because it doesn't finish with / like $target do) */
                if($source."/" == $target) 
                {
                    if($redirect_list[$i][0] != '#')
                    {
                        /* We comment line because if we let it, we will have an infinite loop*/
                        $redirect_list[$i] = '#'.$redirect_list[$i]. 
                                            ' # Target has changed and became same as source so this line was commented, just to keep a trace of what happened';
                    }
                }
                else /* Source and target are not the same */
                {
                    $redirect_list[$i] = $new_line;
                }

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

            }

        }
    } 

    /* .htaccess update */
    insert_with_markers( $htaccess, JAHIA_REDIRECT_MARKER, $redirect_list );
}

add_action( 'post_updated', 'update_jahia_redirections', 10, 3 );

?>