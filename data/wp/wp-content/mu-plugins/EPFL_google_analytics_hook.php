<?php
/*
 * Plugin Name: EPFL Google Analytics connector
 * Plugin URI:
 * Description: Must-use plugin for the EPFL website.
 * Version: 1.0
 * Author: wwp-admin@epfl.ch
 * */

/* Hook that add the Google Analytics header to all pages
 *
 * https://premium.wpmudev.org/blog/create-google-analytics-plugin/
 */

function google_analytics_connector_render() {
?>

<!-- Global Site Tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-4833294-1"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag("js", new Date());
    gtag("config", "UA-4833294-1", { "anonymize_ip": true });
</script>

<?php
}
add_action('wp_head', 'google_analytics_connector_render', 10);
?>
