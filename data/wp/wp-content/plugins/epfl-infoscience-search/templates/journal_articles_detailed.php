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

    if ($publication['journal'][0]['publisher']) {
        echo "<span><i>" . $publication['journal'][0]['publisher'] . "</i>. </span>";
    }

    if ($publication['publication_year']) {
        echo "<span>" . $publication['publication_year'][0] . ". </span>";
    }

    if ($publication['doi']) {
        echo "<p>DOI : " . $publication['doi'][0] . ".</p>";
    }

    if ($publication['journal'][0]['volume']) {
        echo '<span>' . __('Vol.', 'epfl_infoscience') . ' ' . $publication['journal'][0]['volume'] .'</span> ';
        if ($publication['journal'][0]['number'] || $publication['journal'][0]['page']) {
            echo '<span>, </span>';
        } else {
            echo '<span>. </span>';
        }
    }

    if ($publication['journal'][0]['number']) {
        echo '<span>' . __('num.', 'epfl_infoscience') . ' ' . $publication['journal'][0]['number'] .'</span> ';
        if ($publication['journal'][0]['page']) {
            echo '<span>, </span>';
        } else {
            echo '<span>. </span>';
        }
    }

    if ($publication['journal'][0]['page']) {
        echo '<span>' . __('p.', 'epfl_infoscience') . ' ' . $publication['journal'][0]['page'] .'.</span> ';
    }
?>
