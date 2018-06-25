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

    if ($publication['report_url']) {
        echo "<span>" . $publication['report_url'][0] . "</span>";
        echo "<span>. </span>";
        echo '<p><a href="' . $publication['report_url'][0] . '" target="_blank">' . $publication['report_url'][0] . '</a>.</p>';
    }
?>
