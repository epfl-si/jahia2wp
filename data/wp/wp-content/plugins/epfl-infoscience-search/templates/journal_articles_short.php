<?php include $template_base_path . 'common/authors.php' ?>

<?php 
    if ($publication['author']) {
        echo "<span> : </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
    }
        
    echo "<span>";
    if ($publication['journal'][0]['publisher']) {
        echo "; ";
    } else {
        echo ". ";
    }
    echo "</span>";

    if ($publication['journal'][0]['publisher']) {
        echo "<span><i>" . $publication['journal'][0]['publisher'] . "</i></span>";
        echo "<span>. </span>";
    }

    if ($publication['publication_year']) {
        echo "<span><i>" . $publication['publication_year'][0] . "</i></span>";
        echo "<span>. </span>";
    }

    if ($publication['doi']) {
        echo "<span>DOI : " . $publication['doi'][0] . "</span>";
    }

?>
