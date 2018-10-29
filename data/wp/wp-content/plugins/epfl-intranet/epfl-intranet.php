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

function ___($text)
{
    return __($text, "epfl-intranet");
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
        //add_action('tequila_save_user', array($this, 'tequila_save_user'));
    }

}

class Settings extends \EPFL\SettingsBase
{
    const SLUG = "epfl_intranet";
    var $is_debug_enabled = true;

    function hook()
    {
        parent::hook();
        $this->add_options_page(
	        ___('Réglages intranet'),    // $page_title
            ___('EPFL Intranet'),        // $menu_title
            'manage_options');           // $capability

        add_action('admin_init', array($this, 'setup_options_page'));


        if(!is_admin())
        {
            if(trim($this->get('enabled'))==1)
            {
                require_once(dirname(__FILE__) . "/inc/protect-site.php");
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
      BUT : Add/remove .htaccess content, at the specified location

            This function is an enhancement of WordPress function 'insert_with_markers'.
            https://developer.wordpress.org/reference/functions/insert_with_markers/

      IN  : $insertion     -> Array with lines to insert
      IN  : $at_beginning  -> To tell if we have to add it at the beginning of the file or not.
   */
   function update_htaccess($insertion, $at_beginning=false)
   {
      $filename = get_home_path().'.htaccess';
      $marker = 'EPFL-Intranet';

      if(!file_exists($filename))
      {
         if(!is_writable(dirname($filename)))
         {
            return false;
         }
         if(!touch($filename))
         {
            return false;
         }
      }
      elseif(!is_writeable($filename))
      {
         return false;
      }

      if(!is_array($insertion))
      {
         $insertion = explode("\n", $insertion);
      }

      $start_marker = "# BEGIN {$marker}";
      $end_marker   = "# END {$marker}";

      $fp = fopen($filename, 'r+');
      if(!$fp)
      {
           return false;
      }

      // Attempt to get a lock. If the filesystem supports locking, this will block until the lock is acquired.
      flock($fp, LOCK_EX);

      $lines = array();
      while (!feof($fp))
      {
         $lines[] = rtrim(fgets($fp), "\r\n");
      }

      // Split out the existing file into the preceding lines, and those that appear after the marker
      $pre_lines = $post_lines = $existing_lines = array();
      $found_marker = $found_end_marker = false;
      foreach ($lines as $line)
      {
         if(!$found_marker && false !== strpos($line, $start_marker))
         {
            $found_marker = true;
            continue;
         }
         elseif(!$found_end_marker && false !== strpos($line, $end_marker))
         {
            $found_end_marker = true;
            continue;
         }

         if(!$found_marker)
         {
            $pre_lines[] = $line;
         }
         elseif($found_marker && $found_end_marker)
         {
            $post_lines[] = $line;
         }
         else
         {
            $existing_lines[] = $line;
         }
      }

      // Check to see if there was a change
      if($existing_lines === $insertion)
      {
         flock($fp, LOCK_UN);
         fclose($fp);

         return true;
      }

      /* Si on doit positionner le contenu au dÃ©but du fichier*/
      if($at_beginning && !$found_marker)
      {
          // Generate the new file data
         $new_file_data = implode("\n", array_merge(array($start_marker),
                                                   $insertion,
                                                   array($end_marker),
                                                   $pre_lines,
                                                   $post_lines));
      }
      else
      {
          // Generate the new file data
         $new_file_data = implode("\n", array_merge($pre_lines,
                                                   array($start_marker),
                                                   $insertion,
                                                   array($end_marker),
                                                   $post_lines));
      }


      // Write to the start of the file, and truncate it to that length
      fseek($fp, 0);
      $bytes = fwrite($fp, $new_file_data);
      if($bytes)
      {
         ftruncate($fp, ftell($fp));
      }
      fflush($fp);
      flock($fp, LOCK_UN);
      fclose($fp);

      return (bool) $bytes;
   }


    /**
    * Validate activation/deactivation. In fact we just add things into .htaccess file to protect medias.
    */
    function validate_enabled($enabled)
    {
        /* Website protection is enabled */
        if($enabled == '1')
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
                              ___("Impossible de mettre à jour le fichier .htaccess"),
                              'error');
              $enabled = '0';
           }
        }
        else /* We don't want to protect website */
        {
           if($this->update_htaccess(array())===false)
           {
              add_settings_error('cannotUpdateHtAccess',
                              'empty',
                              ___("Impossible de mettre à jour le fichier .htaccess"),
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
                $epfl_accred_group = 'intranet-epfl';
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
     * Prepare the admin menu with settings and their values
     */
    function setup_options_page()
    {


        $this->add_settings_section('section_about', ___('À propos'));
        $this->add_settings_section('section_settings', ___('Paramètres'));


        /* Check box to activate or not the functionality */
        $this->register_setting('enabled', array(
                'type'    => 'boolean',
                'sanitize_callback' => array($this, 'validate_enabled')));

        $enabled = $this->get('enabled');
        if(empty($enabled)) $enabled=0;

        $this->add_settings_field(
                'section_settings',
                'enabled',
                ___("Protéger l'accès au site"),
                array(
                    'type'        => 'select',
                    'options'     => array(0 => ___('Non'), 1 => ___('Oui')),
                    'value'       => $enabled,
                    'help' => ___('Si cette case est cochée, une authentification est nécessaire pour accéder au site.')
                )
            );


        /* Group list for restriction */
        $this->register_setting('restrict_to_groups', array(
                'type'    => 'text',
                'sanitize_callback' => array($this, 'validate_restrict_to_groups')));

        $this->add_settings_field(
                'section_settings',
                'restrict_to_groups',
                ___("Restreindre l'accès au(x) groupe(s)"),
                array(
                    'type'        => 'text',
                    'help' => ___('Si ce champ est laissé vide, seule une authentification sera demandée')
                )
            );

    }

    function render_section_about()
    {
        echo "<p>\n";
        echo ___(<<<ABOUT
A besoin du plugin <a href="https://github.com/epfl-sti/wordpress.plugin.accred">EPFL-Accred</a> pour fonctionner
correctement. <br>Permet de limiter l'accès au site en forçant les utilisateurs à s'authentifier via
<a href="https://github.com/epfl-sti/wordpress.plugin.tequila">Tequila</a>. <br>On peut soit demander
juste une authentification, soit forcer en plus à ce que l'utilisateur fasse partie d'un des groupes définis.<br>
Si le plugin est activé, les <b>fichiers sont aussi protégés</b>.
ABOUT
);
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
