<?php

$filename = $args[0];

// Get the content of the temporary file
$content = file_get_contents( $filename );

// Find pattern <!-- wp:gallery { ....   <!-- /wp:gallery -->
$index_start = strpos($content, "<!-- wp:gallery");
$index_end = strpos($content, "<!-- /wp:gallery -->") + strlen("<!-- /wp:gallery -->");
$length_gallery = $index_end - $index_start;

if ($index_start != FALSE) {
    // Keep gallery HTML
    $gallery_content = substr($content, $index_start, $length_gallery);

    // Replace gallery HTML by pattern $god
    $god = "######GALLERY#GUTENBERG#BLOCK#############";
    $content = substr_replace($content, $god, $index_start, strlen($gallery_content));
}

// Add auto <p>
$content = wpautop( $content );

if ($index_start != FALSE) {
    // Search pattern $god
    $index_start = strpos($content, $god);
    $index_end = strpos($content, $god) + strlen($god);

    // Replace $god by gallery HTML
    $content = substr_replace($content, $gallery_content, $index_start, strlen($god));
}

// Save the new content inside temporary file
file_put_contents($filename, $content);

?>
