<?PHP
    /* Lists WordPress registerd shortcodes, all separated with a coma (CSV like)*/
    global $shortcode_tags;
    echo implode(",", array_keys($shortcode_tags));
?>