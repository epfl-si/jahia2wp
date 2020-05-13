=== Remote Content Shortcode ===
Contributors: doublesharp
Tags: shortcode, content, import, http, web page, scraper, DOM, remote
Requires at least: 2.8
Tested up to: 4.7.2
Stable tag: 1.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Embed remote content into a post or page using a shortcode. Easily select and replace DOM elements.

== Description ==
Use the `[remote_content url="http://www.example.com"]` shortcode to import remotely hosted content into your posts or page using `cURL`. Supports HTML `GET` and `POST`, `BASIC` authentication, HTML entity escaping/encoding, CSS selectors and element removal, regular expression replacements, and caching. 

This shortcode will let you...

* Display the contents of a document stored in Subversion or Git repository into a post where it can then be formatted using SyntaxHighlighter.
* Quickly integrate content from other CMS systems, for example a company intranet.
* Control access to protected data using WordPress' permissions.
* Select and remove DOM elements based on CSS selectors (like jQuery).
* Find and replace text based on PHP regular expressions.
* HTML encode the remote content.
* Strip tags from the remote content.
* Cache the remote content.

= Usage =
**Attributes**
`[remote_content url="http://www.example.com" method="GET" timeout="10" userpwd="username:password" htmlentities="false" strip_tags="false" decode_atts="false" selector="body" remove="img" find="~domain\.com~" replace="new-domain.com" cache="true" cache_ttl="3600"]`

* **`url`**
  * The url that you want to request.
* `method=[`**` GET `**` | POST ]`
  * The HTTP request type, defaults to **`GET`**.
* `params="key=value, foo, bar"`
  * The keys listed here will be parsed from the querystring and appended to the `url`. Separate keys by with commas. If the value is not found in the querystring the default value can be specified by placing it after an equals sign: `parameter=default`.
* `timeout=[ 0-9... `**`10`**` ]`
  * The request timeout in seconds if it can't be fetched from the cache, defaults to **`10` seconds**.
* `userpwd=[`**` username:password` **`| post_meta | site_option | constant ]`
  * The username and password to send for `BASIC` authentication. It is recommended to not set the username and password directly in the tag, as ***YOUR PASSWORD WILL BE PUBLICLY VISIBLE ON YOUR WEBSITE IF THIS PLUGIN IS DISABLED***, and instead use one of the other options. By order of priority, if the value matches a post `meta_key` the `meta_value` is used, if it matches a `site_option` the `option_value` is used, and if it matches a constant the constant value is used, otherwise the string data is parsed as is. The format is `username:password`.
* `htmlentities=[`**` false `**`| true ]`
  * If you want to HTML encode the content for display, set to `true`, defaults to **`false`**.
* `strip_tags=[`**` false `**`| true ]`
  * strip all HTML tags from the response, defaults to **`false`**.
* `decode_atts=[`**` false `**`| true ]`
  * The SyntaxHighlighter plugin will HTML encode your shortcode attributes, so `attr="blah"` becomes `attr=&quot;blah&quot;`. This fixes it to the intended value when set to `true`, defaults to **`false`**.
* `selector=[ CSS Selectors... ]`
  * The CSS selector or comma separated list or selectors for the content you would like to display, for example `div.main-content` or `div.this-class #this-id`, defaults to the **entire document**.
* `remove=[ CSS Selectors... ]`
  * The CSS selector or comma separated list or selectors for the content that you would like to remove from the content, for example `h2.this-class` or `div#this-id`, defaults to no **replacement**.
* `find=[ regex ]`
  * Use a PHP regular expression to find content and replace it based on the `replace` attribute, for example `~http://([^\.]*?)\.example\.com~`, defaults to **disabled**.
* `replace=[ regex ]`
  * The replacement text to use with the results of the `find` regular expression, for example `https://\\1.new-domain.com`, defaults to **empty string replacement**.
* `cache=[`**` true `**`| false ]`
  * Set to `false` to prevent the contents from being cached in the WP-Cache/WordPress transients, defaults to **`true`** for performance.
* `cache_ttl=[ 0-9... `**`3600`**` ]`
  * Set the number of seconds to cache the results, using 0 for "as long as possible", defaults to **3600 seconds** (one hour).

**Shortcode Contents + POST**
If there is any content within the shortcode tags, it is sent as part of the request to the remote server. 
`
[remote_content url="http://www.example.com" method="POST"]
{ json: { example: some_data } }
[/remote_content]
`

== Installation ==
1. Download the plugin and extract to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the `[remote_content url="example.com"]` shortcode in your pages, posts, and custom post types.


== Frequently Asked Questions ==
= I've activated the Remote Content Shortcode plugin, but nothing happens =
Ensure that the following requirements are met.
* Your server must allow outbound `HTTP` and outbound requests.
* The remote server must be accessible to the server where your site is hosted.
* Your authentication credentials must be set correctly to access password protected content.

If you have command line access to your server, the `curl` command is a good way to verify that your server can access the content so that it can be displayed via the shortcode.

== Screenshots ==
1. Shortcode example to fetch source from a WordPress Plugin Subversion Repository.
2. Displaying the contents of a PHP file from the WordPress Plugin Subversion Repository using SyntaxHighlighter.

== Changelog ==
= 1.4.2 =
* Add filters for third party modification of values.
  * `remote_content_shortcode_url`, arguments: `$url`
    * Modify the URL before it is fetched
  * `remote_content_shortcode_postfields`, arguments: `$tag_content`, `$url`
    * Modify the tag content before it is POSTed
  * `remote_content_shortcode_ssl_verifyhost`, arguments: `false`, `$url`
    * Verify host SSL certificates
  * `remote_content_shortcode_ssl_verifypeer`, arguments: `false`, `$url`
    * Verify peer SSL certificates
  * `remote_content_shortcode_phpQuery`, arguments: `$response`, `$url`, `$selector`, `$remove`
    * Modify response content before it is sent to `phpQuery`
  * `remote_content_shortcode_return`, arguments: `$response`, `$url`
    * Modify response before it is returned

= 1.4 =
* Convert `&amp;` to `&` in URLs to account for Visual/Text editor encoding.

= 1.3.3 =
* Fix for multiple `params` attributes.

= 1.3.2 =
* Fix for `params` attribute.

= 1.3.1 =
* Add support for passing through querystring parameters.

= 1.3 =
* Use double quotes in find/replace by using `%QUOT%` in its place, for example `find="~ (src|href)=(%QUOT%|')?(/)?~" replace=" \\1=\\2http://www.example.com/"`
* Bug fix for regex/non-empty replace.

= 1.2 =
* Support case insensitive `true/false` and `GET/POST` values.
* Bug fix for remote content caching.

= 1.1 =
* CSS selectors for filtering and removal.
* Regular expression replacement support.
* Support for `strip_tags`.
* Added attribute to allow caching to be disabled.

= 1.0 =
Initial version.