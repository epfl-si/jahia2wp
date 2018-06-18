<?php

Class PeopleRender
{
   /**
     * Build HTML.
     *
     * @param $items: response of API.
     */  
	public static function epfl_people_build_html($items): string
	{
		$html = "";
		
		foreach ($items as $sciper => $data)
		{
			$html .= "<div><strong>$data->nom</strong> $data->prenom</div>";
		}
		
		return $html;
	}
}

?>