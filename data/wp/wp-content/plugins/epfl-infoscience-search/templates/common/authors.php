<?php
# TODO: make clickable authors
foreach($publication['author'] as $index => $author) {
    if ($index == 5) {
        echo "<span> et al. </span>";
        break;
    } else {
        echo "<span>";
        if ($index != 0){
            echo "; ";
        }
        echo "</span>";
        echo "<span>";
        echo $publication['author'][$index];
        echo "</span>";
    }
}
?>
