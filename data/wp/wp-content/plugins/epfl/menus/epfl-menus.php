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

class MenuError extends \Exception {}

class TreeError extends \Exception {}

class TreeLoopError extends TreeError {
    function __construct($ids_array) {
        $msg = implode(" ", array_keys($ids_array));
        parent::__construct($msg);
    }
}

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
        $this->_MUTATE_validate_and_toposort();
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

    function copy_classes ($another_menu_tree) {
        return $this->_copy_attributes($another_menu_tree, array('classes'));
    }

    function copy_current ($another_menu_tree) {
        return $this->_copy_attributes($another_menu_tree,
                                       array('current', 'current_item_ancestor',
                                             'current_item_parent'));
    }

    /**
     * Enforce the same values on attributes 'current',
     * 'current_item_ancestor' and 'current_item_parent' and 'classes'
     * as WordPress' @link wp_nav_menu function would:
     *
     * - every parent of an item that is 'current' is 'current_item_parent'
     *
     * - every parent or ancestor of an item that is 'current' is
         'current_item_ancestor'
     *
     * - every node that has children has class 'menu-item-has-children'
     *
     * @return A fixed-up copy of $this
     */
    function fixup_tree_attributes_and_classes() {
        $copy = $this->copy();
        $current_items = array();  // Plural 'coz why not?
        foreach ($copy->items as $item) {
            if ($item->current) {
                $current_items[$this->_get_id($item)] = $item;
            }
        }

        $current_ancestors = array();
        foreach ($current_items as $item) {
            $current_ancestor_id = $this->_get_parent_id($item);
            if (! ($current_ancestor = $copy->items[$current_ancestor_id])) continue;
            $current_ancestors[$current_ancestor_id] = $current_ancestor;
            $current_ancestor->current_item_parent = true;
            $current_ancestor->current_item_ancestor = true;
        }

        while(count($current_ancestors)) {
            $current_ancestors_next = array();
            foreach ($current_ancestors as $current_ancestor) {
                $current_ancestor_id = $this->_get_parent_id($current_ancestor);
                if (! ($current_ancestor =
                       $copy->items[$current_ancestor_id])) continue;
                $current_ancestors_next[$current_ancestor_id] = $current_ancestor;
                $current_ancestor->current_item_ancestor = true;
            }
            $current_ancestors = $current_ancestors_next;
        }

        $all_parents = array();
        foreach ($copy->items as $item) {
            if (! ($parent_id = $this->_get_parent_id($item))) continue;
            $all_parents[$parent_id] = 1;
        }
        foreach (array_keys($all_parents) as $parent_id) {
            $copy->items[$parent_id]->classes[] = 'menu-item-has-children';
            // wp_nav_menu() caller will eliminate any duplicates
        }

        return $copy;
    }

    private function _copy_attributes ($another_menu, $attributes) {
        $thisclass = get_called_class();
        if ($another_menu instanceof $thisclass) {
            $src_items = $another_menu_tree->items;
        } else {
            $src_items = array();
            foreach ($another_menu as $item) {
                $src_items[$this->_get_id($item)] = $item;
            }
        }

        $copy = $this->copy();
        foreach ($copy->items as $id => &$item) {
            foreach ($attributes as $k) {
                if ($src_items[$id]->$k) {
                    $item->$k = $src_items[$id]->$k;
                }
            }
        }
        return $copy;
    }

   /**
    * Sort the tree in-place in topological order (ancestors before
    * descendants)
    *
    * At the same time, validate the tree so that
    *
    *  - All ->_get_parent_id's are 0 or within the graph
    *
    *  - There are no ancestry loops
    */
    private function _MUTATE_validate_and_toposort () {
        // Based on https://en.wikipedia.org/wiki/Topological_sorting#Kahn's_algorithm
        $children = array();    // Adjacency list, keyed by parent ID
        $safe     = array();    // List of nodes with no loops in their ancestry
                                // (S in the Wikipedia algorithm)

        foreach ($this->items as $item) {
            $id = $this->_get_id($item);
            $parent_id = $this->_get_parent_id($item);
            if (! $parent_id) {
                $safe[] = $id;
            } elseif (! array_key_exists($parent_id, $this->items)) {
                throw new TreeError("Parent of $id ($parent_id) unknown");
            } elseif (! $children[$parent_id]) {
                $children[$parent_id] = array($id);
            } else {
                $children[$parent_id][] = $id;
            }
        }

        $sorted = array();    // L in the Wikipedia algorithm
        while(count($safe)) {
            $n = array_shift($safe);  // Proof of termination starts here

            // $n is 1) known not to have loops in its ancestry, 2)
            // at the right place at the end of the topological sort.
            $sorted[] = $n;

            if ($children[$n]) {
                // All children of a safe node are safe
                $safe = array_merge($safe, $children[$n]);
                // Discard the corresponding edges from the adjacency list
                unset($children[$n]);
            }
        }

        if (count($children)) {
            // Some nodes were not visited; they must have cycles.
            throw new TreeLoopError(array_keys($children));
        }

        $unsorted_items = $this->items;
        $this->items = array();
        foreach ($sorted as $id) {
            $this->items[$id] = $unsorted_items[$id];
        }
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
            $this->_get_parent_id($at_item),
            $this->copy(), $this->_renumber(static::coerce($bag)));
    }

    function reverse_graft ($at_item, $into) {
        $parent_id = $this->_get_parent_id($at_item);
        $into_renumbered = $this->_renumber($into, /*&*/$parent_id);
        return static::_MUTATE_graft($parent_id, $into_renumbered, $this);
    }

    /**
     * Pluck all items in MenuItemBag that match $callable_predicate,
     * and return them separately from the pruned tree. Discard
     * now-dangling descendants of the plucked items.
     *
     * @param $callable_predicate A callable that takes a $item as the
     *        sole parameter, and returns a true value for items to
     *        remove from the tree, and a false value for those to
     *        keep. Items that are descendants of an item for which
     *        $callable_predicate returned true, will be discarded
     *        (regardless of what $callable_predicate returned for them,
     *        if indeed $callable_predicate was called at all for them
     *        which is left unspecified)
     *
     * @return A pair of the form array($pruned_tree, $removed) where
     *         $pruned_tree is a newly created copy of the pruned tree
     *         (or $this if $callable_predicate never returned true),
     *         and $removed is the list of items for which
     *         $callable_predicate returned a true value (*not* the
     *         descendants thereof).
     */
    function pluck ($callable_predicate) {
        $remaining_candidates = array();

        $pruned = array();
        foreach ($this->items as $item) {
            if (call_user_func($callable_predicate, $item)) {
                $pruned[$this->_get_id($item)] = $item;
            } else {
                $remaining_candidates[] = $item;
            }
        }

        if (! count($pruned)) {
            return array($this, []);  // No change
        }

        // Keep descendants of $pruned out of $remaining
        $remaining = array();
        foreach ($remaining_candidates as $rc) {
            $ancestor_ids = array();

            for ($ancestor = $rc; $ancestor;
                 $ancestor = $this->get_parent($ancestor))
            {
                $ancestor_id = $this->_get_id($ancestor);
                if ($ancestor_ids[$ancestor_id]) {
                    throw new TreeLoopError($ancestor_ids);
                } else {
                    $ancestor_ids[$ancestor_id] = 1;
                }
                if ($pruned[$ancestor_id]) continue 2;
            }
            $remaining[] = $rc;
        }

        $thisclass = get_called_class();
        return array(new $thisclass($remaining), $pruned);
    }

    /**
     * Apply a function on each item in this MenuItemBag.
     *
     * @param $func A callable that takes a $item as the sole
     *        parameter, and returns either a menu item (with a type
     *        and structure similar to $item) or NULL
     *
     * @return A new MenuItemBag made out of all the non-NULL values
     *         returned by $func
     */
    function map ($func) {
        $mapped_items = array();
        foreach ($this->items as $item) {
            $mapped_item = call_user_func($func, $item);
            if ($mapped_item !== NULL) {
                $mapped_items[] = $mapped_item;
            }
        }
        $thisclass = get_called_class();
        return new $thisclass($mapped_items);
    }

    /**
     * @return A copy of $this without the ExternalMenuItem nodes
     */
    function trim_external () {
        return $this->pluck(function($item) {
            return ExternalMenuItem::looks_like($item);
        })[0];
    }

    /**
     * @return A copy of $this with ExternalMenuItem entries annotated
     *         with their ->rest_url, and stripped from other
     *         irrelevant (internal-only) metadata
     */
    function export_external () {
        return $this->map(function($item) {
            if ($emi = ExternalMenuItem::get($item)) {
                unset($item->url);
                unset($item->guid);
                unset($item->object_id);  // Only makes sense locally

                $item->rest_url = $emi->get_rest_url();
            }
            return $item;
        });
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
        $item = clone($item);
        $this->_MUTATE_set_id       ($item, (int) $this->_get_id       ($item));
        $this->_MUTATE_set_parent_id($item, (int) $this->_get_parent_id($item));
        $this->items[$this->_get_id($item)] = $item;
    }

    function _MUTATE_graft ($new_parent_id, $mutated_outer, $inner) {
        foreach ($inner->copy()->as_list() as $item) {
            if (! $mutated_outer->_get_parent_id($item)) {
                // $item is new, thanks to ->copy() being
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
     * _get_largest_negative_unused_id.
     *
     * @param $other_bag An instance of this class (call @link coerce
     *        yourself if needed)
     *
     * @param &$follow_this_id If passed-in as the ID of an item in
     *                         the tree being renumbered, will be
     *                         mutated (and passed-out) as the ID of the
     *                         same item after renumbering
     *
     * @return A new instance of this class, fully unshared from both
     *         $this and $other_bag
     */
    protected function _renumber ($other_bag, &$follow_this_id = NULL) {
        $next_id = $this->_get_largest_negative_unused_id();

        $translation_table = array();
        foreach ($other_bag->items as $item) {
            $orig_id        = $this->_get_id       ($item);
            $orig_parent_id = $this->_get_parent_id($item);
            foreach (array($orig_id, $orig_parent_id) as $old_id) {
                if ($old_id and ! $translation_table[$old_id]) {
                    $translation_table[$old_id] = $next_id--;
                }
            }
        }

        // Now that we have a complete $translation_table, start over
        // and renumber
        $translated = array();
        foreach ($other_bag->items as $item) {
            $item = clone $item;

            $orig_id = $this->_get_id($item);
            $this->_MUTATE_set_id($item, $translation_table[$orig_id]);

            if ($orig_parent_id = $this->_get_parent_id($item)) {
                $this->_MUTATE_set_parent_id(
                    $item, $translation_table[$orig_parent_id]);
            }

            $translated[] = $item;
        }

        if ($follow_this_id) {  // Not NULL, not zero
            $follow_this_id = $translation_table[$follow_this_id];
        }

        $thisclass = get_called_class();
        return new $thisclass($translated);
    }

    protected function _get_largest_negative_unused_id () {
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
    static function by_term ($term_or_term_id) {
        if (is_object($term_or_term_id)) {
            $term_id = $term_or_term_id->term_id;
        } else {
            $term_id = $term_or_term_id;
        }

        $thisclass = get_called_class();
        return new $thisclass($term_id);
    }

    private function __construct ($term_id) {
        if ($term_id > 0) {
            $this->term_id = $term_id;
        } else {
            throw new \Error("Bogus term ID $term_id");
        }
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

    /**
     * @return A site-wide unique integer ID for this Menu
     */
    function get_term_id () {
        return (int)$this->term_id;
    }

    function equals ($that) {
        $thisclass = get_called_class();
        if ($that and ($that instanceof $thisclass)) {
            return $this->get_term_id() === $that->get_term_id();
        } else {
            return false;
        }
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
     * Compute "stitched down" menu rooted at this Menu.
     *
     * ExternalMenuItem entries present in the menu are decorated with
     * the contents of the remote menu, as obtained over REST; they
     * remain in the returned tree as a positional indicator for REST
     * clients (call @link MenuItemBag::trim_external to remove them,
     * e.g. prior to passing to a frontend walker).
     *
     * Keep our own nodes (the ones we have authority for) with
     * positive IDs and renumber all grafted nodes with negative IDs.
     *
     * @return A @link MenuItemBag instance, in which the
     *         authoritative menu entries (the ones retrieved from the
     *         local database) have positive IDs, while the remote
     *         IDs are negative
     */
    function get_stitched_down_tree () {
        $tree = $this->_get_local_tree();
        foreach ($tree->as_list() as $item) {
            if (! ($emi = ExternalMenuItem::get($item))) continue;
            $soa_url = Site::externalify_url(
                $emi->get_site_url() ?
                $emi->get_site_url() :
                $emi->get_rest_url());

            $remote_menu = $emi->get_remote_menu();
            if ($remote_menu) {
                $tree = $tree->graft(
                    $item,
                    $remote_menu->annotate_roots(array(
                        self::SOA_SLUG => $soa_url)));
            } else {
                error_log("$emi has no remote menu");
            }
        }

        return $tree;
    }

    /**
     * Compute the fully stitched menu that surrounds this Menu.
     *
     * Like @link get_stitched_down_tree, but additionally if we are
     * rendering the primary menu and this is not the root site, graft
     * ourselves into the root site's menu at the proper point.
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
    function get_fully_stitched_tree ($mme) {
        $tree = $this->get_stitched_down_tree();
        if (! $mme->is_main()) return $tree;
        if (Site::this_site()->is_root()) return $tree;

        if (! $root_menu = $this->_get_root_menu($mme)) {
            error_log("Unable to stitch up - Root menu not found for $mme");
            return $tree;
        }

        $soa_slug = self::SOA_SLUG;
        $site_url = site_url();
        if (! preg_match('#/$#', $site_url)) {
            $site_url .= '/';
        }
        $root_menu = $root_menu->pluck(
            function($item) use ($soa_slug, $site_url) {
                return $item->$soa_slug === $site_url;
            })[0];

        // When stitching up, the graft point(s) are any and all
        // ExternalMenuItem's that point to ourselves.
        $grafted_count = 0;
        $self = $this;
        foreach (
            array_filter($root_menu->as_list(),
                         function($item) use ($self) {
                             return $self->_corresponds($item);
                         })
            as $graft_point)
        {
            $tree = $tree->reverse_graft($graft_point, $root_menu);
            $grafted_count++;
        }

        if (! $grafted_count) {
            error_log(sprintf(
                'Cannot find graft point - Unable to stitch up\n%s',
                var_export($root_menu, true)));
        }

        return $tree;
    }

    protected function _get_root_menu ($mme) {
        $emi = ExternalMenuItem::find(array(
               'site_url'       => Site::root()->get_localhost_url(),
               'remote_slug'    => $mme->get_theme_location()
        ))
            ->first_preferred(array(
                'language' => $mme->get_language()));

        if (! $emi) return;

        return $emi->get_remote_menu();
    }

    /**
     * @param $item An item out of a @link MenuItemBag
     *
     * @return True iff $item designates us - That is, it came from
     *         the JSON representation of some ExternalMenuItem in
     *         another WordPress in the same pod, that points to this
     *         Menu instance.
     */
    protected function _corresponds ($item) {
        if (! ExternalMenuItem::looks_like($item)) return false;
        $url = Site::this_site()->get_relative_url($item->rest_url);
        if (! $url) return false;

        // Works by parsing the ->rest_url, so there is coupling with
        // MenuRESTController
        $matched = array();
        if (! preg_match(
            '#/wp-json/(.*)/menus/(.*?)(?:\?lang=(.*))?$#',
            $url, $matched)) {
            return false;
        }

        $mme = MenuMapEntry::find(array('theme_location' => $matched[2]))
              ->first_preferred(array('language' => $matched[3]));
        if (! $mme) return false;

        return $this->equals($mme->get_menu());
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
    private function __construct(
        $term_or_term_id, $theme_location, $description, $language = NULL)
    {
        $this->menu = Menu::by_term($term_or_term_id);
        $this->theme_location = $theme_location;
        $this->description = $description;
        $this->language = $language;
    }

    function __toString () {
        $thisclass = get_called_class();
        return "<$thisclass('$this->theme_location', '$this->language')>";
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

    function is_main () {
        $loc = $this->get_theme_location();
        return ($loc === 'primary' or $loc === 'top');
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

    // For ->meta()->{get_,set_}foo():
    const META_ACCESSORS = array(
        // {get_,set_} suffix  => in-database post meta name ("-emi-" stands for "external menu item")
        'rest_url'             => 'epfl-emi-rest-api-url',
        'rest_subscribe_url'   => 'epfl-emi-rest-api-subscribe-url',
        'items_json'           => 'epfl-emi-remote-contents-json',
        'last_synced'          => 'epfl-emi-last-synced-epoch',
        'sync_started_failing' => 'epfl-emi-sync-started-failing-epoch',

        // The following two meta fields are *optional* and only exist
        // for "true" WordPress-generated menus. (This is not always
        // the case - Think single-purpose footer menu server written
        // in node.js)
        'site_url'             => 'epfl-emi-site-url',
        // Also from this perspective, we call this a slug not a
        // theme_location (which is what it is from the perspective of
        // the remote MenuMapEntry, but we don't want to know or care
        // about that)
        'remote_slug'          => 'epfl-emi-remote-slug',
    );

    // For get_or_create():
    const META_PRIMARY_KEY = array('epfl-emi-rest-api-url');

    static function get_post_type () {
        return self::SLUG;
    }

    /**
     * @return The URL of the local WordPress site that this
     *         ExternalMenuItem comes from, or NULL if this
     *         ExternalMenuItem doesn't live in this pod.
     */
    function get_site_url () {
        return $this->meta()->get_site_url();
    }

    /**
     * @return The URL that this ExternalMenuItem obtains the remote
     *         menu from (in JSON form)
     */
    function get_rest_url () {
        return $this->meta()->get_rest_url();
    }

    /**
     * Overridden to also accept a 'nav-menu-item' Post object with
     * ->object === "epfl-external-menu".
     */
    static function get ($what) {
        if (static::looks_like($what)
            and ($what->object_id)) {
            return parent::get($what->object_id);
        } else {
            return parent::get($what);
        }
    }

    /**
     * Returns true iff $what looks like a menu item (e.g. from
     * a @link MenuItemBag) that was an ExternalMenuItem at the time
     * it was inserted into the wp-admin menu.
     *
     * Unlike checking @link get for a NULL return value, this check
     * doesn't hit the database; it will work even for
     * ExternalMenuItem's that are not local to this Wordpress site.
     */
    static function looks_like ($what) {
        return (is_object($what)
                and ($what->post_type === 'nav_menu_item')
                // Don't test for ->object_id here, as
                // MenuItemBag->export_external() could have scrubbed
                // it before serving over REST
                and ($what->object === static::get_post_type()));
    }

    use FindFromAllTrait;

    static function load_from_filesystem () {
        $me = Site::this_site();
        $neighbors = array_merge(array(Site::root()),
                                 $me->get_subsites());

        $instances = array();
        foreach ($neighbors as $site) {
            if ($site->equals($me)) continue;
            try {
                $instances = array_merge($instances, static::_load_from_site($site));
            } catch (RESTRemoteError $e) {
                error_log("[Not our fault, IGNORED] " . $e);
                continue;
            }
        }
        return $instances;
    }

    static protected function _load_from_site ($site) {
        return static::load_from_wp_site_url($site->get_localhost_url());
    }

    /**
     * @return An array of instances of this class
     */
    static function load_from_wp_site_url ($site_url) {
        $instances = array();
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
                $that = static::get_or_create(
                    static::_make_wp_rest_url($site_url, $menu_slug, $lang));
                // Unlike an ExternalMenuItem that would be just
                // created from its REST URL, for this one we do know
                // that it comes from a "true" Wordpress. Note down
                // the additional metadata we know (for the purpose
                // of @link Menu::_get_root_menu)
                $that->meta()->set_site_url($site_url);
                $that->meta()->set_remote_slug($menu_slug);
                // Keep Polylang language in sync, so that the little flags
                // show up in the wp-admin list UI.
                $that->set_language($lang);

                $site_url_dir = parse_url($site_url, PHP_URL_PATH);
                $title = $menu_descr->description . "[$lang] @ $site_url_dir";
                $that->update(array('post_title' => $title));

                $that->try_refresh();
                $instances[] = $that;
            }
        }
        return $instances;
    }

    static private function _make_wp_rest_url ($site_url, $menu_slug,
                                               $lang = NULL) {
        $stem = "menus/$menu_slug";
        if ($lang) {
            $stem .= "?lang=$lang";
        }
        return REST_URL::remote($site_url, $stem)->fully_qualified();
    }

    protected function _do_refresh () {
        if (! ($get_url = $this->get_rest_url())) {
            $this->error_log("doesn't look very external to me");
            return;
        }

        $menu_contents = RESTClient::GET_JSON($get_url);
        $this->set_remote_menu($menu_contents->items);
        $this->meta()->set_rest_subscribe_url(
            $menu_contents->get_link('subscribe'));
    }

    function try_refresh () {
        if ($this->_refreshed !== NULL) {
            // Do not attempt twice in same query cycle
            return $this->_refreshed;
        }
        try {
            $this->_do_refresh();
            $this->_refreshed = true;
            $this->meta()->set_last_synced(time());
            $this->meta()->del_sync_started_failing();
            return true;
        } catch (RESTClientError $e) {
            $this->error_log("unable to refresh: $e");
            if (! $this->meta()->get_sync_started_failing()) {
                $this->meta()->set_sync_started_failing(time());
            }
            $this->_refreshed = false;
            return false;
        }
    }

    function set_remote_menu ($what) {
        $what = MenuItemBag::coerce($what);
        $this->meta()->set_items_json(json_encode($what->as_list()));
    }

    function get_remote_menu () {
        $json = $this->meta()->get_items_json();
        if (! $json) return;
        return new MenuItemBag(json_decode($json));
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
        // There is coupling with Menu::_corresponds as far as
        // the URI structure is concerned.
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
            'items'  => $menu->get_stitched_down_tree()
                             ->export_external()->as_list()));
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
        $emis = array();
        try {
            $emis = ExternalMenuItem::load_from_filesystem();
        } catch (\Throwable $t) {
            error_log("ExternalMenuItem::load_from_filesystem(): $t");
            static::admin_error(___('Unable to load menus of sites in the same pod'));
            // Continue
        }

        $errors = 0;
        foreach (array_merge($emis,
                             // Optimization: keep the
                             // ExternalMenuItem's obtained above,
                             // along with their state, so as to avoid
                             // ->try_refresh()ing them twice.
                             ExternalMenuItem::all_except($emis))
                 as $emi)
        {
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
     * Make the "edit" screen for menu objects show an auto-fields
     * meta box (see @link \EPFL\AutoFields\AutoFieldsController)
     */
    public static function register_meta_boxes () {
        static::_auto_fields_controller()->add_meta_boxes();
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
        add_action('admin_enqueue_scripts', function() {
            if (get_current_screen()->base === 'nav-menus') {
                _MenusJSApp::load();
            }
        });
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
        // Note: Polylang also hooks into wp_nav_menu_objects at
        // priority 10 and it doesn't really care about the original
        // menu set. We need to register ourselves after it. (Besides,
        // Polylang can produce invalid menus in the sense of
        // _MUTATE_validate_and_toposort, containing dangling parent
        // pointers.)
        add_filter('wp_nav_menu_objects',
                   array(get_called_class(), 'filter_wp_nav_menu_objects'),
                   11, 2);
    }

    /**
     * Hooked to the @link wp_nav_menu_objects filter.
     *
     * Before rendering a menu to the front-end, replace its tree with
     * the fully stitched one. Maintain all Wordpress-y bells and
     * whistles such as additional CSS classes ('class' attribute),
     * ancestry decoration ('current', 'current_item_parent' and
     * 'current_item_ancestor' attributes) etc.
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
            ->get_fully_stitched_tree(
                static::_guess_menu_entry_from_menu_term($args->menu))
            ->trim_external()
            ->copy_classes($menu_items)
            ->copy_current($menu_items)
            ->fixup_tree_attributes_and_classes()
            ->as_list();
    }

    /**
     * @return The @link MenuMapEntry instance we are rendering the
     *         menu for.
     */
    static function _guess_menu_entry_from_menu_term ($menu) {
        $menu_term_id = (int) (is_object($menu) ?
                               $menu->term_id   : $menu);
        $current_lang = (
            function_exists('pll_current_language')   ? 
            pll_current_language()                    : NULL);

        return MenuMapEntry
            ::find(array('menu_term_id' => $menu_term_id))
            ->first_preferred(array('language' => $current_lang));
    }
}

MenuFrontendController::hook();
