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

    if ($publication['publication_location']) {
        echo "<span>" . $publication['publication_location'][0] . "</span>";
        if ($publication['publication_institution'] || $publication['publication_year']) {
            echo '<span>: </span>';
        } elseif ($publication['isbn']) {
            echo '<span> - </span>';
        } else {
            echo '<span>. </span>';
        }
    }

    if ($publication['publication_institution']) {
        echo "<span>" . $publication['publication_institution'][0] . "</span>";
        if ($publication['publication_year']) {
            echo '<span>, </span>';
        } elseif ($publication['isbn']) {
            echo '<span> - </span>';
        } else {
            echo '<span>. </span>';
        }
    }

    if ($publication['publication_year']) {
        echo "<span>" . $publication['publication_year'][0] . "</span>";
        if ($publication['isbn']) {
            echo '<span> - </span>';
        } else {
            echo '<span>. </span>';
        }
    }

    if ($publication['isbn']) {
        echo "<span>ISBN : " . $publication['isbn'][0] . ". </span>";
    }

    echo '</p>';

    if ($publication['doi']) {
        echo "<p>DOI : " . $publication['doi'][0] . ".</p>";
    }    
?>
