<?php
$authors = [];

#check if we come from template, or from publication as default
if (isset($template_authors)) {
    $authors = $template_authors;
} else {
    $authors = $publication['author'];
}

# TODO: make clickable authors
foreach($authors as $index => $author) {
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
        echo '<a href="' . $authors[$index]['search_url'] . '" target="_blank">';
        echo $authors[$index]['initial_name'];
        echo '</a>';
        echo "</span>";
    }
}
?>
