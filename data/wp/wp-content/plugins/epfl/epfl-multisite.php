<?php

/* Copyright © 2018 École Polytechnique Fédérale de Lausanne, Switzerland */
/* All Rights Reserved, except as stated in the LICENSE file. */
/**
 * Special (non-menu) provisions for EPFL-style nested-site deployments
 */

namespace EPFL\Multisite;

if (! defined( 'ABSPATH' )) {
    die( 'Access denied.' );
}

function has_wordpress_cookie () {
    foreach ($_COOKIE as $cookie_name => $unused_value) {
        if (preg_match('#^wordpress_logged_in_#', $cookie_name)) {
            return true;
        }
    }
    return false;
}

function get_logged_out_redirect_url () {
    return get_option("epfl-multisite-logged-out-redirect");
}

add_action('template_redirect', function() {
    $redirect = get_logged_out_redirect_url();
    if ($redirect && ! has_wordpress_cookie()) {
        header("Location: $redirect");
        exit;
    }
});
