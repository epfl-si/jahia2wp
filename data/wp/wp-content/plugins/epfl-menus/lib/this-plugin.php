<?php

/**
 * Encapsulate and simplify a few plugin-related pieces of the WordPress API
 */

namespace EPFL\ThisPlugin;

if (! defined( 'ABSPATH' )) {
    die( 'Access denied.' );
}

function topdir () {
    return dirname(__DIR__);
}

/**
 * @return The top-level PHP file of this plugin (wanted by e.g. @link plugins_url)
 */
function entry_point () {
    return topdir() . "/epfl-menus.php";
}

/**
 * Reference, enqueue etc. assets in this plugin by relative path
 */
class Asset {
    function __construct ($relpath) {
        $this->relpath = $relpath;
    }

    function url () {
        return plugins_url($this->relpath, entry_point());
    }

    function abspath () {
        return topdir() . "/" . $this->relpath;
    }

    function enqueue_script ($deps = ['jquery']) {
        return wp_enqueue_script(
            basename($this->relpath, ".js"),
            $this->url(),
            $deps,
            filemtime($this->abspath()));
    }

    function enqueue_style () {
        return wp_enqueue_style(
            basename($this->relpath, ".css"),
            $this->url(),
            [],
            filemtime($this->abspath()));
    }
}

function on_activate ($callable) {
    register_activation_hook(entry_point(), $callable);
}

function on_deactivate ($callable) {
    register_deactivation_hook(entry_point(), $callable);
}
