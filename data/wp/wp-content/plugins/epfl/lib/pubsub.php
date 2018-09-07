<?php

/**
 * Poor man's EAI with Web hooks and PHP/MySQL.
 *
 * Signaling only - Payload propagation is left to caller code.
 * Provides causality chaining, loop elimination and (still TODO)
 * basic webhook authentication and Prometheus-style monitoring.
 */

namespace EPFL\Pubsub;

if (! defined('ABSPATH')) {
    die('Access denied.');
}

require_once(__DIR__ . '/rest.php');
use \EPFL\REST\REST_API;
use \EPFL\REST\RESTClient;
use \EPFL\REST\RESTClientError;

require_once(__DIR__ . '/this-plugin.php');

/**
 * Your entry point to everything pubsub.
 *
 * An instance of this class represents "us" as a member of a
 * publish-subscribe mesh. There can be as many pubsub channels
 * as desired, distinguished by the $slug constructor parameter.
 *
 * Technically, the PubsubController API is no true pubsub as it can
 * only be used to convey a semaphore information with no payload,
 * e.g. "something has just changed". Callers are expected to
 * propagate the payload (e.g. to find out what exactly just changed)
 * through some other mechanism, such as a separate REST API call.
 *
 * Subscribers call @link subscribe, to cause a POST query to be sent
 * synchronously to the subscriber, which persists the contact
 * information in-database. Every time the publisher calls one
 * of @link initiate or @link forward in its own instance of
 * PubsubController, ours get a callback for every callable that was
 * registered with @link add_listener (which cannot be made
 * persistent, and therefore must be done as part of e.g. a
 * 'rest_api_init' action set up with @link add_action).
 *
 * The publish-side API provides two methods, @link initiate and @link
 * forward, in order to offer causality chaining and spanning-tree
 * style loop detection.
 */
class PubsubController
{
    /**
     * @param $slug A unique string for this pubsub channel.
     *              Will be used as a URL part, so should
     *              contain no weird characters except slash
     *              (and then, only in the middle)
     */
    function __construct ($slug) {
        $this->slug = $slug;
        $this->set_site_url(preg_replace('^http:', 'https:', site_url()));

        $this->listeners = [];

        REST_API::POST_JSON(
            "/pubsub/$slug/subscribe", $this, 'on_REST_subscribe');
        REST_API::POST_JSON(
            "/pubsub/$slug/webhook", $this, 'on_REST_webhook');
    }

    public function set_site_url ($site_url) {
        $this->my_urls = PubsubURLs::by_site_url($site_url, $this->slug);
    }

    public function instance_id () {
        return $this->my_urls->instance_id();
    }

    /**
     * Subscribe to $remote_url, and have them call us back
     *
     * That fact should be persisted on the remote side.
     *
     * @param $remote_url can be constructed e.g. with @link PubsubURL::subscribe_url
     */
    public function subscribe ($remote_url) {
        $slug = $this->slug;
        $nonce = "SOMENONCE";  // TODO: pick a real one, remember the last two
        RESTClient::POST_JSON(
            $remote_url,
            array(
                "instance_id"   => $this->instance_id(),
                "callback_url"  => $this->my_urls->webhook(array("nonce" => $nonce))));
    }

    /**
     * Call $callable every time we receive a webhook response
     * following a call to @link subscribe
     *
     * $callable is called with an instance of @link Event as the sole
     * parameter, meaning (since @link Event instances don't have a
     * user-supplied payload) that PubsubController cannot actually
     * convey any data around. It is up to the caller code to procure
     * any desired payload through another means, such as a subsequent
     * REST API call in the other direction.
     */
    public function add_listener ($callable) {
        $this->listeners[] = $callable;
    }

    public /* not really */
    function on_REST_subscribe ($req) {
        $params = $req->get_params();
        $remote_instance_id = $params["instance_id"];
        _Subscriber::overwrite($this->slug, $remote_instance_id, $callback_url);
    }

    public /* not really */
    function on_REST_webhook ($req) {
        // TODO: validate the nonce
        $event = Event::unmarshall($req->get_params());
        foreach ($this->listeners as $callable) {
            call_user_func($callable, $event);
        }
    }

    /**
     * Forward $event to all subscribers for this pubsub slug
     *
     * Avoid loops by doing nothing if $event has already been seen
     * by any given subscriber.
     */
    public function forward ($event) {
        $event = $event->forward($this->instance_id());
        foreach (_Subscriber::by_slug($slug) as $subscriber) {
            if (! $subscriber->has_seen($event)) {
                $subscriber->send($event);
            }
        }
    }

    /**
     * Ping all subscribers to this pubsub slug "motu proprio"
     */
    public function initiate () {
        $event = Event::start($this->instance_id());
        $this->forward($event);
    }

    // TODO: plumb down Prometheus counters
}

/**
 * Parse/pretty-print URLs according to this module's URL layout
 */
class PubsubURLs {
    protected __construct () {}

    static function by_site_url ($site_url, $slug) {
        $that = new get_called_class();
        $that->instance_id = $site_url . "/wp-json/epfl/v1/pubsub/" . $slug;
        return $that;
    }

    static function guess_by_webhook ($webhook) {
        $that = new get_called_class();
        $instance_id = preg_replace('/?.*$/', '', $webhook);
        $instance_id = preg_replace('/webhook$', '', $instance_id);
        $that->instance_id = $instance_id;
        return $that;
    }

    public function instance_id () {
        return $this->instance_id;
    }

    public function subscribe_url () {
        return $this->instance_id . '/subscribe';
    }

    public function webhook ($query_params = NULL) {
        $url = $this->instance_id . '/webhook',
        if ($query_params) {
            $query_params_encoded = http_build_query($query_params);
            $url = (FALSE === strpos('?', $url)) ? 
                   "$url?$query_params_encoded" :
                   "$url&$query_params_encoded";
        }
        return $url;
    }
}

/**
 * Persistent subscriber lists, with contact details and
 * automated cleanup of failing subscribers
 *
 * An instance represents one subscriber, and it knows how
 * to send an @link Event to it through its webhook.
 */
class _Subscriber
{
    const DEAD_SUBSCRIBER_TIMEOUT_SECS = 86400 * 30;

    static function hook () {
        \EPFL\ThisPlugin\on_activate(
            array(get_called_class(), "create_table"));
    }

    static function create_table () {
        global $wpdb;
        $wpdb->query("CREATE TABLE IF NOT EXISTS subscriber (
  slug VARCHAR(100) NOT NULL,
  instance_id VARCHAR(1024),
  callback_url VARCHAR(1024),
  last_failure DATETIME,
  UNIQUE KEY `subscriber_composite_key`
);");
    }

    static public function overwrite ($slug, $instance_id, $callback_url) {
        $thisclass = get_called_class();
        $that = new $thisclass();
        $that->instance_id  = $instance_id;
        $that->slug         = $slug;
        $that->callback_url = $callback_url;

        $that->_delete();
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT INTO subscriber (instance_id, slug, callback_url)
             VALUES (%s, %s, %s)",
            $this->instance_id, $this->slug, $this->callback_url));

        return $that;
    }

    protected function __construct () {}

    private function _delete () {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM subscriber
                 WHERE instance_id = %s AND slug = %s",
            $this->instance_id, $this->slug));
    }

    static function by_slug ($slug) {
        $thisclass = get_called_class();
        $objects = array();
        for ($wpdb->get_results($wpdb->prepare(
            "SELECT slug, instance_id, callback_url, UNIX_TIMESTAMP(last_failure)
             FROM subscriber
             WHERE slug = %s", $slug)) as $line) {
            $that = new $thisclass();
            $that->instance_id  = $db_line->instance_id;
            $that->slug         = $db_line->slug;
            $that->callback_url = $db_line->callback_url;
            $that->last_failure = $db_line->last_failure;
            $objects[] = $that;
        }
        return $objects;
    }

    public function has_seen ($event) {
        return $event->has_in_path($this->instance_id);
    }

    public function send ($event) {
        try {
            RESTClient::POST_JSON($this->callback_url, $event->marshall());
            $this->mark_success();
        } catch (RESTClientError $e) {
            error_log($e);
            $this->mark_error();
        }
    }

    public function mark_error () {
        global $wpdb;
        if (! $this->last_failure) {
            $this->last_failure = time();
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "UPDATE subscriber SET last_failure = FROM_UNIXTIME(%d)
                 WHERE instance_id = %s AND slug = %s",
                $this->last_failure, $this->instance_id, $this->slug));
        } else if (time() - $this->last_failure
                   > self::DEAD_SUBSCRIBER_TIMEOUT_SECS) {
            $this->_delete();
        }
    }

    public function mark_success () {
        if (! $this->last_failure) return;
        
        $wpdb->query($wpdb->prepare(
            "UPDATE subscriber SET last_failure = NULL
                 WHERE instance_id = %s AND slug = %s",
            $this->instance_id, $this->slug));
    }
}

/**
 * A data structure used to prevent loops in pubsub propagation.
 *
 * Instances of this class are immutable. They contain the start event
 * timestamp and a propagation path as a list of pubsub instance IDs
 * strings.
 */
class Event
{
    static function unmarshall ($json) {
        $thisclass = get_called_class();
        $that = new $thisclass();
        $data = is_string($json) ? json_decode($json) : $json;
        $that->timestamp = $data->timestamp;
        $that->path      = $data->path;
        return $that;
    }

    function marshall () {
        return json_encode(array(
            'timestamp' => $this->timestamp,
            'path'      => $this->path));
    }

    static function start ($my_instance_id) {
        $thisclass = get_called_class();
        $that = new $thisclass();
        $that->timestamp = time();
        $that->path = [];
        $that->add_to_path($my_instance_id);
        return $that;
    }

    static function has_in_path ($instance_id) {
        return in_array($instance_id, $event->path)
    }

    /**
     * @return A new Event instance with $via_instance_id
     *         appended to the path
     */
    function forward ($via_instance_id) {
        $thisclass = get_called_class();
        $that = new $thisclass();
        $that->timestamp = $this->timestamp;
        $that->path      = $this->path;
        $that->path[]    = $via_instance_id;
        return $that;
    }
}
