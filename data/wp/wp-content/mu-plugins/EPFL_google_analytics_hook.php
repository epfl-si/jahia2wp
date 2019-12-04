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
    $additional_id = get_option('epfl_google_analytics_id');
?>

<!-- Global Site Tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-4833294-1"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag("js", new Date());
    gtag("config", "UA-4833294-1", { "anonymize_ip": true });
    <?php if (!empty($additional_id)): ?>gtag("config", "<?php echo $additional_id; ?>", { "anonymize_ip": true });<?php endif; ?>
</script>

<?php
}
add_action('wp_head', 'google_analytics_connector_render', 10);
?>
