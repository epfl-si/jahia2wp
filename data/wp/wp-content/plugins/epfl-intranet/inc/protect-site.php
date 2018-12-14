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

    /* Defining URL on which we have to redirect to */
	$http = ( empty( $_SERVER['HTTPS'] ) || ( $_SERVER['HTTPS'] == 'off' ) )?'http://':'https://';
	$after_login_url = $http . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

	/* Forcing cache-control entry from headers otherwise, if we already have a Tequila token but we are not currently
	authenticated on present WordPress website, we will redirect (below) to "wp-login.php" but this redirect will be
	cached. This mean that access to current URL and redirect to wp-login.php is cached.
	And when we will come back from Tequila (without any login because we already have the token), we will be redirected
	(locally it seems) to primary asked URL and the cache will answer and tell to redirect on wp-login.php... so we will
	be stuck in an infinite loop that could destroy the world!
	The only way to fix this is to update the header specifying we must not cache the page.
	And, this problem does not occurs when we primary authenticate on Tequila (if we don't have any token) because we
	will go on Tequila website and come back on WordPress. And in this case, cache is not used. */
	header('Cache-Control: no-cache, no-store, must-revalidate');

	/*	wp_redirect( wp_login_url() ) goes to WP login URL right after exit on the line that follows. */
	wp_redirect( wp_login_url($after_login_url) );
	exit;
}

?>