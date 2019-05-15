<?php

/**
 * A set of abstract base classes targeted at model code
 */

namespace EPFL\Model;

if (! defined('ABSPATH')) {
    die('Access denied.');
}

use \WP_Query;

class ModelException extends \Exception {
    public function __construct ($e) {
        if (is_wp_error($e)) {
            parent::__construct(implode("\n", $e->get_error_messages()));
            $this->wp_error = $e;
        } else {
            parent::__construct($e);
        }
    }

    static function check ($e) {
        if (is_wp_error($e)) {
            $thisclass = get_called_class();
            throw new $thisclass($e);
        } else {
            return $e;
        }
    }
}

class UnicityException extends ModelException {}


/**
 * Abstract base class for model objects based on the WPDB API.
 */
abstract class WPDBModel
{
    static function _prepare ($sql, $placeholders_array) {
        $sql = preg_replace('/%T/', static::TABLE_NAME, $sql, 1);

        if (count($placeholders_array)) {
            global $wpdb;
            $sql = call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(array($sql), $placeholders_array));
        }

        return $sql;
    }

    static function query ($sql, ...$placeholders) {
        global $wpdb;
        return $wpdb->query(static::_prepare($sql, $placeholders));
    }

    static function insert ($sql, ...$placeholders) {
        global $wpdb;
        $wpdb->query(static::_prepare($sql, $placeholders));
        return $wpdb->insert_id;
    }

    static function get_results ($sql, ...$placeholders) {
        global $wpdb;
        return $wpdb->get_results(static::_prepare($sql, $placeholders));
    }

    static function get_var ($sql, ...$placeholders) {
        global $wpdb;
        return $wpdb->get_var(static::_prepare($sql, $placeholders));
    }

    static function hook () {
        if (method_exists(get_called_class(), "create_tables")) {
            require_once(__DIR__ . '/this-plugin.php');
            \EPFL\ThisPlugin\on_activate(
                array(get_called_class(), "create_tables"));
        }
        if (method_exists(get_called_class(), "drop_tables")) {
            require_once(__DIR__ . '/this-plugin.php');
            \EPFL\ThisPlugin\on_deactivate(
                array(get_called_class(), "drop_tables"));
        }
    }

    static protected function _as_db_results ($array_or_object) {
        if (is_array($array_or_object)) {
            $object = new \stdClass();
            foreach ($array_or_object as $k => $v) {
                $object->$k = $v;
            }
            return $object;
        } else {
            return $array_or_object;
        }
    }
}

/**
 * Abstract base class for posts
 *
 * Since WP_Post is final (https://core.trac.wordpress.org/ticket/24672),
 * the next best thing is a has-a relationship.
 */
abstract class Post
{
   /**
    * Subclasses should define this method to return a Boolean
    * indicating whether $this->ID points to a "real" instance
    * of said subclass.
    */
    abstract protected function _belongs ();

    /**
     * Protected constructor
     */
    protected function __construct ($id)
    {
        $this->ID = $id;
    }

    /**
     * Wrap one WP_Post in a Post instance
     *
     * @return an instance of this class or null
     */
    static function get ($post_or_post_id)
    {
        if (is_object($post_or_post_id)) {
            $post_id = $post_or_post_id->ID;
        } else {
            $post_id = $post_or_post_id;
        }

        $theclass = get_called_class();
        $that = new $theclass($post_id);
        if (is_object($post_or_post_id)) {
            $that->_wp_post = $post_or_post_id;
        }
        if (! $that->_belongs()) { return; }
        return $that;
    }

    function wp_post ()
    {
        if (! property_exists($this, '_wp_post')) {
            $this->_wp_post = get_post($this->ID);
        }
        return $this->_wp_post;
    }

    /**
     * Metaprogrammed accessors e.g.
     *
     *    $mypost->meta()->get_myprop();
     *
     * These must be declared like this:
     *
     *    const META_ACCESSORS = array(
     *      'myprop' => 'my-meta-slug'
     *    )
     *
     * Or just
     *
     *    const META_ACCESSORS = array(
     *      'myprop'
     *    )
     *
     * There is a performance price, so if you don't want to pay for
     * it, don't call this method - Instead, write ordinary accessors
     * that wrap @link update_post_meta and @link get_post_meta. (And
     * then if you want @link AutoFields functionality, you need to
     * handle that explicitly as well.)
     */
    function meta () {
        if (! isset($this->_meta)) {
            $this->_meta = new _PostMeta(get_called_class(), $this->ID);
        }
        return $this->_meta;
    }

    /**
     * Like @link wp_insert_post, but returns an instance of the class
     * or throws a @link ModelException
     */
    public static function insert ($postarr) {
        if (! $postarr['post_status']) {
            $postarr['post_status'] = 'publish';
        }
        ModelException::check($id = wp_insert_post($postarr, true));
        return static::get($id);
    }

    /**
     * Like @link insert, but for an existing post object.
     */
    public function update ($postarr) {
        $postarr['ID'] = $this->ID;
        ModelException::check(wp_update_post($postarr, true));
    }

    public function get_language () {
        if (! function_exists('pll_get_post_language')) {
            return NULL;
        }
        return pll_get_post_language($this->ID);
    }

    public function set_language ($newlang) {
        if (! function_exists('pll_set_post_language')) return;
        return pll_set_post_language($this->ID, $newlang);
    }

    public function get_title () {
        return $this->wp_post()->post_title;
    }

    public function set_title ($title) {
        wp_update_post([
            'ID' => $this->ID,
            'post_title' =>  $title
            ]
        );
    }

    function error_log ($msg) {
        error_log(get_called_class() . "::get($this->ID): " . $msg);
    }

    /**
     * Iterate with $callback over all instances returned by $query.
     *
     * @param $wp_query An instance of @link WP_Query
     *
     * @param $callback A callable that will be called with each
     *                  instance of this class in turn. The WordPress
     *                  global variables will be updated as if within
     *                  "The loop".
     */
    public static function foreach_query ($wp_query, $callback) {
        $in_the_loop = new _InTheLoopHelper($wp_query);
        try {
            $in_the_loop->enter();

            while ($wp_query->have_posts()) {
                $wp_query->next_post();
                if ($epfl_post = static::get($wp_query->post)) {
                    call_user_func($callback, $epfl_post);
                }
            }
        } finally {
            $in_the_loop->leave();
        }
    }
    
    function __toString () {
        return sprintf('<%s(%d)>', get_called_class(), $this->ID);
    }
}


/**
 * Metaprogrammed helper class for single-valued persistent instance
 * accessors implemented on top of get_post_meta / update_post_meta.
 */
class _PostMeta {
    private $_owner_class;
    private $_post_id;
    private $_meta;

    function __construct ($owner_class, $post_id) {
        $this->_owner_class    = $owner_class;
        $this->_post_id        = $post_id;

        $this->_meta_accessors = array();
        foreach ($owner_class::META_ACCESSORS as $k => $v) {
            if (is_int($k)) {
                $this->_meta_accessors[$v] = $v;
            } else {
                $this->_meta_accessors[$k] = $v;
            }
        }
    }

    function __call ($name, $arguments) {
        $cmd = substr($name, 0, 3);
        $stem = substr($name, 4);
        $meta_name = $this->_meta_accessors[$stem];
        if (! $meta_name) {
            throw new \Exception(
                sprintf('Fatal error: Call to undefined method %s::%s',
                        $this->_owner_class, $name));
        }

        if ($cmd === 'get') {
            return get_post_meta($this->_post_id, $meta_name, true);
        } elseif ($cmd === 'set') {
            $this->_update_meta_auto_fields();
            return $this->_update_post_meta($this->_post_id, $meta_name, $arguments[0]);
        } elseif (substr($name, 0, 4) === 'del_') {
            $this->_update_meta_auto_fields();
            return delete_post_meta($this->_post_id, $meta_name);
        } else {
            throw new \Exception(
                sprintf('Fatal error: Call to undefined method %s::%s',
                        $this->_owner_class, $name));
        }
    }

    /**
     * Like Wordpress' @link update_post_meta, sans the wp_unslash
     * monkey business
     */
    private function _update_post_meta ($id, $meta_key, $meta_value) {
        $filter_name = "sanitize_post_meta_{$meta_key}";
        $return_pristine_value = function(...$whatever) use ($meta_value) {
                return $meta_value;
            };
        try {
            add_filter($filter_name, $return_pristine_value);
            return update_post_meta($id, $meta_key, $meta_value);
        } finally {
            remove_filter($filter_name, $return_pristine_value);
        }
    }

    function has ($stem) {
        return !! $this->_meta_accessors[$stem];
    }

    /**
     * Automatically provide @link \EPFL\AutoFields\AutoFields functionality
     * based on the META_ACCESSORS constant of the owner class.
     *
     * Called when writing through $my_model_object->meta()->set_foo(...)
     * for all entries in META_ACCESSORS, regardless of which one we are
     * currently writing to.
     */
    function _update_meta_auto_fields () {
        if (isset($this->_meta_auto_fields_done)) return;
        require_once(__DIR__ . '/auto-fields.php');
        \EPFL\AutoFields\AutoFields::of($this->_owner_class)->append(
            array_values($this->_meta_accessors));
        $this->_meta_auto_fields_done = TRUE;
    }
}


/**
 * Abstract base class for posts that belong to a custom post type
 */
abstract class TypedPost extends Post
{
    /**
     * @return the post_type slug for instances of this class
     */
    static abstract function get_post_type ();

    function _belongs ()
    {
        return ($this->wp_post()->post_type ===
                get_called_class()::get_post_type());
    }

    /**
     * Iterate with $callback over all instances of this class.
     */
    public static function foreach ($callback) {
        $wp_query = (new _WPQueryBuilder(static::get_post_type()))
                  ->all()->wp_query();
        return static::foreach_query($wp_query, $callback);
    }

    /**
     * Overridden to create items of the correct post type.
     */
    public static function insert ($postarr) {
        $postarr['post_type'] = static::get_post_type();
        static::_inserted_or_deleted();
        return parent::insert($postarr);
    }

    static function make_polylang_translatable () {
        $post_type = static::get_post_type();
        add_filter('pll_get_post_types',
                   function($post_types) use ($post_type) {
                       $post_types[] = $post_type;
                       return $post_types;
                   });
    }

    private static $all = array();  // Keyed by class name
    static function all () {
        $thisclass = get_called_class();
        if (! array_key_exists($thisclass, TypedPost::$all)) {
            TypedPost::$all[$thisclass] = array();
            static::foreach(function($that) use ($thisclass) {
                TypedPost::$all[$thisclass][] = $that;
            });
        }
        return TypedPost::$all[$thisclass];
    }

    static private function _inserted_or_deleted () {
        unset(TypedPost::$all[$thisclass]);
    }
}


/**
 * A class of Posts that can be referenced by some other kind of
 * unique key than the WordPress post ID.
 *
 * The class provides "named constructors"
 * @link get_by_unique_key and @link get_or_create (in addition to the
 * parent class' @link get) which implement fetching/creating objects
 * from an array containing the unique key, possiby composite (i.e.
 * comprised of more than one value).
 *
 * 'Protected virtual' methods @link _query_by_unique_keys and @link
 * _insert_by_unique_keys are provided to provide the default behavior
 * of using Wordpress "post metas" to materialize the unique key(s)
 * in-database. Specifically, the class-level constant
 * META_PRIMARY_KEY shall be the name of a WordPress meta field or a
 * list of same that make up the (possibly composite) "primary key".
 * Subclasses may override both these methods to provide any mapping
 * from the "primary key" to the database and back.
 */
abstract class UniqueKeyTypedPost extends TypedPost
{
    static function get_or_create (...$unique_keys)
    {
        if ($existing = static::_get_by_unique_keys($unique_keys)) {
            return $existing;
        } else {
            return static::_insert_by_unique_keys($unique_keys);
        }
    }

    static function all_except ($instances) {
        $instances_by_primary_key = array();
        foreach ($instances as $instance) {
            $k = serialize($instance->get_unique_keys());
            $instances_by_primary_key[$k] = $instance;
        }

        $retval = array();
        foreach (static::all() as $instance) {
            $k = serialize($instance->get_unique_keys());
            if (! array_key_exists($k, $instances_by_primary_key)) {
                $retval[] = $instance;
            }
        }

        return $retval;
    }

    static function get_by_unique_key ($unique_key) {
        return static::_get_by_unique_keys(array($unique_key));
    }

    static function get_by_unique_keys (...$unique_keys) {
        return static::_get_by_unique_keys($unique_keys);
    }

    static protected function _get_by_unique_keys ($unique_keys) {
        $result = static::_query_by_unique_keys($unique_keys)->result();
        if ($result) {
            $theclass = get_called_class();
            return new $theclass($result->ID);
        }
    }

    /**
     * The piece of @link get_or_create that deals with the "get" part.
     *
     * The base class' implementation reads the META_PRIMARY_KEY and
     * turns $unique_keys into a @link _WPQueryBuilder::by_meta query.
     * A subclass may elect to override this method, as well as
     * @link _insert_by_unique_keys, to map $unique_keys to/from the
     * database in a different way.
     */
    static protected function _query_by_unique_keys ($unique_keys) {
        return (new _WPQueryBuilder(static::get_post_type()))->by_meta(
            static::_metaify($unique_keys));
    }

    /**
     * The piece of @link get_or_create that deals with the "create" part.
     *
     * The base class' implementation reads the META_PRIMARY_KEY and
     * turns $unique_keys into the "meta_input" parameter to
     * @link wp_insert_post. A subclass may elect to override this
     * method, as well as
     * @link _query_by_unique_keys, to map $unique_keys to/from the
     * database in a different way.
     */
    static protected function _insert_by_unique_keys ($unique_keys) {
        return static::insert(array('meta_input' =>
                                    static::_metaify($unique_keys)));
    }

    static protected function _metaify ($unique_keys) {
        $meta = array();
        foreach (static::_get_primary_key_names() as $k) {
            $meta[$k] = array_shift($unique_keys);
        }
        return $meta;
    }

    static protected function _get_primary_key_names () {
        $primary_keys = static::META_PRIMARY_KEY;
        if (! is_array($primary_keys)) {
            $primary_keys = array($primary_keys);
        }
        return $primary_keys;
    }

    function get_unique_keys () {
        $unique_keys = array();
        foreach (static::_get_primary_key_names() as $k) {
            $unique_keys[$k] = get_post_meta($this->ID, $meta_name, true);
        }
    }
}


/**
 * A helper class to prepare and invoke custom WP_Query objects.
 */
class _WPQueryBuilder
{
    function __construct ($query)
    {
        if (is_string($query)) {
            $query = array('post_type' => $query);
        }
        $this->query = $query;
    }

    function by_meta ($meta_array)
    {
        if (! array_key_exists("meta_query", $this->query)) {
            $this->query["meta_query"] = array('relation' => 'AND');
        }

        foreach ($meta_array as $k => $v) {
            array_push($this->query["meta_query"], array(
                'key'     => $k,
                'value'   => $v,
                'compare' => '='
            ));
        }

        return $this;
    }

    private $_saved_query;
    function wp_query ()
    {
        if (! $this->query) {
            throw new Exception("->wp_query, ->result or ->results together may only be called once per _WPQueryBuilder object");
        }
        $query = $this->query;
        unset($this->query);           // ->wp_query is a one-shot pistol
        $this->_saved_query = $query;  // For ->moniker()'s use only

        // Using Polylang's auto-filter-by-language feature in
        // something called model.php, that focuses on primary keys,
        // is a bad idea all around (e.g. think what happens when you
        // disable the Polylang plugin?). But if caller insists we
        // let them.
        if (! array_key_exists('lang', $query)) {
            $query['lang'] = '';
        }
        return new WP_Query($query);
    }

    function result ()
    {
        $results = $this->wp_query()->get_posts();
        if (count($results) > 1) {
            throw new UnicityException(sprintf(
                "%s returned %d results (%s",
                $this->moniker(), count($results),
                var_export(array_map(function($post) { return $post->ID; }, $results),
                          true)));
        }
        return $results[0];
    }

    function results ()
    {
        if (! $this->query["posts_per_page"]) {
            $this->all();
        }
        return $this->wp_query()->get_posts();
    }

    function all ()
    {
        $this->query["posts_per_page"] = -1;
        return $this;
    }

    function moniker ($set_it = null) {
        if ($set_it !== null) {
            $this->moniker = $set_it;
            return $this;    // Chainable
        } elseif ($this->moniker) {
            return $this->moniker;
        } else {
            $q = ($this->query ? $this->query : $this->_saved_query);
            return "<" . var_export($q, true) . ">";
        }
    }
}


/**
 * A helper to help us pretend that we are @link in_the_loop .
 *
 * While there is no telling whether being "in the loop" will help us
 * in any way whatsoever with procuring sex and/or drugs, it does help
 * with WordPress avoiding a so-called "N+1 query problem" with
 * thumbnails.
 *
 * To wit, post-thumbnail-template.php has code that goes like
 *
 * 		if ( in_the_loop() )
 *			update_post_thumbnail_cache();
 *
 * This means a contrario that when not in_the_loop(), each and every
 * call to get_the_post_thumbnail() and friends, results in a new
 * query to the database.
 *
 * @see https://secure.phabricator.com/book/phabcontrib/article/n_plus_one/
 * @see https://www.reddit.com/r/OutOfTheLoop/
 */
class _InTheLoopHelper
{
    /**
     * Prepare to pretend that $wp_query is the main query (which is what
     * in_the_loop() checks).
     */
    function __construct ($wp_query)
    {
        $this->wp_query = $wp_query;
    }

    /**
     * Start pretending to be in the loop.
     */
    function enter ()
    {
        $this->_in_the_loop_orig = $this->wp_query->in_the_loop;
        $this->wp_query->in_the_loop = true;

        global $wp_query;
        $this->_wp_query_orig = $wp_query;
        $wp_query = $this->wp_query;
    }

    /**
     * Stop pretending to be in the loop and restore all state (both
     * global and in the $wp_query object passed to the constructor)
     * as it was before @link enter.
     *
     * Should be called from a "finally" block.
     */
    function leave ()
    {
        $this->wp_query->in_the_loop = $this->_in_the_loop_orig;

        global $wp_query;
        $wp_query = $this->_wp_query_orig;
    }
}
