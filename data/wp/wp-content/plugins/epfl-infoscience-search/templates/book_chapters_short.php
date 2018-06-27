<?php include $template_base_path . 'common/authors.php' ?>

<?php 
    if ($publication['author']) {
        echo "<span> : </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
        echo "<span>";
        if ($publication['journal'][0]['publisher'] || 
            $publication['journal'][0]['publication_location'] || 
            $publication['journal'][0]['publication_institution'] ||
            $publication['journal'][0]['publication_year']) {
            echo "; ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }
        
    if ($publication['journal'][0]['publisher']) {
        echo "<span><i>" . $publication['journal'][0]['publisher'] . "</i></span>";
        if ($publication['journal'][0]['publication_location'] || 
            $publication['journal'][0]['publication_institution'] ||
            $publication['journal'][0]['publication_year']) {
            echo "<span>; </span>";
        } else {
            echo "<span>. </span>";
        }
    }

    if ($publication['publication_location']) {
        echo "<span>" . $publication['publication_location'][0] . "</span>";

        if ($publication['publication_institution']) {
            echo "<span>: </span>";
        } elseif ($publication['publication_year']) {
            echo "<span>, </span>";
        } else {
            echo "<span>. </span>";
        }
    }

    if ($publication['publication_institution']) {
        echo "<span>" . $publication['publication_institution'][0] . "</span>";
        if ($publication['publication_year']) {
            echo "<span>, </span>";
        } else {
            echo "<span>. </span>";
        }
    }

    if ($publication['publication_year']) {
        echo "<span>" . $publication['publication_year'][0] . ". </span>";
    }

    if ($publication['journal'][0]['page']) {
        echo '<span>' . __('p.', 'epfl-infoscience-search') . ' ' . $publication['journal'][0]['page'] .'.</span> ';
    }
?>
