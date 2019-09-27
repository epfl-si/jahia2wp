<?php

$filename = $args[0];

$content = file_get_contents($filename);

//print_r($args);
//print_r($content);

echo wpautop( $content );

?>