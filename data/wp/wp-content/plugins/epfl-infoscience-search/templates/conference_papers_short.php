<?php include $template_base_path . 'common/authors.php' ?>

<?php 
    if ($publication['author']) {
        echo "<span> : </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
        echo "<span>";
        echo ". ";
        echo "</span>";
    }

    if ($publication['publication_year']) {
        echo "<span>" . $publication['publication_year'][0];
        echo ". </span>";
    }

    if ($publication['conference'][0]['name']) {
        echo "<span>" . $publication['conference'][0]['name'];
        if ($publication['conference'][0]['location'] || $publication['conference'][0]['date']) {
            echo "<span>, </span>";
        } else {
            echo ". </span>";
        }
    }

    if ($publication['conference'][0]['location']) {
        echo "<span>" . $publication['conference'][0]['location'] . "</span>";
        if ($publication['conference'][0]['date']) {
            echo "<span>, </span>";
        } else {
            echo ". </span>";
        }
    }
    
    if ($publication['conference'][0]['date']) {
        echo "<span>" . $publication['conference'][0]['date'] . "</span>";
        echo "<span>. </span>";
    }

    if ($publication['journal'][0]['page']) {
        echo '<span>' . __('p.', 'epfl_infoscience') . ' ' . $publication['journal'][0]['page'] .'.</span> ';
    }

    if ($publication['doi']) {
        echo "<span>DOI : " . $publication['doi'][0] . ".</span>";
    }    
?>
