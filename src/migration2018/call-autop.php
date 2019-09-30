<?php
$filename = $args[0];
// Get the content of the temporary file
$content = file_get_contents( $filename );
$content = wpautop( $content );
// Save the new content inside temporary file
file_put_contents($filename, $content);
?>
