<?php
/*
 * Plugin Name: EPFL Functions
 * Plugin URI: 
 * Description: Must-use plugin for the EPFL website.
 * Version: 0.0.8
 * Author: Aline Keller
 * Author URI: http://www.alinekeller.ch
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

    /* Extensions and Mimes types can be found here:
    https://www.lifewire.com/mime-types-by-content-type-3469108
    */

    $mime_types['ppd'] = 'application/vnd.cups-ppd'; //Adding ppd extension
    $mime_types['tex'] = 'application/x-tex'; //Adding tex extension
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

/* CloudFlare doesn't like the Polylang cookie (or any cookie);
 * however, we still want the homepage to use it (and bypass all
 * caches). */
$current_url = $_SERVER["SCRIPT_URL"];
if ($current_url != "/") {
    define('PLL_COOKIE', false);
}

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


function allow_svg_in_tinymce( $init ) {
    /* Code taken from here : https://gist.github.com/Kelderic/f092abf13d5373f245f90ab42e7f885d and
    'script' tag has been removed for security reasons */

	$svgElemList = array(
		'a',
		'altGlyph',
		'altGlyphDef',
		'altGlyphItem',
		'animate',
		'animateColor',
		'animateMotion',
		'animateTransform',
		'circle',
		'clipPath',
		'color-profile',
		'cursor',
		'defs',
		'desc',
		'ellipse',
		'feBlend',
		'feColorMatrix',
		'feComponentTransfer',
		'feComposite',
		'feConvolveMatrix',
		'feDiffuseLighting',
		'feDisplacementMap',
		'deDistantLight',
		'feFlood',
		'feFuncA',
		'feFuncB',
		'feFuncG',
		'feFuncR',
		'feGaussianBlur',
		'feImage',
		'feMerge',
		'feMergeNode',
		'feMorphology',
		'feOffset',
		'fePointLight',
		'feSpecularLighting',
		'feSpotLight',
		'feTile',
		'feTurbulance',
		'filter',
		'font',
		'font-face',
		'font-face-format',
		'font-face-name',
		'font-face-src',
		'font-face-url',
		'foreignObject',
		'g',
		'glyph',
		'glyphRef',
		'hkern',
		'image',
		'line',
		'lineGradient',
		'marker',
		'mask',
		'metadata',
		'missing-glyph',
		'pmath',
		'path',
		'pattern',
		'polygon',
		'polyline',
		'radialGradient',
		'rect',
		'set',
		'source',
		'stop',
		'style',
		'svg',
		'switch',
		'symbol',
		'text',
		'textPath',
		'time',
		'title',
		'tref',
		'tspan',
		'use',
		'view',
        'vkern'
	);

	// extended_valid_elements is the list of elements that TinyMCE allows. This checks
	// to make sure it exists, and then implodes the SVG element list and adds it. The
	// format of each element is 'element[attributes]'. The array is imploded, and turns
	// into something like '...svg[*],path[*]...'

	if ( !isset( $init['extended_valid_elements'] ) ) {
	    $init['extended_valid_elements'] = "";
	}
	else
	{
		$init['extended_valid_elements'] .= ",";
	}
	$init['extended_valid_elements'] .= implode('[*],',$svgElemList).'[*]';


	// return value
	return $init;
}
add_filter('tiny_mce_before_init', 'allow_svg_in_tinymce');


/*
    Add tags present in HTML styleguide (https://epfl-idevelop.github.io/elements/#/) to allow users to use them directly
    in "Text Editor"
*/
function epfl_2018_add_allowed_tags($tags)
{

    /* We extend needed attributes */
    $tags['button']['data-toggle'] = true;
    $tags['button']['data-target'] = true;
    $tags['button']['data-dismiss'] = true;
    $tags['button']['data-content'] = true;
    $tags['button']['aria-expanded'] = true;
    $tags['button']['aria-controls'] = true;
    $tags['button']['aria-label'] = true;
    $tags['button']['aria-haspopup'] = true;
    $tags['button']['aria-hidden'] = true;

    $tags['span']['aria-hidden'] = true;
    $tags['span']['aria-label'] = true;
    $tags['span']['itemprop'] = true;
    $tags['span']['content'] = true;

    $tags['div']['aria-expanded'] = true;
    $tags['div']['aria-labelledby'] = true;
    $tags['div']['itemprop'] = true;
    $tags['div']['itemscope'] = true;
    $tags['div']['itemtype'] = true;

    $tags['a']['data-toggle'] = true;
    $tags['a']['aria-hidden'] = true;
    $tags['a']['aria-controls'] = true;
    $tags['a']['aria-selected'] = true;
    $tags['a']['aria-label'] = true;
    $tags['a']['aria-haspopup'] = true;
    $tags['a']['aria-expanded'] = true;
    $tags['a']['aria-describedby'] = true;
    $tags['a']['tabindex'] = true;
    $tags['a']['accesskey'] = true;
    $tags['a']['itemprop'] = true;
    $tags['a']['data-page-id'] = true;

    $tags['table']['data-tablesaw-mode'] = true;

    $tags['img']['aria-labelledby'] = true;

    $tags['figure']['itemprop'] = true;
    $tags['figure']['itemscope'] = true;
    $tags['figure']['itemtype'] = true;

    $tags['strong']['itemprop'] = true;

    $tags['p']['itemprop'] = true;
    $tags['p']['itemscope'] = true;
    $tags['p']['itemtype'] = true;

    $tags['nav']['aria-label'] = true;
    $tags['nav']['aria-labelledby'] = true;
    $tags['nav']['aria-describedby'] = true;

    $tags['li']['aria-current'] = true;

    $tags['ul']['aria-hidden'] = true;

    /* Some tags are not present in WordPress 4.9.8 so we add them if necessary. Code is done to be compatible if
    tags are added in a future WordPress version */

    if(!array_key_exists('svg', $tags)) $tags['svg'] = [];
    $tags['svg']['class'] = true;
    $tags['svg']['aria-hidden'] = true;

    if(!array_key_exists('use', $tags)) $tags['use'] = [];
    $tags['use']['xlink:href'] = true;

    if(!array_key_exists('time', $tags)) $tags['time'] = [];
    $tags['time']['datetime'] = true;

    if(!array_key_exists('source', $tags)) $tags['source'] = [];
    $tags['source']['media'] = true;
    $tags['source']['srcset'] = true;


    if(!array_key_exists('picture', $tags)) $tags['picture'] = [];


    return $tags;
}
add_filter('wp_kses_allowed_html', 'epfl_2018_add_allowed_tags');


/*
    Deregister all styles which are not necessary for visitor pages

    Based on information found here (section "Disable Plugin Stylesheets in WordPress"):
    https://www.wpbeginner.com/wp-tutorials/how-wordpress-plugins-affect-your-sites-load-time/

    But there's a mistake in the procedure. The CSS ids cannot be used directly to do the job, you have
    to remove the "-css" at the end because it is automatically added by WordPress but the initial
    name used to register style. And to deregister, you have to use the name used to register it
*/
function epfl_deregister_visitor_styles()
{
    if(!is_admin())
    {

        wp_dequeue_style( 'varnish_http_purge' );
        wp_deregister_style( 'varnish_http_purge' );

        wp_dequeue_style( 'wpmf-material-design-iconic-font.min' );
        wp_deregister_style( 'wpmf-material-design-iconic-font.min' );
    }
}
add_action( 'wp_enqueue_scripts', 'epfl_deregister_visitor_styles', 100 );

?>
