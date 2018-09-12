<?php

/**
 * Poor man's EAI with Web hooks and PHP/MySQL.
 *
 * Technically, the pubsub API is no true pubsub as it can only be
 * used to convey a semaphore information with no payload, e.g.
 * "something has just changed". Callers are expected to propagate the
 * payload (e.g. to find out what exactly just changed) through some
 * other mechanism, such as a separate REST API call.
 *
 * This module provides causality chaining, loop elimination, basic
 * security with nonce-based webhook authentication (on top of
 * SSL/TLS) and (still TODO) Prometheus-style monitoring.
 */

namespace EPFL\Pubsub;

if (! defined('ABSPATH')) {
    die('Access denied.');
}

require_once(__DIR__ . '/rest.php');
use \EPFL\REST\REST_API;
use \EPFL\REST\RESTClient;
use \EPFL\REST\RESTClientError;

require_once(__DIR__ . '/model.php');
use \EPFL\Model\WPDBModel;

class TestWebhookFlowException extends \Exception {}
class CausalityLoopError extends \Exception {}

/**
 * Controller for a subscription.
 *
 * Subscribers first call @link subscribe, to cause a POST query to be
 * sent synchronously to the publisher, which persists the contact
 * information in-database. Every time the publisher has data to send
 * (see @link PublishController for details), our SubscribeController
 * instance will call back every callable that was registered
 * with @link add_listener.
 *
 * Since PHP is unable to persist closures, it is up to caller code to
 * thereafter re-create / call @link add_listener on as many instances
 * as there are subscription topics it is interested in, at the
 * beginning of request cycle that could receive a webhook call
 * (typically from a 'rest_api_init' action; see @link add_action).
 */
class SubscribeController
{
    /**
     * @param $slug A unique persistent string for this subscribe controller,
                    also used for loop elimination.
     */
    function __construct ($slug) {
        $this->slug = $slug;
    
        $this->listeners = [];
    }

    static function hook () {
        $thisclass = get_called_class();

        // Same entry points for all webhooks; demultiplexing is
        // performed on the nonce.
        REST_API::POST_JSON(
            "/pubsub/webhook", $thisclass, 'on_REST_webhook');
        REST_API::POST_JSON(
            "/pubsub/webhook-test", $thisclass, 'on_REST_test_webhook');
    }

    /**
     * Call $callable every time we receive a webhook response
     * following a call to @link subscribe
     *
     * $callable is called with an instance of @link Causality as the sole
     * parameter, meaning (since @link Causality instances don't have a
     * user-supplied payload) that PubsubController cannot actually
     * convey any data around. It is up to the caller code to procure
     * any desired payload through another means, such as a subsequent
     * REST API call in the other direction.
     */
    public function add_listener ($callable) {
        $this->listeners[] = $callable;
    }

    public /* not really */
    static function on_REST_webhook ($req) {
        $sub = _Subscription::get_by_nonce($nonce = $req->params["nonce"]);
        if (! $sub) {
            _Events::error_invalid_nonce($nonce);
            return;
        }
        _Events::request_webhook_received($sub->slug);
        $event = Causality::unmarshall($req->get_params())
                 ->received($this->_get_subscriber_id());
        foreach ($this->listeners as $callable) {
            call_user_func($callable, $event);
        }
        _Events::request_webhook_successful($sub->slug);
    }

    /**
     * Subscribe to $remote_url using a newly minted nonce
     *
     * That change shall be persisted on the remote side (by @link
     * PublishController), as well as on the local side (since from
     * then on one should be constructing a SubscribeController
     * instance with the same $slug on basically every request cycle).
     *
     * Set up a newly minted nonce (or renew it in case of subsequent
     * calls). Before the subscription URL on the other side responds,
     * expect a test query to /wp-json/epfl/v1/webhook-test using the
     * same nonce.
     */
    public function subscribe ($remote_url) {
        $sub = _Subscription::temporary();

        try {
            $response = RESTClient::POST_JSON(
                $remote_url,
                array(
                    "subscriber_id" => $this->_get_subscriber_id(),
                    "callback_url"  => $this->my_urls->webhook(array("nonce" => $nonce))));
            $sub->expect_confirmed();
        } finally {
            $sub->cleanup();
        }
        
        (new _Subscription($this->slug))->register($this->my_urls, $remote_url);
    }

    public /* not really */
    static function on_REST_test_webhook ($req) {
        $tmp_sub = _Subscription::get_by_nonce(
            $req->params['nonce'], /* $confirmed = */ FALSE);
        $tmp_sub->confirmed();
    }

    private function _get_subscriber_id () {
        return $this->slug . "@" . site_url();
    }
}

SubscribeController::hook();


/**
 * Persistent records for subscriptions
 *
 * Note: what to do when a subscription gets an update (see @link
 * SubscribeController::add_listener) cannot be persisted (it's code -
 * and that's just not the way PHP rolls). The only thing we keep
 * around is security information (i.e. the nonce, and its validation
 * state).
 */
class _Subscription extends WPDBModel
{
    protected const TABLE_NAME = "epfl_pubsub_subscriptions";

    static function create_tables () {
        static::query("CREATE TABLE IF NOT EXISTS %T (
  slug VARCHAR(100) NOT NULL,
  nonce VARCHAR(100) NOT NULL,
  confirmed BOOL NOT NULL
);");
    }

    static function drop_tables () {
        static::query("DROP TABLE IF EXISTS %T");
    }
    
    protected function __construct ($slug, $nonce, $confirmed = TRUE) {
        $this->slug = $slug;
        $this->nonce = $nonce;
        $this->confirmed = $confirmed;
    }

    static function get_by_nonce ($nonce, $confirmed = TRUE) {
        $confirmed_sql = $confirmed ? "TRUE" : "FALSE";
        $results = static::get_results(
            "SELECT slug FROM %T WHERE nonce = %s 
             AND confirmed = $confirmed_sql",
            $nonce);
        if (count($results)) {
            $thisclass = get_called_class();
            $that = new $thisclass($results[0]->slug, $nonce, $confirmed);
        } else {
            return NULL;
        }
    }

    static function make_temporary ($slug) {
        $thisclass = get_called_class();
        return new $thisclass($slug, $thisclass::_make_nonce, false);
    }

    static function _make_nonce () {
        $strength_in_bytes = 40;  // Should be an even number, so that
                                  // there are no extra =='s at the end
                                  // of the base64'd string
        return base64_encode(get_random_bytes(40));
    }

    function confirm () {
        if ($this->confirmed) {
            throw new TestWebhookFlowException("Unexpected attempt to re-confirm $this->slug");
        }
        $this->query('BEGIN WORK');
        try {
            $count_changed = $this->query(
                'UPDATE %T SET confirmed = TRUE
                 WHERE confirmed = FALSE AND NONCE = %s',
                $this->nonce);

            if ($count_changed != 1) {
                throw new TestWebhookFlowException(
                    "Bad update count: slug $this->slug," .
                    " expecting 1, got $count_changed");
            }
            $this->query('COMMIT WORK');
            $this->confirmed = true;
        } finally {
            if (! $this->confirmed) {
                $this->query('ROLLBACK WORK');
            }
        }
    }

    function expect_confirmed () {
        $thisclass = get_called_class();
        if ($thisclass->get_by_nonce($this->nonce)) return;
        throw new TestWebhookFlowException(
            "No call to /webhook-test received for $this->slug at this time");
    }

    function cleanup () {
        if (! $this->confirmed) return;
        $this->query("DELETE FROM %T WHERE slug = %s AND nonce != %s",
                     $this->slug, $this->nonce);
    }
}

_Subscription::hook();


/**
 * Controller for something you want to publish.
 *
 * An instance of this class represents "us" as the sender of some
 * publish-subscribe feed. There can be as many such feeds as desired,
 * distinguished by the $slug constructor parameter.
 *
 * To publish something (or more precisely, signal that something is
 * available for consumption *somewhere else*) call one of @link
 * initiate and @link forward, which provide causality chaining and
 * spanning-tree style loop elimination.
 */
class PublishController
{
    function __construct ($subscribe_uri) {
        $this->subscribe_uri = $subscribe_uri;

        REST_API::POST_JSON(
            $subscribe_uri, $this, 'on_REST_subscribe');
    }

    public /* not really */
    function on_REST_subscribe ($req) {
        _Events::request_subscribe_received($this->subscribe_uri);
        $params = $req->get_params();
        $subscriber_id = $params["subscriber_id"];
        $callback_url  = $params["callback_url"];

        $sub = _Subscriber::overwrite($this->subscribe_uri, $subscriber_id, $callback_url);
        $test_url = preg_replace('|/webhook([&?]|$)', '/webhook-test$1',
                                 $callback_url);
        if ($test_url != $callback_url) {
            $sub->attempt_post($test_url, array());
        }
        // Otherwise assume the subscriber doesn't want a synchronous call
        // to /webhook-test
        _Events::request_subscribe_successful($this->subscribe_uri);
   }

    /**
     * Forward $event to all subscribers
     *
     * Avoid loops by doing nothing if $event has already been seen
     * by any given subscriber.
     */
    public function forward ($event) {
        _Events::action_forward($this->subscribe_uri, $event);
        $this->_do_forward($event);
    }

    /**
     * Ping all subscribers from a new (cause-less) event
     */
    public function initiate () {
        $event = Causality::start();
        _Events::action_initiate($this->subscribe_uri, $event);
        $this->forward($event);
    }

    public function _do_forward ($event) {
            foreach (_Subscriber::by_publisher_url($this->subscribe_uri)
                 as $sub) {
            if ($sub->has_seen($event)) continue;
            $sub->attempt_post($sub->callback_url, $event->marshall());
            _Events::forward_sent($this->subscribe_uri, $event);
        }
    }
}

/**
 * Persistent records for subscribers, with contact details and
 * automated cleanup of failing subscribers
 *
 * An instance represents one subscriber, and besides it model duties
 * it also knows how to do some controller things, namely to send the
 * 'real' webhook (using an instance of @link Causality as the sole
 * payload), and the test one (to synchronously check that two-way
 * communication is working at @link SubscribeController::subscribe
 * time).
 */
class _Subscriber extends WPDBModel
{
    private const DEAD_SUBSCRIBER_TIMEOUT_SECS = 86400 * 30;
    protected const TABLE_NAME = "epfl_pubsub_subscribers";

    static function create_tables () {
        static::query("CREATE TABLE IF NOT EXISTS %T (
  publisher_url VARCHAR(1024) NOT NULL,
  subscriber_id VARCHAR(1024),
  callback_url VARCHAR(1024),
  last_failure DATETIME
);");
    }
    // We never drop that table, even on plugin removal.

    static public function overwrite ($publisher_url, $subscriber_id, $callback_url) {
        $thisclass = get_called_class();
        $that = new $thisclass();
        $that->publisher_url = $publisher_url;
        $that->subscriber_id = $subscriber_id;
        $that->callback_url  = $callback_url;

        $that->_delete();
        $that->query(
            "INSERT INTO %T (publisher_url, subscriber_id, callback_url)
             VALUES (%s, %s, %s)",
            $this->publisher_url, $this->subscriber_id, $this->callback_url);

        return $that;
    }

    protected function __construct () {}

    private function _delete () {
        $this->query("DELETE FROM %T
                      WHERE subscriber_id = %s AND publisher_url = %s",
                     $this->subscriber_id, $this->publisher_url);
    }

    static function by_slug ($slug) {
        $thisclass = get_called_class();
        $objects = array();
        foreach
            (static::get_results( "SELECT slug, subscriber_id, callback_url,
                                          UNIX_TIMESTAMP(last_failure)
                                   FROM %t
                                   WHERE slug = %s",
                                  $slug)
             as $line) {
            $that = new $thisclass();
            $that->subscriber_id  = $db_line->subscriber_id;
            $that->slug         = $db_line->slug;
            $that->callback_url = $db_line->callback_url;
            $that->last_failure = $db_line->last_failure;
            $objects[] = $that;
        }
        return $objects;
    }

    public function has_seen ($event) {
        return $event->has_in_path($this->subscriber_id);
    }

    public function attempt_post ($url, $payload) {
        // So technically there is controller code in there, but
        // attempting to untangle it would create more coupling than
        // it would save.
        try {
            RESTClient::POST_JSON($url, $payload);
            $this->mark_success();
        } catch (RESTClientError $e) {
            error_log($e);
            $this->mark_error();
        }
    }

    public function mark_success () {
        if (! $this->last_failure) return;
        
        $this->query(
            "UPDATE %T SET last_failure = NULL
                 WHERE subscriber_id = %s AND slug = %s",
            $this->subscriber_id, $this->slug);
        $this->last_failure = NULL;
    }

    public function mark_error () {
        if (! $this->last_failure) {
            $this->last_failure = time();
            $this->query(
                "UPDATE %T SET last_failure = FROM_UNIXTIME(%d)
                 WHERE subscriber_id = %s AND slug = %s",
                $this->last_failure, $this->subscriber_id, $this->slug);
        } else if (time() - $this->last_failure
                   > self::DEAD_SUBSCRIBER_TIMEOUT_SECS) {
            $this->_delete();
        }
    }
}

_Subscriber::hook();



/**
 * A data structure used to prevent loops in pubsub propagation.
 *
 * Instances of this class are immutable. They contain the start event
 * timestamp and a propagation path as a list of pubsub instance ID
 * strings.
 */
class Causality
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

    static function start () {
        $thisclass = get_called_class();
        $that = new $thisclass();
        $that->timestamp = time();
        $that->path = [];
        return $that;
    }

    static function has_in_path ($subscriber_id) {
        return in_array($subscriber_id, $event->path);
    }

    /**
     * @return A new Causality instance with $via_subscriber_id
     *         appended to the path
     */
    function received ($via_subscriber_id) {
        if (array_search($via_subscriber_id, $this->path)) {
            throw new CausalityLoopError(
                implode(" -> ", $this->path) .
                " loops back to us: $via_subscriber_id");
        }

        $thisclass = get_called_class();
        $that = new $thisclass();
        $that->timestamp = $this->timestamp;
        $that->path      = $this->path;
        $that->path[]    = $via_subscriber_id;
        return $that;
    }
}


// TODO: unstub
class _Events
{
    static function request_webhook_received ($slug) {}
    static function request_webhook_successful ($slug) {}
    static function request_subscribe_received ($slug) {}
    static function request_subscribe_successful ($slug) {}
    static function forward_sent ($slug, $event) {}
    static function action_initiate ($slug, $event) {}
    static function action_forward ($slug, $event) {}
    static function error_invalid_nonce ($msg, $slug) {
        error_log($msg);
    }
}
