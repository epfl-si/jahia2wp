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
            $that->term_id = $locations[$menu_slug];  # Language-dependent
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

    static function autodetect () {
        # http://ch1.php.net/manual/en/class.recursivedirectoryiterator.php#114504
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

            $api_base_url = $site_url . "/$relpath/wp-json/epfl/v1";
            try {
                $langs = RESTClient::GET_JSON("$api_base_url/languages");
            } catch (RESTRemoteError $e) {
                error_log("[Not our fault, IGNORED] " . $e);
                continue;
            }
            foreach ($langs as $lang) {
                try {
                    $menus = RESTClient::GET_JSON("$api_base_url/menus?lang=$lang");
                } catch (RESTRemoteError $e) {
                    error_log("[Not our fault, IGNORED] " . $e);
                    continue;
                }
                
                foreach ($menus as $menu) {
                    error_log(var_export($menu, true));
                }
            }
        }
    }
}

/**
 * An external menu that needs fetching and/or integrating
 * with the "normal" menus.
 */
class ExternalMenuItem extends \EPFL\Model\TypedPost
{
    const SLUG = "epfl-external-menu";

    static function get_post_type () {
        return self::SLUG;
    }

    static function all () {
        $retval = [];
        static::foreach(function($that) use ($retval) {
            $retval[] = $that;
        });
        return $retval;
    }
}


/**
 * Enumerate menus over the REST API
 *
 * Enumeration is independent of languages (or lack thereof) i.e.
 * there is exactly one result per theme slot (in the sense of
 * @link get_registered_nav_menus).
 *
 * @url /epfl/v1/menus
 */
class MenuRESTController
{
    static public $pubsub;

    static function hook () {
        REST_API::GET_JSON(
            '/menus',
            get_called_class(), 'get_menus');
        REST_API::GET_JSON(
            '/menus/(?P<slug>[a-zA-Z0-9_-]+)',
            get_called_class(), 'get_menu');

        # "The feature is available only in Polylang Pro." #groan
        REST_API::GET_JSON(
            '/languages',
            function() { return pll_languages_list(); });

        add_action('rest_api_init', function() {
            foreach (ExternalMenuItem::all() as $external_menu) {
                self::$pubsub = new PubsubController(static::menu_api_slug($external_menu));
                self::$pubsub->add_listener(function($event) use ($thisclass) {
                    _MenuStitcher::of($external_menu)->changed();
                    $pubsub->forward($event);
                });
            }
        });
    }

    // TODO: serve all menus in all languages; provide suitable
    // endpoint URLs for the "subscribe" and "query" APIs
    static function get_menus () {
        $retval = [];
        foreach (Menu::all() as $menu) {
            array_push($retval, array(
                'slug'        => $menu->slug,
                'description' => $menu->description));
        }
        return $retval;
    }

    static function get_menu ($data) {
        $menu_slug = $data['slug'];  # Matched from the URL with a named pattern
        return array(
            "status" => "OK",
            "items"  => Menu::by_slug($slug)::get_stitched_tree()

        );
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
        add_action('init', array($thisclass, 'register_post_type'));
        add_action('admin_head', array($thisclass, 'render_css'));
        add_action('admin_head-edit.php',array($thisclass, 'add_refresh_button'));
        add_action('admin_init', function() use ($thisclass) {
            require_once(__DIR__ . '/../lib/ajax.php');
            $endpoint = new \EPFL\AJAX\Endpoint('EPFLMenus');
            $endpoint->register_handlers($thisclass);
            $endpoint->admin_enqueue('edit.php');
        });
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
                                            'register_meta_box')
        ));
    }

    /**
     * Invoked by the refresh button in the wp-admin area
     */
    static function ajax_refresh () {
        Menu::autodetect();
    }

    /**
     * Make the "edit" screen for menu objects show the custom main-matter
     * widget
     */
    public static function register_meta_box() {
        $this_class = get_called_class();

        add_meta_box(
            "epfl_menu_edit_metabox",
            __x("External Menu", "Post edit metabox"),
            array(get_called_class(), 'render_meta_box'),
            null, 'normal', 'high');
    }

    /**
     * Render the meta box appearing as the main matter on the "edit"
     * screen for epfl-external-menu custom post type.
     */
    public static function render_meta_box () {
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

    public static function render_css () {
        ?>
<style>
#edit-external-menu {
        background-color: yellow;
}
</style>
<?php
    }

    public static function add_refresh_button () {
        // https://stackoverflow.com/a/29813737/435004 says,
        // there's no doing this but with jQuery. So let's throw
        // in some AJAX on top, shall we?
        global $current_screen;

        if (ExternalMenuItem::SLUG != $current_screen->post_type) return;
        ?><script>
jQuery(function($) {
    $('a.page-title-action').remove();
    $('h1.wp-heading-inline').after('<button class="page-title-action"><?php _e("Refresh"); ?></button>');
    var $button = $('h1.wp-heading-inline').next();
    $button.click(function() {
        window.EPFLMenus.post("refresh")
              .done(function() {
                  console.log("Refresh done");
                  window.location.reload(true);
              })
              .fail(function() {
                  alert("<?php __e('Refresh failed'); ?>");
              });
    });
});
</script><?php
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
}

MenuItemController::hook();


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
            if ('nav-menus' !== get_current_screen()->base) return;
            (new Asset("menus/epfl-menus-admin.js"))->enqueue_script();
            (new Asset("menus/epfl-menus-admin.css"))->enqueue_style();
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
				<span class="spinner"></span>
			</span>
		</p>

	</div><!-- /.externalmenudiv -->

        <?php
    }
}

MenuEditorController::hook();
