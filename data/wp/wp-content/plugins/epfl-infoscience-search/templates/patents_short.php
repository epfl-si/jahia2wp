<?php include $template_base_path . 'common/authors.php' ?>

<?php 
    if ($publication['author']) {
        echo "<span> ; </span>";
    }

    if ($publication['corporate_name']) {
        echo "<span>" . $publication['corporate_name'][0] . "</span>";
        if ($publication['company_name']) {
            echo "<span> / </span>";
        } else {
            echo "<span>: </span>";    
        }
        echo "<span>. </span>";
    }

    if ($publication['company_name']) {
        echo "<span>" . $publication['company_name'][0] . "</span>";
        echo "<span>: </span>";
    }

    if ($publication['title']) {
        echo "<span><strong>" . $publication['title'][0] . "</strong></span>";
        echo "<span>";
        if ($publication['patent']) {
            echo " ; ";
        } else {
            echo ". ";
        }
        echo "</span>";
    }

    if ($publication['patent']) {
        $len_patents = count($publication['patent']);
        foreach ($publication['patent'] as $index => $patent) {
            echo "<span>" . $patent['number'] . " ";

            if ($patent['state']) {
                echo "(" . $patent['state'] . ")";
            }
            echo "</span>";

            # last ?
            if ($index == $len_patents - 1) {
                echo "<span>. </span>";
            } else {
                echo "<span>; </span>";
            }
        }
    }

    if ($publication['publication_date']) {
        echo "<span>" . $publication['publication_date'][0] . "</span>";
        echo "<span>. </span>";
    }
?>
