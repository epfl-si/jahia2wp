<?php
/* Copyright © 2018 École Polytechnique Fédérale de Lausanne, Switzerland */
/* All Rights Reserved, except as stated in the LICENSE file. */
/**
 * Stitch menus across sites
 *
 * The EPFL is a pretty big place, and Wordpress' access control is
 * not up to task to its administrative complexity. The solution we
 * came up with is to apportion pages and posts of large Web sites
 * into as many WordPress instances, living under the same Apache
 * server and URL tree, as there are administrative subdivisions to
 * cater to. This is made transparent to the visitor through a number
 * of tricks and extensions, including this one.
 */

namespace EPFL\Menus;

if (! defined( 'ABSPATH' )) {
    die( 'Access denied.' );
}

require_once(__DIR__ . '/../lib/rest.php');
use \EPFL\REST\REST_API;

class MenuError extends \Exception {};

class MenuController
{
    static function hook () {
        REST_API::GET_JSON(
            '/menus',
            get_called_class(), 'REST_get_menus');
        REST_API::GET_JSON(
            '/menus/(?P<slug>[a-zA-Z0-9_-]+)',
            get_called_class(), 'REST_get_menu');
    }

    /**
     * Enumerate menus over the REST API
     *
     * Enumeration is independent of languages (or lack thereof) i.e.
     * there is exactly one result per theme slot (in the sense of
     * @link get_registered_nav_menus).
     *
     * @url /epfl/v1/menus
     *
     * @returns A list of objects like {'slug': slug,
     *          'description': description }
     */
    static function REST_get_menus () {
        $retval = [];
        foreach (get_registered_nav_menus() as $slug => $description) {
            array_push($retval, array(
                'slug'        => $slug,
                'description' => $description));
        }
        return $retval;
    }

    static function REST_get_menu ($data) {
        $menu_slug = $data['slug'];  # Matched from the URL with a named pattern

        $locations = get_nav_menu_locations();
        if (! ($locations && $term_id = $locations[$menu_slug])) {
            throw new MenuError("No registered menu with slug $menu_slug");
        }

        if (is_wp_error($menu = get_term($term_id))) {
            throw new MenuError(
                "Cannot find term with id $term_id for menu $menu_slug");
        }
        
        return array(
            "status" => "OK",
            "items"  => wp_get_nav_menu_items($menu->term_id)
        );
    }
}

MenuController::hook();
