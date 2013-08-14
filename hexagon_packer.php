<?php

//--------------------------------------------------------------------------------------------------
// Class to pack circles into a hexagon
class HexagonPacker
{
	var $x;
	var $y;
	var $origin_x;
	var $origin_y;
	var $radius;
	var $num_circles;
	var $rings;
	
	//----------------------------------------------------------------------------------------------
	function __construct($num_circles)
	{
		$this->origin_x = 0;
		$this->origin_y = 0;
		$this->radius = 10;
		$this->num_circles = $num_circles;
		$this->rings = $this->numRings($this->num_circles);
	}
	
	//----------------------------------------------------------------------------------------------
	function numRings($n)
	{
		return ceil(($n - 1)/6);
	}
	
	//----------------------------------------------------------------------------------------------
	function getWidth($n)
	{
		return 2 * ($n - 1) + 1;
	}
	

	//----------------------------------------------------------------------------------------------
	function toSvg($standalone = false)
	{
		$xml ='';
		
		if ($standalone)
		{
			$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<svg xmlns:xlink="http://www.w3.org/1999/xlink" 
			xmlns="http://www.w3.org/2000/svg" 
			width="200px" height="200px">
			 <style type="text/css">
			<![CDATA[
			circle {
				stroke:black;
				fill:white;
			}
			]]>
			</style>
			';
		}

		$total = 1;
				
		// draw first one
		$this->x = $this->origin_x;
		$this->y = $this->origin_y;
		$xml .= '   <circle cx="' . $this->x . '" cy="' . $this->y . '" r="' . $this->radius . '" />' . "\n";

		// draw rings
		
		for ($i = 1; $i <= $this->rings; $i++)
		{
			$num_in_ring = $i * 6;
			$count = 0;
			
			// First circle
			$this->x = $i * 2 * $this->radius;
			$this->y = 0;
			
			$this->x += $this->origin_x;
			$this->y += $this->origin_y;
			
			$d = sqrt(3);
			
			$direction = array(
				array(-1, -1),
				array(-2, 0),
				array(-1, +1),
				array(1, +1),
				array(2, 0),
				array(1, -1)
				);
				
			$path = 0;
			while (($count < $num_in_ring) && ($total < $this->num_circles))
			{
				$j = 0;
				while (($j < $i) && ($total < $this->num_circles))
				{
					$this->x += $direction[$path][0] * $this->radius;
					$this->y += $direction[$path][1] * $d * $this->radius;
					$j++;
					$count++;
					$total++;

					$xml .= '   <circle cx="' . $this->x . '" cy="' . $this->y . '" r="' . $this->radius . '" />' . "\n";					
				}
				$path++;
			}
		}
		
		if ($standalone)
		{		
			$xml .= '</svg>' . "\n";
		}
		
		return $xml;

		
	}


}

if (0)
{
	// test

	$h = new HexagonPacker(22);

	echo $h->toSvg();
	
}





?>