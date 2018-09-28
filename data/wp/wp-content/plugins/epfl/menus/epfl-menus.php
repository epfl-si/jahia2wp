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
 * Object model for "normal" WordPress menus, augmented with support
 * for EPFL-style menu stitching.
 */
class Menu {
    private function __construct ($slug, $description, $term_id) {
        assert($term_id);
        $this->slug        = $slug;
        $this->description = $description;
        $this->term_id     = $term_id;
    }

    /**
     * @return The slug this menu is known as to API consumers. Note that
     *         this is not a unique key (see @link get_term_id for that):
     *         depending on the language (passed through the ?lang=
     *         query parameter in REST API calls), the same slug might
     *         stand for different instances of Menu.
     */
    function get_slug () {
        return $this->slug;
    }

    /**
     * @return A site-wide unique integer ID for this Menu
     */
    function get_term_id () {
        return $this->term_id;
    }

    function get_description () {
        return $this->description;
    }

    static function all () {
        $thisclass = get_called_class();
        $locations = get_nav_menu_locations();
        $all = array();
        foreach (get_registered_nav_menus() as $slug => $description) {
            if (! ($term_id = $locations[$slug])) continue;
            $all[] = new $thisclass(
                $slug, $description, $term_id);
        }
        return $all;
    }

    static function by_slug ($slug) {
        foreach (static::all() as $that) {
            if ($that->get_slug() === $slug) {
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

    function update ($emi) {
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
        'rest_url'             => 'epfl-emi-rest-api-url',
        'rest_subscribe_url'   => 'epfl-emi-rest-api-subscribe-url',
        'remote_slug'          => 'epfl-emi-remote-slug',
        'items_json'           => 'epfl-emi-remote-contents-json',
        'last_synced'          => 'epfl-emi-last-synced-epoch',
        'sync_started_failing' => 'epfl-emi-sync-started-failing-epoch',
    );

    static function get_post_type () {
        return self::SLUG;
    }

    private static $_all_emis;
    static function all () {
        if (! static::$_all_emis) {
            static::$_all_emis = [];
            static::foreach(function($that) use ($retval) {
                static::$_all_emis[] = $that;
            });
        }
        return static::$_all_emis;
    }

    static private function _all_changed () {
        static::$_all_emis = NULL;
    }

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
        static::load_from_wp_base_url($site->get_localhost_url());
    }

    static function load_from_wp_base_url ($base_url) {
        foreach (RESTClient::GET_JSON(REST_URL::remote($base_url, 'languages'))
                 as $lang) {
            try {
                $menu_descrs = RESTClient::GET_JSON(REST_URL::remote(
                    $base_url,
                    "menus?lang=$lang"));
            } catch (RESTClientError $e) {
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

                $base_url_dir = parse_url($base_url, PHP_URL_PATH);
                $title = $menu_descr->description . " @ $base_url_dir";
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
            $this->error_log("doesn't look very external to me");
            return;
        }

        $menu_contents = RESTClient::GET_JSON($get_url);
        $this->set_remote_menu_items($menu_contents->items);
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

    function set_remote_menu_items ($struct) {
        $this->meta()->set_items_json(json_encode($struct));
    }

    function get_remote_menu_items () {
        return json_decode($this->meta()->get_items_json());
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
            foreach (Menu::all() as $menu) {
                $thisclass::_get_publish_controller($menu);
            }
        });
    }

    static function get_menus () {
        $retval = [];
        foreach (Menu::all() as $menu) {
            array_push($retval, array(
                'slug'        => $menu->get_slug(),
                'description' => $menu->get_description(),
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
            if (function_exists('pll_current_language')) {
                $inlang = " in language " . pll_current_language();
            }
            throw new RESTAPIError(404, array(
                'status' => 'NOT_FOUND',
                'msg' => "No menu with slug $slug$inlang"));
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
                foreach (Menu::all() as $menu) {
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
