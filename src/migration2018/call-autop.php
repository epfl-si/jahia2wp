<?php

$filename = $args[0];
$content = file_get_contents( $filename );
$content = "toto";
$content = wpautop( $content, false );

// Soit on save dans le fichier directement 
file_put_contents($filename, $content);
// Soit on retourne via un 
//echo $content;
?>