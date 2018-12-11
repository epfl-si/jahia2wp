<?php
/*
 * Plugin Name: EPFL Functions
 * Plugin URI: 
 * Description: Must-use plugin for the EPFL website.
 * Version: 0.0.3.1
 * Author: Aline Keller
 * Author URI: http://www.alinekeller.ch
 */

/*
 * File Upload Security
 
 * Sources: 
 * http://www.geekpress.fr/wordpress/astuce/suppression-accents-media-1903/
 * https://gist.github.com/herewithme/7704370
 
 * See also Ticket #22363
 * https://core.trac.wordpress.org/ticket/22363
 * and #24661 - remove_accents is not removing combining accents
 * https://core.trac.wordpress.org/ticket/24661
*/ 

add_filter('robots_txt', 'get_robots_txt');

/**
 * Returns the content of robots.txt. We override the one
 * coming by default to add wp-login.php.
 */
function get_robots_txt($original) {

    $text = "User-agent: *
Disallow: /wp-login.php
Disallow: /wp-admin/
";

    return $text;
}

add_filter( 'sanitize_file_name', 'remove_accents', 10, 1 );
add_filter( 'sanitize_file_name_chars', 'sanitize_file_name_chars', 10, 1 );
 
function sanitize_file_name_chars( $special_chars = array() ) {
	$special_chars = array_merge( array( '’', '‘', '“', '”', '«', '»', '‹', '›', '—', 'æ', 'œ', '€','é','à','ç','ä','ö','ü','ï','û','ô','è' ), $special_chars );
	return $special_chars;
}


/*--------------------------------------------------------------

 # REST API

--------------------------------------------------------------*/

/*
 * Disable display list of users from /wp-json/wp/v2/users/
 */
add_filter( 'rest_endpoints', function( $endpoints ){
        if ( isset( $endpoints['/wp/v2/users'] ) ) {
                    unset( $endpoints['/wp/v2/users'] );
                    }
            if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
                    unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
                    }
            return $endpoints;
});


/*--------------------------------------------------------------
  
 # Content improvements
 
--------------------------------------------------------------*/
 
/*
 * Remove empty <p> tags
 */

add_filter( 'the_content', 'remove_empty_p', 20, 1 );
function remove_empty_p( $content ){
// clean up p tags around block elements
$content = preg_replace( array(
  '#<p>\s*<(div|aside|section|article|header|footer)#',
  '#</(div|aside|section|article|header|footer)>\s*</p>#',
  '#</(div|aside|section|article|header|footer)>\s*<br ?/?>#',
  '#<(div|aside|section|article|header|footer)(.*?)>\s*</p>#',
  '#<p>\s*</(div|aside|section|article|header|footer)#',
  ), array(
  '<$1',
  '</$1>',
  '</$1>',
  '<$1$2>',
  '</$1',
  ), $content );
 
return preg_replace('#<p>(\s|&nbsp;)*+(<br\s*/*>)*(\s|&nbsp;)*</p>#i', '', $content);
}


/*--------------------------------------------------------------
  
 # Gallery improvements
 
--------------------------------------------------------------*/

/*
 * Add the title of an image to it's anchor in WP galleries
 */

function add_title_attachment_link($link, $id = null) {
	$id = intval( $id );
	$_post = get_post( $id );
	$post_title = esc_attr( $_post->post_title );
	return str_replace('<a href', '<a title="'. $post_title .'" href', $link);
}
add_filter('wp_get_attachment_link', 'add_title_attachment_link', 10, 2);

/* 
 * Link to large instead of full size images in galleries
 * http://oikos.org.uk/2011/09/tech-notes-using-resized-images-in-wordpress-galleries-and-lightboxes/
 */

function oikos_get_attachment_link_filter( $content, $post_id, $size, $permalink ) {
 
    // Only do this if we're getting the file URL
    if (! $permalink) {
        // This returns an array of (url, width, height)
        $image = wp_get_attachment_image_src( $post_id, 'large' );
        $new_content = preg_replace('/href=\'(.*?)\'/', 'href=\'' . $image[0] . '\'', $content );
        return $new_content;
    } else {
        return $content;
    }
}
 
add_filter('wp_get_attachment_link', 'oikos_get_attachment_link_filter', 10, 4);


/*--------------------------------------------------------------
  
 # Custom post types
 
--------------------------------------------------------------*/



/*--------------------------------------------------------------

 # File upload extension whitelist

--------------------------------------------------------------*/
function epfl_mimetypes($mime_types){
    $mime_types['ppd'] = 'application/vnd.cups-ppd'; //Adding ppd extension
    return $mime_types;
}
add_filter('upload_mimes', 'epfl_mimetypes', 1, 1);


/*--------------------------------------------------------------
  
 # Shortcodes
 
--------------------------------------------------------------*/

/**
 * Create custom shortcodes
 *
 * @link https://codex.wordpress.org/Shortcode_API
 */

// Désactive wpautop

function remove_wpautop($content) { 
  $content = do_shortcode( shortcode_unautop($content) ); 
  $content = preg_replace( '#^<\/p>|^<br \/>|<p>$#', '', $content );
  return $content;
}
 
// Publications
   
function content_publication_list( $atts, $content = null ) { 
  $return = '<section class="publications clearfix">';
  $return .= do_shortcode($content);
  $return .= '</section>';
  return $return;
}
add_shortcode('list-publications', 'content_publication_list');

function content_publication( $atts, $content = null ) { 
  $return = '<article class="publication clearfix">';
  $return .= do_shortcode($content);
  $return .= '</article>';
  return $return;
}
add_shortcode('publication', 'content_publication');

function links( $atts, $content = null ) { 
  $return = '<p class="links">';
  $return .= do_shortcode(remove_wpautop($content));
  $return .= '</p>';
  return $return;
}
add_shortcode('links', 'links');

function faq_item( $atts, $content = null ) { 
  $a = shortcode_atts( array(
        'title' => 'Title',
    ), $atts );
  $return = '<section class="faq-item"><h3 class="title faq-title" id="">' . esc_attr( $a['title'] ) . '</h3><div class="content">';
  $return .= do_shortcode($content);
  $return .= '</div></section>';
  return $return;
}
add_shortcode('faq-item', 'faq_item');

function colored_box( $atts, $content = null ) { 
  $return = '<section class="colored-box">';
  $return .= do_shortcode($content);
  $return .= '</section>';
  return $return;
}
add_shortcode('colored-box', 'colored_box');


/*--------------------------------------------------------------

 # CloudFlare

--------------------------------------------------------------*/

/* CloudFlare doesn't like the Polylang cookie (or any cookie) */
define('PLL_COOKIE', false);

/*
    If we have 302 redirection on local address, we transform them to 303 to avoid CloudFlare to cache
    them. If we don't do this, we have issues to switch from one language to another (Polylang) because the
    first time we visit the homepage, it does a 302 to default lang homepage and this request is cached in cloudflare
    so it's impossible to switch to the other language
*/
function http_status_change_to_non_cacheable($status, $location) {
      /* We update header to avoid caching when using 302 redirect on local host */
   if($status==302 && strpos($location, $_SERVER['SERVER_NAME'])!==false)
   {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
   }
   return $status;
}
add_filter( 'wp_redirect_status', 'http_status_change_to_non_cacheable', 10, 2);


?>
