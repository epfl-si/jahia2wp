<?php include $template_base_path . 'common/authors.php' ?>

<?php 
    if ($publication['author']) {
        echo "<span> : </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
        echo "<span>";
        if ($publication['journal'][0]['publication_location'] || 
            $publication['journal'][0]['publication_institution']) {
            echo " ; ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }
        
    if ($publication['journal'][0]['publication_location']) {
        echo "<span>" . $publication['journal'][0]['publication_location'] . "</span>";

        if ($publication['journal'][0]['publication_institution'] || $publication['journal'][0]['publication_date']) {
            echo "<span>: </span>";
        } else {
            echo "<span>. </span>";
        }
    }

    if ($publication['journal'][0]['publication_institution']) {
        echo "<span>" . $publication['journal'][0]['publication_institution'] . "</span>";
        if ($publication['journal'][0]['publication_date']) {
            echo "<span>, </span>";
        } else {
            echo "<span>. </span>";
        }
    }

    if ($publication['publication_date']) {
        echo "<span>" . $publication['publication_date'][0] . ". </span>";
    }
?>
