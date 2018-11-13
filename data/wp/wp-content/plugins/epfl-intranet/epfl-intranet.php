<?php
/*
 * Plugin Name: EPFL Intranet
 * Description: Use EPFL Accred to allow website access only to specific group(s) or just force to be authenticated
 * Version:     0.10
 * Author:      Lucien Chaboudez
 * Author URI:  mailto:lucien.chaboudez@epfl.ch
 */

namespace EPFL\Intranet;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

if (! class_exists("EPFL\\SettingsBase") ) {
    require_once(dirname(__FILE__) . "/inc/settings.php");
}

/**
Plugin function to translate text
*/
function ___($text)
{
    return __($text, "epfl-intranet");
}

// load .mo file for translation
function epfl_intranet_load_plugin_textdomain()
{
    load_plugin_textdomain( 'epfl-intranet', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'EPFL\Intranet\epfl_intranet_load_plugin_textdomain' );

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

    }

}

class Settings extends \EPFL\SettingsBase
{
    const SLUG = "epfl_intranet";
    var $is_debug_enabled = false;

    function hook()
    {
        parent::hook();
        $this->add_options_page(
	        ___('Intranet settings'),    // $page_title
            ___('EPFL Intranet'),        // $menu_title
            'manage_options');           // $capability

        add_action('admin_init', array($this, 'setup_options_page'));

        /* Website is private */
        if(trim($this->get('enabled'))==1)
        {
            /* If visiting website */
            if(!is_admin())
            {
                require_once(dirname(__FILE__) . "/inc/protect-site.php");
            }
            else /* On admin console */
            {
                $restricted_to_groups = $this->get('subscriber_group', 'epfl_accred');

                /* Only authentication needed*/
                if($restricted_to_groups == "*")
                {
                    $restrict_message = ___("Website access needs Tequila/Gaspar authentication");
                }
                else /* Authentication AND authorization needed*/
                {
                    $restrict_message = sprintf(___("Website access is restricted to following group(s): %s"),
                                            $restricted_to_groups);
                }


                echo '<div class="notice notice-info">'.
                     '<img src="' . plugins_url( 'img/lock.svg', __FILE__ ) . '" style="height:32px; width:32px; float:left; margin:3px 15px 3px 0px;">'.
                     '<p><b>EPFL Intranet - </b> '.$restrict_message.'</p>'.
                     '</div>';
            }
        }


    }

    /***************************************************************************************/
    /************************ Override some methods to fit our needs ***********************/

    /**
     * @return The current setting for $key
     */
    public function get ($key, $force_slug=null)
    {
        $optname = $this->option_name($key, $force_slug);
        if ( $this->is_network_version() ) {
            return get_site_option( $optname );
        } else {
            return get_option( $optname );
        }
    }

    /**
     * @return Update the current setting for $key
     */
    public function update ($key, $value, $force_slug=null)
    {
        $optname = $this->option_name($key, $force_slug);
        if ( $this->is_network_version() ) {
            return update_site_option( $optname );
        } else {
            return update_option( $optname , $value);
        }
    }


    function option_name ($key, $force_slug=null)
    {
        $slug = ($force_slug!==null) ? $force_slug : $this::SLUG;

        if ($this->is_network_version()) {
            return "plugin:" . $slug . ":network:" . $key;
        } else {
            return "plugin:" . $slug . ":" . $key;
        }
    }


    /************************ Override some methods to fit our needs ***********************/
    /***************************************************************************************/


  /*************************************************************************************************/

   /*
      BUT : Add/remove .htaccess content
   */
   function update_htaccess($insertion, $at_beginning=false)
   {

      $filename = get_home_path().'.htaccess';
      $marker = 'EPFL-Intranet';

      return insert_with_markers($filename, $marker, $insertion);

   }


    /**
    * Validate activation/deactivation. In fact we just add things into .htaccess file to protect medias.
    */
    function validate_enabled($enabled)
    {
        /* Website protection is enabled */
        if($enabled == '1')
        {

            /* If prerequisite are not met, */
            if(!$this->check_prerequisites())
            {
                $enabled = '0';

            }
            else
            {

               $lines = array();

               $lines[] = "RewriteEngine On";
               // if requested URL is in media folder,
               $lines[] = "RewriteCond %{REQUEST_URI} wp-content/uploads/";

               // We redirect on a file which will check if logged in (we add path to requested file as parameter
               $lines[] = "RewriteRule wp-content/uploads/(.*)$ wp-content/plugins/epfl-intranet/inc/protect-medias.php?file=$1 [QSA,L]";
               if($this->update_htaccess($lines, true)===false)
               {
                  add_settings_error('cannotUpdateHtAccess',
                                  'empty',
                                  ___("Impossible to update .htaccess file"),
                                  'error');
                  $enabled = '0';
               }
             }
        }
        else /* We don't want to protect website */
        {
           if($this->update_htaccess(array())===false)
           {
              add_settings_error('cannotUpdateHtAccess',
                              'empty',
                              ___("Impossible to update .htaccess file"),
                              'error');
           }
        }

        return $enabled;
    }

    /**
    * Validate entered group list for which to restrict access
    */
    function validate_restrict_to_groups($restrict_to_groups)
    {

        /* If functionality is activated, */
        if($this->get('enabled') == 1)
        {
            $this->debug("Intranet activated");
            /* If access only need authentication */
            if(empty(trim($restrict_to_groups)))
            {
                /* All group have access, Accred plugin will handle this*/
                $epfl_accred_group = '*';
            }
            else /* We have to filter for one (or more) group(s) */
            {
                /* We remove unecessary spaces*/
                $restrict_to_groups = implode(",", array_map('trim', explode(",", $restrict_to_groups) ) );
                $epfl_accred_group = $restrict_to_groups;
            }
        }
        else /* Website protection is disabled */
        {
            $this->debug("Intranet deactivated");
            $epfl_accred_group = "";
        }

        $this->debug("Access restricted to: ". var_export($epfl_accred_group, true));

        /* We update subscribers group for EPFL Accred plugin to allow everyone */
        $this->update('subscriber_group', $epfl_accred_group, 'epfl_accred');

        return $restrict_to_groups;

    }


    /**
    * Check if all dependencies are present
    */
    function check_prerequisites()
    {
        $accred_min_version = 0.11;
        $accred_plugin_relative_path = 'accred/EPFL-Accred.php';
        $accred_plugin_full_path = ABSPATH. 'wp-content/plugins/'. $accred_plugin_relative_path;

        /* Accred Plugin missing */
        if(!is_plugin_active($accred_plugin_relative_path))
        {
            add_settings_error(null,
                  			   null,
                  			    ___("Cannot activate plugin!<br>EPFL-Accred plugin is not installed/activated"),
                  			   'error');
            return false;
        }
        else /* Accred plugin present */
        {
            /* Getting data */
            $plugin_data = get_plugin_data($accred_plugin_full_path);

            /* Check if version is 'vpsi' */
            if(preg_match('/\(vpsi\)\s*$/', $plugin_data['Version'])!==1)
            {
                add_settings_error(null,
                  			   null,
                  			    ___("Cannot activate plugin!<br>This is not 'vpsi' version of EPFL-Accred plugin which is installed"),
                  			   'error');
                return false;
            }
            else /* It's VPSI version */
            {
                /* Version is like:
                0.11 (vpsi) */
                preg_match('/^(\d+\.\d+)\s*\(vpsi\)\s*$/', $plugin_data['Version'], $output);

                /* $output is array like :
                array(0	=>	0.11 (vpsi)
                      1	=>	0.11) */

                error_log(var_export($output, true));
                /* Check min version */
                if(floatval($output[1]) < $accred_min_version)
                {
                    add_settings_error(null,
                  			   null,
                  			    sprintf(___("Cannot activate plugin!<br>EPFL-Accred 'vpsi' plugin version must be at least %s (version %s installed)"),
                                        $accred_min_version, $output[1]),
                  			   'error');

                    return false;
                }

            }
        }

        return true;
    }

    /**
     * Prepare the admin menu with settings and their values
     */
    function setup_options_page()
    {

        $this->add_settings_section('section_about', ___('About'));
        $this->add_settings_section('section_settings', ___('Settings'));


        /* Check box to activate or not the functionality */
        $this->register_setting('enabled', array(
                'type'    => 'boolean',
                'sanitize_callback' => array($this, 'validate_enabled')));

        $enabled = $this->get('enabled');
        if(empty($enabled)) $enabled=0;

        $this->add_settings_field(
                'section_settings',
                'enabled',
                ___("Protect website access"),
                array(
                    'type'        => 'select',
                    'options'     => array(0 => ___('No'), 1 => ___('Yes')),
                    'value'       => $enabled,
                    'help' => ___('If "Yes", authentication is necessary to access website.')
                )
            );


        /* Group list for restriction */
        $this->register_setting('restrict_to_groups', array(
                'type'    => 'text',
                'sanitize_callback' => array($this, 'validate_restrict_to_groups')));

        $this->add_settings_field(
                'section_settings',
                'restrict_to_groups',
                ___("Restrict access to group(s)"),
                array(
                    'type'        => 'text',
                    'help' => ___('If field is left empty, only an authentication will be requested.<br>Several groups can be entered, just separated with a comma.')
                )
            );

    }

    function render_section_about()
    {
        echo "<p>\n";
        echo ___('Needs <a href="https://github.com/epfl-sti/wordpress.plugin.accred/tree/vpsi">EPFL-Accred</a> (VPSI version) plugin
to work correctly. <br>Allows to restrict website access by forcing user to authenticate using
<a href="https://github.com/epfl-sti/wordpress.plugin.tequila">Tequila</a>. <br>You can either only request and authentication,
either force to be member of one of the defined groups (https://groups.epfl.ch).<br>
If plugin is activated, <b>media files are also protected</b>.');
        echo "</p>\n";
    }


    function render_section_settings ()
    {
        // Nothing — The fields in this section speak for themselves
    }


    function debug ($msg)
    {
        if ($this->is_debug_enabled) {
            error_log($msg);
        }
    }


}


Controller::getInstance()->hook();
