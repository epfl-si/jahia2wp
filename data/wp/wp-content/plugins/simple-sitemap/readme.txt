=== Simple Sitemap ===
Contributors: dgwyer
Tags: seo sitemap, html, sitemap, html sitemap, seo, global, sort, shortcode, pages, posts, custom post types, post types, responsive, responsive sitemap
Requires at least: 3.0
Tested up to: 4.7
Stable tag: 2.1

The simplest responsive HTML sitemap available for WordPress! No setup required. Flexible customization options available.

== Description ==

Improve your SEO ranking by adding a HTML sitemap!

Very quick and easy to use. Add a powerful fully responsive HTML sitemap to your website today! Simply enter the <code>[simple-sitemap]</code> shortcode in a post, page, custom post type, or text widget and you're good to go. Simple as that!

The sitemap shortcode has several attributes you can use to control how your sitemap is rendered including:

*   'types': comma separated list of post types to display in the sitemap
*   'show_label': show the heading label for each post ['true'|'false']
*   'links': show sitemap list as links or plain text ['true'|'false']
*   'page_depth': hierarchy of child pages to show [0|1|2|3]
*   'order': sort order of list ['asc'|'desc']
*   'orderby': field to sort by [title|author|date|ID]
*   'exclude': comma separated list of post IDs to exclude

This gives your visitors an efficient way to view ALL site content in ONE place. It is also great for SEO purposes and makes it easier for spiders to index your site.

To display the sitemap simply add the [simple-sitemap] shortcode to any post or page (or text widget) and you'll have a full indexed sitemap enabled on your website!

Please <a href="https://wordpress.org/support/view/plugin-reviews/simple-sitemap"><strong>rate</strong></a> this Plugin if you find it useful. It only takes a moment but it's very much appreciated. :)

><strong>We're proud to announce that <a href="https://wpgoplugins.com/plugins/simple-sitemap-pro">Simple Sitemap Pro</a> is now available!</strong>
>
>Upgrade today for even more flexible sitemap options including:
>
> *   *New* tabbed sitemap layout.
> *   Fully responsive sitemap for column AND tabbed layouts, on all devices!
> *   Display sitemap in a horizontal list.
> *   Show hierarchical parent pages as links or plain text.
> *   Exclude individual pages.
> *   Customize sitemap titles for specific pages (via filter).
>
> Checkout the <a href="https://wordpress.org/plugins/simple-sitemap/screenshots/">screenshots</a>, or click <a href="https://wpgoplugins.com/plugins/simple-sitemap-pro">here</a> for more details.
>
>See our <a href="https://www.wpgoplugins.com" target="_blank">WordPress plugin site</a> for more top plugins!

== Installation ==

1. Via the WordPress admin go to Plugins => Add New.
2. Enter 'Simple Sitemap' (without quotes) in the textbox and click the 'Search Plugins' button.
3. In the list of relevant Plugins click the 'Install' link for Simple Sitemap on the right hand side of the page.
4. Click the 'Install Now' button on the popup page.
5. Click 'Activate Plugin' to finish installation.
6. Add [simple-sitemap] shortcode to a page to display the sitemap on your site.

== Screenshots ==

1. Once plugin has been activated simply add the [simple-sitemap] shortcode to any page, post, or text widget.
2. Simple Sitemap displays a list of all the specified post types.
3. Plugin admin page details all the shortcode attributes available.
4. Display sitemap inside a tabbed layout! (Pro only)
5. Tabbed sitemap is fully responsive too. (Pro only)
6. Easily add icons and captions to each sitemap item. (Pro only)
7. Added icons and captions look great on mobile devices. (Pro only)
8. Remove ALL parent page links and leave just the title text. (Pro only)
9. Remove specific parent page links ONLY by entering a comma separated list of parent page IDs. (Pro only)
10. Show sitemap in a horizontal list separated by any character(s). (Pro only)

== Changelog ==

*2.1*

* Fixed broken image links on plugin settings page.

*2.0*

* Plugin settings page updated.

*1.9 update*

* Fixed compatibility bug with WordPress 4.7.

*1.87 update*

* Update plugin setting links.

*1.86 update*

* Added links to 'Pro' version.

*1.85 update*

* Updated plugin description.

*1.84 update*

* Updated information about the Pro version of the plugin.

*1.83 update*

* Updated the docs in plugin options for the 'orderby' shortcode attribute. A link to the full list of available attributes is included.

*1.82 update*

* Better security.
* Fix: Some pretty permalinks weren't being displayed properly for posts.

*1.81 update*

* Screenshots updated.

*1.8 update*

* Plugin completely rewritten to include a range of shortcode attributes to make rendering the sitemap much more flexible!
* All previous plugin options removed from the plugin settings page. Use the new shortcode attributes instead. See the plugin settings page for full deatils.
* New, cleaner HTML and CSS. New CSS classes used.

*1.7 update*

* Translation support added!

*1.65 update*

* More settings page updates.

*1.64 update*

* Settings page updated.

*1.63*

* Fixed bug with CPT links.

*1.62*

* Sitemap shortcode now works in text widgets.

*1.61*

* Fixed bug limiting CPT posts to displaying a maximum of 5 each.

*1.6*

* Links on Plugins page updated.
* Removed front end drop downs. Sitemap rendering now solely controlled via plugin settings.
* Support for Custom Post Types added!

*1.54*

* Security issue addressed.

*1.53*

* All functions now properly name-spaced.
* Added $wpdb->prepare() to SQL query.

*1.52*

* Updated Plugin options page text.
* Now works nicely in sidebars (via a Text widget)!
* Fixed bug where existing Plugin users saw no posts/pages on the sitemap after upgrade to 1.51.
* Added a 'Settings' link to the main Plugins page, next to the 'Deactivate' link to allow easy navigation to the Simple Sitemap Plugin options page.

*1.51*

* Updated WordPress compatibility version.
* Update to Plugin option page text.

*1.5*

* Updated for WordPress 3.5.1.
* Minor CSS bug fixed.
* ALL Plugin styles affecting the sitemap have been removed to allow the current theme to control the styles. This enables the sitemap to blend in with the current theme, and allows for easy customisation of the CSS as there are plenty of sitemap classes to hook into.
* All sitemap content is now listed in a single column to allow for additional listings for CPT to be added later.
* New Plugin options to show/hide posts or pages.

*1.4.1*

* Minor updates to Plugin options page, and some internal functions.

*1.4*

* Plugin option added to exclude pages by ID!
* Bug fix: ALL posts are now listed and are not restricted by the Settings -> Reading value.

*1.3.1*

* Fixed HTML bug. Replaced deprecated function.

*1.3*

* Dropdown sort boxes on the front end now work much better in all browsers. Thanks to Matt Bailey for this fix.

*1.28*

* Changed the .sticky CSS class to be .ss_sticky to avoid conflict with the WordPress .sticky class.

*1.27*

* Fixed minor bug in 'Posts' view, when displaying the date. There was an erroneous double quotes in the dates link.

*1.26*

* Fixed CSS bug. Was affecting the size of some themes Nav Menu font sizes.

*1.25*

* Now supports WordPress 3.0.3
* Updated Plugin options page
* Fixed issue: http://wordpress.org/support/topic/plugin-simple-sitemap-duplicated-id-post_item
* Fixed issue: http://wordpress.org/support/topic/plugin-simple-sitemap-empty-span-when-post-is-not-sticky

*1.20*

* Added Plugin admin options page
* Fixed several small bugs
* Sitemap layout tweaked and generally improved
* Added new rendering of sitemap depending on drop-down options
* New options to sort by category, author, tags, and date improved significantly

*1.10 Fixed so that default permalink settings work fine on drop-down filter*

*1.01 Minor amendments*

*1.0 Initial release*