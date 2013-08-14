<?php

// Create cluster maps

// requires Graphviz and a writable temporary folder

$config['graphviz']  = '/Applications/Graphviz/Graphviz.app/Contents/MacOS';
$config['application']  = 'dot';
//$config['application']  = 'circo';


require_once(dirname(__FILE__) . '/hexagon_packer.php');

//--------------------------------------------------------------------------------------------------
// Class to create cluster map
class ClusterMap
{
	var $clusters 	= array();
	var $sets 		= array();
	var $union 		= array();
	var $nodes 		= array();
	var $edges 		= array();
	
	var $base_filename = '';
	var $dot_filename = '';
	var $svg_filename = '';
	
	var $svg = '';
	
	//----------------------------------------------------------------------------------------------
	function __construct($clusters)
	{	
		$this->clusters = $clusters;
		
		foreach ($this->clusters as $c)
		{
			$this->union = array_merge($this->union, $c);
		}
		$this->union = array_unique($this->union);
		$this->sets = array();
	}
	
	//----------------------------------------------------------------------------------------------
	function set_label($set)
	{
		$label = join(',', $set);
		//$label = md5($label);
		return $label;
	}
	
	//----------------------------------------------------------------------------------------------
	// Allocate elements to sets
	function create_sets()
	{
		foreach ($this->union as $element)
		{
			$s = array();
			foreach ($this->clusters as $k => $c)
			{
				if (in_array($element, $c))
				{
					$s[] = $k;  
				}
			}
			$label = $this->set_label($s);			
			$this->sets[$label][] = $element;
		}	
	}

	//----------------------------------------------------------------------------------------------
	function create_graph()
	{
		$this->nodes = array();
		
		$label2nodes = array();
	
		$count = 1;
	
		// The label for each cluster is a node in the graph
		foreach ($this->clusters as $cluster_name => $cluster)
		{
			$node = new stdclass;
			$node->id = 'node' . $count;
			$node->label = $cluster_name;
			
			$this->nodes[] = $node;
			
			$label2nodes[$cluster_name] = $node->id;
			$count++;
		}
		
		//print_r($this->sets);
		
		// Each set of cluster elements is a node
		foreach ($this->sets as $set_name => $set)
		{
			$node = new stdclass;
			$node->id = 'node' . $count;
			$node->label = $this->set_label($set);
			$node->count = count($set);
			
			// How many rings in the hexagon packing do we need
			// to accommodate this number of circles?
			$k = 0;
			$j = 1;
			do
			{
				$k += $j * 6;
				$j++;
			}
			while ($k < $node->count);
			$node->diameter =  2 * ($j-1) + 1;
			
			$this->nodes[] = $node;
			
			$label2nodes[$node->label] = $node->id;
			$count++;
		}

		//print_r($this->nodes);
		
		// edges
		$this->edges = array();
		foreach ($this->clusters as $cluster_name => $cluster)
		{
			foreach ($this->sets as $set_name => $set)
			{
				$intersection = array_intersect($cluster, $set);
				if (count($intersection) != 0)
				{
					$label = $this->set_label($set);
					$this->edges[] = array($label2nodes[$cluster_name], $label2nodes[$label]);
				}
			}
		}		
		
		//print_r($this->edges);
		

		
		
	}
	
	//----------------------------------------------------------------------------------------------
	function graph2dot()
	{
		$dot = "graph G {\n";

		foreach ($this->nodes as $node)
		{
			// Note arbitray adjustment of circle width. Need to investigate this properly...
			if (isset($node->diameter))
			{
				$dot .= $node->id . ' [shape=circle,fillcolor="yellow",style=filled,label="",fixedsize=true,width="' . $node->diameter/3 . '"]' . ";\n";
			}
			else
			{
				$dot .= $node->id . ' [label="' . addcslashes($node->label, '"') . '"]' . ";\n";
			}
		}
		
		foreach ($this->edges as $e)
		{
			$dot .=  '"' . addcslashes($e[0], '"') . '" -- "' . addcslashes($e[1], '"') . '"' . ";\n";
		}		
		
		$dot .= "}\n";
		
		//echo $dot;
		
		return $dot;
	}
	
	//----------------------------------------------------------------------------------------------
	function graph2svg($basename = '')
	{
		global $config;
		
		$dot = $this->graph2dot();
		
		if ($basename != '')
		{
			$this->base_filename = dirname(__FILE__) . '/tmp/' . $basename;		
		}
		else
		{
			$this->base_filename = dirname(__FILE__) . '/tmp/' . uniqid();
		}
		$this->dot_filename = $this->base_filename . '.dot';
		$this->svg_filename = $this->base_filename . '.svg';

		file_put_contents($this->dot_filename, $dot);
		
		$path = $config['graphviz'];
		$command = $path . '/' . $config['application'] . ' ' . $this->dot_filename . ' -Tsvg -o ' . $this->svg_filename;
		//echo $command . "\n";
		system($command);	
		
		return $this->svg_filename;
	}
	
	//----------------------------------------------------------------------------------------------
	function add_hexagons()
	{
		$xml = file_get_contents($this->svg_filename);

		$dom= new DOMDocument;
		$dom->loadXML($xml);
		$xpath = new DOMXPath($dom);
		
		$xpath->registerNamespace("svg", 	"http://www.w3.org/2000/svg");
		
		foreach ($this->nodes as $n)
		{
			if (isset($n->diameter))
			{
				$x = 0;
				$y = 0;
				
				$nodeCollection = $xpath->query ('//svg:g[@id="' . $n->id . '"]/svg:ellipse/@cx');
				foreach($nodeCollection as $node)
				{
					$x = $node->firstChild->nodeValue;
				}
				
				$nodeCollection = $xpath->query ('//svg:g[@id="' . $n->id . '"]/svg:ellipse/@cy');
				foreach($nodeCollection as $node)
				{
					$y = $node->firstChild->nodeValue;
				}
				
				// If we've found the node, generate SVG for hexagonal packing, and insert into DOM
				if ($x != 0 && $y != 0)
				{
					// Create packing for this node
					$h = new HexagonPacker($n->count);
					$svg = $h->toSvg();
					
					//echo $svg;
					
					$nodeCollection = $xpath->query ('//svg:g[@id="' . $n->id . '"]');
					foreach($nodeCollection as $node)
					{
						$g = $dom->createElement('g');
						
						// translate content to centre of node
						$g->setAttribute('transform', "translate($x,$y)");
						
						// insert SVG
	   					$fragment = $dom->createDocumentFragment();
						$fragment->appendXML($svg);
						$g->appendChild($fragment);
						$node->appendChild($g);
					}
				}
			}
		}
		
		$this->svg = $dom->saveXML();
		
		file_put_contents($this->svg_filename, $svg);
	}

}
		
// tests

if (0)
{

	$filename = 'clusters.txt';
	
	$file = @fopen($filename, "r") or die("couldn't open $filename");
	
	$file_handle = fopen($filename, "r");
	
	$clusters = array();
	
	while (!feof($file_handle)) 
	{
		$line = trim(fgets($file_handle));
		
		$parts = explode("\t", $line);
		
		$cluster_name = $parts[0];
		$member = $parts[1];
		
		if (!isset($clusters[$cluster_name]))
		{
			$clusters[$cluster_name] =  array();
		}
		$clusters[$cluster_name][] = $member;
	}
	

	$c = new ClusterMap($clusters);
	
	$c->create_sets();
	
	print_r($c->sets);
	$c->create_graph();
	$dot = $c->graph2dot();
	
	$c->graph2svg('graph');
	$c->add_hexagons();


}


if (0)
{

/*
$clusters = array();
$clusters['one'] 	= array('a', 'b', 'c', 'g');
$clusters['two'] 	= array('b', 'c', 'd');
$clusters['three'] 	= array('d', 'e');
$clusters['four'] 	= array('a', 'b', 'c', 'e', 'f');
*/
/*
$clusters = array();
$clusters['gbif'] = array('atra','calodera','nuchalis','olivacea','papuensis','psammophis','reticulata','rufescens','simplex','torquata','vestigiatus');
$clusters['reptile'] = array('angusticeps','calodera','flagellatio','olivacea','papuensis','psammophis','quaesitor','rimicola','rufescens','shinei','simplex','torquata','vestigiata');
$clusters['ncbi'] = array('papuensis','psammophis','vestigiata');
*/
$clusters = array();
$clusters['Catalogue of Life']=array(
'Demansia atra',
'Demansia calodera',
'Demansia olivacea',
'Demansia papuensis',
'Demansia psammophis',
'Demansia rufescens',
'Demansia simplex',
'Demansia torquata');

$clusters['NCBI']=array('Demansia papuensis',
'Demansia psammophis',
'Demansia vestigiata');

$clusters['The Reptile Database']=array('Demansia angusticeps',
'Demansia calodera',
'Demansia flagellatio',
'Demansia olivacea',
'Demansia papuensis',
'Demansia psammophis',
'Demansia quaesitor',
'Demansia rimicola',
'Demansia rufescens',
'Demansia shinei',
'Demansia simplex',
'Demansia torquata',
'Demansia vestigiata');

$clusters['ITIS']=array('Demansia atra',
'Demansia calodera',
'Demansia olivacea',
'Demansia papuensis',
'Demansia psammophis',
'Demansia reticulata',
'Demansia rufescens',
'Demansia simplex',
'Demansia torquata');

/*
$clusters = array();
$clusters['IUCN'] 	= array(
'Leptoperla',
'Riekoperla');


$clusters['Species2000'] 	= array(
'Acroperla',
'Alfonsoperla',
'Andiperla',
'Andiperlodes',
'Antarctoperla',
'Apteryoperla',
'Araucanioperla',
'Aubertoperla',
'Aucklandobius',
'Cardioperla',
'Ceratoperla',
'Chilenoperla',
'Claudioperla',
'Dinotoperla',
'Dundundra',
'Eunotoperla',
'Falklandoperla',
'Gripopteryx',
'Guaranyperla',
'Holcoperla',
'Illiesoperla',
'Kirrama',
'Leptoperla',
'Limnoperla',
'Megaleptoperla',
'Megandiperla',
'Neboissoperla',
'Neopentura',
'Nescioperla',
'Nesoperla',
'Newmanoperla',
'Notoperla',
'Notoperlopsis',
'Nydyse',
'Paragripopteryx',
'Pelurgoperla',
'Plegoperla',
'Potamoperla',
'Rakiuraperla',
'Rhithroperla',
'Riekoperla',
'Rungaperla',
'Senzilloides',
'Taraperla',
'Teutoperla',
'Trinotoperla',
'Tupiperla',
'Uncicauda',
'Vesicaperla',
'Zelandobius',
'Zelandoperla');


$clusters['NCBI'] 	= array(
'Antarctoperlinae',
'Gripopteryginae',
'Leptoperlinae',
'Paragripopteryginae',
'Zelandoperlinae');

$clusters['GBIF'] 	= array(
'Abranchioperla',
'Acroperla',
'Aldia',
'Alfonsoperla',
'Andiperla',
'Andiperlodes',
'Antarctoperla',
'Apteryoperla',
'Araucanioperla',
'Aubertoperla',
'Aucklandobius',
'Cardioperla',
'Cardioperlisca',
'Ceratoperla',
'Chilenoperla',
'Claudioperla',
'Dinotoperla',
'Dundundra',
'Eodinotoperla',
'Eunotoperla',
'Falklandoperla',
'Griphopteryx',
'Gripoptera',
'Gripopteryx',
'Guaranyperla',
'Holcoperla',
'Illiesoperla',
'Jewettoperla',
'Kirrama',
'Klapopteryx',
'Leptoperla',
'Limnoperla',
'Megaleptoperla',
'Megandiperla',
'Neboissoperla',
'Neopentura',
'Nescioperla',
'Nesoperla',
'Newmanoperla',
'Notoperla',
'Notoperlopsis',
'Nydyse',
'Paragripopteryx',
'Paranotoperla',
'Pehuenioperla',
'Pelurgoperla',
'Plegoperla',
'Potamoperla',
'Rakiuraperla',
'Rhithroperla',
'Riekoperla',
'Rungaperla',
'Senzilla',
'Senzilloides',
'Taraperla',
'Teutoperla',
'Trinotoperla',
'Tupiperla',
'Uncicauda',
'Vesicaperla',
'Zelandobius',
'Zelandoperla');

*/

$clusters=array();
$clusters['Glauconycteris'] = array(
'alboguttata J. A. Allen, 1917',
'alboguttatus',
'argentata Dobson, 1875',
'beatrix Thomas, 1901',
'curryae Eger & Schlitter, 2001',
'egeria Thomas, 1913',
'gleni Peterson & Smith, 1973',
'humeralis J. A. Allen, 1917',
'kenyacola Peterson, 1982',
'machadoi Hayman, 1963',
'poensis Gray, 1842',
'superba Hayman, 1939',
'variegata Tomes, 1861'
);

$clusters['Chalinolobus'] = array(
'alboguttatus',
'argentatus Dobson, 1875',
'beatrix Thomas, 1901',
'dwyeri Ryan, 1966',
'egeria Thomas, 1913',
'gleni Peterson & Smith, 1973',
'gouldii Gray, 1841',
'kenyacola Peterson, 1982',
'morio Gray, 1841',
'neocaledonicus Revilliod, 1914',
'nigrogriseus Gould, 1852',
'picatus Gould, 1852',
'poensis Gray, 1842',
'superbus Hayman, 1939',
'tuberculatus Forster, 1844',
'variegatus Tomes, 1861'
);

// cleaned
$clusters=array();
$clusters['Glauconycteris'] = array(
'alboguttata',
'alboguttatus',
'argentata Dobson, 1875',
'beatrix Thomas, 1901',
'curryae Eger & Schlitter, 2001',
'egeria Thomas, 1913',
'gleni Peterson & Smith, 1973',
'humeralis J. A. Allen, 1917',
'kenyacola Peterson, 1982',
'machadoi Hayman, 1963',
'poensis Gray, 1842',
'superba Hayman, 1939',
'variegata Tomes, 1861'
);

$clusters['Chalinolobus'] = array(
'alboguttatus',
'argentata Dobson, 1875',
'beatrix Thomas, 1901',
'dwyeri Ryan, 1966',
'egeria Thomas, 1913',
'gleni Peterson & Smith, 1973',
'gouldii Gray, 1841',
'kenyacola Peterson, 1982',
'morio Gray, 1841',
'neocaledonicus Revilliod, 1914',
'nigrogriseus Gould, 1852',
'picatus Gould, 1852',
'poensis Gray, 1842',
'superba Hayman, 1939',
'tuberculatus Forster, 1844',
'variegata Tomes, 1861'
);

$c = new ClusterMap($clusters);

$c->create_sets();

print_r($c->sets);
$c->create_graph();
$dot = $c->graph2dot();

$c->graph2svg('Bats2');
$c->add_hexagons();


}
		
			
?>