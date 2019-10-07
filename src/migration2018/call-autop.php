<?php

$filename = $args[0];

// Get the content of the temporary file
$content = file_get_contents( $filename );

// Find pattern <!-- wp:gallery { ....   <!-- /wp:gallery -->
$index_start = strpos($content, "<!-- wp:gallery");
$index_end = strpos($content, "<!-- /wp:gallery -->") + strlen("<!-- /wp:gallery -->");
$length_gallery = $index_end - $index_start;

if ($index_start !== FALSE) {
    // Keep gallery HTML
    $gallery_content = substr($content, $index_start, $length_gallery);

    // Replace gallery HTML by pattern $substitute
    $substitute = "######GALLERY#GUTENBERG#BLOCK#############";
    $content = substr_replace($content, $substitute, $index_start, strlen($gallery_content));
}

// Add auto <p>
$content = wpautop( $content );

if ($index_start !== FALSE) {
    // Search pattern $substitute
    $index_start = strpos($content, $substitute);

    // Replace $substitute by gallery HTML
    $content = substr_replace($content, $gallery_content, $index_start, strlen($substitute));
}

$content = str_replace("<p><!-- ", "<!-- ", $content);
$content = str_replace("--></p>", "-->", $content);

$content = shortcode_unautop($content);

// Replacement to have "correct" unicode encoded strings
$content = str_replace("\\\\u003c", "\\u003c", $content);
$content = str_replace("\\\\u003e", "\\u003e", $content);


// Save the new content inside temporary file
file_put_contents($filename, $content);

?>
