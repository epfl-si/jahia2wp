<?php include $template_base_path . 'common/authors.php' ?>

<?php 
    if ($publication['author']) {
        echo "<span> : </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
        echo "<span>";
        if ($publication['conference'][0]['name'] ||
            $publication['conference'][0]['location'] ||
            $publication['conference'][0]['date']) {
            echo " ; ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }
        
    if ($publication['conference'][0]['name']) {
        echo "<span>" . $publication['conference'][0]['name'] . "</span>";
        echo "<span>";
        if ($publication['conference'][0]['location'] || $publication['conference'][0]['date']) {
            echo ", ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }

    if ($publication['conference'][0]['location']) {
        echo "<span>" . $publication['conference'][0]['location'] . "</span>";
        echo "<span>";
        if ($publication['conference'][0]['date']) {
            echo ", ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }    

    if ($publication['conference'][0]['date']) {
        echo "<span>" . $publication['conference'][0]['date'] . "</span>";
        echo "<span>.</span>";
    }
?>
