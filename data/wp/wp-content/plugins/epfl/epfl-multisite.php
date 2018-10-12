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

add_action('template_redirect', function() {
    if (! class_exists('\SEED_CSP4')) return;
    foreach ($_COOKIE as $cookie_name => $unused_value) {
        if (preg_match('#^wordpress_logged_in_#', $cookie_name)) {
            remove_action('template_redirect', array(\SEED_CSP4::get_instance(), 'render_comingsoon_page'));
            break;
        }
    }
}, 8);
