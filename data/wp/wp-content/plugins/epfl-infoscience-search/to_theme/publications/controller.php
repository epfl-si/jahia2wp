<?php
add_action('epfl_infoscience_search_action', 'renderPublicationsSearchResult', 1, 5);

function renderPublicationsSearchResult($grouped_by_publications,
                                 $url,
                                 $format,
                                 $summary,
                                 $thumbnail) {
  ob_start();

  if (is_admin()) {
    // render placeholder for backend editor
    set_query_var('epfl_placeholder_title', 'Infoscience search');
    get_template_part('shortcodes/placeholder');

  } else {
    set_query_var('epfl_infoscience_search_grouped_by_publications', $grouped_by_publications);
    set_query_var('epfl_infoscience_search_url', $url);
    set_query_var('epfl_infoscience_search_format', $format);
    set_query_var('epfl_infoscience_search_summary', $summary);
    set_query_var('epfl_infoscience_search_thumbnail', $thumbnail);
    get_template_part('shortcodes/publications/view');
  }
  return ob_end_flush();
}
