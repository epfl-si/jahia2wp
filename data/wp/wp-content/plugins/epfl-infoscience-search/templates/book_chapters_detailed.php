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
        echo '<span><i>' . $publication['journal'][0]['publisher'] .'</i></span> ';
        if ($publication['publication_location'] || $publication['publication_institution'] || 
            $publication['publication_year']) {
            echo '<span>; </span>';
        } else {
            echo '<span>. </span>';
        }
    }

    if ($publication['publication_location']) {
        echo "<span>" . $publication['publication_location'][0] . "</span>";
        if ($publication['publication_institution']) {
            echo '<span>: </span>';
        } elseif ($publication['publication_year']) {
            echo '<span>, </span>';
        } else {
            echo '<span>. </span>';
        }
    }

    if ($publication['publication_institution']) {
        echo "<span>" . $publication['publication_institution'][0] . "</span>";
        if ($publication['publication_year']) {
            echo '<span>, </span>';
        } else {
            echo '<span>. </span>';
        }
    }

    if ($publication['publication_year']) {
        echo "<span>" . $publication['publication_year'][0] . ". </span>";
    }

    if ($publication['journal'][0]['page']) {
        echo '<span>' . __('p.', 'epfl_infoscience') . ' ' . $publication['journal'][0]['page'] .'. </span>';
        if ($publication['isbn']) {
            echo '<span> - </span>';
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
