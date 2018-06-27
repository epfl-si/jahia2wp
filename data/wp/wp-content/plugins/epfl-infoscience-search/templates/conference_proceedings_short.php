<?php 
    if ($publication['author_1']) {
        $template_authors = $publication['author_1'];
        include $template_base_path . 'common/authors.php';
        echo "<span> : </span>";
    } elseif ($publication['author_3']) {
        $template_authors = $publication['author_3'];
        include $template_base_path . 'common/authors.php';
        echo "<span> : </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
        echo "<span>";
        if ($publication['journal'][0]['publisher']) {
            echo " ; ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }

    if ($publication['publication_date']) {
        echo "<span>" . $publication['publication_date'][0];
        echo ". </span>";
    }

    if ($publication['conference'][0]['name']) {
        echo "<span>" . $publication['conference'][0]['name'];
        echo ". </span>";
    }

    if ($publication['conference'][0]['location']) {
        echo "<span>" . $publication['conference'][0]['location'] . "</span>";
        echo "<span>. </span>";
    }
    
    if ($publication['conference'][0]['date']) {
        echo "<span>" . $publication['conference'][0]['date'] . "</span>";
        echo "<span>. </span>";
    }
?>
