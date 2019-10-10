<?php
$filename = $args[0];

// Get the content of the temporary file
$content = file_get_contents( $filename );

// Exemple de information1 présent dans un shortcode
//$content = "Responsable%20d'unit%C3%A9%20%3A%20%3Ca%20href%3D%22https%3A%2F%2Fpeople.epfl.ch%2Fpatrick.pugeaud%3Flang%3Dfr%26amp%3Bcvlang%3Dfr%22%3E%3Cstrong%3EPatrick%20Pugeaud%3C%2Fstrong%3E%3C%2Fa%3E";

// On fait un URL Decode pour obtenir du HTML
$content = urldecode($content);

// Ajouter des <p> comme le fait gutenberg
$content =  "<p>". $content . "</p>";

// encode comme Gutenberg
$content = wp_json_encode( $content, JSON_HEX_TAG | 
              JSON_HEX_APOS | 
              JSON_HEX_QUOT | 
              JSON_HEX_AMP | 
              //JSON_UNESCAPED_UNICODE | 
              JSON_UNESCAPED_SLASHES
            );


// Save the new content inside temporary file
file_put_contents($filename, $content);

?>