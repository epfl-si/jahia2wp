<?php
/*
 * Plugin Name: EPFL Accred
 * Description: Automatically sync access rights to WordPress from EPFL's institutional data repositories
 * Version:     0.9
 * Author:      Dominique Quatravaux
 * Author URI:  mailto:dominique.quatravaux@epfl.ch
 */

namespace EPFL\Accred;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

if (! class_exists("EPFL\\SettingsBase") ) {
    require_once(dirname(__FILE__) . "/inc/settings.php");
}
require_once(dirname(__FILE__) . "/inc/cli.php");

function ___($text)
{
    return __($text, "epfl-accred");
}

class Roles
{
    private static function _allroles ()
    {
        return array(
            "administrator" => ___("Administrateurs"),
            "editor"        => ___("Éditeurs"),
            "author"        => ___("Auteurs"),
            "contributor"   => ___("Contributeurs"),
            "subscriber"    => ___("Abonnés")
        );
    }

    static function plural ($role)
    {
        return Roles::_allroles()[$role];
    }

    static function keys ()
    {
        return array_keys(Roles::_allroles());
    }

    static function compare ($role1, $role2)
    {
        if ($role1 === null and $role2 === null) return 0;
        if ($role1 === null) return 1;
        if ($role2 === null) return -1;
        $index1 = array_search($role1, Roles::keys());
        $index2 = array_search($role2, Roles::keys());
        return $index1 <=> $index2;
    }
}

class Controller
{
    static $instance = false;
    var $settings = null;
    var $is_debug_enabled = false;

    function debug ($msg)
    {
        if ($this->is_debug_enabled) {
            error_log($msg);
        }
    }

    public function __construct ()
    {
        $this->settings = new Settings();
    }

    public static function getInstance ()
    {
        if ( !self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    function hook()
    {
        $this->settings->hook();
        (new CLI($this))->hook();
        add_action('tequila_save_user', array($this, 'tequila_save_user'));
    }

    /**
     * Create or update the Wordpress user from the Tequila data
     */
    function tequila_save_user($tequila_data)
    {
        $user = get_user_by("login", $tequila_data["username"]);
        $user_role = $this->settings->get_access_level($tequila_data);
        if (! $user_role) {
            $user_role = "";  // So that wp_update_user() removes the right
        }

        if (empty(trim($user_role)) && $user === false) {
            // User unknown and has no role: die() early (don't create it)
            do_action("epfl_accred_403_user_no_role");
            die();
        }

        $userdata = array(
            'user_nicename'  => $tequila_data['uniqueid'],  // Their "slug"
            'nickname'       => $tequila_data['uniqueid'],
            'user_email'     => $tequila_data['email'],
            'user_login'     => $tequila_data['username'],
            'first_name'     => $tequila_data['firstname'],
            'last_name'      => $tequila_data['name'],
            'role'           => $user_role,
            'user_pass'      => null);
        $this->debug(var_export($userdata, true));
        if ($user === false) {
            $this->debug("Inserting user");
            $new_user_id = wp_insert_user($userdata);
            if ( ! is_wp_error( $new_user_id ) ) {
                $user = new \WP_User($new_user_id);
            } else {
                echo $new_user_id->get_error_message();
                die();
            }
        } else {  // User is already known to WordPress
            $this->debug("Updating user");
            $userdata['ID'] = $user->ID;
            $user_id = wp_update_user($userdata);
        }

        if (empty(trim($user_role))) {
            // User with no role, but exists in database: die late
            // (*after* invalidating their rights in the WP database)
            do_action("epfl_accred_403_user_no_role");
            die();
        }
    }
}

class Settings extends \EPFL\SettingsBase
{
    const SLUG = "epfl_accred";
    var $vpsi_lockdown = false;
    var $is_debug_enabled = false2;

    function hook()
    {
        parent::hook();
        $this->add_options_page(
            ___('Réglages de Accred'),                  // $page_title,
            ___('Accred (contrôle d\'accès)'),          // $menu_title,
            'manage_options');                          // $capability
        add_action('admin_init', array($this, 'setup_options_page'));

    }

    /**
    * Validate entered unit label and save unit id in DB if label is correct.
    */
    function validate_unit($unit_label)
    {
        if(empty($unit_label))
        {
            add_settings_error(
			    'unit',
			    'empty',
			    ___('Ne peut pas être vide'),
			    'error'
		    );
        }
        else
        {

            /* Getting LDAP ID from label*/
            $unit_id = $this->get_ldap_unit_id($unit_label);

            if($unit_id === null)
            {
                add_settings_error(
                    'unit',
                    'empty',
                    ___("Unité ".$unit_label." non trouvée dans LDAP"),
                    'error'
                );
            }
            else /* ID has been found, we update it in database */
            {
                $this->update('unit_id', $unit_id);
            }
        }
        return $unit_label;

    }

    /**
     * Prepare the admin menu with settings and their values
     */
    function setup_options_page()
    {

        /* We first get unit ID to update unit label in database if it changed */
        $unit_id = $this->get('unit_id');
        if(!empty($unit_id))
        {
            $unit_label = $this->get_ldap_unit_label($unit_id);
            $this->update('unit', $unit_label);
        }


        $this->add_settings_section('section_about', ___('À propos'));
        $this->add_settings_section('section_help', ___('Aide'));
        $this->add_settings_section('section_settings', ___('Paramètres'));

        foreach ($this->role_settings() as $role => $role_setting) {
            $this->register_setting($role_setting, array(
                'type'    => 'string',
                'default' => ''
            ));
        }

        if (! $this->vpsi_lockdown) {
            $this->register_setting('unit', array(
                'type'    => 'string',
                'sanitize_callback' => array($this, 'validate_unit'),
            ));
            // See ->sanitize_unit()
            $this->add_settings_field(
                'section_settings', 'unit', ___('Unité'),
                array(
                    'type'        => 'text',
                    'help' => ___('Si ce champ est rempli, les droits accred de cette unité sont appliqués en sus des groupes ci-dessous.')
                )
            );
        }

        // Not really a "field", but use the rendering callback mechanisms
        // we have:
        $this->add_settings_field(
            'section_settings', 'admin_groups', ___("Contrôle d'accès par groupe"),
            array(
                'help' => ___('Groupes permettant l’accès aux différents niveaux définis par Wordpress.')
            )
        );
    }

    function render_section_about()
    {
        echo "<p>\n";
        echo ___(<<<ABOUT
<a href="https://github.com/epfl-sti/wordpress.plugin.accred">EPFL-Accred</a>
peut être utilisé avec ou sans le <a
href="https://github.com/epfl-sti/wordpress.plugin.tequila">plug-in
EPFL-tequila</a>. Il crée automatiquement les utilisateurs dans Wordpress,
et synchronise leurs droits depuis les informations institutionnelles
de l'EPFL — Soit depuis Accred, soit depuis un groupe <i>ad
hoc</i>.
ABOUT
);
        echo "</p>\n";
    }

    function render_section_help ()
    {
        echo "<p>\n";
        echo ___(<<<HELP
En cas de problème avec EPFL-Accred veuillez créer une
    <a href="https://github.com/epfl-sti/wordpress.plugin.accred/issues/new"
    target="_blank">issue</a> sur le dépôt
    <a href="https://github.com/epfl-sti/wordpress.plugin.accred/issues">
    GitHub</a>.
HELP
);
        echo "</p>\n";
    }

    function render_section_settings ()
    {
        // Nothing — The fields in this section speak for themselves
    }
    function render_field_admin_groups ()
    {
        $role_column_head  = ___("Rôle");
        $group_column_head = ___("Groupe");
        echo <<<TABLE_HEADER
            <table id="admin_groups">
              <tr><th>$role_column_head</th>
                  <th>$group_column_head</th></tr>
TABLE_HEADER;
        foreach ($this->role_settings() as $role => $role_setting) {
            $input_name = $this->option_name($role_setting);
            $input_value = $this->get($role_setting);
            $access_level = Roles::plural($role);
            echo <<<TABLE_BODY
              <tr><th>$access_level</th><td><input type="text" name="$input_name" value="$input_value" class="regular-text"/></td></tr>
TABLE_BODY;
        }
        echo <<<TABLE_FOOTER
            </table>
TABLE_FOOTER;
    }

    /**
     * @return One of the WordPress roles e.g. "administrator", "editor" etc.
     *         or null if the user designated by $tequila_data doesn't belong
     *         to any of the roles.
     */
    function get_access_level ($tequila_data)
    {
        $this->debug("get_access_level() called for " . var_export($tequila_data, true));
        $access_levels = array(
            $this->get_access_level_from_groups($tequila_data),
            $this->get_access_level_from_accred($tequila_data));
        $this->debug("Before sorting:" . var_export($access_levels, true));
        usort($access_levels, 'EPFL\Accred\Roles::compare');
        $this->debug("After sorting:" . var_export($access_levels, true));
        return $access_levels[0];
    }

    function get_access_level_from_groups ($tequila_data)
    {
        if (empty(trim($tequila_data['group']))) return null;
        $user_groups = explode(",", $tequila_data['group']);

        foreach ($this->role_settings() as $role => $role_setting) {
            $role_group = $this->get($role_setting);
            if (empty(trim($role_group))) continue;

            if (in_array($role_group, $user_groups)) {
                $this->debug("Access level from groups is $role");
                return $role;
            }
        }
        return null;
    }

    function get_access_level_from_accred ($tequila_data)
    {
        $owner_unit_id = trim($this->get('unit_id'));
        if (empty($owner_unit_id)) {
            return null;
        }

        /* Looking for unit label in LDAP because matching is done with it*/
        $owner_unit = strtoupper($this->get_ldap_unit_label($owner_unit_id));
        $this->debug("Owner unit label (toUpper) found for ID '".$owner_unit_id."' = ".$owner_unit);

        if ($this->_find_unit_in_droits($owner_unit, $tequila_data['droit-WordPress.Admin'])) {
            $this->debug("Access level from accred is administrator");
            return "editor";
        } elseif ($this->_find_unit_in_droits($owner_unit, $tequila_data['droit-WordPress.Editor'])) {
            $this->debug("Access level from accred is editor");
            return "editor";
        } else {
            return null;
        }
    }

    function debug ($msg)
    {
        if ($this->is_debug_enabled) {
            error_log($msg);
        }
    }

    function _find_unit_in_droits ($unit, $comma_separated_list_of_units)
    {
        if (empty(trim($comma_separated_list_of_units)) or empty(trim($unit))) {
            return FALSE;
        }
        $found = array_search($unit, explode(",", $comma_separated_list_of_units));
        return ($found !== FALSE);
    }

    function role_settings ()
    {
        $retval = array();
        if ($this->vpsi_lockdown) {
            $roles = ["subscriber"];
        } else {
            $roles = Roles::keys();
        }
        foreach ($roles as $role) {
            $retval[$role] = $role . "_group";
        }
        return $retval;
    }

    function sanitize_unit ($value)
    {
        return strtoupper(trim($value));
    }


    /**
     * Returns the LDAP unit label from it's id.
     */
    function get_ldap_unit_label($unit_id)
    {
        $dn = "o=epfl,c=ch";

        $ds = ldap_connect("ldap.epfl.ch") or die ("Error connecting to LDAP");

        if ($ds === false) {
          return false;
        }

        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

        $result = ldap_search($ds, $dn, "(&(uniqueidentifier=". $unit_id .")(objectclass=EPFLorganizationalUnit))");

        if ($result === false) {
          return false;
        }

        $infos = ldap_get_entries($ds, $result);

        $unit_label = ($infos['count'] > 0) ? $infos[0]['cn'][0]:null;

        ldap_close($ds);

        return $unit_label;
    }

    /**
     * Returns the LDAP unit id from it's label.
     */
    function get_ldap_unit_id($unit_label)
    {
        $dn = "o=epfl,c=ch";

        $ds = ldap_connect("ldap.epfl.ch") or die ("Error connecting to LDAP");

        if ($ds === false) {
          return false;
        }

        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

        $result = ldap_search($ds, $dn, "(&(cn=". $unit_label .")(objectclass=EPFLorganizationalUnit))");

        if ($result === false) {
          return false;
        }

        $infos = ldap_get_entries($ds, $result);

        $unit_id = ($infos['count'] > 0) ? $infos[0]['uniqueidentifier'][0]:null;

        ldap_close($ds);

        return $unit_id;
    }
}


if (file_exists(dirname(__FILE__) . "/site.php")) {
    require_once(dirname(__FILE__) . "/site.php");
}

Controller::getInstance()->hook();
