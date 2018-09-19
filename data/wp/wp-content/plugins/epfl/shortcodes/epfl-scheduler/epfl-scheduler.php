<?php
/**
 * Plugin Name: EPFL scheduler shortcode
 * Description: provides a shortcode to display content according dates
 * @version: 1.1
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

/**
 * Validate date or time
 *
 * examples:
 * validateDate('2018-03-26')
 * validateDate('15:05:03', 'H:i:s')
 *
 * @param $date: date to validate
 * @param $format: default date format
 * @return True if the date or time is in the right format
 */
function epfl_scheduler_validate_date( string $date, string $format = 'Y-m-d' ): bool {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

/**
 * Check parameters
 *
 * @param $start_date: start date
 * @param $end_date: end date
 * @param $start_time: start time
 * @param $end_time: end time
 * @return True if the parameters are populated and in the right format
 */
function epfl_scheduler_check_parameters( $start_date, $end_date, $start_time, $end_time ) {
    if ( $start_date !== '' && $end_date !== '' && epfl_scheduler_validate_date($start_date) && epfl_scheduler_validate_date($end_date) ) {
        return FALSE;
    }
    if ( $start_time === '' || epfl_scheduler_validate_date($start_time, 'H:i:s') == FALSE ) {
        return FALSE;
    }
    if ( $end_time === '' || epfl_scheduler_validate_date($end_time, 'H:i:s') == FALSE ) {
        return FALSE;
    }
    return TRUE;
}

/**
 * Scheduler shortcode
 *
 * @param $atts: user input parameters
 * @param $content: content
 * @return
 */
function epfl_scheduler_shortcode( $atts, $content = '', $tag )
{
    // extract shortcode parameter
    $atts = shortcode_atts( array(
        'start_date' => '',
        'end_date'   => '',
        'start_time' => '',
        'end_time'   => '',
    ), $atts, $tag );

    // sanitize parameters
    $start_date = sanitize_text_field( $atts['start_date'] );
    $end_date   = sanitize_text_field( $atts['end_date'] );
    $start_time = sanitize_text_field( $atts['start_time'] );
    $end_time   = sanitize_text_field( $atts['end_time'] );

    // check parameters
    if ( epfl_scheduler_check_parameters( $start_date, $end_date, $start_time, $end_time ) ) {
        return "";
    }

    // initialize time
    if ( $start_time === '' ) {
        $start_time = '00:00:00';
    }
    if ( $end_time === '' ) {
        $end_time = '00:00:00';
    }

    // convert input user to string 'yyyy:mm:ddThh:mm:ss'
    $start_date = $start_date . "T" . $start_time;
    $end_date = $end_date . "T" . $end_time;
    
    date_default_timezone_set('Europe/Paris');

    // convert date string to datetime
    $start_date = strtotime( $start_date );
    $end_date = strtotime( $end_date );
    $now = time();

    // check if we can display content
    if ( $now >= $start_date && $now <= $end_date ) {
        return do_shortcode($content);
    }
}

add_action( 'init', function() {

    // Define shortcode
    add_shortcode('epfl_scheduler', 'epfl_scheduler_shortcode');

});

add_action( 'register_shortcode_ui', function() {
    shortcode_ui_register_for_shortcode(
        'epfl_scheduler',
        array(
            'label' => 'Scheduler',
            'listItemImage' => '<img src="' . plugins_url( 'img/scheduler.svg', __FILE__ ) . '" >',
            'attrs'         => array(
                array(
                    'label'         => '<h3>' . esc_html__('Start date', 'epfl') . '</h3>',
                    'attr'          => 'start_date',
                    'type'          => 'date',
                    'description'   => esc_html__('Please select a start date. Format: mm/dd/yyyy', 'epfl'),
                ),
                array(
                    'label'         => '<h3>' . esc_html__('End date', 'epfl') . '</h3>',
                    'attr'          => 'end_date',
                    'type'          => 'date',
                    'description'   => esc_html__('Please select an end date. Format: mm/dd/yyyy', 'epfl'),
                ),
                array(
                    'label'         => '<h3>' . esc_html__('Start time', 'epfl') . '</h3>',
                    'attr'          => 'start_time',
                    'type'          => 'text',
                    'value'         => '00:00:00',
                    'description'   => esc_html__('Please select a start time. Format: hh:mm:ss', 'epfl'),
                ),
                array(
                    'label'         => '<h3>' . esc_html__('End time', 'epfl') . '</h3>',
                    'attr'          => 'end_time',
                    'type'          => 'text',
                    'value'         => '00:00:00',
                    'description'   => esc_html__('Please select an end time. Format: hh:mm:ss', 'epfl'),
                ),
            ),
            'inner_content' => array(
                'label'        => '<h3>' . esc_html__( 'Content of scheduler', 'epfl' ) . '</h3>',
                'description'  => esc_html__('You can enter text to display above'),
            ),
            'post_type'     => array( 'post', 'page' ),
        )
    );
});

?>
