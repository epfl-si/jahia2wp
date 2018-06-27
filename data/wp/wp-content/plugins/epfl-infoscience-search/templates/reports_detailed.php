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

    if ($publication['publication_date']) {
        echo "<span>" . $publication['publication_date'][0] . ". </span>";
    }

    if ($publication['publication_page']) {
        echo '<span>' . __('p.', 'epfl-infoscience-search') . ' ' . $publication['publication_page'][0] .'. </span> ';
    }

    echo '</p>';

    if ($publication['report_url']) {
        echo '<p><a href="' . $publication['report_url'][0] . '" target="_blank">' . $publication['report_url'][0] . '</a>.</p>';
    }
?>
