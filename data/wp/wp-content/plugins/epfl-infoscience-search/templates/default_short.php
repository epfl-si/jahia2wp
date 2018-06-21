<?php include $template_base_path . 'common/authors.php' ?>

<?php 
    if ($publication['author']) {
        echo "<span> : </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
        echo "<span>. </span>";
    }
        
    if ($publication['publication_year']) {
        echo "<span>" . $publication['publication_year'][0] . "</span>";
        echo "<span>. </span>";
    }
?>
