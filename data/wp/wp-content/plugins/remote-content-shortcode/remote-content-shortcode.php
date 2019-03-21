<?php
/*
Plugin Name: Remote Content Shortcode
Plugin URI: http://www.doublesharp.com
Description: Embed remote content with a shortcode
Author: Justin Silver
Version: 1.4.3
Author URI: http://doublesharp.com
License: GPL2
*/

if ( ! class_exists( 'RemoteContentShortcode' ) ):

class RemoteContentShortcode {

	private static $instance;

	private function __construct() { }

	public static function init() {
		if ( ! is_admin() && ! self::$instance ) {
			self::$instance = new RemoteContentShortcode();
			self::$instance->add_shortcode();
		}
	}

    // Parse response to extract headers (as associative array) and response.
	private function extract_header_and_response($header_size, &$headers, &$response){

        // Extracting headers and response because everything is concatenated
        $header_str = substr($response, 0, $header_size);
        $response = substr($response, $header_size);

        // We put all header info in an associative array
        $headers = array();
        foreach (explode("\n", $header_str) as $header)
        {
            if (preg_match('/^([^:]+):(.*)$/', trim($header), $output)!==1) continue;
            $headers[$output[1]] = trim($output[2]);
        }
	}


    // Returns encoding present in header (or default encoding if not present)
	private function get_encoding($headers)
	{
	    // Encoding used by default when nothing is specified in header
	    $default_encoding = "ISO-8859-1";

        // In normal cases, this index should be present in header but we test just in case...
	    if(!array_key_exists('Content-Type', $headers)) return $default_encoding;

	    if(preg_match('/charset=(.*)/i', $headers['Content-Type'], $output)==1)
	    {
	        return $output[1];
	    }
	    // If not specified in header, we return default one
	    return $default_encoding;
	}


	public function add_shortcode(){
		add_shortcode( 'remote_content', array( &$this, 'remote_content_shortcode' ) );
	}

	public function remote_content_shortcode( $atts, $content=null ) {
		// decode and remove quotes, if we wanted to (for use with SyntaxHighlighter)
		if ( isset( $atts['decode_atts'] ) ) {
			switch ( strtolower( html_entity_decode( $atts['decode_atts'] ) ) ) {
			 	case 'true': case '"true"':
					foreach ( $atts as $key => &$value ) {
						$value = html_entity_decode( $value );
						if ( strpos( $value, '"' ) === 0 ) $value = substr( $value, 1, strlen( $value ) - 2 );
					}
			 		break;
			 	default:
			 		break;
			}
		}

		$atts = shortcode_atts(
			array(
				'userpwd' => false,
				'method' => 'GET',
				'timeout' => 10,
				'url' => '',
				'selector' => false,
				'remove' => false,
				'find' => false,
				'replace' => false,
				'htmlentities' => false,
				'params' => false,
				'strip_tags' => false,
				'cache' => true,
				'cache_ttl' => 3600,
			),
			$atts
		);

		// convert %QUOT% to "
		$atts['find'] = $this->quote_replace( $atts['find'] );
		$atts['replace'] = $this->quote_replace( $atts['replace'] );

		// extract attributes
		extract( $atts );

		// normalize parameters
		$is_cache = strtolower( $cache ) != 'false';
		$is_htmlentities = strtolower( $htmlentities ) == 'true';
		$is_strip_tags = strtolower( $strip_tags ) == 'true';
		$method = strtoupper( $method );

		$group = 'remote_content_cache';
		$key = implode( $atts );
		$error = false;
		if ( ! $is_cache || false === ( $response = wp_cache_get( $key, $group ) ) ){

			// if we don't have a url, don't bother
			if ( empty( $url ) ) return;

			// Check if IP is in the EPFL range
			$host = parse_url($url, PHP_URL_HOST);
    		$ip = gethostbyname($host);
			$ip_regex = "/^128\.17(8|9)/";
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				$ip_regex = "/^2001:620:618:/";
			}
			if (preg_match( $ip_regex, $ip ) !== 1) {
				return '<h3 style="color:red;">URL ' . $url . ' is outside of EPFL</h3>';
			}

			// change ampersands back since WP will encode them between the visual/text editor
			if ( strpos( $url, '&amp;' ) !== false ) {
				$url = str_replace( '&amp;', '&', $url );
			}

			// inherit params from the parent page query string
			if ( ! empty($params) ) {
				if (strpos($url, '?') === false) {
					$url .= '?';
				}

				$qs = explode( ',', trim( $params ) );

				foreach ( $qs as $q ) {
					$q = trim( $q );
					if ( strpos( $q, '=') !== false ) {
						$p = explode('=', $q );
						$q = trim( $p[0] );
						$v = trim( $p[1] );
					}
					if ( isset( $_REQUEST[$q] ) ) {
						$v = $_REQUEST[$q];
					}
					$url .= "&{$q}={$v}";
				}
			}

			// apply filters to the arguments
			$url = apply_filters( 'remote_content_shortcode_url', $url );
			$content = apply_filters( 'remote_content_shortcode_postfields', $content, $url );
			$ssl_verifyhost = apply_filters( 'remote_content_shortcode_ssl_verifyhost', false, $url );
			$ssl_verifypeer = apply_filters( 'remote_content_shortcode_ssl_verifypeer', false, $url );

			// get the user:password BASIC AUTH
			if ( ! empty( $userpwd ) ){
				global $post;
				if ( false!==( $meta_userpwd = get_post_meta( $userpwd, $post->ID, true ) ) ) {
					// if the userpwd is a post meta, use that
					$userpwd = $meta_userpwd;
				} elseif ( false !== ( $option_userpwd = get_option( $userpwd ) ) ) {
					// if the userpwd is a site option, use that
					$userpwd = $option_userpwd;
				} elseif ( defined( $userpwd ) ) {
					// if the userpwd is a constant, use that
					$userpwd = constant( $userpwd );
				}
				/* lastly assume the userpwd is plaintext, this is not safe as it will be
				 displayed in the browser if this plugin is disabled */
			}

			// set up curl
			$ch = curl_init();
			// the url to request
			curl_setopt( $ch, CURLOPT_URL, $url );
			// set a timeout
			curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
			// return to variable
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			// (don't) verify host ssl cert
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, $ssl_verifyhost );
			// (don't) verify peer ssl cert
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, $ssl_verifypeer );
			// Allow URLs to be redirected (301) on another address
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			// To also have header information to have charset used to encode page
			curl_setopt($ch, CURLOPT_HEADER, 1);
			// send a user:password
			if ( ! empty( $userpwd ) ) {
				curl_setopt( $ch, CURLOPT_USERPWD, $userpwd );
			}
			// optionally POST
			if ( $method == 'POST' ) {
				curl_setopt( $ch, CURLOPT_POST, true );
			}
			// send content of tag
			if ( ! empty( $content ) ) {
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $content );
			}
			// fetch remote contents
			if ( false === ( $response = curl_exec( $ch ) ) )	{
				// if we get an error, use that
				$error = curl_error( $ch );
			}

			// Getting header size for later
			if($response) $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			// close the resource
			curl_close( $ch );

			if ( $response ){

                // Extracting response and headers (as array)
                $headers = array();
                $this->extract_header_and_response($header_size, $headers, $response);

                // We ensure that function exists otherwise, if we have to re-encode, this will leads to a
                // 500 Internal Server error. To have this function, package "mbstring" must be installed
                if(function_exists('mb_convert_encoding'))
                {

                    $response_encoding = $this->get_encoding($headers);

                    // If response is not encoded using UTF-8
                    if(strtolower($response_encoding) != "utf-8")
                    {
                        // we re-encode it to have UTF-8
                        $response = mb_convert_encoding($response, "UTF-8", $response_encoding);
                    }
                }

				if ( $selector || $remove ){
					// include phpQuery
					include_once( 'inc/phpQuery.php' );
					// filter the content
					$response = apply_filters( 'remote_content_shortcode_phpQuery', $response, $url, $selector, $remove );
					// load the response HTML DOM
					phpQuery::newDocument( $response );
					// $remove defaults to false
					if ( $remove ) {
						// remove() the elements
						pq( $remove )->remove();
					}
					// use a CSS selector or default to everything
					$response = pq( $selector );
				}

				// perform a regex find and replace
				if ( $find ) {
					$response = preg_replace( $find, $replace | '', $response );
				}

				// strip the tags
				if ( $is_strip_tags ) {
					$response = strip_tags( $response );
				}

				// HTML encode the response
				if ( $is_htmlentities ) {
					$response = htmlentities( $response );
				}



			} else {
				// send back the error unmodified so we can debug
				$response = $error;
			}

			// Cache the result based on the TTL
			if ( $is_cache ) {
				wp_cache_set( $key, $response, $group, $cache_ttl );
			}
		}
		
		// filter the response
		return apply_filters( 'remote_content_shortcode_return', $response, $url );
	}

	private function quote_replace( $input ){
		if ( ! $input ) return false;
		return str_replace( '%QUOT%', '"', strval( $input ) );
	}
}

// init the class/shortcode
RemoteContentShortcode::init();

endif; //class exists
