<?php

require_once 'utils.php';

Class ScienceQAShortCakeConfig
{
	private static function get_lang_options()
	{
		return array(
			array('value' => 'en', 'label' => esc_html__('English', 'epfl-scienceqa')),
			array('value' => 'fr', 'label' => esc_html__('French', 'epfl-scienceqa')),
		);
	}

	public static function config()
	{
		shortcode_ui_register_for_shortcode(

			'epfl_scienceqa',

			array(
				'label' => __('Science Q&A', 'epfl-scienceqa'),
				'listItemImage' => '<img src="' . plugins_url( 'img/scienceqa.svg', __FILE__ ) . '" >',
				'attrs'         => array(
						array(
							'label'         => '<h3>' . esc_html__('Select a language', 'epfl-scienceqa') . '</h3>',
							'attr'          => 'lang',
							'type'          => 'radio',
							'options'       => ScienceQAShortCakeConfig::get_lang_options(),
							'description'   => esc_html__('The language used to render Science Q&A survey', 'epfl-scienceqa'),
							'value'         => 'en',
						),
					),

				'post_type'     => array( 'post', 'page' ),
			)
		);
	}
}
?>
