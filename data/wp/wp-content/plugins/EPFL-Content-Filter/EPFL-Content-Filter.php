<?php
/**
 * Plugin Name: EPFL Content Filter
 * Plugin URI: https://www.epfl.ch
 * Description: Removes the unfiltered_html and unfiltered_upload capabilities to administrator and editor roles
 * Author: Emmanuel Jaep
 * Author URI: hhttps://people.epfl.ch/emmanuel.jaep?lang=en
 * Version: 0.1.0
 * License: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */


function epfl_restrict_edition_settings_log($message)
{
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

/*
    GOAL: Add or remove specific capabilities for 2 roles.

    IN  : $add -> TRUE  = Add capabilities
                  FALSE = Remove capabilities
*/
function epfl_add_remove_specific_admin_cap($add)
{
    $action = ($add)?"Adding":"Removing";

    epfl_restrict_edition_settings_log("add_remove_specific_admin_cap called (add=".(($add)?'true':'false').")");
    $roles_to_update = array(
        'administrator',
        'editor'
    );
    $admin_caps = array(
        'unfiltered_html',
        'unfiltered_upload'
    );

    foreach ($roles_to_update as $role_to_update) {
        $role = get_role($role_to_update);

        epfl_restrict_edition_settings_log("Updating role $role_to_update...");

        foreach ($admin_caps as $admin_cap) {

            epfl_restrict_edition_settings_log("$action $admin_cap capability");
            if($add)
            {
                $role->add_cap($admin_cap);
            }
            else
            {
                $role->remove_cap($admin_cap);
            }
        }
    }
}

function epfl_remove_specific_admin_cap()
{
    epfl_add_remove_specific_admin_cap(false);
}

function epfl_restore_specific_admin_cap()
{
    epfl_add_remove_specific_admin_cap(true);
}

/* Normal hooks when using this plugin as a regular plugin*/
register_activation_hook(__FILE__, 'epfl_remove_specific_admin_cap');
register_deactivation_hook(__FILE__, 'epfl_restore_specific_admin_cap');