<?php

?>
<ul class="record-metadata">
	<p></p>
	<p class="infoscience_links">
    <?php
        echo '<a class="infoscience_link_detailed" href="https://infoscience.epfl.ch/record/'. $publication['record_id'][0] . '"?ln=en" target="_blank">' . esc_html__("Detailed record", "epfl_infoscience") . '</a>';

        if ($publication['url']['fulltext'][0]) {
            echo ' - ';
            echo '<a class="infoscience_link_fulltext" href="'. $publication['url']['fulltext'][0] . '" target="_blank">'. esc_html__("Full text", "epfl_infoscience") .'</a>';
        }
        # echo ' - ';
        # echo '<a class="infoscience_link_official" href="https://dx.doi.org/'. $publication['doi'][0] . '" target="_blank">' . esc_html__("View at publihser", "epfl_infoscience") . '</a>'
    ?>
	</p>
</ul>
