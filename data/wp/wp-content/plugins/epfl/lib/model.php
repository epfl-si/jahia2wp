<?php

/**
 * A set of abstract base classes targeted at model code
 */

namespace EPFL\Model;

if (! defined('ABSPATH')) {
    die('Access denied.');
}

use \WP_Query;

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

    static function get_results ($sql, ...$placeholders) {
        global $wpdb;
        return $wpdb->get_results(static::_prepare($sql, $placeholders));
    }

    static function get_var ($sql, ...$placeholders) {
        global $wpdb;
        return $wpdb->get_var(static::_prepare($sql, $placeholders));
    }

    static function hook_table_creation () {
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
        if (! $this->_wp_post) {
            $this->_wp_post = get_post($this->ID);
        }
        return $this->_wp_post;
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
     *
     * @param $callback A callable that will be called with each
     * instance of this class in turn. The WordPress global variables
     * will be updated as if within "The loop".
     */
    public static function foreach ($callback)
    {
        $all = (new _WPQueryBuilder(static::get_post_type()))->all()->wp_query();
        $in_the_loop = new _InTheLoopHelper($all);
        try {
            $in_the_loop->enter();

            while ($all->have_posts()) {
                $all->next_post();
                if ($epfl_post = static::get($all->post)) {
                    call_user_func($callback, $epfl_post);
                }
            }
        } finally {
            $in_the_loop->leave();
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
        if (! $this->query["meta_query"]) {
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
            throw new Exception("Can only call one of ->wp_query, ->result or ->results");
        }
        $retval = new WP_Query($this->query);
        $this->_saved_query = $this->query;
        unset($this->query);
        return $retval;
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
