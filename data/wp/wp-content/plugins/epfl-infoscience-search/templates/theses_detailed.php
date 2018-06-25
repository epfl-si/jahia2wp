<?php 
    if ($publication['title']) {
        echo '<h3 class="infoscience_title">' . $publication['title'][0] .'</h3>';
    }
    if ($publication['author'] || $publication['director']) {
        echo '<p class="infoscience_authors">';

        if ($publication['author']) {
            include $template_base_path . 'common/authors.php';
        }
        
        if ($publication['author'] && $publication['director']) {
            echo "<span> / </span>";
        }
    
        if ($publication['director']) {
            $template_authors = $publication['director'];
            include $template_base_path . 'common/authors.php';
            echo "<span> (" . __('Dir.', 'epfl_infoscience') . ") </span>";
        }
        echo '</p>';
    }
    
    if ($publication['publication_location'] ||
        $publication['publication_institution'] ||
        $publication['publication_year']) {
        echo '<p class="infoscience_host">';

    
        if ($publication['publication_location']) {
            echo "<span>" . $publication['publication_location'][0] . "</span>";

            if ($publication['publication_institution'] || $publication['publication_year']) {
                echo "<span>, </span>";
            } else {
                echo "<span>. </span>";
            }
        }

        if ($publication['publication_institution']) {
            echo "<span>" . $publication['publication_institution'][0] . "</span>";
            if ($publication['publication_year']) {
                echo "<span>, </span>";
            } else {
                echo "<span>. </span>";
            }
        }

        if ($publication['publication_year']) {
            echo "<span>" . $publication['publication_year'][0] . ". </span>";
        }    

        if ($publication['publication_page']) {
            echo '<span>' . __('p.', 'epfl_infoscience') . ' ' . $publication['publication_page'][0] .'.</span> ';
        }

        echo '</p>';
    }

    if ($publication['doi']) {
        echo "<p>DOI : " . $publication['doi'][0] . ".</p>";
    }   
?>
