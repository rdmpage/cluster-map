<?php

error_reporting(E_ALL);


require_once(dirname(__FILE__) . '/cluster_map.php');


function main()
{
	$display_form = true;	

	// Handle file upload
	if (isset($_FILES['uploadedfile']))
	{
		$display_form = false;

		
		if ($_FILES["uploadedfile"]["error"] > 0)
		{
			echo "Return Code: " . $_FILES["uploadedfile"]["error"];
		}
		else
		{
			$download = false;
			if (isset($_POST['download']) && ($_POST['download'] == 'download'))
			{
				$download = true;
			}
		
			$id = uniqid();
			$filename = "tmp/" . $id;
			move_uploaded_file($_FILES["uploadedfile"]["tmp_name"], $filename);
			
			//echo $filename;
			
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
			
			//print_r($c->sets);
			$c->create_graph();
			$dot = $c->graph2dot();
			
			$c->graph2svg($id);
			$c->add_hexagons();
			
			echo '	<html>
        <head>
            <meta charset="utf-8"/>
			<style type="text/css">
			  body {
				margin: 20px;
				font-family:sans-serif;
			  }
			</style>
            <title>Cluster Map</title>
        </head>
		<body>
		<h1>Cluster Map</h1>';
		
		echo '<h2>Diagram</h2>';
		echo $c->svg;
		
		echo '<h2>Overlapping clusters</h2>';
		echo '<ul>';
		foreach ($c->sets as $set_name => $set)
		{
			if (preg_match('/,/', $set_name))
			{
				echo '<li>' . $set_name;
				echo '<ul>';
				foreach ($set as $s)
				{
					echo '<li>' . $s . '</li>';
				}
				echo '</ul>';
				echo '</li>';
			}
		}
		echo '</ul>';
		
		echo '
		</body>
		</html>';
			


		}
	}
	
	if ($display_form)
	{
$html = <<<EOT
<!DOCTYPE html>
	<html>
        <head>
            <meta charset="utf-8"/>
			<style type="text/css">
			  body {
				margin: 20px;
				font-family:sans-serif;
			  }
			</style>
            <title>Cluster Map</title>
        </head>
		<body>
			<h1>Cluster Map</h1>
			
			<p>Upload a file describing the clusters. File should be tab-delimited text, 
			with each line consisting of a cluster label, a "tab", then the item. For example:</p>
			
<pre>
cluster_1 a
cluster_1 b
cluster_2 b
cluster_2 c			
</pre>
			
			
			
			<form enctype="multipart/form-data" action="index.php" method="POST">
				Choose a file to upload: <input name="uploadedfile" type="file" /><br />
				<br />
				
				<input type="submit" value="Upload File" /><br />
			</form>
		</body>
	</html>
EOT;

echo $html;

	}
}

main();

?>