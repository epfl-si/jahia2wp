<?php

#####################################################################
#
# Custom XML importer for the jahia2wp project
#
# Works a lot like "wp import", but fetches attachments and
# de-dupes
#
# Usage:
#
# wp --path=/path/to/your/WordPress/instance \
#    --require=/path/to/this/script \
#    jahia2wp import [ -fetch-attachments ] <file.xml>
#
#####################################################################

define('WP_LOAD_IMPORTERS', true);
define('IMPORT_DEBUG', true);

WP_CLI::add_command("jahia2wp import", "jahia2wp_import");

function jahia2wp_import ()
{
    ob_start("accumulate_and_transform", 1);
    try {
        do_import();
    } finally {
        ob_flush();
        ob_end_clean();
    }
}

function do_import ()
{
    do_action('admin_init');
    $importer_plugin_file = WP_PLUGIN_DIR .
                          '/wordpress-importer/wordpress-importer.php';
    if (! is_file($importer_plugin_file)) {
        ?>

File <?php echo $importer_plugin_file; ?> Not found

Please install the wordpress-importer plugin, e.g.

  wp --path=... plugin install --activate wordpress-importer

<?php
        exit(1);
    }

    global $argv;
    $filename = end($argv); reset($argv);
    if (! is_file($filename)) {
        ?>
File not found: <?php echo $filename; ?>

Usage : wp eval [...] <filename>
<?php
        exit(1);
    }

    add_filter("wp_import_existing_post", "identify_structural_pages_by_guid", 10, 2);
    add_action("wp_import_insert_post", "record_guid", 10, 4);

    global $wp_import;
    $wp_import->fetch_attachments = (FALSE !== array_search("-fetch-attachments", $argv));
    $wp_import->import($filename);
}

############################# FUNCTIONS ##################################

function html2text ($html) {
    $html = preg_replace("|<br[ ]*[/]?>|", "\n", $html);
    $html = preg_replace("|<[/]?p[ ]*[/]?>|", "\n", $html);
    $html = preg_replace("|<[^>]*>|", "\n", $html);

    $html = str_replace("&#8220;", '"', $html);
    $html = str_replace("&#8221;", '"', $html);

    return $html;
}

function accumulate_and_transform ($buf, $phase) {
    static $accumulator = "";
    $accumulator .= $buf;
    $accumulator = html2text($accumulator);
    [$out, $accumulator] = explode("<", $accumulator);
    return $out;
}

# Items with a negative ID - so-called "structural" pages, menus etc.
# have been created by the EPFL XML processing pipeline, not by "wp
# export". Unlike the default behavior for "normal" pages and posts,
# identified by their title and creation date, we de-duplicate structural
# items by their <guid> field (in the sense that importing the same
# item again with the same <guid> has no effect).

$EPFL_IMPORT_GUID_META = "epfl-ventilation-guid";

function identify_structural_pages_by_guid ($post_exists_orig, $post)
{
    if (! ($post->ID < 0)) return $post_exists_orig;

    $query = new \WP_Query(array(
        'post_type' => 'page',
        'meta_query' => array(array(
            'key'     => $EPFL_IMPORT_GUID_META,
            'value'   => $post['guid'],
            'compare' => '='
        ))));
    $results = $query->get_posts();
    if (sizeof($results) >= 1) {
        return $results[0]->ID;
    } else {
        return 0;  # Unknown structural node, must insert
    }
}

function record_guid ($post_id, $original_post_ID, $postdata, $post)
{
    update_post_meta($post_id, $EPFL_IMPORT_GUID_META, $postdata['guid']);
}
