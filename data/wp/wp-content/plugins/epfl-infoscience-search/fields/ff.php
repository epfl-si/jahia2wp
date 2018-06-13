<?php

/**
 * Set a custom design style for EPFL needs
 */
class Shortcode_UI_EPFL_style_Fields {

	/**
	 * Shortcake Fields controller instance.
	 *
	 * @access private
	 * @var object
	 */
	private static $instance;

	/**
	 * Default settings for each field
	 *
	 * @access private
	 * @var array
	 */
	private $field_defaults = array(
		'template' => 'shortcode-ui-field-text',
		'view'     => 'editAttributeField',
		'encode'   => false,
	);

	/**
	 * Settings for default Fields.
	 *
	 * @access private
	 * @var array
	 */
	private $fields = array(
		'text'     => array(),
		'textarea' => array(
			'template' => 'shortcode-ui-field-textarea',
		),
		'url'      => array(
			'template' => 'shortcode-ui-field-url',
		),
		'select'   => array(
			'template' => 'shortcode-ui-field-select',
		),
		'checkbox' => array(
			'template' => 'shortcode-ui-field-checkbox',
		),
		'radio'    => array(
			'template' => 'shortcode-ui-field-radio',
		),
		'email'    => array(
			'template' => 'shortcode-ui-field-email',
		),
		'number'   => array(
			'template' => 'shortcode-ui-field-number',
		),
		'date'     => array(
			'template' => 'shortcode-ui-field-date',
		),
		'range'    => array(
			'template' => 'shortcode-ui-field-range',
		),
	);

	/**
	 * Get instance of Shortcake Field controller.
	 *
	 * Instantiates object on the fly when not already loaded.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	/**
	 * Set up actions needed for default fields
	 */
	private function setup_actions() {
		add_filter( 'shortcode_ui_fields', array( $this, 'filter_shortcode_ui_fields' ) );
		add_action( 'enqueue_shortcode_ui', array( $this, 'action_enqueue_shortcode_ui' ) );
		add_action( 'shortcode_ui_loaded_editor', array( $this, 'action_shortcode_ui_loaded_editor' ) );
	}

	/**
	 * Get all registered fields
	 *
	 * @return array
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Add localization data needed for default fields
	 */
	public function action_enqueue_shortcode_ui() {
		wp_localize_script( 'shortcode-ui', 'shortcodeUIFieldData', $this->fields );
	}

	/**
	 * Output styles and templates used by post select field.
	 */
	public function action_shortcode_ui_loaded_editor() {

		?>

		<script type="text/html" id="tmpl-shortcode-ui-field-post-select">
			<div class="field-block shortcode-ui-field-post-select shortcode-ui-attribute-{{ data.attr }}">
				<label for="{{ data.id }}">{{{ data.label }}}</label>
				<select id="{{ data.id }}" name="{{ data.name }}" class="shortcode-ui-post-select" ></select>
				<# if ( typeof data.description == 'string' && data.description.length ) { #>
					<p class="description">{{{ data.description }}}</p>
				<# } #>
			</div>
		</script>

		<?php
	}


}
