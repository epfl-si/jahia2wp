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

require_once(ABSPATH . 'wp-admin/includes/class-walker-nav-menu-edit.php');

require_once(dirname(__DIR__) . '/lib/model.php');
use \EPFL\Model\TypedPost;

require_once(dirname(__DIR__) . '/lib/results.php');
use \EPFL\Results\FindFromAllTrait;

require_once(dirname(__DIR__) . '/lib/rest.php');
use \EPFL\REST\REST_API;
use \EPFL\REST\RESTClient;
use \EPFL\REST\RESTClientError;
use \EPFL\REST\RESTRemoteError;
use \EPFL\REST\RESTAPIError;
use \EPFL\REST\REST_URL;

require_once(dirname(__DIR__) . '/lib/admin-controller.php');
use \EPFL\AdminController\TransientErrors;
use \EPFL\AdminController\CustomPostTypeController;

require_once(dirname(__DIR__) . '/lib/pubsub.php');
use \EPFL\Pubsub\PublishController;
use \EPFL\Pubsub\SubscribeController;
use \EPFL\Pubsub\TestWebhookFlowException;

require_once(dirname(__DIR__) . '/lib/i18n.php');
use function EPFL\I18N\___;
use function EPFL\I18N\__x;
use function EPFL\I18N\__e;

require_once(dirname(__DIR__) . '/lib/this-plugin.php');
use EPFL\ThisPlugin\Asset;

require_once(dirname(__DIR__) . '/lib/pod.php');
use EPFL\Pod\Site;

class MenuError extends \Exception {};



/**
 * A Wordpress-style list of menu objects.
 *
 * An instance represents a list of objects that are typically
 * manipulated by Wordpress and its so-called walkers in order to
 * construct menus. Instances are immutable: all mutating operations
 * return a fresh object where all $item objects (the ones returned
 * by @link as_list) are also fresh.
 */
class MenuItemBag
{
    function __construct ($items) {
        if (! is_array($items)) {
            throw new \Error('Bad argument: ' . var_export($items, true));
        }
        $this->items = array();
        foreach ($items as $item) {
            $this->_MUTATE_add_item($item);
        }
    }

    static function coerce ($what) {
        $thisclass = get_called_class();
        if ($what instanceof $thisclass) {
            return $what;
        } else {
            return new $thisclass($what);
        }
    }

    function copy () {
        $thisclass = get_called_class();
        $copied_items = array();
        foreach ($this->as_list() as $item) {
            $copied_items[] = clone $item;
        }
        return new $thisclass($copied_items);
    }

    function copy_classes_and_currents ($another_bag) {
        $another_bag = static::coerce($another_bag);
        $copy = $this->copy();
        foreach ($copy->items as $unused_id => $item) {
            foreach (array('classes', 'current',
                           'current_item_ancestor', 'current_item_parent')
                     as $k) {
                $item->$k = $another_bag->find($item)->$k;
            }
        }
        return $copy;
    }

    function as_list () {
        return array_values($this->items);
    }

    function find ($item_or_id) {
        $id = is_object($item_or_id) ? $item_or_id->ID : $item_or_id;
        return $this->items[$id];
    }

    function annotate_roots ($annotations) {
        $copy = $this->copy();
        foreach ($copy->items as $item) {
            if (! $this->get_parent($item)) {
                foreach ($annotations as $k => $v) {
                    // ->copy() is a deep copy so this is safe.
                    $item->$k = $v;
                }
            }
        }
        return $copy;
    }

    function graft ($at_item, $bag) {
        return static::_MUTATE_graft(
            $at_item, $this->copy(), $this->_renumber(static::coerce($bag)));
    }

    function reverse_graft ($at_item, $into) {
        return static::_MUTATE_graft(
            $at_item, $this->_renumber($into), $this);
    }

    /**
     * Pluck all items in MenuItemBag that match $callable_predicate,
     * and return them separately from the pruned tree
     *
     * @param $callable_predicate A callable that takes a $item as the
     *        sole parameter, and returns a true value for items to
     *        remove from the tree, and a false value for those to
     *        keep.
     *
     * @return A pair of the form array($pruned_tree, $removed) where
     *         $pruned_tree is a newly created copy of the pruned tree
     *         (or $this if $callable_predicate never returned true),
     *         and $removed is the list of items for which
     *         $callable_predicate returned a true value.
     */
    function pluck ($callable_predicate) {
        $remaining = array();
        $pruned = array();
        foreach ($this->items as $item) {
            if (call_user_func($callable_predicate, $item)) {
                $pruned[] = $item;
            } else {
                $remaining[] = $item;
            }
        }
        if (count($pruned)) {
            $thisclass = get_called_class();
            return array(new $thisclass($remaining), $pruned);
        } else {
            return array($this, []);  // No change to $this
        }
    }

    /**
     * @return (A copy of) $this without the ExternalMenuItem nodes
     */
    function trim_external () {
        return $this->pluck(function($item) {
            return ExternalMenuItem::get($item);
        })[0];
    }

    function get_parent ($item) {
        return $this->items[$this->_get_parent_id($item)];
    }

    private function _get_id ($item) {
        return $item->db_id;
    }

    private function _get_parent_id ($item) {
        return $item->menu_item_parent;
    }

    // Mutators must be called *only* in the constructor or
    // on (the items of) an instance obtained with @link copy.
    private function _MUTATE_set_id ($item, $new_id) {
        $item->db_id = $new_id;
        // In actual menus, ->ID and ->db_id are always the same.
        // Strange things happen if they get desynched e.g. visibility
        // of deeper menu items seems to be regulated by the ->ID,
        // while parent/child relationship works with the ->db_id.
        // #gofigure
        $item->ID = $new_id;
    }

    private function _MUTATE_set_parent_id ($item, $new_parent_id) {
        $item->menu_item_parent = $new_parent_id;
    }

    private function _MUTATE_add_item ($item) {
        if ($this->items[$this->_get_id($item)]) {
            throw new \Error("Duplicate ID: " . $this->_get_id($item));
        }
        $this->items[$this->_get_id($item)] = $item;
    }

    function _MUTATE_graft ($at_item, $mutated_outer, $inner) {
        $new_parent_id = $mutated_outer->_get_parent_id($at_item);
        foreach ($inner->as_list() as $item) {
            if (! $mutated_outer->_get_parent_id($item)) {
                // $item is not shared with $this thanks to ->copy() being
                // a deep copy.
                $this->_MUTATE_set_parent_id($item, $new_parent_id);
            }
            $mutated_outer->_MUTATE_add_item($item);
        }
        return $mutated_outer;
    }

    /**
     * Compute and return a copy of $other_bag with all IDs changed
     * so that they don't clash with those in this instance.
     *
     * Renumbering always uses *negative* numbers for IDs; the
     * positive numbers are reserved for pristine (i.e.,
     * authoritative) nodes in the menus served over REST, so be sure
     * to ->_renumber() the things that you are adding to your tree
     * out of some REST query, and keep the locally-issued IDs as they
     * are.
     *
     * This method does the right thing when this instance already
     * contains items with negative IDs, thanks to @link
     * _get_highest_negative_unused_id.
     *
     * @param $other_bag An instance of this class (call @link coerce
     *        yourself if needed)
     *
     * @return A new instance of this class, fully unshared from both
     *         $this and $other_bag
     */
    protected function _renumber ($other_bag) {
        $next_id = $this->_get_highest_negative_unused_id();

        $translation_table = array();
        $translated = array();
        foreach ($other_bag->items as $item) {
            $orig_id        = $this->_get_id       ($item);
            $orig_parent_id = $this->_get_parent_id($item);
            foreach (array($orig_id, $orig_parent_id) as $old_id) {
                if ($old_id and ! $translation_table[$old_id]) {
                    $translation_table[$old_id] = $next_id--;
                }
            }
            $item = clone $item;
            if ($orig_id) {
                $this->_MUTATE_set_id       ($item, $translation_table[$orig_id]);
            }
            if ($orig_parent_id) {
                $this->_MUTATE_set_parent_id($item, $translation_table[$orig_parent_id]);
            }
            $translated[] = $item;
        }

        $thisclass = get_called_class();
        return new $thisclass($translated);
    }

    protected function _get_highest_negative_unused_id () {
        $myids = array_keys($this->items);
        $myids[] = 0;  // Ensure return value is -1 or less
        return min($myids) - 1;
    }
}


/**
 * Object model for "normal" WordPress menus, augmented with support
 * for EPFL-style menu stitching.
 */
class Menu {
    private function __construct ($term_id) {
        if ($term_id > 0) {
            $this->term_id = $term_id;
        } else {
            throw new \Error("Bogus term ID $term_id");
        }
    }

    /**
     * @return A site-wide unique integer ID for this Menu
     */
    function get_term_id () {
        return $this->term_id;
    }

    /**
     * @return A list of all instances used anywhere in the theme's menus,
     *         according to @link MenuMapEntry
     */
    static function all_mapped () {
        $all = array();
        foreach (MenuMapEntry::all() as $entry) {
            $menu = $entry->get_menu();
            if (! $all[$menu->get_term_id()]) {
                $all[$menu->get_term_id()] = $menu;
            }
        }
        return array_values($all);
    }

    static function by_term ($term_or_term_id) {
        if (is_object($term_or_term_id)) {
            $term_id = $term_or_term_id->term_id;
        } else {
            $term_id = $term_or_term_id;
        }

        $thisclass = get_called_class();
        return new $thisclass($term_id);
    }

    /**
     * @return A @link MenuItemBag instance
     */
    private function _get_local_tree () {
        $items = wp_get_nav_menu_items($this->term_id);
        if ($items === FALSE) {
            throw new MenuError(
                "Cannot find term with ID $this->term_id");
        }
        return new MenuItemBag($items);
    }

    private const SOA_SLUG = 'epfl_soa';

    /**
     * Compute the fully stitched menu that surrounds this Menu.
     *
     * ExternalMenuItem entries present in the menu are decorated with
     * the contents of the remote menu, as obtained over REST; they
     * remain in the returned tree as a positional indicator for REST
     * clients (call @link MenuItemBag::trim_external to remove them,
     * e.g. prior to passing to a frontend walker).
     *
     * If we are rendering the primary menu and this is not the root
     * site, graft ourselves into the root site's menu at the proper
     * point.
     *
     * In both grafting operations ("down" for ExternalMenuItems, "up"
     * for the root site's menu), keep our own nodes (the ones we have
     * authority for) with positive IDs and renumber all foreign nodes
     * with negative IDs.
     *
     * @param $mme A @link MenuMapEntry instance representing the menu
     *             being rendered.
     *
     * @return A @link MenuItemBag instance, in which the
     *         authoritative menu entries (the ones retrieved from the
     *         local database) have positive IDs, while the remote
     *         IDs are negative
     */
    function get_stitched_tree ($mme) {
        $tree = $this->_get_local_tree();
        foreach ($tree->as_list() as $item) {
            if (! ($emi = ExternalMenuItem::get($item))) continue;
            $tree = $tree->graft(
                $item,
                $emi->get_remote_menu()->annotate_roots(array(
                    self::SOA_SLUG => Site::externalify_url(
                        $emi->get_site_url()))));
        }

        $theme_location = $mme->get_theme_location();
        if ($theme_location === 'primary' and
            (! Site::this_site()->is_root())) {
            $root_menu = ExternalMenuItem
                       ::find(array(
                           'site_url'       => Site::root()->get_localhost_url(),
                           'theme_location' => $theme_location
                       ))
                       ->first_preferred(array(
                           'language' => $mme->get_language()))
                       ->get_remote_menu();
            $tree = $this->_stitch_up($root_menu, $tree);
        }

        // TODO: Normalize (don't remove) the ExternalMenuItem entries.
        //
        // * Should remove useless and misleading guid and url
        //
        // * Should add sufficient info so that ->_stitch_up() (called
        //   from another WP) be in a position to figure out which
        //   ExternalMenuItem is the one for them
        return $tree;
    }

    protected function _stitch_up ($under, $tree) {
        $soa_slug = self::SOA_SLUG;
        $site_url = site_url();
        $under = $under->pluck(function($item) use ($soa_slug, $site_url) {
            return $item->$soa_slug === $site_url;
        })[0];

        // When stitching up, we remove all ExternalMenuItem's and graft
        // ourselves under the (first) one that is for us.
        //
        // While we can have ExternalMenuItem's in the tree served
        // over REST, these should be only "ours" i.e. the ones we
        // have authority for. Those are in $tree, not in $under.
        [$under, $graft_points] = $under->pluck(function($item) {
            return $item->object === ExternalMenuItem::SLUG;
        });
        $graft_points = array_filter($graft_points, function() {
            return true;  // XXX Good enough for testing only; see TODO above
        });
        
        if (! $graft_points[0]) {
            error_log(sprintf(
                'Cannot find graft point - Unable to stitch up\n%s',
                var_export($under, true)));
            return $tree;
        }
        return $tree->reverse_graft($graft_points[0], $under);
    }

    /**
     * @param $emi The instance of ExternalMenuItem whose menu received
     *             an update event
     *
     * @return true iff this menu changed
     */
    function update ($emi) {
        // XXX Lazy but correct implementation (for low values of correct):
        // do nothing
    }
}


/**
 * Model for menus as belonging to the theme's menu map.
 *
 * An instance represents one menu from the Appearance → Menus screen
 * in wp-admin; it has-a @link Menu, and also a description, a theme
 * location (e.g. "primary") and a language.
 */
class MenuMapEntry
{
    function __construct($term_or_term_id, $theme_location, $description,
                         $language = NULL) {
        $this->menu = Menu::by_term($term_or_term_id);
        $this->theme_location = $theme_location;
        $this->description = $description;
        $this->language = $language;
    }

    function get_menu () {
        return $this->menu;
    }

    function get_menu_term_id () {
        return (int) $this->menu->get_term_id();
    }

    /**
     * Returns the name of the location (as defined by the theme) that
     * this entry appears under; also used as a portion of the URL
     * ("slug") by API consumers (see @link ExternalMenuItem).
     *
     * @return A string designating the menu position, e.g. "primary"
     *         or "footer"
     */
    function get_theme_location () {
        return $this->theme_location;
    }

    function get_description () {
        return $this->description;
    }

    function get_language () {
        return $this->language;
    }

    /**
     * @return A list of instances across all languages
     */
    static function all () {
        if ($all_from_polylang = static::_all_from_polylang()) {
            return $all_from_polylang;
        } else {
            return static::all_in_current_language();
        }
    }

    use FindFromAllTrait;

    /**
     * @return An list of MenuMapEntry instances in the current language.
     */
    static function all_in_current_language () {
        $thisclass = get_called_class();

        $registered = get_registered_nav_menus();
        $lang = function_exists('pll_current_language') ?
                pll_current_language()                  : NULL;

        $all = array();
        // Polylang hooks into the 'theme_mod_nav_menu_locations'
        // filter, therefore get_nav_menu_locations() depends on the
        // current language.
        foreach (get_nav_menu_locations() as $theme_location => $term_id) {
            if (! $term_id) continue;
            if (! $registered[$theme_location]) continue;
            $all[] = new $thisclass(
                $term_id, $theme_location,
                /* $description = */ $registered[$theme_location],
                $lang);
        }
        return $all;
    }

    static function _all_from_polylang () {
        // Polylang may be inactive, but with its options still
        // available in-database so check this first.
        if (! function_exists('pll_current_language')) return NULL;

        // Really, frobbing through the Polylang persistent data is
        // the least fragile way to go about this.
        $poly_options = get_option('polylang');  // Auto-deserializes
        if (! $poly_options) return NULL;

        $thisclass = get_called_class();
        $registered = get_registered_nav_menus();

        $all = array();
        foreach ($poly_options['nav_menus'][get_stylesheet()]
                 as $theme_location => $menus) {
            if (! $registered[$theme_location]) continue;
            foreach ($menus as $lang => $term_id) {
                if (! $term_id) continue;
                $all[] = new $thisclass(
                    $term_id, $theme_location,
                    /* $description = */ $registered[$theme_location],
                    $lang);
            }
        }
        return $all;
    }

    /**
     * @return The menu map entry associated with $theme_location in
     *         the current language.
     *
     * Note that when Polylang is in play, the result depends on the
     * current language (typically passed by a ?lang=XX query
     * parameter in REST queries)
     */
    static function by_theme_location ($theme_location) {
        foreach (MenuMapEntry::all_in_current_language() as $entry) {
            if ($entry->get_theme_location() === $theme_location) {
                return $entry;
            }
        }
    }
}


/**
 * An external menu that needs fetching and/or integrating
 * with the "normal" menus.
 *
 * Instances are represented in the Wordpress database as posts
 * of custom post type "epfl-external-menu", keyed by the URL
 * of the REST API that provides their contents.
 */
class ExternalMenuItem extends \EPFL\Model\UniqueKeyTypedPost
{
    const SLUG = "epfl-external-menu";

    // For get_or_create():
    const META_PRIMARY_KEY = array(
        'epfl-emi-site-url',
        'epfl-emi-remote-slug',
        'epfl-emi-remote-lang');

    // For ->meta()->{get_,set_}foo():
    const META_ACCESSORS = array(
        // {get_,set_} suffix => in-database post meta name ("-emi-" stands for "external menu item")
        'site_url'             => 'epfl-emi-site-url',
        'rest_subscribe_url'   => 'epfl-emi-rest-api-subscribe-url',
        // Note: from the ExternalMenuItem perspective this is called
        // a slug, despite it corresponding to MenuMapEntry's
        // theme_location, because there is nothing that says that the
        // remote end has to be in a theme (or be Wordpress at all -
        // Think single-purpose footer menu server written in node.js)
        'remote_slug'          => 'epfl-emi-remote-slug',
        'remote_lang'          => 'epfl-emi-remote-lang',
        'items_json'           => 'epfl-emi-remote-contents-json',
        'last_synced'          => 'epfl-emi-last-synced-epoch',
        'sync_started_failing' => 'epfl-emi-sync-started-failing-epoch',
    );

    static function get_post_type () {
        return self::SLUG;
    }

    function get_site_url () {
        return $this->meta()->get_site_url();
    }

    /**
     * Overridden to also accept a 'nav-menu-item' Post object with
     * ->object === "epfl-external-menu".
     */
    static function get ($what) {
        if (is_object($what)
            and ($what->post_type === 'nav_menu_item')
            and ($what->object === static::get_post_type()))
        {
            return parent::get($what->object_id);
        } else {
            return parent::get($what);
        }
    }

    use FindFromAllTrait;

    static function load_from_filesystem () {
        $me = Site::this_site();
        $neighbors = array_merge(array(Site::root()),
                                 $me->get_subsites());
        foreach ($neighbors as $site) {
            if ($site->equals($me)) continue;
            try {
                static::_load_from_site($site);
            } catch (RESTRemoteError $e) {
                error_log("[Not our fault, IGNORED] " . $e);
                continue;
            }
        }
    }

    static protected function _load_from_site ($site) {
        static::load_from_wp_site_url($site->get_localhost_url());
    }

    static function load_from_wp_site_url ($site_url) {
        foreach (RESTClient::GET_JSON(REST_URL::remote($site_url, 'languages'))
                 as $lang) {
            try {
                $menu_descrs = RESTClient::GET_JSON(REST_URL::remote(
                    $site_url,
                    "menus?lang=$lang"));
            } catch (RESTClientError $e) {
                error_log("[Not our fault, IGNORED] " . $e);
                continue;
            }

            foreach ($menu_descrs as $menu_descr) {
                $menu_slug = $menu_descr->slug;
                $that = static::get_or_create($site_url, $menu_slug, $lang);
                // Keep Polylang language in sync, so that the little flags
                // show up in the wp-admin list UI.
                $that->set_language($lang);

                $site_url_dir = parse_url($site_url, PHP_URL_PATH);
                $title = $menu_descr->description . "[$lang] @ $site_url_dir";
                $that->update(array('post_title' => $title));

                $that->refresh();
            }
        }
    }

    protected function _get_rest_url () {
        $site_url = $this->meta()->get_site_url();
        $menu_slug = $this->meta()->get_remote_slug();
        $lang = $this->get_language();
        return REST_URL::remote($site_url, "menus/$menu_slug?lang=$lang")
            ->fully_qualified();
    }

    function refresh () {
        if ($this->_refreshed) return;  // Not twice in same query cycle
        if (! ($get_url = $this->_get_rest_url())) {
            $this->error_log("doesn't look very external to me");
            return;
        }

        $menu_contents = RESTClient::GET_JSON($get_url);
        $this->set_remote_menu($menu_contents->items);
        $this->meta()->set_rest_subscribe_url(
            $menu_contents->get_link('subscribe'));

        $this->_refreshed = true;
    }

    function try_refresh () {
        if ($this->_refreshed) return;  // Not twice in same query cycle
        try {
            $this->refresh();
            $this->meta()->set_last_synced(time());
            $this->meta()->del_sync_started_failing();
            return true;
        } catch (RESTClientError $e) {
            $this->error_log("unable to refresh: $e");
            if (! $this->meta()->get_sync_started_failing()) {
                $this->meta()->set_sync_started_failing(time());
            }
        }
    }

    function set_remote_menu ($what) {
        if (method_exists($what, 'as_list')) {
            $what = $what->as_list();
        }
        $this->meta()->set_items_json(json_encode($what));
    }

    function get_remote_menu () {
        return new MenuItemBag(json_decode($this->meta()->get_items_json()));
    }

    function get_subscribe_url () {
        return $this->meta()->get_rest_subscribe_url();
    }

    function get_sync_status () {
        $status = new \stdClass();
        $status->failing_since = $this->meta()->get_sync_started_failing();
        $status->last_success  = $this->meta()->get_last_synced();
        return $status;
    }

    function is_failing () {
        return !! $this->meta()->get_sync_started_failing();
    }

    function has_succeeded () {
        return !! $this->meta()->get_last_synced();
    }
}

/**
 * Enumerate menus over the REST API
 *
 * Enumeration is independent of languages (or lack thereof) i.e.
 * there is at most one result per theme slot (in the sense of
 * @link get_registered_nav_menus). It is also independent of the
 * pubsub mechanism, in the sense that the code thereof is not invoked
 * here (but URLs that point to the pubsub REST endpoints, do get
 * computed and served)
 *
 * Additionally, the MenuRESTController class is in charge of managing
 * the @link PublishController instances. (See @link MenuItemController
 * for the subscribe side.)
 *
 * @url /epfl/v1/menus
 */
class MenuRESTController
{
    static function hook () {
        $thisclass = get_called_class();
        REST_API::GET_JSON(
            '/menus',
            $thisclass, 'get_menus');
        REST_API::GET_JSON(
            '/menus/(?P<slug>[a-zA-Z0-9_-]+)',
            $thisclass, 'get_menu');

        // "The feature is available only in Polylang Pro." #groan
        REST_API::GET_JSON(
            '/languages',
            function() { return pll_languages_list(); });

        add_action('rest_api_init', function() use ($thisclass) {
            foreach (Menu::all_mapped() as $menu) {
                $thisclass::_get_publish_controller($menu)->serve_api();
            }
        });
    }

    static function get_menus () {
        $retval = [];
        foreach (MenuMapEntry::all_in_current_language() as $entry) {
            array_push($retval, array(
                'slug' => $entry->get_theme_location(),
                'description'    => $entry->get_description(),
            ));
        }
        return $retval;
    }

    static function get_menu ($data) {
        $slug = $data['slug'];  // Matched from the URL with a named pattern

        if (! ($entry = MenuMapEntry::by_theme_location($slug))) {
            if (function_exists('pll_current_language')) {
                $inlang = " in language " . pll_current_language();
            }
            throw new RESTAPIError(404, array(
                'status' => 'NOT_FOUND',
                'msg' => "No menu with slug $slug$inlang"));
        }

        $menu = $entry->get_menu();
        $response = new \WP_REST_Response(array(
            'status' => 'OK',
            'items'  => $menu->get_stitched_tree($entry)->as_list()));
        // Note: this link is for subscribing to changes in any
        // language, not just the one being served now.
        $response->add_link(
            'subscribe',
            REST_URL::local_wrt_request(static::_get_subscribe_uri($menu))
            ->fully_qualified());
        return $response;
    }

    /**
     * Returns the URI that provides the webhook subscription service
     * for $menu
     *
     * Note that this URI may *not* be unique per $menu, and in fact
     * isn't even in the day-one implementation (two translations of
     * the same menu, share the same pubsub endpoint)
     */
    static function _get_subscribe_uri ($menu) {
        $term_id = $menu->get_term_id();
        return "menus/$term_id/subscribe";
    }

    static private $pubs = array();
    static private function _get_publish_controller ($menu) {
        $term_id = $menu->get_term_id();
        if (! static::$pubs[$term_id]) {
            static::$pubs[$term_id] = new PublishController(
                static::_get_subscribe_uri($menu));
        }
        return static::$pubs[$term_id];
    }

    /**
     * Shall be called whenever $menu changes (from the point
     * of view of @link get_menu)
     */
    static function menu_changed ($menu, $causality = NULL) {
        $publisher = static::_get_publish_controller($menu);
        if ($causality) {
            $publisher->forward($causality);
        } else {
            $publisher->initiate();
        }
    }
}

MenuRESTController::hook();


/**
 * The controller for external menu items (w/ custom post type)
 *
 * An external menu item is a "graft point" in the menu where another
 * site's menu shall appear. This class is responsible for the
 * creation and mutation of external menu items per se and their
 * metadata - not their place, or lack thereof, in any menu; for that
 * see @link MenuEditorController instead. This class is also not in
 * play (except for the concern of registering the custom post type)
 * when rendering front-end pages; see @link MenuFrontendController.
 *
 * An external menu item is handled as a Wordpress custom post type,
 * with in-database persistence and a custom editor page in the admin
 * menu featuring a custom rendering widget as the main matter,
 * instead of the customary TinyMCE.
 *
 * MenuItemController is a "pure static" class, i.e. no instances are
 * ever constructed.
 */
class MenuItemController extends CustomPostTypeController
{
    static function get_model_class () {
        return ExternalMenuItem::class;
    }

    static function hook () {
        parent::hook();

        static::get_model_class()::make_polylang_translatable();

        $thisclass = get_called_class();
        $thisclass::hook_meta_boxes();

        add_action('init', array($thisclass, 'register_post_type'));

        add_action('rest_api_init', function() use ($thisclass) {
            foreach (static::get_model_class()::all()
                     as $emi) {
                $thisclass::hook_pubsub($emi);
            }
        });

        static::hook_refresh_button();

        static::hook_admin_list();

        static::_auto_fields_controller()->hook();
    }

    static function hook_pubsub ($emi) {
        $thisclass = get_called_class();
        static::_get_subscribe_controller($emi)->add_listener(
            function($event) use ($thisclass, $emi) {
                foreach (Menu::all_mapped() as $menu) {
                    if ($menu->update($emi)) {
                        MenuRESTController::menu_changed($menu, $event);
                    }
                }
            });
    }

    static function hook_refresh_button () {
        $thisclass = get_called_class();

        // Server side:
        add_action('admin_init', function() use ($thisclass) {
            $thisclass::_get_api()->register_handlers($thisclass);
        });

        // JS side:
        add_action('current_screen', function() use ($thisclass) {
            if ((get_current_screen()->base === 'edit') &&
                $_REQUEST['post_type'] === static::get_post_type())
            {
                $thisclass::_get_api()->admin_enqueue();
                _MenusJSApp::load();
            }
        });
    }

    static private function _get_api () {
        require_once(dirname(__DIR__) . '/lib/wp-admin-api.php');
        return new \EPFL\AdminAPI\Endpoint('EPFLMenus');
    }

    static private $subs = array();
    static private function _get_subscribe_controller ($emi) {
        $cache_key = $emi->ID;
        if (! static::$subs[$cache_key]) {
            static::$subs[$cache_key] = new SubscribeController(
                // The subscribe slug will be embedded in webhook
                // URLs, so it must not change inbetween queries.
                // Using the post ID is perhaps a bit difficult to
                // read out of error logs, but certainly the easiest
                // and most future-proof way of going about it.
                "menu/" . $emi->ID);
        }
        return static::$subs[$cache_key];
    }

    /**
     * Register a custom post type for external menus
     *
     * A "post" of this post type has no title, content, tags etc. It only
     * exists to point to the remote API endpoint that enumerates the menu,
     * and to itself appear in this site's menu.
     *
     * @see https://stackoverflow.com/a/20294968/435004
     */
    static function register_post_type () {
        register_post_type(static::get_post_type(), array(
            'labels' => array(
                'name'               => ___('External Menus'),
                'singular_name'      => ___('External Menu'),
                'menu_name'          => ___('External Menus'),
                'name_admin_bar'     => __x('External Menu', 'add new on admin bar'),
		'add_new'            => ___('Add New External Menu'),
		'add_new_item'       => ___('Add New External Menu'),
                'view_item'          => ___('View External Menu'),
                'edit_item'          => ___('Edit External Menu'),
                'all_items'          => ___('All External Menus'),
                'not_found'          => ___('No external menus found.'),
            ),
            'supports' => array('title', 'custom-fields'),
            'public'                => false,
            'show_ui'               => true,
            'show_in_nav_menus'     => true,
            'has_archive'           => false,
            'menu_icon'             => 'dashicons-list-view',
            'capabilities'          => static::capabilities_for_edit_but_not_create(),
            'register_meta_box_cb' => array(get_called_class(),
                                            'register_meta_boxes')
        ));
    }

    /**
     * Invoked through form POST by the wp-admin refresh button
     */
    static function admin_post_refresh () {
        try {
            ExternalMenuItem::load_from_filesystem();
        } catch (\Throwable $t) {
            error_log("ExternalMenuItem::load_from_filesystem(): $t");
            static::admin_error(___('Unable to load menus of sites in the same pod'));
            // Continue
        }

        $errors = 0;
        $all = ExternalMenuItem::all();
        foreach ($all as $emi) {
            if (! $emi->try_refresh()) {
                $errors++;
                continue;
            }

            $subscribe_url = $emi->get_subscribe_url();
            if (! $subscribe_url) {
                $emi->error_log("has no subscribe URL");
                continue;
            }
            try {
                static::_get_subscribe_controller($emi)->
                    subscribe($subscribe_url);
            } catch (RESTClientError | TestWebhookFlowException $e) {
                $emi->error_log(
                    "Unable to subscribe at $subscribe_url: $e");
                $errors++;
            }
        }

        if (! $errors) {
            static::admin_notice(___('Refresh successful'));
        } else {
            static::admin_error(sprintf(
                ___('Refresh failed (%d failed out of %d)'),
                $errors, count($all)));
        }

        return "edit.php?post_type=" . static::get_post_type();
    }

    /**
     * Make the "edit" screen for menu objects show the custom
     * main-matter widget and an auto-fields meta box (see @link
     * \EPFL\AutoFields\AutoFieldsController)
     */
    public static function register_meta_boxes () {
        $this_class = get_called_class();

        static::add_meta_box('edit', __x('External Menu', 'Post edit metabox'));

        static::_auto_fields_controller()->add_meta_boxes();
    }

    /**
     * Render the meta box appearing as the main matter on the "edit"
     * screen for epfl-external-menu custom post type.
     */
    public static function render_meta_box_edit () {
        global $post;
        ?>
        <div class="edit-external-menu">
		<p id="menu-item-url-wrap" class="wp-clearfix">
			<label class="howto" for="custom-menu-item-url"><?php _e( 'Menu appearance' ); ?></label>
			<img src='http://www.creditlenders.info/wp-content/uploads/stock-image-our-pick-of-the-best-worst-stock-images-sutton-silver.jpg' style='max-width: 100px;'>
		</p>
        </div>
 <?php
    }

    private static function capabilities_for_edit_but_not_create () {
        return array(
            'create_posts'       => '__NEVER_PERMITTED__',
            'edit_post'          => 'edit_posts',
            'read_post'          => 'read_post',
            'delete_post'        => 'delete_posts',  // #WAT
            'edit_posts'         => 'edit_posts',
            'edit_others_posts'  => 'edit_others_posts',
            'publish_posts'      => 'publish_posts',
            'read_private_posts' => 'read_private_posts',
        );
    }

    private static function _auto_fields_controller () {
        require_once(dirname(__DIR__) . '/lib/auto-fields.php');
        return new \EPFL\AutoFields\AutoFieldsController(
            ExternalMenuItem::class);
    }

    static function hook_admin_list () {
        // Rendered by render_date_column method, below
        static::column('date')->set_title(___('Synced'))->hook();

        add_filter('post_class',
                   array(get_called_class(), '_filter_post_class'),
                   10, 4);

        $yellow = 'rgba(248, 247, 202, 0.45)';
        $stripesize = '20px'; $stripesize2x = '40px';
        static::add_editor_css("
.wp-list-table tr.sync-failed {
  background-image: repeating-linear-gradient(-45deg,$yellow,$yellow $stripesize,transparent $stripesize,transparent $stripesize2x);
}
");
    }

    static function render_date_column ($emi) {
        $ss = $emi->get_sync_status();
        if ($ss->failing_since) {
            printf('<span class="epfl-menus-sync-failing">%s</span>',
                   sprintf(___('Failing for %s'),
                           human_time_diff($ss->failing_since)));
            $echoed_something = 1;
        }
        if ($ss->last_success) {
            if ($echoed_something) { echo '<br/>'; }
            printf('Last sync success: %s ago',
                   human_time_diff($ss->last_success));
            $echoed_something = 1;
        }
        if (! $echoed_something) {
            echo ___('Never synced yet');
        }
    }

    /**
     * Filter function for the @link post_class filter
     *
     * Add CSS classes to the <tr> element to indicate success /
     * failure state of the last sync.
     */
    static function _filter_post_class ($classes_orig, $class, $post_id) {
        $post = ExternalMenuItem::get($post_id);
        if (! $post) return $classes_orig;

        $classes = $classes_orig;
        if ($post->is_failing()) {
            $classes[] = 'sync-failed';
        } elseif ($post->has_succeeded()) {
              $classes[] = 'sync-success';
        }
        return $classes;
    }
}

MenuItemController::hook();


class _MenusJSApp
{
    /**
     * A good hook to call this from is @link admin_enqueue_scripts
     */
    static function load () {
        (new Asset("lib/polyfills.js"))->enqueue_script();
        (new Asset("menus/epfl-menus-admin.js"))->enqueue_script();
        (new Asset("menus/epfl-menus-admin.css"))->enqueue_style();
        add_action('admin_print_footer_scripts', function() {
            $screen = array('base' => get_current_screen()->base);
            if ($_REQUEST['post_type']) {
                $screen['post_type'] = $_REQUEST['post_type'];
            }
            echo "\n<script>\n";
            // Since the app is loaded from multiple wp-admin scripts,
            // pass enough info so that the JS side can orient itself:
?>
window.wp.screen = <?php echo json_encode($screen); ?>;
<?php
            // And I have yet to come up with a better idea than this
            // to translate UI strings in the JS app:
?>
window.wp.translations = {
  refresh_button: "<?php echo __x('Refresh', 'JS button'); ?>",
  refresh_failed: "<?php __e('Refresh failed'); ?>"
};
<?php

            echo "</script>\n";
        });
    }
}

################## External menus in admin menu editor #################



/**
 * The controller to handle external menu entries in the wp-admin menu
 * editor
 *
 * This is a "pure static" class, i.e. no instances are ever
 * constructed.
 */
class MenuEditorController
{
    function hook () {
        add_action('admin_init', array(get_called_class(), 'add_meta_box'));

        add_action('admin_enqueue_scripts', function() {
            if (get_current_screen()->base === 'nav-menus') {
                _MenusJSApp::load();
            }
        });
    }

    function add_meta_box () {
        add_meta_box('add-epfl-external-menu',
                     __x('External Menu', 'Add to menu'),
                     array(get_called_class(), 'render_meta_box'),
                     'nav-menus', 'side', 'default');
    }

    /**
     * Render the left-hand-side meta box that lets one add an
     * external menu item to the menu.
     */
    public static function render_meta_box () {
        ?>
	<div class="add-external-menu">
		<input type="hidden" value="custom" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type]" />
		<p id="menu-item-url-wrap" class="wp-clearfix">
			<label class="howto" for="custom-menu-item-url"><?php _e( 'Menu appearance' ); ?></label>
			<img src='http://www.creditlenders.info/wp-content/uploads/stock-image-our-pick-of-the-best-worst-stock-images-sutton-silver.jpg' style='max-width: 100px;'>
		</p>

		<p id="menu-item-name-wrap" class="wp-clearfix">
			<label class="howto" for="custom-menu-item-name"><?php __e( 'Menu URL' ); ?></label>
			<input id="custom-menu-item-name" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-title]" type="text" class="regular-text menu-item-textbox" />
		</p>

		<p class="button-controls wp-clearfix">
			<span class="add-to-menu">
				<input type="submit"<?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?> class="button submit-add-to-menu right" value="<?php esc_attr_e('Add to Menu'); ?>" name="add-custom-menu-item" id="submit-externalmenudiv" />
			</span>
		</p>

	</div><!-- /.externalmenudiv -->

        <?php
    }
}

MenuEditorController::hook();


################## External menus on the main site #################

/**
 * Arrange for the front-end to render complete, stitched menus.
 *
 * This class has no impact on any of the wp-admin pages.
 */
class MenuFrontendController
{
    static function hook () {
        add_filter('wp_nav_menu_objects',
                   array(get_called_class(), 'filter_wp_nav_menu_objects'),
                   10, 2);
    }

    /**
     * Hooked to the @link wp_nav_menu_objects filter.
     *
     * Before rendering a menu to the front-end, replace its tree with
     * the fully stitched one.
     *
     * @param $menu_items The menu as extracted from the database as a
     *                    flat list of "spare parts", chained by their
     *                    ->menu_item_parent and ->db_id fields.
     *                    (Additionally, this module assumes that
     *                    their ->ID is always the same as their
     *                    ->db_id.)
     *
     * @param $args As passed to Wordpress' @link wp_nav_menu function
     */
    static function filter_wp_nav_menu_objects ($menu_items, $args) {
        $menu_orig = Menu::by_term($args->menu);
        if (! $menu_orig) return $menu_items;

        return $menu_orig
            ->get_stitched_tree(
                static::_guess_menu_entry_from_wp_nav_menu_args($args))
            ->trim_external()
            ->copy_classes_and_currents($menu_items)
            ->as_list();
    }

    /**
     * @return The @link MenuMapEntry instance we are rendering the
     *         menu for.
     */
    static function _guess_menu_entry_from_wp_nav_menu_args ($args) {
        $menu_term_id = (int) (is_object($args->menu) ?
                               $args->menu->term_id   : $args->menu);
        $current_lang = (
            function_exists('pll_current_language')   ? 
            pll_current_language()                    : NULL);

        $best = MenuMapEntry
                    ::find(array('menu_term_id' => $menu_term_id))
                    ->first_preferred(array('language' => $current_lang));
        if ($best) {
            return $best;
        } else {
            throw new \Error(
                'Cannot find MenuMapEntry instance for $args = '
                . var_export($args, true)
                . " (\$current_lang = $current_lang)");
        }
    }
}

MenuFrontendController::hook();
