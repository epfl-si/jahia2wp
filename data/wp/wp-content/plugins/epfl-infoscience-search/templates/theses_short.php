<?php include $template_base_path . 'common/authors.php' ?>

<?php 
    if ($publication['author'] && $publication['director']) {
        echo "<span> / </span>";
    }

    if ($publication['director']) {
        $template_authors = $publication['director'];
        include $template_base_path . 'common/authors.php';
        echo "<span> (" . __('Dir.', 'epfl-infoscience-search') . ") </span>";
        echo "<span> : </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
        echo "<span>. </span>";
    }
    
    if ($publication['publication_location']) {
        echo "<span>" . $publication['publication_location'][0] . "</span>";

        if ($publication['publication_institution'] || $publication['publication_date']) {
            echo "<span>, </span>";
        } else {
            echo "<span>. </span>";
        }
    }

    if ($publication['publication_institution']) {
        echo "<span>" . $publication['publication_institution'][0] . "</span>";
        if ($publication['publication_date']) {
            echo "<span>, </span>";
        } else {
            echo "<span>. </span>";
        }
    }

    if ($publication['publication_date']) {
        echo "<span>" . $publication['publication_date'][0] . ". </span>";
    }

    if ($publication['publication_page']) {
        echo '<span>' . __('p.', 'epfl-infoscience-search') . ' ' . $publication['publication_page'][0] .'.</span> ';
    }

    if ($publication['doi']) {
        echo "<span>DOI : " . $publication['doi'][0] . ".</span>";
    }
?>
