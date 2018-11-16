<?php

/**
 * Preproduction hacks and quirks
 */

namespace EPFL\Preprod;

function is_subscriber () {
    if (! is_user_logged_in()) return false;

    $user = wp_get_current_user();
    foreach ($user->roles as $role) {
        if ($role === 'subscriber') {
            return true;
        }
    }
    return false;
}

// Not sure if useful
add_filter('wp_headers', function($headers, $that) {
    $has_cache_control_header = false;
    foreach (headers_list() as $header) {
        if (preg_match('/^cache-control/i', $header)) {
            $has_cache_control_header = true;
            break;
        }
    }

    if ($has_cache_control_header && is_subscriber()) {
        // WP wants to prevent caching for any logged-in user, which
        // defeats our plans in the case of "VIP" preview users
        unset($headers['Cache-Control']);
    }

    return $headers;
}, 10, 2);


add_filter( 'cache_control_nocacheables', function($orig_cacheability) {
    if (is_subscriber()) {
        return false;
    }
});
