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
                throw new \Error('Unable to find root site from ' . ABSPATH);
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

    static private function _htdocs_split ($abspath = NULL) {
        if ($abspath === NULL) { $abspath = ABSPATH; }
        $abspath = preg_replace('#/+#', '/', $abspath);
        if (! preg_match('#(^.*/htdocs)(/.*|)$#', $abspath, $matched)) {
            throw new \Error('Unable to find htdocs in ' . $abspath);
        }
        $htdocs = $matched[1];
        $below_htdocs = preg_replace('#^/#', '', 
                                     preg_replace('#/$#', '', $matched[2]));
        return array($htdocs, $below_htdocs);
    }

    function get_localhost_url () {
        $subpath = $this->path_under_htdocs;
        return 'https://localhost:8443/' . ($subpath ? $subpath . '/' : '');
    }

    function get_url () {
        return static::externalify_url($this->get_localhost_url());
    }

    /**
     * Utility function to get our own serving address in host:port
     * notation.
     *
     * @return A string of the form $host or $host:$port, parsed out
     *         of the return value of @link site_url
     */
    static function my_hostport () {
        $site_url = site_url();
        $host = parse_url($site_url, PHP_URL_HOST);
        $port = parse_url($site_url, PHP_URL_PORT);
        return $port ? "$host:$port" : $host;
    }

    static function externalify_url ($localhost_url) {
        $myhostport = static::my_hostport();
        return preg_replace('#^https://localhost:8443/#', "https://$myhostport/", $localhost_url);
    }

    function equals ($that) {
        return $this->path_under_htdocs === $that->path_under_htdocs;
    }

    function is_root () {
        return $this->equals($this->root());
    }

    function get_subsites () {
        if (! $this->htdocs_path) {
            throw new \Error("Sorry, ->get_subsites() only works on ::this_site() for now");
        }

        return static::_get_subsites_recursive (
            $this->htdocs_path, $this->path_under_htdocs);
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
     * @return The part of $url that is relative to the site's root,
     *         or NULL if $url is not "under" this site. (Note: being
     *         part of any of the @link get_subsite s is not checked
     *         here; such URLs will "count" as well.)
     */
    function get_relative_url ($url) {
        $url = static::externalify_url($url);
        $count_replaced = 0;
        $url = preg_replace(
            '#^' . quotemeta($this->get_url()) . '#',
            '/', $url, -1, $count_replaced);
        if ($count_replaced) return $url;
    }
}
