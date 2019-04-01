<?php
/**
 * Plugin Name: EPFL
 * Description: Provides many epfl shortcodes
 * @version: 1.20
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

require_once 'lib/utils.php';
require_once 'lib/language.php';
require_once 'shortcodes/epfl-news/epfl-news.php';
require_once 'shortcodes/epfl-memento/epfl-memento.php';
require_once 'shortcodes/epfl-toggle/epfl-toggle.php';
require_once 'shortcodes/epfl-cover/epfl-cover.php';
require_once 'shortcodes/epfl-links-group/epfl-links-group.php';
require_once 'shortcodes/epfl-card/epfl-card.php';
require_once 'shortcodes/epfl-video/epfl-video.php';
require_once 'shortcodes/epfl-quote/epfl-quote.php';
require_once 'shortcodes/epfl-people/epfl-people.php';
require_once 'shortcodes/epfl-map/epfl-map.php';
require_once 'shortcodes/epfl-infoscience-search/epfl-infoscience-search.php';
require_once 'shortcodes/epfl-scheduler/epfl-scheduler.php';
require_once 'shortcodes/epfl-social-feed/epfl-social-feed.php';
require_once 'shortcodes/epfl-xml/epfl-xml.php';
require_once 'shortcodes/epfl-faq/epfl-faq.php';
require_once 'shortcodes/epfl-share/epfl-share.php';
require_once 'shortcodes/epfl-contact/epfl-contact.php';
require_once 'shortcodes/epfl-tableau/epfl-tableau.php';
require_once 'shortcodes/epfl-google-forms/epfl-google-forms.php';
require_once 'shortcodes/epfl-servicenow-search/epfl-servicenow-search.php';
require_once 'menus/epfl-menus.php';
// Disabled due to 'epfl-intranet' plugin use
//require_once 'preprod.php';

if (class_exists('\WP_CLI')) {
    require_once 'menus/wpcli.php';
}

// load .mo file for translation
function epfl_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
    load_plugin_textdomain( 'epfl-infoscience-search', FALSE, basename( dirname( __FILE__ ) ) . '/shortcodes/epfl-infoscience-search/languages/' );
}
add_action( 'plugins_loaded', 'epfl_load_plugin_textdomain' );

/**
 * Load tags if we are in the labs instance.
 * Tags are provided from the "Source de vérité" app.
 *
 * @return list of tags
 *
 */
function epfl_fetch_site_tags () {
    $site_url = get_site_url();

    $tags = NULL;
    $cache_timeout = 4 * HOUR_IN_SECONDS;

    if ( (defined('WP_DEBUG') && WP_DEBUG) || false === ( $tags = get_transient( 'epfl_custom_tags' ) ) ) {
      // this code runs when there is no valid transient set

      $tag_provider_url = 'https://wp-veritas.epfl.ch/api/v1';
      $site = [];

      // first, fetch for the id of this site
      $url_site_to_id = $tag_provider_url . '/sites?site_url=' . rawurlencode($site_url);
      $site = Utils::get_items($url_site_to_id);

      if ($site === false) { # wp-veritas is not responding, get the local option and
                             # set a transient, so we dont refresh everytime
        $tags_and_urls_from_option = get_option('epfl:custom_tags');
        if ($tags_and_urls_from_option === false) {
          # no option set ?
          set_transient( 'epfl_custom_tags', [], $cache_timeout );
          return;
        } else {
            set_transient( 'epfl_custom_tags', $tags_and_urls_from_option, $cache_timeout );
            return $tags_and_urls_from_option;
        }
      } else {
        # wp-veritas is responding; from the site id, get the tags
        if (!empty($site)) {
          $tags_and_urls = []; // [[tag, url], ...]
          $tags = $site->tags;

          if (!empty($tags)) {
            # all goods, we have data !
            set_transient( 'epfl_custom_tags', $tags, $cache_timeout );
            # persist into options too, as a fallback if wp_veritas is no more online
            update_option('epfl:custom_tags', $tags);
            return $tags;
          } else {
            # nothing for this site ? time to remove local entry
            set_transient( 'epfl_custom_tags', [], $cache_timeout );
            delete_option('epfl:custom_tags');
            return;
          }
        }
      }
    } else {
        return $tags;
    }
  }

?>
