<?php

/* Edit this file to site.php to tweak this plugin for your site. */

namespace EPFL\Accred;

if (! defined('ABSPATH')) {
    die('Access denied.');
}

add_action("epfl_accred_403_user_no_role", function() {
    header("Location: " . get_403_url());
});

/**
 * Returns the URL the user is redirect to for a 403 (access denied) error.
 */
function get_403_url()
{	
    $right = "WordPress.Editor";
	
    $unit_id = Controller::getInstance()->settings->get('unit_id');

    $unit_label = Controller::getInstance()->settings->get_ldap_unit_label($unit_id);
	    
    $url = "/global-error/403.php?error_type=accred&right=${right}&unit_id=${unit_id}&unit_label=${unit_label}";

    return $url;
}

// Controller::getInstance()->is_debug_enabled = true;
// Controller::getInstance()->settings->is_debug_enabled = true;

// Uncomment to lock down the EPFL-Accred settings page to the user-serviceable
// parts only, as per VPSI policy.
// Controller::getInstance()->settings->vpsi_lockdown = true;
