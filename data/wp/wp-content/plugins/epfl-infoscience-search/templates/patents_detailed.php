<?php
    if ($publication['title']) {
        echo '<h3 class="infoscience_title">' . $publication['title'][0] .'</h3>';
    }

    if ($publication['author']) {
        echo '<p class="infoscience_authors">';
        include $template_base_path . 'common/authors.php';
        echo '</p>';
    }

    echo '<p class="infoscience_host">';

    if ($publication['corporate_name']) {
        echo "<span>" . $publication['corporate_name'][0] . "</span>";
        if ($publication['company_name']) {
            echo "<span> / </span>";
        } else {
            echo "<span>: </span>";    
        }
    }

    if ($publication['company_name']) {
        echo "<span>" . $publication['company_name'][0] . "</span>";
        if ($publication['publication_year']) {
            echo "<span>: </span>";
        } else {
            echo "<span>. </span>";
        }
    }

    if ($publication['publication_year']) {
        echo "<span>" . $publication['publication_year'][0] . ". </span>";
    }

    echo '</p>';

    if ($publication['patent']) {
        echo '<p>';
        echo '<div>';
        echo '<div class="patent-patents-list-left">';
        echo '<span>'.  __('Patent number(s)', 'epfl-infoscience-search') . ' :</span>';
        echo '</div>';

        echo '<div class="patent-patents-list-right">';

        foreach ($publication['patent'] as $patent) {
            echo "<span>" . $patent['number'] . " ";

            if ($patent['state']) {
                echo "(" . $patent['state'] . ")";
            }

            echo "</span><br />";
        }

        echo '</div>';
        echo '</div>';
        echo '</p>';
    }


?>
