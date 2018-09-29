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
        if (0 !== strpos($path_under_htdocs, '/')) {
            throw new \Error("Weird \$path_under_htdocs: $path_under_htdocs");
        }
        $this->path_under_htdocs = $path_under_htdocs;
    }

    function __toString () {
        return "<\\EPFL\\Pod\\Site($this->path_under_htdocs)>";
    }

    static function this_site () {
        $thisclass = get_called_class();
        $that = new $thisclass(static::_our_path());
        $that->abspath = ABSPATH;
        return $that;
    }

    static protected function _our_path () {
        return parse_url(site_url(), PHP_URL_PATH);
    }

    static function relative_to_this_site ($relpath) {
        if (0 === strpos($path_under_htdocs, '/')) {
            throw new \Error("\$relpath ($relpath) should not start with a slash");
        }
        $thisclass = get_called_class();
        return new $thisclass(static::_our_path() . "/$relpath");
    }

    static function root () {
        $thisclass = get_called_class();
        preg_match('#htdocs(/[^/]*)#', ABSPATH, $matched);
        if (! $matched) {
            throw new \Error('Unable to find htdocs in ' . ABSPATH);
        }
        return new $thisclass($matched[1]);
    }

    function get_localhost_url () {
        return 'https://localhost:8443' . $this->path_under_htdocs;
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
        if (! $this->abspath) {
            throw new \Error("Sorry, ->get_subsites() only works on ::this_site() for now");
        }

        // http://ch1.php.net/manual/en/class.recursivedirectoryiterator.php#114504
        $all_abspath_lazy = new \RecursiveDirectoryIterator(
            ABSPATH,
            \RecursiveDirectoryIterator::SKIP_DOTS);  // Dude, so 1990's.

        $wp_configs_lazy = new \RecursiveCallbackFilterIterator(
            $all_abspath_lazy,
            function ($current, ...$unused) {
                // Because there is but one return value from this
                // here callback (not that RecursiveFilterIterator,
                // with its sole overridable method, would be any
                // different), the PHP API for filtering trees
                // conflates filtering directories and "pruning" them
                // (in the sense of find(1)). Despite the strong itch
                // to just reimplement a RecursiveWhateverIterator as
                // an anonymous class (which would take about as many
                // lines as this comment), I went with the only
                // slightly inelegant hack of filtering wp-config.php
                // files, rather than directories that have a
                // wp-config.php file in them.
                if ($current->isDir()) {
                    // Returning false means to prune the directory, so
                    // be conservative.
                    return ! preg_match('/^wp-/', $current->getBasename());
                } else {
                    return $current->getBaseName() === 'wp-config.php';
                }
            });

        $retval = array();
        foreach ((new \RecursiveIteratorIterator($wp_configs_lazy))
                 as $info) {
            $relpath = substr(dirname($info->getPathname()),
                              strlen($this->abspath));
            if (! $relpath) continue;  // Skip our own directory
            $retval[] = static::relative_to_this_site($relpath);
        }

        return $retval;
    }
}
