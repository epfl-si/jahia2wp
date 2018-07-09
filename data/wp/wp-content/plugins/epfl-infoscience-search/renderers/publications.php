<?php
require_once 'fields.php';

# mainly used to sanitize external values when we convert to class name
define('DOCTYPE_TO_CLASS_NAME_MAP', [
    'BOOK CHAPTERS' => 'BookChapters',
    'BOOKS' => 'Books',
    'CONFERENCE PAPERS' => 'ConferencePapers',
    'CONFERENCE PROCEEDINGS' => 'ConferenceProceedings',
    'JOURNAL ARTICLES' => 'JournalArticles',
    'PATENTS' => 'Patents',
    'POSTERS' => 'Posters',
    'REPORTS' => 'Reports',
    'STUDENT PROJECTS' => 'StudentProjects',
    'TALKS' => 'Talks',
    'THESES' => 'Theses',
    'WORKING PAPERS' => 'WorkingPapers',
]);

function get_render_class_for_publication($publication, $format) {
    # by default, use one of this
    if ($format === "detailed") {
        $record_renderer_class_base = 'DetailedInfosciencePublicationRender';
    } else {
        $record_renderer_class_base = 'ShortInfosciencePublicationRender';
    }

    if (InfoscienceFieldRender::field_exists($publication['doctype'])) {
        # doctype determine the render, find it in the map
        $doctype_to_find = strtoupper($publication['doctype'][0]);
        
        if (array_key_exists($doctype_to_find, DOCTYPE_TO_CLASS_NAME_MAP)) {
            
            $record_renderer_class = DOCTYPE_TO_CLASS_NAME_MAP[$doctype_to_find] . $record_renderer_class_base;
            
            if (class_exists($record_renderer_class)) {
                return $record_renderer_class;
            }                
        }
    }

    return $record_renderer_class_base;
}

/* 
* Publication
*/ 
Class InfosciencePublicationRender {
    protected static function pre_render() {
        $html_rendered = "";
        $html_rendered .= '<div class="infoscience_record">';
        $html_rendered .= '  <div class="infoscience_data">';
        $html_rendered .= '    <div class="record-content">';

        return $html_rendered;
    }

    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);
        
        return $html_rendered;
    }

    protected static function post_render($publication, $summary, $thumbnail) {
        $html_rendered = "";
        if ($summary) {
            $html_rendered .= SummaryInfoscienceFieldRender::render($publication, false);
        }
        $html_rendered .= '      </div>';
        $html_rendered .= '      ' . self::render_links($publication, $thumbnail);
        $html_rendered .= '  </div>';
        $html_rendered .= '</div>';

        return $html_rendered;
    }

    protected static function render_links($publication, $thumbnail) {
        $template_base_path = plugin_dir_path(__FILE__);
        $links_path = $template_base_path . 'common/' . 'links-bar.php';

        ob_start();
        include($links_path);
        return ob_get_clean();
    }
}

Class DetailedInfosciencePublicationRender extends InfosciencePublicationRender {
    protected static $format="detailed";

    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class ShortInfosciencePublicationRender extends InfosciencePublicationRender {
    protected static $format="short";

    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format, InfoscienceFieldRender::field_exists('journal', 'publisher'));
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}


/* 
* Doctypes specific render 
*/

Class BookChaptersDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= '<p class="infoscience_host">';

        $has_next = InfoscienceFieldRender::field_exists($publication['publication_location']) || 
            InfoscienceFieldRender::field_exists($publication['publication_institution']) ||
            InfoscienceFieldRender::field_exists($publication['publication_date']);
        $html_rendered .= JournalPublisherInfoscienceFieldRender::render($publication, self::$format, $has_next);

        $html_rendered .= BooksChaptersPublicationLocationInsitutionDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= JournalPageInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= ISBNInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= '</p>';
        $html_rendered .= DOIInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class BookChaptersShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        
        $has_next = InfoscienceFieldRender::field_exists($publication['journal'], 'publisher') ||
            InfoscienceFieldRender::field_exists($publication['publication_location']) || 
            InfoscienceFieldRender::field_exists($publication['publication_institution']) ||
            InfoscienceFieldRender::field_exists($publication['publication_date']);

        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format, $has_next);

        $has_next = InfoscienceFieldRender::field_exists($publication['publication_location']) || 
            InfoscienceFieldRender::field_exists($publication['publication_institution']) ||
            InfoscienceFieldRender::field_exists($publication['publication_date']);
        $html_rendered .= JournalPublisherInfoscienceFieldRender::render($publication, self::$format, $has_next);

        $html_rendered .= BooksChaptersPublicationLocationInsitutionDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= JournalPageInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class BooksDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= '<p class="infoscience_host">';

        $html_rendered .= BooksPublicationLocationInsitutionDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= ISBNInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= '</p>';
        $html_rendered .= DOIInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class BooksShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);

        $has_next = InfoscienceFieldRender::field_exists($publication['publication_location']) || 
                    InfoscienceFieldRender::field_exists($publication['publication_institution']);
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= BooksPublicationLocationInsitutionDateInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class ConferencePapersDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= '<p class="infoscience_host">';

        $html_rendered .= JournalPublisherInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= ConferenceDataInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= JournalPageInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= '</p>';
        $html_rendered .= DOIInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class ConferencePapersShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);        
        $html_rendered .= ConferenceDataInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= JournalPageInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= DOIInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class ConferenceProceedingsDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        
        if (InfoscienceFieldRender::field_exists($publication['author_1'])) {
            $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author_1'], self::$format);
        } elseif (InfoscienceFieldRender::field_exists($publication['author_3'])) {
            $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author_3'], self::$format);
        }

        $html_rendered .= '<p>' . PublicationDateInfoscienceFieldRender::render($publication, self::$format) . '</p>';
        
        $html_rendered .= '<p class="infoscience_host">';
        $html_rendered .= ConferenceDataInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= '</p>';

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class ConferenceProceedingsShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        
        if (InfoscienceFieldRender::field_exists($publication['author_1'])) {
            $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author_1'], self::$format);
        } elseif (InfoscienceFieldRender::field_exists($publication['author_3'])) {
            $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author_3'], self::$format);
        }

        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, 'detailed');

        $html_rendered .= ConferenceProceedingsDataInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class JournalArticlesDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format, InfoscienceFieldRender::field_exists('journal', 'publisher'));
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= '<p class="infoscience_host">';
        $html_rendered .= JournalPublisherInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= JournalDetailsInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= '</p>';
        $html_rendered .= DOIInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class JournalArticlesShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format, InfoscienceFieldRender::field_exists('journal', 'publisher'));
        $html_rendered .= JournalPublisherInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= DOIInfoscienceFieldRender::render($publication, self::$format);
        
        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class PatentsDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        
        $html_rendered .= '<p class="infoscience_host">';

        $has_next = InfoscienceFieldRender::field_exists($publication['company_name']);
        $html_rendered .= CorporateNameInfoscienceFieldRender::render($publication, self::$format, $has_next);

        $has_next = InfoscienceFieldRender::field_exists($publication['publication_date']);
        $html_rendered .= CompanyNameInfoscienceFieldRender::render($publication, self::$format, $has_next);

        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= '</p>';

        $html_rendered .= PatentsInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class PatentsShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);

        $has_next = InfoscienceFieldRender::field_exists($publication['company_name']);
        $html_rendered .= CorporateNameInfoscienceFieldRender::render($publication, self::$format, $has_next);

        $html_rendered .= CompanyNameInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format, InfoscienceFieldRender::field_exists('patent'));

        $html_rendered .= PatentsInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);
        
        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class PostersDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        
        $html_rendered .= '<p class="infoscience_host">';
        $html_rendered .= ConferenceDataInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= '</p>';

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class PostersShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);

        $has_next = InfoscienceFieldRender::field_exists($publication['conference'], 'name') || 
            InfoscienceFieldRender::field_exists($publication['conference'], 'location') ||
            InfoscienceFieldRender::field_exists($publication['conference'], 'date');
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format, $has_next);

        $html_rendered .= ConferenceDataInfoscienceFieldRender::render($publication, self::$format);
        
        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class ReportsDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        
        $html_rendered .= '<p class="infoscience_host">';
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= PublicationPageInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= '</p>';

        $html_rendered .= ReportUrlInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class ReportsShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();

        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, 'detailed');
        $html_rendered .= ReportUrlInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class StudentProjectsDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        
        $html_rendered .= '<p class="infoscience_host">';
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= '</p>';

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class StudentProjectsShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();

        $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], self::$format);
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format, InfoscienceFieldRender::field_exists('publication_date'));
        $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class TalksDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        return PostersDetailedInfosciencePublicationRender::render($publication, $summary, $thumbnail);
    }
}

Class TalksShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        return PostersShortInfosciencePublicationRender::render($publication, $summary, $thumbnail);
    }
}

Class ThesesDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();

        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= DirectorAuthorInfoscienceFieldRender::render($publication, self::$format);
        
        $host_rendered = "";
        $host_rendered .= BooksChaptersPublicationLocationInsitutionDateInfoscienceFieldRender::render($publication, self::$format);
        $host_rendered .= PublicationPageInfoscienceFieldRender::render($publication, self::$format);

        if ($host_rendered && !empty($host_rendered)) {
            $html_rendered .= '<p class="infoscience_host">';
            $html_rendered .= $host_rendered;
            $html_rendered .= '</p>';
        }

        $html_rendered .= DOIInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class ThesesShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        $html_rendered = self::pre_render();

        $html_rendered .= DirectorAuthorInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= TitleInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= BooksChaptersPublicationLocationInsitutionDateInfoscienceFieldRender::render($publication, self::$format);
        $html_rendered .= PublicationPageInfoscienceFieldRender::render($publication, self::$format);
        
        $html_rendered .= DOIInfoscienceFieldRender::render($publication, self::$format);

        $html_rendered .= self::post_render($publication, $summary, $thumbnail);

        return $html_rendered;
    }
}

Class WorkingPapersDetailedInfosciencePublicationRender extends DetailedInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        return ReportsDetailedInfosciencePublicationRender::render($publication, $summary, $thumbnail);
    }
}

Class WorkingPapersShortInfosciencePublicationRender extends ShortInfosciencePublicationRender {
    public static function render($publication, $summary, $thumbnail) {
        return ReportsShortInfosciencePublicationRender::render($publication, $summary, $thumbnail);
    }
}

?>
