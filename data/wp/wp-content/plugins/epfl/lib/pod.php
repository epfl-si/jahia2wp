<?php
/* Copyright © 2018 École Polytechnique Fédérale de Lausanne, Switzerland */
/* All Rights Reserved, except as stated in the LICENSE file. */

namespace EPFL\Pod;

if (! defined( 'ABSPATH' )) {
    die( 'Access denied.' );
}

use \Error;


/**
 * Model for EPFL-style Wordpress-in-Docker directory layout
 *
 * An instance represents one of the Wordpress sites in the same
 * filesystem as this Wordpress.
 */
class Site {
    protected function __construct ($path_under_htdocs) {
        $this->path_under_htdocs = $path_under_htdocs;
    }

    function __toString () {
        return "<\\EPFL\\Pod\\Site(\"$this->path_under_htdocs\")>";
    }

    static function this_site () {
        $thisclass = get_called_class();
        list($htdocs_path, $under_htdocs) = static::_htdocs_split();
        $that = new $thisclass($under_htdocs);
        $that->htdocs_path = $htdocs_path;
        return $that;
    }

    static function root () {
        [ $htdocs, $my_subdirs ] = static::_htdocs_split();
        $path_components = explode('/', $my_subdirs);

        for($under_htdocs = '';
            true;
            $under_htdocs = ($under_htdocs ? "$under_htdocs/" : "") .
                            array_shift($path_components))
        {
            if (static::exists("$htdocs/$under_htdocs")) {
                $thisclass = get_called_class();
                return new $thisclass($under_htdocs);
            }
            if (! count($path_components)) {
                throw new \Error('Unable to find root site from ' . WP_CONTENT_DIR);
            }
        }
    }

    /**
     * True iff $path contains a Wordpress install.
     *
     * @param $path A path; if relative, it is interpreted relative to
     *              PHP's current directory which is probably not what
     *              you want.
     */
    static function exists ($path) {
        if (! preg_match('#^/#', $path)) {
            $path = static::_htdocs_split()[0] . "/$path";
        }
        return is_file("$path/wp-config.php");
    }

    /**
     * @return A list of two elements, where the first is the absolute path
     *         to the htdocs directory and the second is the relative path
     *         from the htdocs directory to the directory containing
     *         wp-config.php
     */
    static private function _htdocs_split () {
        $wp_content_dir = preg_replace('#/+#', '/', WP_CONTENT_DIR);
        if (! preg_match('#(^.*/htdocs)(/.*|)$#', $wp_content_dir, $matched)) {
            throw new \Error('Unable to find htdocs in ' . $wp_content_dir);
        }
        $htdocs = $matched[1];
        $below_htdocs = preg_replace('#^/#', '',
                                     preg_replace('#/wp-content/?$#', '',
                                                  $matched[2]));
        return array($htdocs, $below_htdocs);
    }

    function get_path () {
        $path = "/" . $this->path_under_htdocs;
        if (! preg_match('#/$#', $path)) {
            $path = "$path/";
        }
        return $path;
    }

    function get_url () {
        return 'https://' . static::my_hostport() . $this->get_path();
    }

    /**
     * Utility function to get our own serving address in host:port
     * notation.
     *
     * @return A string of the form $host or $host:$port, parsed out
     *         of the return value of @link site_url
     */
    static function my_hostport () {
        return static::_parse_hostport(site_url());
    }

    static private function _parse_hostport ($url) {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        return $port ? "$host:$port" : $host;
    }

    function make_absolute_url ($url) {
        if (parse_url($url, PHP_URL_HOST)) {
            return $url;
        } elseif (preg_match('#^/#', $url)) {
            $myhostport = static::my_hostport();
            return "https://$myhostport$url";
        } else {
            return $this->get_url() . $url;
        }
    }

    /**
     * @return The part of $url that is relative to the site's root,
     *         or NULL if $url is not "under" this site. (Note: being
     *         part of any of the @link get_subsite s is not checked
     *         here; such URLs will "count" as well.)
     */
    function make_relative_url ($url) {
        if ($hostport = static::_parse_hostport($url)) {
            if ($hostport === static::my_hostport()
                || $hostport === "localhost:8443") {   // XXX TMPHACK
                $url = preg_replace("#^https?://$hostport#", '', $url);
            } else {
                return NULL;
            }
        }
        $count_replaced = 0;
        $url = preg_replace(
            '#^' . quotemeta($this->get_path()) . '#',
            '/', $url, -1, $count_replaced);
        if ($count_replaced) return $url;
    }

    function equals ($that) {
        return $this->path_under_htdocs === $that->path_under_htdocs;
    }

    function is_root () {
        return $this->equals($this->root());
    }

    /**
     * The main root Site is the one at the root of the filesystem and
     * has not a configurated root menu.
     */
    function is_main_root () {
        return $this->is_root() && empty($this->get_configured_root_menu_url());
    }

    function get_subsites () {
        if (! $this->htdocs_path) {
            throw new \Error("Sorry, ->get_subsites() only works on ::this_site() for now");
        }

        return static::_get_subsites_recursive (
            $this->htdocs_path, $this->path_under_htdocs);
    }

    /**
     * @return The 'menu_root_provider_url' entry in epfl-wp-sites-config.ini , or NULL if no such URL is configured.
     */
    function get_configured_root_menu_url () {
        return $this->get_pod_config('menu_root_provider_url');
    }

    static function _get_subsites_recursive ($htdocs_path, $path) {
        $retvals = array();
        foreach (scandir("$htdocs_path/$path") as $filename) {
            if (preg_match('#^([.]|wp-)#', $filename)) continue;
            $subdir = strlen($path) ? "$path/$filename" : $filename;
            if (! is_dir("$htdocs_path/$subdir")) continue;
            if (static::exists($subdir)) {
                $thisclass = get_called_class();
                $retvals[] = new $thisclass($subdir);
            } else {
                $retvals = array_merge($retvals, static::_get_subsites_recursive($htdocs_path, $subdir));
            }
        }
        return $retvals;
    }

    /**
     * Get a pod config value, or all config
     *
     * Configuration file should be in www.epfl.ch/htdocs/epfl-wp-sites-config.ini
     *
     * @return (String|bool) false if not found
     */

    static function get_pod_config ($key='') {
        $ini_path = static::_get_wp_site_config_path();
        if ($ini_path) {
            $ini_array = parse_ini_file($ini_path);
            if (!empty($key)) {
                if (array_key_exists($key, $ini_array)){
                    return $ini_array[$key];
                }
            } else {
                return $ini_array;
            }
        }
    }

    static $_wp_site_config_path_cache = FALSE;
    static function _get_wp_site_config_path () {
        if (FALSE === static::$_wp_site_config_path_cache) {
            [ $htdocs, $my_subdirs ] = static::_htdocs_split();

            for(
              $path_components = explode('/', $my_subdirs);
              ;
              array_pop($path_components))
            {
                $try_path = $htdocs . "/" . implode("/", $path_components) . "/epfl-wp-sites-config.ini";
                if (file_exists($try_path)) {
                    static::$_wp_site_config_path_cache = $try_path;
                    break;
                }
                if (!count($path_components)) break;
            }
            if (FALSE === static::$_wp_site_config_path_cache) {
              static::$_wp_site_config_path_cache = NULL;
            }
        }

        return static::$_wp_site_config_path_cache;
    }
}
