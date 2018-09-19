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

require_once(__DIR__ . '/../lib/model.php');
use \EPFL\Model\TypedPost;

require_once(__DIR__ . '/../lib/rest.php');
use \EPFL\REST\REST_API;
use \EPFL\REST\RESTClient;
use \EPFL\REST\RESTRemoteError;
use \EPFL\REST\RESTAPIError;
use \EPFL\REST\REST_URL;

require_once(__DIR__ . '/../lib/pubsub.php');
use \EPFL\Pubsub\PublishController;
use \EPFL\Pubsub\SubscribeController;

require_once(__DIR__ . '/../lib/i18n.php');
use function EPFL\I18N\___;
use function EPFL\I18N\__x;
use function EPFL\I18N\__e;

require_once(__DIR__ . '/../lib/this-plugin.php');
use EPFL\ThisPlugin\Asset;

class MenuError extends \Exception {};


/**
 * Object model for "normal" WordPress menus, augmented with support
 * for EPFL-style menu stitching.
 */
class Menu {
    static $all;
    private function __construct () {
    }

    static function all () {
        $thisclass = get_called_class();
        $locations = get_nav_menu_locations();
        $all = array();
        foreach (get_registered_nav_menus() as $slug => $description) {
            $that = new $thisclass();
            $that->slug = $slug;
            $that->description = $description;
            $that->term_id = $locations[$slug];  // Language-dependent
            $all[] = $that;
        }
        return $all;
    }

    static function by_slug ($slug) {
        foreach (static::all() as $that) {
            if ($that->slug === $slug) {
                return $that;
            }
        }
    }

    function get_local_tree () {
        if (! $this->term_id) { return NULL; }
        $tree = wp_get_nav_menu_items($this->term_id);
        if ($tree === FALSE) {
            throw new MenuError(
                "Cannot find term with ID $this->term_id for menu $this->slug");
        }
        return wp_get_nav_menu_items($this->term_id);
    }

    function get_stitched_tree () {
        return $this->get_local_tree();  // XXX Not quite!
    }

    function update ($external_menu_item) {
        // XXX Lazy but correct implementation (for low values of correct):
        // do nothing
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
    const META_PRIMARY_KEY = 'epfl-emi-rest-api-url';

    // For ->meta()->{get_,set_}foo():
    const META_ACCESSORS = array(
        // {get_,set_} suffix => in-database post meta name ("-emi-" stands for "external menu item")
        'rest_url'            => 'epfl-emi-rest-api-url',
        'rest_subscribe_url'  => 'epfl-emi-rest-api-subscribe-url',
        'remote_slug'         => 'epfl-emi-remote-slug',
        'items_json'          => 'epfl-emi-remote-contents-json'
    );

    static function get_post_type () {
        return self::SLUG;
    }

    private static $_all_menus;
    static function all () {
        if (! static::$_all_menus) {
            static::$_all_menus = [];
            static::foreach(function($that) use ($retval) {
                static::$_all_menus[] = $that;
            });
        }
        return static::$_all_menus;
    }

    static private function _all_changed () {
        static::$_all_menus = NULL;
    }

    static function load_from_filesystem () {
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

        $site_url = site_url();
        $site_url = preg_replace('|^https://jahia2wp-httpd/|',
                                 'https://jahia2wp-httpd:8443/', $site_url);
        foreach ((new \RecursiveIteratorIterator($wp_configs_lazy))
                 as $info) {
            $relpath = substr(dirname($info->getPathname()), strlen(ABSPATH));
            if (! $relpath) continue;  // Skip our own directory

            try {
                static::load_from_wp_base_url($site_url . "/$relpath");
            } catch (RESTRemoteError $e) {
                error_log("[Not our fault, IGNORED] " . $e);
                continue;
            }
        }
    }

    static function load_from_wp_base_url ($base_url) {
        foreach (RESTClient::GET_JSON(REST_URL::remote($base_url, 'languages'))
                 as $lang) {
            try {
                $menu_descrs = RESTClient::GET_JSON(REST_URL::remote(
                    $base_url,
                    "menus?lang=$lang"));
            } catch (RESTRemoteError $e) {
                error_log("[Not our fault, IGNORED] " . $e);
                continue;
            }
                
            foreach ($menu_descrs as $menu_descr) {
                $menu_slug = $menu_descr->slug;
                $get_url = REST_URL::remote(
                    $base_url, "menus/$menu_slug?lang=$lang")
                         ->fully_qualified();
                $that = static::get_or_create($get_url);
                $that->meta()->set_remote_slug($menu_slug);

                $title = $menu_desc['description'] . " @ $base_url";
                $that->update(array('post_title' => $title));
                $that->set_language($lang);

                $that->refresh();
            }
        }

        static::_all_changed();
    }

    function refresh () {
        if ($this->_refreshed) return;  // Not twice in same query cycle
        if (! ($get_url = $this->meta()->get_rest_url())) {
            error_log("Menu item #$this->ID doesn't look very external to me");
            return;
        }

        $menu_contents = RESTClient::GET_JSON($get_url);
        $this->set_remote_menu_items($menu_contents->items);
        $this->meta()->set_rest_subscribe_url(
            $menu_contents->get_link('subscribe'));

        $this->_refreshed = true;
    }

    function set_remote_menu_items ($struct) {
        $this->meta()->set_items_json(json_encode($struct));
    }

    function get_remote_menu_items () {
        return json_decode($this->meta()->get_items_json());
    }

    function get_subscribe_url () {
        return $this->meta()->get_rest_subscribe_url();
    }
}

/**
 * Enumerate menus over the REST API
 *
 * Enumeration is independent of languages (or lack thereof) i.e.
 * there is exactly one result per theme slot (in the sense of
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
        REST_API::GET_JSON(
            '/menus',
            get_called_class(), 'get_menus');
        REST_API::GET_JSON(
            '/menus/(?P<slug>[a-zA-Z0-9_-]+)',
            get_called_class(), 'get_menu');

        // "The feature is available only in Polylang Pro." #groan
        REST_API::GET_JSON(
            '/languages',
            function() { return pll_languages_list(); });

        foreach (Menu::all() as $menu) {
            static::_get_publish_controller($menu);
        }
    }

    static function get_menus () {
        $retval = [];
        foreach (Menu::all() as $menu) {
            array_push($retval, array(
                'slug'        => $menu->slug,
                'description' => $menu->description,
            ));
        }
        return $retval;
    }

    static function get_menu ($data) {
        $slug = $data['slug'];  // Matched from the URL with a named pattern

        if ($menu = Menu::by_slug($slug)) {
            $response = new \WP_REST_Response(array(
                'status' => 'OK',
                'items'  => $menu->get_stitched_tree()));
            // Note: this link is for subscribing to changes in any
            // language, not just the one being served now.
            $response->add_link(
                'subscribe',
                REST_URL::local_wrt_request(static::_get_subscribe_uri($menu))
                ->fully_qualified());
            return $response;
        } else {
            throw new RESTAPIError(404, array(
                'status' => 'NOT_FOUND',
                'msg' => "No menu with slug $slug"));
        }
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
        return "menus/$menu->slug/subscribe";
    }

    static private $pubs = array();
    static private function _get_publish_controller ($menu) {
        if (! static::$pubs[$menu]) {
            static::$pubs[$menu] = new PublishController(
                static::_get_subscribe_uri($menu));
        }
        return static::$pubs[$menu];
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
 * see @link MenuEditorController instead.
 *
 * An external menu item is handled as a Wordpress custom post type,
 * with in-database persistence and a custom editor page in the admin
 * menu featuring a custom rendering widget as the main matter,
 * instead of the customary TinyMCE.
 *
 * MenuItemController is a "pure static" class, i.e. no instances are
 * ever constructed.
 */
class MenuItemController
{
    static function hook () {
        $thisclass = get_called_class();

        // See docstring for @link register_post_type
        add_action('init', array($thisclass, 'register_post_type'));

        add_action('rest_api_init', function() use ($thisclass) {
            foreach (ExternalMenuItem::all() as $external_menu_item) {
                $thisclass::hook_pubsub($external_menu_item);
            }
        });

        static::hook_refresh_button();

        static::_auto_fields_controller()->hook();
    }

    static function hook_pubsub ($external_menu_item) {
        $thisclass = get_called_class();
        static::_get_subscribe_controller($external_menu_item)->add_listener(
            function($event) use ($thisclass, $external_menu_item) {
                foreach (Menu::all() as $menu) {
                    if ($menu->update($external_menu_item)) {
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
                $_REQUEST['post_type'] === ExternalMenuItem::SLUG)
            {
                $thisclass::_get_api()->admin_enqueue();
                _MenusJSApp::load();
            }
        });
    }

    static private function _get_api () {
        require_once(__DIR__ . '/../lib/wp-admin-api.php');
        return new \EPFL\AdminAPI\Endpoint('EPFLMenus');
    }

    static private $subs = array();
    static private function _get_subscribe_controller ($external_menu_item) {
        $cache_key = $external_menu_item->ID;
        if (! static::$subs[$cache_key]) {
            static::$subs[$cache_key] = new SubscribeController(
                // The subscribe slug will be embedded in webhook
                // URLs, so it must not change inbetween queries.
                // Using the post ID is perhaps a bit difficult to
                // read out of error logs, but certainly the easiest
                // and most future-proof way of going about it.
                "menu/" . $external_menu_item->ID);
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
        register_post_type(ExternalMenuItem::get_post_type(), array(
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
        ExternalMenuItem::load_from_filesystem();
        foreach (ExternalMenuItem::all() as $external_menu_item) {
            $external_menu_item->refresh();
            static::_get_subscribe_controller($external_menu_item)->
                subscribe($external_menu_item->get_subscribe_url());
        }
        return "edit.php?post_type=" . ExternalMenuItem::SLUG;
    }

    /**
     * Make the "edit" screen for menu objects show the custom
     * main-matter widget and an auto-fields meta box (see @link
     * \EPFL\AutoFields\AutoFieldsController)
     */
    public static function register_meta_boxes () {
        $this_class = get_called_class();

        add_meta_box(
            "epfl_menu_edit_metabox",
            __x("External Menu", "Post edit metabox"),
            array(get_called_class(), 'render_edit_meta_box'),
            null, 'normal', 'high');

        static::_auto_fields_controller()->add_meta_boxes();
    }

    /**
     * Render the meta box appearing as the main matter on the "edit"
     * screen for epfl-external-menu custom post type.
     */
    public static function render_edit_meta_box () {
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
            'delete_post'        => 'delete_post', 
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
