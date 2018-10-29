<?php
/*
	Initiated when on the "public" web site,
	i.e. - not an Admin panel.
*/

//	Exit if .php file accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/*	Earliest Action Hook possible is 'template_redirect',
	AFTER Rewrite: URL changed with Pretty Permalinks and
	correcting the presence or absence of www. in domain name.
	
	Unfortunately, a wpengine.com (hosting site) mandatory plugin
	appears to be blocking this hook, so the next hook in time sequence
	is being used:
	'get_header'
*/
add_action( 'get_header', 'epfl_intranet_force_login' );

add_action( 'login_init', 'epfl_intranet_login' );
add_filter( 'login_url', 'epfl_intranet_login_url' );

/**
 * Login Detection
 * 
 * Set a global variable, $epfl_intranet_is_login, whenever a login occurs
 *
 * @return   NULL                Nothing is returned
 */
function epfl_intranet_login() {
	global $epfl_intranet_is_login;
	$epfl_intranet_is_login = TRUE;
}

/**
 * Present a login screen to anyone not logged in
 * 
 * Check for already logged in or just logged in.
 * Only called when is_admin() is FALSE
 *
 * @return   NULL                Nothing is returned
 */
function epfl_intranet_force_login() {
	/*	return statements are performed only if User does not need to login.
	
		First, check if User is on a Login panel.
	*/
	global $epfl_intranet_is_login;
	if ( isset( $epfl_intranet_is_login )  || is_user_logged_in() ) {
		return;
	}

	/*	wp_redirect( wp_login_url() ) goes to WP login URL right after exit on the line that follows. */
	wp_redirect( wp_login_url() );
	exit;
}

/**
 * Add Landing Location to Login URL
 * 
 * Although written to modify the Login URL in the Meta Widget,
 * to implement Landing Location, wp_login_url() is also called
 * near the end of epfl_intranet_force_login() above.
 *
 * @param	string	$login_url	Login URL
 * @param	string	$redirect	Path to redirect to on login.	
 * @return	string				Login URL
 */
function epfl_intranet_login_url( $login_url ) {
	/*	remove_query_arg() simply returns $login_url if a ?redirect_to= query is not present in the URL.
	*/
	$url = remove_query_arg( 'redirect_to', $login_url );



	/*	$redirect_to is the URL passed to the standard WordPress login URL,
		via the ?redirect_to= URL query parameter, to go to after login is complete.
	*/
	//	$_SERVER['HTTPS'] can be off in IIS
    if ( empty( $_SERVER['HTTPS'] ) || ( $_SERVER['HTTPS'] == 'off' ) ) {
        $http = 'http://';
    } else {
        $http = 'https://';
    }

	$redirect_to = $http . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

	/*	Also avoids situations where specific URL is requested, 
		but URL is blank.
	*/	
	if ( !empty( $redirect_to ) ) {
		$url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $url );
	}
	return $url;
}



?>