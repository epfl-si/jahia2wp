<?php
    if ($publication['title']) {
        echo '<h3 class="infoscience_title">' . $publication['title'][0] .'</h3>';
    }

    if ($publication['author']) {
        echo '<p class="infoscience_authors">';
        include $template_base_path . 'common/authors.php';
        echo '</p>';
    }

    echo '<p class="infoscience_host">';

    if ($publication['journal'][0]['publisher']) {
        echo "<span><i>" . $publication['journal'][0]['publisher'] . "</i>. </span>";
    }

    if ($publication['publication_date']) {
        echo "<span>" . $publication['publication_date'][0] . ". </span>";
    }

    if ($publication['conference'][0]['name']) {
        echo "<span>" . $publication['conference'][0]['name'];
        if ($publication['conference'][0]['location'] || $publication['conference'][0]['date']) {
            echo ", ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }

    if ($publication['conference'][0]['location']) {
        echo "<span>" . $publication['conference'][0]['location'];
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

    if ($publication['journal'][0]['page']) {
        echo '<span>' . __('p.', 'epfl-infoscience-search') . ' ' . $publication['journal'][0]['page'] .'. </span> ';
    }

    echo '</p>';

    if ($publication['doi']) {
        echo "<p>DOI : " . $publication['doi'][0] . ".</p>";
    }    
?>
