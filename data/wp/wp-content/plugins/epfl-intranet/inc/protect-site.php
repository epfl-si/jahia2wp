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

/**
 * Present a login screen to anyone not logged in
 * 
 * Check for already logged in or just logged in.
 * Only called when is_admin() is FALSE
 *
 * @return   NULL                Nothing is returned
 */
function epfl_intranet_force_login() {
	/* If user is logged in, we can exit */
	if (is_user_logged_in() ) {
		return;
	}

	/*	wp_redirect( wp_login_url() ) goes to WP login URL right after exit on the line that follows. */
	wp_redirect( wp_login_url() );
	exit;
}

?>