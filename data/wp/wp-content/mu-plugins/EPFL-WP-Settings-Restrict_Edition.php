<?php
/**
 * Plugin Name: Custom restrictions for EPFL
 * Plugin URI: https://www.epfl.ch
 * Description: Removes the unfiltered_html and unfiltered_upload capabilities to administrator and editor roles
 * Author: Emmanuel Jaep
 * Author URI: hhttps://people.epfl.ch/emmanuel.jaep?lang=en
 * Version: 0.1.0
 * License: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
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

function remove_specific_admin_cap()
{
    epfl_restrict_edition_settings_log("remove_specific_admin_cap called");
    $roles_to_update = array(
        'administrator',
        'editor'
    );
    $admin_caps_to_remove = array(
        'unfiltered_html',
        'unfiltered_upload'
    );

    foreach ($roles_to_update as $role_to_update) {
        $role = get_role($role_to_update);

        epfl_restrict_edition_settings_log("Updating role $role_to_update");

        foreach ($admin_caps_to_remove as $admin_cap_to_remove) {

            epfl_restrict_edition_settings_log("Removing $admin_cap_to_remove capability");
            $role->remove_cap($admin_cap_to_remove);
        }

    }
}

function restore_specific_admin_cap()
{
    epfl_restrict_edition_settings_log("remove_specific_admin_cap called");
    $roles_to_update = array(
        'administrator',
        'editor'
    );
    $admin_caps_to_restore = array(
        'unfiltered_html',
        'unfiltered_upload'
    );

    foreach ($roles_to_update as $role_to_update) {
        $role = get_role($role_to_update);

        epfl_restrict_edition_settings_log("Updating role $role_to_update");

        foreach ($admin_caps_to_restore as $admin_cap_to_restore) {

            epfl_restrict_edition_settings_log("Restoring $admin_cap_to_restore capability");
            $role->add_cap($admin_cap_to_restore);
        }

    }
}

/* Make sure the capabilities are removed when the plugin is used as a MU plugin */
add_action('admin_menu', 'remove_specific_admin_cap');

/* Normal hooks when using this plugin as a regular plugin*/
register_activation_hook(__FILE__, 'remove_specific_admin_cap');
register_deactivation_hook(__FILE__, 'restore_specific_admin_cap');