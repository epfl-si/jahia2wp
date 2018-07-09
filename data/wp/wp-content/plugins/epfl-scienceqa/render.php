<?php

Class ScienceQARender
{
	/**
	 * Build HTML.
	 *
	 * @param $scienceqa: response of scienceqa API.
	 * @param $lang: language identifier.
	 * @return html of template
	 */
	public static function epfl_scienceqa_build_html( $scienceqa, $lang ): string
	{
		$html = '<div>';
		$html .= '  <h3>' . esc_html__('Science Q&A', 'epfl-scienceqa') . '</h3>';
		$html .= '  <div class="scienceqa-image">';
		$html .= '    <img src="' . esc_url( $scienceqa->image ) . '">';
		$html .= '  </div>';
		$html .= '  <p>' . sanitize_text_field( $scienceqa->question ) . '</p>';
		$html .= '  <form action="//qi.epfl.ch/' . $lang . '/question/show/' . esc_attr( $scienceqa->id ). '/" method="POST">';
		foreach ( $scienceqa->answers as $answerId => $answer ) {
			$html .= '  <div>';
			$html .= '    <input type="radio" name="poll[choice]" value="' . esc_attr( $answerId ). '" id="choice' .esc_attr( $answerId ) . '">';
			$html .= '    <label type="radio" for="choice' .esc_attr( $answerId ) . '">' . sanitize_text_field( $answer ) . '</label>';
			$html .= '  </div>';
		}
		$html .= '    <button type="submit">' . esc_html__('Vote', 'epfl-scienceqa') . '</button>';
		$html .= '  </form>';
		$html .= '</div>';

		return $html;
	}
}

?>
