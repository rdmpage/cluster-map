cluster-map
===========

Create cluster maps to display overlapping sets. 

"Cluster maps" were described by Fluit et al., see [Aduna Cluster Map](http://www.aduna-software.com/technology/clustermap) for an implementation:

Fluit, C., Sabou, M., & Harmelen, F. (2006). Visualizing the Semantic Web. (V. Geroimenko & C. Chen, Eds.) (pp. 45–58). Springer Science + Business Media. [doi:10.1007/1-84628-290-X_3](http://dx.doi.org/10.1007/1-84628-290-X_3) (see also http://www.cs.vu.nl/~frankh/abstracts/VSW05.html).

![Cluster](https://raw.github.com/rdmpage/cluster-map/master/diagrams/cluster-map-details.png)

Cluster maps can be thought of as fancy Venn Diagrams, in that they can be used to depict the overlap between sets of objects. The diagram is a graph with two kinds of nodes. One represents categories (in the example above, file formats and search terms), the other represents sets of objects that occur in one or more categories (in the example above, these are files that match the search terms "rdf" and "aperture").

The code in this repository requires [Graphviz](http://www.graphviz.org/) to be installed. Set the path to the program "dot" in $config['graphviz'] in the cluster_map.php (on my Mac this is /Applications/Graphviz/Graphviz.app/Contents/MacOS).

You also need to make the tmp folder writable by the web server.

Under the hood the code reads a simple text file consisting of tab-delimited rows, where the first column is the name of a cluster, the second is the name of the element. For example:

	Molossops	aequatorianus
	Chaerephon	aloysiisabaudiae
	Tadarida	aloysiisabaudiae
	Chaerephon	ansorgei
	Tadarida	ansorgei
	Molossus	ater

This example lists genus (cluster) and species (member) names for some bats generated from the GBIF taxonomy using this SQL:

	SELECT DISTINCT genus, specificEpithet 
		FROM taxon 
		WHERE family='Molossidae Gervais, 1856' 
			AND taxonomicStatus='accepted' 
			AND specificEpithet <> '' 
		ORDER BY specificEpithet;

The complete example file is https://github.com/rdmpage/cluster-map/blob/master/examples/clusters.txt

Part of the resulting cluster map looks like this:

![Bats](https://raw.github.com/rdmpage/cluster-map/master/diagrams/bats.png)
