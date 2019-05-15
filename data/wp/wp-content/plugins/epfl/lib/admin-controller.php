<?php

/**
 * Abstract base classes and utility classes for controllers in the
 * wp-admin area.
 */

namespace EPFL\AdminController;


if (! defined('ABSPATH')) {
    die('Access denied.');
}

/**
 * Manage pop-up errors or notices at the top of admin pages
 *
 * Use WordPress transients to persist the errors, so that they can
 * be shown after the browser navigates to another page. Errors are
 * per-user and per-page (as materialized by the $slug argument
 * to @link publish and subscribe)
 *
 * This is a "pure static" class; no instances are ever constructed.
 */

class TransientError
{
    /**
     * Persist $error to disk to be fetched and displayed soon by
     * @link subscribe.
     *
     * @param $slug The "channel" for errors, to distinguish among
     *              several error contexts (e.g. per post, or per page).
     *              Note that errors are further isolated per user,
     *              i.e. a logged-in user won't see someone else's errors
     *              even if they are published with the same $slug.
     */
    static function publish ($slug, $error) {
        $transient_key = static::_privatify_slug($slug);
        $errors = get_transient($transient_key);
        if (! $errors) {
            $errors = array();
        } elseif (! is_array($errors)) {
            $errors = array($errors);
        }

        $errors[] = $error;
        set_transient($transient_key, $errors,
                      45);  // Seconds before it self-destructs
    }

    /**
     * Add slag to $slug so that our transients are private per user,
     * and don't collide with other Wordpress transients.
     */
    static function _privatify_slug ($slug) {
        $me = get_current_user_id();
        return "epfl:TransientError:$me:$slug";
    }

    /**
     * Show any and all errors stashed with @link publish.
     *
     * Will only render errors from the same logged-in user,
     * *and* having the same $slug set at @link publish time.
     *
     * @param $slug A unique string to classify errors across multiple
     *              channels (e.g. admin pages); must match the $slug
     *              parameter passed to @link publish to recover these
     *              errors and them only.
     *
     * @param $render_callback The render function to pass the errors to.
     *                         Defaults to @link render
     */
    static function subscribe ($slug, $render_callback = NULL) {
        $thisclass = get_called_class();
        if (! $render_callback) {
            $render_callback = array($thisclass, 'render');
        }
        if (doing_action('admin_notices')) {
            $thisclass::_do_fetch_and_render($slug, $render_callback);
        } else {
            add_action(
                'admin_notices',
                array(get_called_class(), 
                      function() use ($thisclass, $slug, $render_callback) {
                          $thisclass::_do_fetch_and_render(
                              $slug, $render_callback);
                      }));
        }
    }

    private static function _do_fetch_and_render ($slug, $render_callback) {
        $transient_key = static::_privatify_slug($slug);
        if ($errors = get_transient($transient_key)) {
            foreach ($errors as $error) {
                call_user_func($render_callback, $error);
            }
            delete_transient($transient_key);
        }
    }

    /**
     * The default renderer for admin notices managed by this class
     *
     * @param $error Either a string, or an array with keys 'level'
     * and 'msg'.
     */
    static function render ($error) {
        if (! is_array($error)) {
            $error = array(
                'level' => 'error',
                'msg' => $error
            );
        }
            ?>
    <div class="notice notice-<?php echo $error['level'] ?> is-dismissible">
        <p><?php echo $error['msg']; ?></p>
    </div><?php
    }
}

/**
 * Abstract base class for all controller classes
 */
abstract class Controller
{
    /**
     * Arrange for a nonfatal error to be shown in a so-called "admin
     * notice."
     *
     * This notice will be put up on any page for which the @link
     * is_active method returns true.
     */
    static protected function admin_notice ($msg, $level = 'notice') {
        TransientError::publish(
            static::_get_transient_errors_slug(), array(
                'level' => $level,
                'msg' => $msg));
    }

    static private function _get_transient_errors_slug () {
        return 'Transients-' . get_called_class();
    }

    static protected function admin_error ($msg) {
        static::admin_notice($msg, 'error');
    }

    static function hook () {
        $thisclass = get_called_class();
        add_action('admin_notices', function() use ($thisclass) {
            if (! $thisclass::is_active()) return;
            TransientError::subscribe(
                static::_get_transient_errors_slug($thisclass));
        });
    }

    /**
     * @return true iff the current page pertains to this controller.
     */
    static abstract protected function is_active ();
}

/**
 * Abstract base class for the controller of a model class that
 * consists of a custom post type that itself derives from abstract
 * class @link TypedPost.
 *
 * This is a "pure static" class; no instances are ever constructed.
 *
 */
abstract class CustomPostTypeController extends Controller
{
    /**
     * @return The model class for this controller
     */
    abstract static function get_model_class ();

    static function is_active () {
        return array_key_exists('post_type', $_REQUEST)?$_REQUEST['post_type'] === static::get_post_type():false;
    }

    static function hook ()
    {
        parent::hook();
        static::hook_meta_boxes();
        add_action("admin_enqueue_scripts", array(get_called_class(), "_editor_css"));
    }

    static private function _belongs ($post_or_post_type) {
        return static::get_model_class()::get($post_or_post_type);
    }

    static function _editor_css ($hook)
    {
        if (! ('post.php' === $hook &&
               static::get_model_class()::get($_GET["post"])) ) return;
        wp_register_style(
            'ws-editor',
            plugins_url( 'ws-editor.css', __DIR__ ) );
        wp_enqueue_style('ws-editor');
    }

    static $_columns;
    /**
     * Add or mutate a column in the WP_List_Table list view in wp-admin
     *
     * By default, the class method "render_${slug}_column" will be called
     * to render the contents of the column.
     *
     * @return _CustomPostTypeControllerColumn
     */
    static function column ($slug) {
        if (! static::$_columns[$slug]) {
            $_columns[$slug] = new _CustomPostTypeControllerColumn
                             (get_called_class(), $slug);
        }
        return $_columns[$slug];
    }

    static function get_post_type ()
    {
        $model_class = static::get_model_class();
        return $model_class::get_post_type();
    }

    /**
     * Add a column in the list view that shows thumbnails
     */
    static function add_thumbnail_column ()
    {
        static::add_editor_css('
td.column-thumbnail img {
    max-width: 100%;
    height: auto;
}
');
        return static::column("thumbnail")
            ->set_title(___( 'Thumbnail' ))
            // Use of static::render_thumbnail_column() is the default
            ->hook_after(1);
    }

    /**
     * Echo what should be in the relevant <td> in the thumbnail column.
     *
     * The base class echoes a single <img> tag. Subclasses might want
     * to echo something instead of, or in addition to that.
     *
     * @param $epfl_post An instance of the class returned by @link
     * get_model_class
     */
    static function render_thumbnail_column ($epfl_post)
    {
        $img = get_the_post_thumbnail($epfl_post);
        if (! $img) return;
        echo $img;
    }

    static $_syncing_on_save = null;
    /**
     * Call ->sync() upon saving a TypedPost object.
     *
     * Call this method from the initialization code (e.g. a `hook`
     * method) in order to enable this functionality for all instances
     * of the @link get_model_class.
     */
    static function call_sync_on_save ()
    {
        $model_class = static::get_model_class();
        add_action(
            'save_post',  // *Not* save_post_$post_type, so that one
                          // may ::call_sync_on_save() also from
                          // within a save_meta_box_foo method
            function ($post_id, $post, $is_update) use ($model_class) {
                $model_obj = $model_class::get($post);
                if (! $model_obj) return;
                if (CustomPostTypeController::$_syncing_on_save === $post_id) {
                    return;  // Break out of possible recursion
                }
                $syncing_on_save_orig = CustomPostTypeController::$_syncing_on_save;
                try {
                    CustomPostTypeController::$_syncing_on_save = $post_id;
                    $model_obj->sync();
                } finally {
                    CustomPostTypeController::$_syncing_on_save = $syncing_on_save_orig;
                };
            }, 10, 3);
    }

    static function add_editor_css ($css)
    {
        add_action('admin_head', function () use ($css) {
            echo "<style>$css</style>";
        });
    }

    /**
     * Hook the meta box business for this class.
     *
     * Note: This is typically not sufficient to enable metabox
     * functionality. One must also call
     * @link add_meta_box once or more, typically from the
     * `register_meta_box_cb` callback passed to @link
     * register_post_type.
     */
    static function hook_meta_boxes ()
    {
        add_action('edit_form_after_title',
                   array(get_called_class(), 'meta_boxes_above_editor'));
        add_action('edit_form_after_editor',
                   array(get_called_class(), 'meta_boxes_after_editor'));
        add_action(sprintf('save_post_%s', static::get_post_type()),
                   array(get_called_class(), 'save_meta_boxes'), 10, 3);
    }

    /**
     * Render all meta boxes configured to show up above the editor.
     */
    static function meta_boxes_above_editor ($post)
    {
        if (! static::_belongs($post)) return;
        do_meta_boxes(get_current_screen(), 'above-editor', $post);
    }

    /**
     * Render all meta boxes configured to show up after the editor.
     */
    static function meta_boxes_after_editor ($post)
    {
        if (! static::_belongs($post)) return;
        do_meta_boxes(get_current_screen(), 'after-editor', $post);
    }

    /**
     * Simpler version of the WordPress add_meta_box function
     *
     * @param $slug Unique name for this meta box. The render function
     * is the method called "render_meta_box_$slug", and the save function
     * is the method called "save_meta_box_$slug" (for the latter see
     * @link save_meta_boxes)
     *
     * @param $title The human-readable title for the meta box
     *
     * @param $position The position to render the meta box at;
     *        defaults to "above-editor" (see @link meta_boxes_above_editor).
     *        Can be set to any legal value for the $priority argument
     *        to the WordPress add_meta_box function, in particular "default"
     *        to render the meta box after the editor.
     */
    static function add_meta_box ($slug, $title, $position = 'above-editor')
    {
        $callback = array(get_called_class(), "render_meta_box_$slug");
        $meta_box_name = sprintf("%s-epfl-meta-box_%s", static::get_post_type(), $slug);
        add_meta_box($meta_box_name, $title,
                     function () use ($meta_box_name, $callback) {
                         wp_nonce_field($meta_box_name, $meta_box_name);
                         global $post;
                         call_user_func($callback, $post);
                     },
                     null, $position);
    }

    static private $saved_meta_boxes = array();
    /**
     * Call the save_meta_box_$slug method for any and all meta box that is
     * posting information.
     *
     * Any and all nonces present in $_REQUEST, for which a corresponding
     * class method exists, are checked; then the class method is called,
     * unless already done in this request cycle.
     */
    static function save_meta_boxes ($post_id, $post, $is_update)
    {
        // Bail if we're doing an auto save
        if (defined( 'DOING_AUTOSAVE' ) && \DOING_AUTOSAVE) return;
        foreach ($_REQUEST as $k => $v) {
            $matched = array();
            if (preg_match(sprintf('/%s-epfl-meta-box_([a-zA-Z0-9_]+)$/',
                                   static::get_post_type()),
                           $k, $matched)) {
                $save_method_name = "save_meta_box_" . $matched[1];
                if (method_exists(get_called_class(), $save_method_name)) {
                    if (! wp_verify_nonce($v, $k)) {
                        wp_die(___("Nonce check failed"));
                    } elseif (! current_user_can('edit_post')) {
                        wp_die(___("Permission denied: edit " . static::get_post_type()));
                    } elseif (self::$saved_meta_boxes[$k]) {
                        // Break out of silly recursion: we call
                        // writer functions such as wp_insert_post()
                        // and wp_update_post(), which call us back
                        return;
                    } else {
                        self::$saved_meta_boxes[$k] = true;
                        call_user_func(
                            array(get_called_class(), $save_method_name),
                            $post_id, $post, $is_update);
                    }
                }
            }
        }  // End foreach
    }
}

/**
 * A custom column in the WP_List_Table view
 *
 * By default, the class method "render_${slug}_column" will be called
 * to render the contents of the column; but this can be changed by
 * invoking @link set_renderer. (In both cases, the callback argument
 * is the instance of the model class to render the column for.)
 */
class _CustomPostTypeControllerColumn
{
    /**
     * "private" constructor, call ::column() in your
     * @link CustomPostTypeController subclass instead.
     */
    function __construct ($owner_controller, $slug)
    {
        $this->owner = $owner_controller;
        $this->slug  = $slug;

        // Default settings:
        $this->set_title($slug);
        $this->set_renderer(array($owner_controller, "render_${slug}_column"));
    }

    function set_title ($translated_title)
    {
        $this->title = $translated_title;
        return $this;  // Chainable
    }

    function add_css ($css)
    {
        // For compatibility with epfl-ws
        $this->owner_controller->add_editor_css($css);
        return $this;
    }

    function set_renderer ($callable)
    {
        $this->render = $callable;
        return $this;
    }

    function make_sortable ($sort_opts)
    {
        $this->sort_opts = $sort_opts;
        return $this;
    }

    function insert_after ($after) {
        $this->insert_after = $after;
        return $this;
    }

    function hook_after ($after) {
        // For compatibility with epfl-ws
        return $this->insert_after($after)->hook();
    }

    function hook ()
    {
        $post_type = $this->get_post_type();
        add_action(
            sprintf('manage_%s_posts_columns', $post_type),
            array($this, '_filter_posts_columns'));

        add_action(
            sprintf('manage_%s_posts_custom_column', $post_type),
            array($this, '_filter_manage_custom_column'),
            10, 2);

        // Sorting
        add_action('pre_get_posts', array($this, '_action_pre_get_posts'));
        add_filter(
            sprintf('manage_edit-%s_sortable_columns', $post_type),
            array($this, '_filter_manage_sortable_columns'));

        return $this;   // Chainable, because why not?
    }

    protected function get_model_class ()
    {
        $controller_class = $this->owner;
        return $controller_class::get_model_class();
    }

    protected function get_post_type ()
    {
        $model_class = $this->get_model_class();
        return $model_class::get_post_type();
    }

    function _filter_posts_columns ($columns)
    {
        if (method_exists('WP_Posts_List_Table', 'column_' . $this->slug)) {
            // We can override a built-in column, but we have to
            // put it under another slug in order to fool
            // WP_List_Table's method dispatch technique.
            $newcolumns = array();
            foreach ($columns as $col_slug => $descr) {
                if ($col_slug === $this->slug) {
                    // @link _filter_manage_custom_column is aware; also,
                    // it just so happens that "column-$col_slug" will
                    // still be part of the class= markup computed by
                    // WP_List_Table::single_row_columns ☺
                    $evading_slug = "epfl-overridden column-$col_slug";
                    $newcolumns[$evading_slug] = $this->title;
                } else {
                    $newcolumns[$col_slug] = $descr;
                }
            }
            return $newcolumns;
        } elseif (is_int($this->insert_after)) {
            // https://stackoverflow.com/a/3354804/435004
            $newcolumns = array_merge(
                array_slice($columns, 0, $this->insert_after, true),
                array($this->slug => $this->title),
                array_slice($columns, $this->insert_after,
                            count($columns) - $this->insert_after, true));
            return $newcolumns;
        } elseif (is_string($this->insert_after)) {
            $newcolumns = array();
            foreach ($columns as $col_slug => $descr) {
                $newcolumns[$col_slug] = $descr;
                if ($col_slug === $this->insert_after) {
                    $newcolumns[$this->slug] = $this->title;
                }
            }
            return $newcolumns;
        } else {
            wp_die("Unsupported value for ->insert_after: " . var_export($this->insert_after, true));
        }
    }

    function _filter_manage_custom_column ($column, $post_id)
    {
        // See $evading_slug in @link _filter_posts_columns
        $column = preg_replace('/^epfl-overridden column-/', '', $column);
        if ($column !== $this->slug) return;

        $model_class = $this->get_model_class();
        $epfl_post = $model_class::get($post_id);
        if (! $epfl_post) return;

        call_user_func($this->render, $epfl_post);
    }

    function _filter_manage_sortable_columns ($columns)
    {
        if (isset($this->sort_ops)) {
            $columns[$this->slug] = $this->slug;
        }
        return $columns;
    }

    /**
     * Honor requests to sort by this column
     */
    function _action_pre_get_posts ($query)
    {
        if ( ! is_admin() ) return;
        if (! property_exists($this, 'sort_opts')) return;
        if ($query->get( 'orderby' ) !== $this->slug) return;

        // Here we could examine $this->sort_opts to support sorting
        // by something other than a meta_key.
        $query->set('orderby', 'meta_value');
        $query->set('meta_key', $this->sort_opts['meta_key']);
    }
}
