<?php
    if ($publication['title']) {
        echo '<h3 class="infoscience_title">' . $publication['title'][0] .'</h3>';
    }

    if ($publication['author_1']) {
        echo '<p class="infoscience_authors">';
        $template_authors = $publication['author_1'];
        include $template_base_path . 'common/authors.php';
        echo '</p>';
    } elseif ($publication['author_3']) {
        echo '<p class="infoscience_authors">';
        $template_authors = $publication['author_3'];
        include $template_base_path . 'common/authors.php';
        echo '</p>';        
    }    

    echo '<p class="infoscience_host">';

    if ($publication['conference'][0]['name']) {
        echo "<span>" . $publication['conference'][0]['name'] . "</span>";
        echo "<span>";
        if ($publication['conference'][0]['location'] || $publication['conference'][0]['date']) {
            echo ", ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }

    if ($publication['conference'][0]['location']) {
        echo "<span>" . $publication['conference'][0]['location'] . "</span>";
        echo "<span>";
        if ($publication['conference'][0]['date']) {
            echo ", ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }    

    if ($publication['conference'][0]['date']) {
        echo "<span>" . $publication['conference'][0]['date'] . ". </span>";
    }

    echo '</p>';
?>
