<?php
/* 
* Fields
*/ 
Class InfoscienceFieldRender {
    /*
    * Check if the filed exist and is not empty, including subfield if provided
    */
    public static function field_exists($field, $subfield=null) {
        $exists = isset($field) && is_array($field) && !empty($field[0]);

        if ($subfield) {
            $exists = $exists && isset($field[0][$subfield]) && !empty($field[0][$subfield]);
        }

        return  $exists;
    }

    # to be overriden
    public static function render($publication, $format, $has_next=false) {
    }

    # when we don'have any special logic
    public static function direct_value_render($value, $format, $has_next=false) {
        $html_rendered = "";

        if ($format === 'detailed') {
            $html_rendered .= '<span">' . $value .'</span>';
            if ($has_next) {
                $html_rendered .= '<span>: </span>';
            }


        } else {
            $html_rendered .= "<span><strong>" . $publication['title'][0] . "</strong></span>";
            if ($has_next) {
                $html_rendered .= "<span> ; </span>";
            } else {
                $html_rendered .= "<span>. </span>";
            }
        }        
    }
}

Class AuthorInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($authors, $format, $post_div="<span> : </span>") {
        $html_rendered = "";

        if ($format === 'detailed') {
            $html_rendered .= '<p class="infoscience_authors">';
        }

        foreach($authors as $index => $author) {
            if ($index == 5) {
                $html_rendered .= "<span> et al. </span>";
                break;
            } else {
                $html_rendered .= "<span>";
                if ($index != 0){
                    $html_rendered .= "; ";
                }
                $html_rendered .= "</span>";
                $html_rendered .= "<span>";
                $html_rendered .= '<a href="' . $author['search_url'] . '" target="_blank">';
                $html_rendered .= $author['initial_name'];
                $html_rendered .= '</a>';
                $html_rendered .= "</span>";
            }
        }

        if ($format === 'detailed') {
            $html_rendered .= '</p>';
        } else {
            if ($authors && !empty($post_div)) {
                $html_rendered .= $post_div;
            }
        }
       
        return $html_rendered;
    }
}

Class DirectorAuthorInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $post_div="<span> : </span>") {
        $html_rendered = "";

        
        if (self::field_exists($publication['author']) || self::field_exists($publication['director'])) {
            if ($format === 'detailed') {
                $html_rendered .= '<p class="infoscience_authors">';
            }

            if (self::field_exists($publication['author'])) {
                $html_rendered .= AuthorInfoscienceFieldRender::render($publication['author'], 'short', $post_div="");
            }

            if (self::field_exists($publication['author']) && self::field_exists($publication['director'])) {
                $html_rendered .= "<span> / </span>";
            }

            if (self::field_exists($publication['director'])) {
                $html_rendered .= AuthorInfoscienceFieldRender::render($publication['director'], 'short', $post_div="");
                $html_rendered .= "<span> (" . __('Dir.', 'epfl-infoscience-search') . ") </span>";
            }
            
            if ($format === 'detailed') {
                $html_rendered .= '</p>';
            }
        }
    
        return $html_rendered;
    }
}


Class TitleInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if ($format === 'detailed') {
            $html_rendered .= '<h3 class="infoscience_title tex2jax_process">' . $publication['title'][0] .'</h3>';
        } else {
            $html_rendered .= "<span class='tex2jax_process'><strong>" . $publication['title'][0] . "</strong></span>";
            if ($has_next) {
                $html_rendered .= "<span> ; </span>";
            } else {
                $html_rendered .= "<span>. </span>";
            }
        }
        
        return $html_rendered;
    }
}

# for 
# - book chapters
# - books
# - theses
Class BooksChaptersPublicationLocationInsitutionDateInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if (self::field_exists($publication['publication_location'])) {
            $html_rendered .= "<span>" . $publication['publication_location'][0] . "</span>";
            if (self::field_exists($publication['publication_institution'])) {
                $html_rendered .= '<span>: </span>';
            } elseif (self::field_exists($publication['publication_date'])) {
                $html_rendered .= '<span>, </span>';
            } else {
                $html_rendered .= '<span>. </span>';
            }
        }

        if ($publication['publication_institution']) {
            $html_rendered .= "<span>" . $publication['publication_institution'][0] . "</span>";
            if (self::field_exists($publication['publication_date'])) {
                $html_rendered .= '<span>, </span>';
            } else {
                $html_rendered .= '<span>. </span>';
            }
        }

        $html_rendered .=  PublicationDateInfoscienceFieldRender::render($publication, 'detailed', $has_next);

        return $html_rendered;
    }
}

# Add ISBN
Class BooksPublicationLocationInsitutionDateInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";
        if ($format === 'detailed') {
            $has_isbn = self::field_exists($publication['isbn']);

            if (self::field_exists($publication['publication_location'])) {
                $html_rendered .= "<span>" . $publication['publication_location'][0] . "</span>";
                if (self::field_exists($publication['publication_institution']) ||
                    self::field_exists($publication['publication_date'])) {
                    $html_rendered .= '<span>: </span>';
                } elseif ($has_isbn) {
                    $html_rendered .= '<span> - </span>';
                } else {
                    $html_rendered .= '<span>. </span>';
                }
            }

            if ($publication['publication_institution']) {
                $html_rendered .= "<span>" . $publication['publication_institution'][0] . "</span>";
                if (self::field_exists($publication['publication_date'])) {
                    $html_rendered .= '<span>, </span>';
                } elseif ($has_isbn) {
                    $html_rendered .= '<span> - </span>';
                } else {
                    $html_rendered .= '<span>. </span>';
                }
            }

            if (self::field_exists($publication['publication_date'])) {
                $html_rendered .= "<span>" . $publication['publication_date'][0] . "</span>";
                
                if ($has_isbn) {
                    $html_rendered .= '<span> - </span>';
                } else {
                    $html_rendered .= '<span>. </span>';
                }
            }
        } else {
            if (self::field_exists($publication['publication_location'])) {
                $html_rendered .= "<span>" . $publication['publication_location'][0] . "</span>";
                if (self::field_exists($publication['publication_institution']) ||
                    self::field_exists($publication['publication_date'])) {
                    $html_rendered .= '<span>: </span>';
                } else {
                    $html_rendered .= '<span>. </span>';
                }
            }
            
            if ($publication['publication_institution']) {
                $html_rendered .= "<span>" . $publication['publication_institution'][0] . "</span>";
                if (self::field_exists($publication['publication_date'])) {
                    $html_rendered .= '<span>, </span>';
                } else {
                    $html_rendered .= '<span>. </span>';
                }
            }

            $html_rendered .= PublicationDateInfoscienceFieldRender::render($publication, 'detailed', $has_next);
        }

        return $html_rendered;
    }
}

Class PublicationDateInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if (self::field_exists($publication['publication_date'])) {
            if ($format === 'detailed') {
                $html_rendered .= "<span>" . $publication['publication_date'][0] . ". </span>";
            } else {
                $html_rendered .= "<span><i>" . $publication['publication_date'][0] . "</i></span>";
                $html_rendered .= "<span>. </span>";
            }
        }

        return $html_rendered;
    }
}

Class PublicationDateAsPInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if (self::field_exists($publication['publication_date'])) {
            if ($format === 'detailed') {
                $html_rendered .= "<p>" . $publication['publication_date'][0] . ". </p>";
            } else {
                $html_rendered .= parent::render($publication, $format, $has_next);
            }
        }

        return $html_rendered;
    }
}

Class DOIInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";
        if (self::field_exists($publication['doi'])) {
            if ($format === 'detailed') {
                $html_rendered .= "<p>DOI&nbsp;:&nbsp;" . $publication['doi'][0] . ".</p>";
            } else {
                $html_rendered .= "<span>DOI&nbsp;:&nbsp;" . $publication['doi'][0] . ".</span>";
            }
        }        
        return $html_rendered;
    }
}

Class ISBNInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";
        if (self::field_exists($publication['isbn'])) {
            if ($format === 'detailed') {
                $html_rendered .= "<span>ISBN&nbsp;:&nbsp;" . $publication['isbn'][0] . ". </span>";
            } else {
                $html_rendered .= "<span>ISBN&nbsp;:&nbsp;" . $publication['isbn'][0] . ". </span>";
            }
        }        
        return $html_rendered;
    }
}


Class SummaryInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        if (self::field_exists($publication['summary'])) {
            return '<p class="infoscience_abstract tex2jax_process">' . $publication['summary'][0] . '</p>';
        } else {
            return "";
        }
    }
}

Class JournalPublisherInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if (self::field_exists($publication['journal'], 'publisher')) {
            if ($format === 'detailed') {
                $html_rendered .= "<span><i>" . $publication['journal'][0]['publisher'] . "</i></span>";
            } else {
                $html_rendered .= "<span><i>" . $publication['journal'][0]['publisher'] . "</i></span>";
            }

            if ($has_next) {
                $html_rendered .= "<span>; </span>";
            } else {
                $html_rendered .= "<span>. </span>";
            }

        return $html_rendered;
        }
    }
}

Class JournalPageInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if (self::field_exists($publication['journal'], 'page')) {
            $html_rendered .= '<span>' . __('p.', 'epfl-infoscience-search') . ' ' . $publication['journal'][0]['page'] .'.</span> ';
        }
 
        return $html_rendered;
    }
}


Class JournalDetailsInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if ($format === 'detailed') {
            if (self::field_exists($publication['journal'], 'volume')) {
                $html_rendered .= '<span>' . __('Vol.', 'epfl-infoscience-search') . ' ' . $publication['journal'][0]['volume'] .'</span> ';

                if (self::field_exists($publication['journal'], 'number') || 
                    self::field_exists($publication['journal'], 'page')) {
                    $html_rendered .= '<span>, </span>';
                } else {
                    $html_rendered .= '<span>. </span>';
                }
            }

            if (self::field_exists($publication['journal'], 'number')) {
                $html_rendered .= '<span>' . __('num.', 'epfl-infoscience-search') . ' ' . $publication['journal'][0]['number'] .'</span> ';
                if (self::field_exists($publication['journal'], 'page')) {
                    $html_rendered .= '<span>, </span>';
                } else {
                    $html_rendered .= '<span>. </span>';
                }
            }

            $html_rendered .= JournalPageInfoscienceFieldRender::render($publication, $format, $has_next);
        } else {
        }
        return $html_rendered;
    }
}

Class ConferenceDataInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if (self::field_exists($publication['conference'], 'name')) {
            $html_rendered .= "<span>" . $publication['conference'][0]['name'];
            
            if (self::field_exists($publication['conference'], 'location') || 
                self::field_exists($publication['conference'], 'date')) {
                $html_rendered .= ", </span>";
            } else {
                $html_rendered .= ". </span>";
            }
        }

        if (self::field_exists($publication['conference'], 'location')) {
            $html_rendered .= "<span>" . $publication['conference'][0]['location'];
            
            if (self::field_exists($publication['conference'], 'location')) {
                $html_rendered .= ", </span>";
            } else {
                $html_rendered .= ". </span>";
            }
        }

        if (self::field_exists($publication['conference'], 'date')) {
            $html_rendered .= "<span>" . $publication['conference'][0]['date'] . ". </span>";
        }

        return $html_rendered;
    }
}

Class ConferenceProceedingsDataInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if ($format === 'detailed') {
            ConferenceDataInfoscienceFieldRender::render($publication, $format, $has_next);
        } else {
            if (self::field_exists($publication['conference'], 'name')) {
                $html_rendered .= "<span>" . $publication['conference'][0]['name'];
                $html_rendered .= ". </span>";
            }
        }

        return $html_rendered;
    }
}

Class CorporateNameInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if (self::field_exists($publication['corporate_name'])) {
            $html_rendered .= "<span>" . $publication['corporate_name'][0] . "</span>";
    
            if ($has_next) {
                $html_rendered .= "<span> / </span>";
            } else {
                $html_rendered .= "<span>: </span>";
            }
        }
        
        return $html_rendered;
    }
}

Class CompanyNameInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";
        
        if (self::field_exists($publication['corporate_name'])) {
            if ($format === 'detailed') {
                $html_rendered .= "<span>" . $publication['company_name'][0] . "</span>";

                if ($has_next) {
                    $html_rendered .= "<span>: </span>";
                } else {
                    $html_rendered .= "<span>. </span>";
                }
            } else {
                $html_rendered .= "<span>" . $publication['company_name'][0] . "</span>";
                $html_rendered .= "<span>: </span>";
            }
        }
        
        return $html_rendered;
    }
}

Class PatentsInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if (self::field_exists($publication['patent'])) {
            if ($format === 'detailed') {
                $html_rendered .= '<p>';
                $html_rendered .= '<div>';
                $html_rendered .= '<div class="patent-patents-list-left">';
                $html_rendered .= '<span>'.  __('Patent number(s)', 'epfl-infoscience-search') . ' :</span>';
                $html_rendered .= '</div>';
        
                $html_rendered .= '<div class="patent-patents-list-right">';
        
                foreach ($publication['patent'] as $patent) {
                    $html_rendered .= "<span>" . $patent['number'] . " ";
        
                    if ($patent['state']) {
                        $html_rendered .= "(" . $patent['state'] . ")";
                    }
        
                    $html_rendered .= "</span><br />";
                }
        
                $html_rendered .= '</div>';
                $html_rendered .= '</div>';
                $html_rendered .= '</p>';
            } else {
                $len_patents = count($publication['patent']);
                foreach ($publication['patent'] as $index => $patent) {
                    $html_rendered .= "<span>" . $patent['number'];
                    $html_rendered .= "</span>";
        
                    # last ?
                    if ($index == $len_patents - 1) {
                        $html_rendered .= "<span>. </span>";
                    } else {
                        $html_rendered .= "<span>; </span>";
                    }
                }
            }
        }

        return $html_rendered;
    }
}

Class PublicationPageInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if ($format === 'detailed') {
            if (self::field_exists($publication['publication_page'])) {
                $html_rendered .= '<span>' . __('p.', 'epfl-infoscience-search') . ' ' . $publication['publication_page'][0] .'.</span> ';
            }
        }

        return $html_rendered;
    }
}

Class ReportUrlInfoscienceFieldRender extends InfoscienceFieldRender {
    public static function render($publication, $format, $has_next=false) {
        $html_rendered = "";

        if (self::field_exists($publication['report_url'])) {
            if ($format === 'detailed') {
                $html_rendered .= '<p><a href="' . $publication['report_url'][0] . '" target="_blank">' . $publication['report_url'][0] . '</a>.</p>';
            } else {
                $html_rendered .= "<span>" . $publication['report_url'][0] . "</span>";
                $html_rendered .= "<span>. </span>";
                $html_rendered .= '<p><a href="' . $publication['report_url'][0] . '" target="_blank">' . $publication['report_url'][0] . '</a>.</p>';
            }
        } 

        return $html_rendered;
    }
}

?>
