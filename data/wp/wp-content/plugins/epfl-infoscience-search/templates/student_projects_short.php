<?php include $template_base_path . 'common/authors.php' ?>

<?php 
    if ($publication['author']) {
        echo "<span> : </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
        
        echo "<span>";

        if ($publication['publication_date']) {
            echo " ; ";
        } else {
            echo ". ";
        }

        echo "</span>";
    }
        
    if ($publication['publication_date']) {
        echo "<span>" . $publication['publication_date'][0] . "</span>";
        echo "<span>.</span>";
    }
?>
