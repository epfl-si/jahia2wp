<?php

Class PeopleRender
{
   /**
     * Build HTML.
     *
     * @param $items: response of API.
     */  
	public static function build_html($items, $ws): string
	{
		switch($ws)
		{
			case "wsgetpeople":
				return PeopleRender::build_html_ws($items);
			case "getProfiles":
				return PeopleRender::build_html_template($items);		
		}
	}
	
	/**
	 * The renderer used by the "wsgetpeople" web service.
	 */
	private static function build_html_ws($items)
	{
		$html = "";
		
		foreach ($items as $sciper => $data)
		{
			$html .= "<div><strong>$data->nom</strong> $data->prenom</div>";
		}
		
		return $html;	
	}
	
   /**
	 * The renderer used by the "getProfiles" web service.
	 */
	private static function build_html_template($items)
	{
		ob_start();
		var_dump($items);
		$debug = ob_get_clean();
		
		return $debug;	
	}
}

?>