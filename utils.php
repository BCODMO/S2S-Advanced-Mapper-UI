<?php 

///
/// Global Variables
///

//namespaces
$bcodmo = "http://ocean-data.org/schema/";
$rdfs = "http://www.w3.org/2000/01/rdf-schema#";
$foaf = "http://xmlns.com/foaf/0.1/";
$time = "http://www.w3.org/2006/time#";
$skos = "http://www.w3.org/2004/02/skos/core#";
$xsd = "http://www.w3.org/2001/XMLSchema#";
$owl = "http://www.w3.org/2002/07/owl#";
$dcterms = "http://purl.org/dc/terms/";

//endpoint info
$endpoint = "http://lod.bco-dmo.org/sparql/";
$param = "query";
$graphParam = "default-graph-uri";
$graph = "http://www.bco-dmo.org/";
$seavoxGraph = "http://vocab.ndg.nerc.ac.uk/";
///
/// Utility Functions
///

/**
 * Builds a basic SPARQL query
 * @param string $query SPARQL query string
 * @return array an array of associative arrays containing the bindings
 */
function sparqlSelect($query) {
	global $endpoint;
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $endpoint);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, getQueryData($query));
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$content = curl_exec($curl);
	curl_close($curl);
	$xml = simplexml_load_string($content);
	$results = array();
	foreach ($xml->results->result as $result) {
		$arr = array();
		foreach($result->binding as $binding) {
			$name = $binding['name']; 
			$arr["$name"] = (string) $binding->children();
		}
		array_push($results,$arr);
	}
	return $results;
}

/**
 * Builds a basic SPARQL query
 * @param string $query SPARQL query string
 * @param string $suffix other options to append to request
 * @return string a URL to make a SPARQL query request
 */
function getQueryData($query, $suffix = '') {
	global $param,$graphParam,$graph;
	return $param.'='.urlencode($query);//.'&'.$graphParam.'='.urlencode($graph).$suffix;
}

///
/// Query Components
///

function getDeployments($box) {
  	 global $endpoint,$param,$bcodmo,$rdfs;
	 
	 $query = "PREFIX bcodmo: <$bcodmo> " .
	 	"PREFIX rdfs: <$rdfs> " .
		"SELECT DISTINCT ?name ?id WHERE { " .
		"?d a ?type . FILTER(?type IN (bcodmo:Deployment, bcodmo:Cruise)) . " .
		"?d rdfs:label ?name . " . 
		"?d dcterms:identifier ?id }";
	$results = sparqlSelect($query);
 	$names = array();
	foreach ($results as $i => $info) $names[urlencode($info['name'])] = $info['id'];
	$deployments = implode(",",array_keys($names));
	$url = "http://mapservice.bco-dmo.org/maps-bin/global/do_map";
	$data = "SERVICE=WFS&VERSION=1.0.0&SRS=EPSG:4269&REQUEST=GetFeature&FEATURE_COUNT=50&TYPENAME=linestring_layer,multipoint_layer&pg=dummy,";
	$data .= $deployments;
	$data .= "&BBOX=".$box[0].','.$box[1].','.$box[2].','.$box[3];
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$content = curl_exec($curl);
	//var_dump($content);
	curl_close($curl);
	$arr = explode("ms:pk",$content);
	$ids = array();
	for ($i = 1; $i < count($arr); $i += 2) {
	    $n = substr($arr[$i], 1, strlen($arr[$i]) - 3);
	    $ids[] = $names[urlencode($n)];
	}
	return $ids;
}

function getPrefixes() {
	global $foaf,$rdfs,$bcodmo,$time,$xsd,$skos,$owl,$dcterms;
	$arr = array(
		'foaf' => $foaf,'rdfs' => $rdfs,'bcodmo' => $bcodmo,'time' => $time,
		'xsd' => $xsd,'skos' => $skos, 'owl' => $owl, 'dcterms' => $dcterms
	);
	$output = '';
	foreach ($arr as $prefix => $uri) $output .= "PREFIX $prefix: <$uri> ";
	return $output;
}

function getQueryHeader($type) {
	global $graph;
	$header = "";
	//$header = 'DEFINE input:inference "http://escience.rpi.edu/ontology/BCO-DMO/bcodmo/3/0/rdfs"';
	$header .= getPrefixes();
	$header .= 'SELECT DISTINCT ';
	switch ($type) {
    case "programs":
    case "projects":
      $header .= '(fn:concat(?title_label," (",?acronym,")") as ?label) ?id (count(DISTINCT ?dataset) AS ?count)';
      break;
		case "datasets":
		    $header .= '?dcname ?dcid ?pid ?pname ?dcurl ?depname ?depid ?dsid ?dsurl';
			break;
		case "platforms":
		    $header .= '(fn:concat(?title_label," ",?base_label) as ?label) ?id (count(DISTINCT ?dataset) AS ?count)';
		    break;
		case "mapper":
		    $header .= '?deploymentId ?datasetId ?deploymentName ?datasetName ?datasetUrl';
		        break;
		case "count":
			$header .= '(count(DISTINCT ?dataset) AS ?count)';
			break;
		case "instcats":
		     $header .= '?label ?id ?parent (count(DISTINCT ?dataset) AS ?count)';
		     break;
		case "paramcats":
		     $header .= '?label ?id ?parent (count(DISTINCT ?dataset) AS ?count)';
		     break;
		default:
			$header .= '?label ?id (count(DISTINCT ?dataset) AS ?count)';
	}
	return $header . ' WHERE { GRAPH <' . $graph . '> { ';
}

function getQueryConstraintBody($box, $begin, $end, $insts, $instcats, $ppl, $prjs, $progs, $deps, $awards, $params, $paramcats, $platforms) {
	$body = '';
	if ($deps) $body .= deploymentConstraint($deps);
	else if ($box) $body .= geoboxConstraint($box);
	if ($awards) $body .= awardConstraint($awards);
	if ($insts) $body .= instrumentConstraint($insts);
	if ($instcats) $body .= instrumentCategoryConstraint($instcats);
	if ($params) $body .= parameterConstraint($params);
	if ($paramcats) $body .= parameterCategoryConstraint($paramcats);
	if ($ppl) $body .= personConstraint($ppl);
	if ($prjs) $body .= projectConstraint($prjs);
	if ($progs) $body .= programConstraint($progs);
	if ($platforms) $body .= platformConstraint($platforms);
	return $body;
}

function getQuerySelectBody($type, $query = '') {
	global $seavoxGraph;
	$body = '';
	switch ($type) {
		case 'instruments':
			$body .= ' ?collection bcodmo:fromInstrument ?instrument . ' .
			      	'?dataset bcodmo:fromCollection ?collection . ' .
				'?instrument rdfs:label ?label . ' .
				'?instrument dcterms:identifier ?id . ';
			break;
		case 'instcats':
			$body .= ' ?collection bcodmo:fromInstrument ?instrument . ' .
			        '?dataset bcodmo:fromCollection	?collection . ' .
				'{ ?instrument a ?id . ' .
				'GRAPH <' . $seavoxGraph . '> { ' .
				'?id skos:prefLabel ?label . ' .
				' FILTER(LANGMATCHES(LANG(?label), "en")) ' .
				'OPTIONAL { ?parent skos:narrower ?id . ' .
         '?scheme skos:member ?parent ' .
          'FILTER (?scheme = <http://vocab.nerc.ac.uk/collection/L05/current/> || ?scheme = <http://vocab.nerc.ac.uk/collection/L22/current/>) ' .
        '} ' .
				'} } UNION { ' .
				'?instrument a ?subInstCat . ' .
				'GRAPH <' . $seavoxGraph . '> { ' .
				' ?id skos:narrower ?subInstCat OPTION(transitive) . ' .
				' ?id skos:prefLabel ?label . ' .
				' FILTER(LANGMATCHES(LANG(?label), "en")) ' .
				'OPTIONAL { ?parent skos:narrower ?id . ' .
        '?scheme skos:member ?parent . ' .
        'FILTER (?scheme = <http://vocab.nerc.ac.uk/collection/L05/current/> || ?scheme = <http://vocab.nerc.ac.uk/collection/L22/current/>) ' .
        '}  ' .
				'} } ';
			break;
		case 'paramcats':
			$body .= ' ?collection bcodmo:hasParameter ?parameter . ' .
			        '?dataset bcodmo:fromCollection	?collection . ' .
				'{ ?parameter a ?id . ' .
				'GRAPH <' . $seavoxGraph .'> { ' .
				'?id skos:prefLabel ?label . ' .
				' OPTIONAL { ?parent skos:narrower ?id . ' .
				    '?scheme skos:member ?parent . ' . 
             'FILTER (?scheme = <http://vocab.nerc.ac.uk/collection/P01/current/> || ?scheme = <http://vocab.nerc.ac.uk/collection/P02/current/>  || ?scheme = <http://vocab.nerc.ac.uk/collection/P03/current/>) } ' .
				' FILTER(LANGMATCHES(LANG(?label), "en")) ' .
				'} } UNION { ?parameter a ?paramcat . ' .
				'GRAPH <' . $seavoxGraph . '> { ' .
				' ?id skos:narrower ?paramcat . ' .
				' ?id skos:prefLabel ?label . ' .
				' FILTER(LANGMATCHES(LANG(?label), "en")) ' .
				' OPTIONAL { ?parent skos:narrower ?id . ' .
				    '?scheme skos:member ?parent . ' .
             'FILTER (?scheme = <http://vocab.nerc.ac.uk/collection/P01/current/> || ?scheme = <http://vocab.nerc.ac.uk/collection/P02/current/>  || ?scheme = <http://vocab.nerc.ac.uk/collection/P03/current/>) } ' . 
				'} }';
			break;
		case 'deployments':
			$body .= '?dataset bcodmo:fromCollection ?collection . ' .
			        '?dataset bcodmo:fromDeployment ?deployment . ' .
				'?deployment rdfs:label ?label . ' .
				'?deployment dcterms:identifier ?id . ';
				
				$role_q = 'UNION { ?dataset bcodmo:hasAgentWithRole ?role . ?dataset bcodmo:fromCollection ?collection . }';
				if( $query && substr($query, -strlen($role_q)) === $role_q){
				  //ends with dataset, dataset-deployment role check
				  $body = 'UNION { ?deployment bcodmo:hasAgentWithRole ?role }' . $body;
				}
			break;
		case 'people':
			$body .= '?person bcodmo:hasRole ?role . ' .
				'?role bcodmo:hasRoleWeight ?weight . ' .
				'FILTER( ?weight <= 10 ) ' .
				'?person rdfs:label ?label . ' .
				'?person dcterms:identifier ?id . ' .
				'{ ?collection bcodmo:hasAgentWithRole ?role . ' .
          '?dataset bcodmo:fromCollection ?collection . ' .
        '} UNION { ' .
          '?dataset bcodmo:hasAgentWithRole ?role . ' .
          '?dataset bcodmo:fromCollection ?collection . ' .
        '}';
        if( $query && strpos($query, '?deployment') !== FALSE){
         $body .= 'UNION { ' .
           '?deployment bcodmo:hasAgentWithRole ?role . ' .
          '}';
        }
			break;
		case 'projects':
			$body .= '?project rdf:type bcodmo:Project . ' .
			         '?project bcodmo:hasDataset ?collection . ' .
			      	'?dataset bcodmo:fromCollection ?collection . ' .
				'?project rdfs:label ?title_label . ' .
        '?project bcodmo:hasAcronym ?acronym . ' .
				'?project dcterms:identifier ?id . ';
			break;
		case 'programs':
			$body .= ' ?project bcodmo:hasDataset ?collection . ' .
			        '?dataset bcodmo:fromCollection ?collection . ' .
				'?program bcodmo:hasProject ?project . ' .
				'?program rdfs:label ?title_label . ' .
        '?program bcodmo:hasAcronym ?acronym . ' .
				'?program dcterms:identifier ?id . ';
			break;
		case 'awards':
			$body .= ' ?collection bcodmo:hasAward ?award . ' .
			      	'?award dcterms:identifier ?id . ' .
			      	'?dataset bcodmo:fromCollection	?collection . ' .
				'?award bcodmo:hasAwardNumber ?label . ';
			break;
		case 'parameters':
			$body .= ' ?collection bcodmo:hasParameter ?parameter . ' .
			        '?dataset bcodmo:fromCollection	?collection . ' .
			      	'?parameter dcterms:identifier ?id . ' .
				'?parameter rdfs:label ?label . ';
			break;
		case 'platforms':
			$body .= ' ?dataset bcodmo:fromCollection ?collection . ' .
			  '?dataset bcodmo:fromDeployment ?deployment . ' .
				'?deployment bcodmo:ofPlatform ?platform . ' .
				'?platform rdfs:label ?base_label . ' .
				'?platform bcodmo:hasPlatformTitle [ skos:prefLabel ?title_label ] . ' .
				'?platform dcterms:identifier ?id . ';
			break;
		case 'datasets':
			$body .= ' ?project bcodmo:hasDataset ?collection . ' .
				'?project dcterms:identifier ?pid . ' .
				'?project rdfs:label ?pname . ' .
				'?dataset bcodmo:fromCollection ?collection . ' .
				'?dataset bcodmo:fromDeployment ?deployment . ' .
				'?collection rdfs:label ?dcname . ' .
				'?collection dcterms:identifier ?dcid . ' .
				'?dataset dcterms:identifier ?dsid . ' .
				'?deployment rdfs:label ?depname . ' .
				'?deployment dcterms:identifier ?depid . ' .
				'OPTIONAL { ?collection bcodmo:hasDatasetURL ?dcurl . }' .
				'OPTIONAL { ?dataset bcodmo:hasDatasetURL ?dsurl . }';
			break;
		case 'mapper':
		        $body .= ' ?dataset bcodmo:fromCollection ?collection . ' .
			        '?collection dcterms:identifier ?datasetId . ' .
				'?collection rdfs:label ?datasetName . ' .
				'OPTIONAL { ?dataset bcodmo:hasDatasetURL ?datasetUrl . }' .
			        '?dataset bcodmo:fromDeployment ?deployment . ' .
				'?deployment dcterms:identifier ?deploymentId . ' .
				'?deployment rdfs:label ?deploymentName . ';
		case 'count':
			$body .= ' ?dataset bcodmo:fromCollection ?collection .';
	}
	return $body;
}

function getQueryFooter($type = null, $limit = null, $offset = 0, $sort = null) {
	$footer = '} }';
	if ($type && $type == 'datasets') {
		if ($limit) $footer .= " LIMIT $limit OFFSET $offset";
		if ($sort) {
			$sortArray = explode(',',$sort);
			$sortLabels = array();
			for ($i = 0; $i < count($sortArray); $i++) {
				if ($sortArray[$i] == '1') {
					$sortLabels[] = "?dcname";
				} else if ($sortArray[$i] == '2') {
					$sortLabels[] = "?pname";
				} else if ($sortArray[$i] == '3') {
					$sortLabels[] = "?depname";
				}
			}
			$footer .= " ORDER BY " . implode(' ', $sortLabels);
		}
	} else if ($type == 'instcats' || $type == 'paramcats') {
	    $footer .= " GROUP BY ?label ?id ?parent";
	} else if ($type == 'platforms') {
	   	$footer .= " GROUP BY ?id ?base_label ?title_label";
	    $footer .= " ORDER BY ?title_label ?base_label";
  } else if ($type == 'programs' || $type == 'projects'){
      $footer .= " GROUP BY ?title_label ?acronym ?id";
	} else if ($type != 'mapper') {
	    $footer .= " GROUP BY ?label ?id";
	}
	return $footer;
}

function awardConstraint($awards) {
	$arr = array();
	for ($i = 0; $i < count($awards); ++$i)
		array_push($arr,'{ ?collection bcodmo:hasAward [ dcterms:identifier "' . $awards[$i] . '"^^xsd:int ] }');
	return implode(' UNION ',$arr) . ' ';
}

function instrumentConstraint($insts) {
	$arr = array();
	for ($i = 0; $i < count($insts); ++$i) {
		array_push($arr,'{ ?instrument a bcodmo:Instrument . ?instrument dcterms:identifier "' . $insts[$i] . '"^^xsd:int . }');
	}
	return implode(' UNION ',$arr) . ' ?collection bcodmo:fromInstrument ?instrument . ';
}

function parameterConstraint($params) {
	$arr = array();
	for ($i = 0; $i < count($params); ++$i) {
		array_push($arr,'{ ?parameter a bcodmo:Parameter . ?parameter dcterms:identifier "' . $params[$i] . '"^^xsd:int . }');
	}
	return implode(' UNION ',$arr) . ' ?collection bcodmo:hasParameter ?parameter . ';
}

function personConstraint($ppl) {
	$arr = array();
	for ($i = 0; $i < count($ppl); ++$i) {
		array_push($arr,'{ ?person a foaf:Person . ?person dcterms:identifier "' . $ppl[$i] . '"^^xsd:int . }');
	}
	return implode(' UNION ',$arr) . ' ?person bcodmo:hasRole ?role . ?role bcodmo:hasRoleWeight ?weight . FILTER ( ?weight <= 10 ) ' . 
	      '{ ?collection bcodmo:hasAgentWithRole ?role . ' .
          '?dataset bcodmo:fromCollection ?collection . ' .
        '} UNION { ' .
          '?dataset bcodmo:hasAgentWithRole ?role . ' .
          '?dataset bcodmo:fromCollection ?collection . ' .
        '}';
}

function projectConstraint($prjs) {
	$arr = array();
	for ($i = 0; $i < count($prjs); ++$i) {
		array_push($arr,'{ ?project a bcodmo:Project . ?project dcterms:identifier "' . $prjs[$i] . '"^^xsd:int . }');
	}
	return implode(' UNION ',$arr) . ' ?project bcodmo:hasDataset ?collection . ';
}

function programConstraint($progs) {
	$arr = array();
	for ($i = 0; $i < count($progs); ++$i) {
		array_push($arr,'{ ?program a bcodmo:Program . ?program dcterms:identifier "' . $progs[$i] . '"^^xsd:int . }');
	}
	return implode(' UNION ',$arr) . ' ?program bcodmo:hasProject ?project . ?project bcodmo:hasDataset ?collection . ';
}

function deploymentConstraint($deps) {
	$arr = array();
	for ($i = 0; $i < count($deps); ++$i) {
	  // don't constrain to Deployment as the type may be Cruise 
	  // something like FILTER (?type = bcodmo:Deployment || ?type = bcdmo:Cruise) may work
		array_push($arr,'{ ?deployment dcterms:identifier "' . $deps[$i] . '"^^xsd:int . }');
	}
	return implode(' UNION ',$arr) . ' ?dataset bcodmo:fromDeployment ?deployment . ?dataset bcodmo:fromCollection ?collection . ';
}

function instrumentCategoryConstraint($seavox) {
	global $seavoxGraph;
	$arr = array();
	for ($i = 0; $i < count($seavox); ++$i)
		array_push($arr,'{ { ?instrument a <' . $seavox[$i] . '> . } UNION { ?instrument a ?subInstCat . GRAPH <' . $seavoxGraph . '> { <' . $seavox[$i] . '> skos:narrower ?subInstCat . } } }');
	return implode(' UNION ',$arr) . '?collection bcodmo:fromInstrument ?instrument . ';
}

function parameterCategoryConstraint($seavox) {
	global $seavoxGraph;
	$a1 = array();
	$a2 = array();
	for ($i = 0; $i < count($seavox); ++$i)
		array_push($a1,'{ <' . $seavox[$i] . '> skos:narrower ?paramcat . }');
	for ($i = 0; $i < count($seavox); ++$i)
		array_push($a2,'{ ?parameter a <' . $seavox[$i] . '> }');
	return '{ ' . implode(' UNION ',$a2) . ' } UNION { ?parameter a ?paramcat . GRAPH <' . $seavoxGraph . '> { ' . implode(' UNION ',$a1) . ' } } ?collection bcodmo:hasParameter ?parameter . ';
}

function platformConstraint($platforms) {
	$arr = array();
	for ($i = 0; $i < count($platforms); ++$i) {
		array_push($arr,'{ ?platform dcterms:identifier "' . $platforms[$i] . '"^^xsd:string . }');
	}
	return implode(' UNION ',$arr) . ' ?deployment bcodmo:ofPlatform ?platform . ?dataset bcodmo:fromDeployment ?deployment . ?dataset bcodmo:fromCollection ?collection . ';
}

function geoboxConstraint($box) {
	$deployments = getDeployments($box);
	if (count($deployments) > 0) return deploymentConstraint($deployments);
	else return deploymentConstraint(array('-9999'));
}

///
/// Services
///

function getResponse($box, $begin, $end, $insts, $instcats, $ppl, $prjs, $progs, $deps, $awards, $params, $paramcats, $platforms, $type, $limit = null, $offset = 0, $sort = null) {
	$query = buildQuery($box, $begin, $end, $insts, $instcats, $ppl, $prjs, $progs, $deps, $awards, $params, $paramcats, $platforms, $type, $limit, $offset, $sort);
//	if ($type == "paramcats") echo $query;
  error_log(preg_replace('/(\n|\t)+/', ' ', $query));
	$results = sparqlSelect($query);
	if ($type == "datasets" || $type == "dataTable")
		$count = getDatasetCount($box, $begin, $end, $insts, $instcats, $ppl, $prjs, $progs, $deps, $awards, $params, $paramcats, $platforms);
	getOutput($results,$type,$limit,$offset,$count);
}

function getDatasetCount($box, $begin, $end, $insts, $instcats, $ppl, $prjs, $progs, $deps, $awards, $params, $paramcats, $platforms)
{
	$query = getQueryHeader('count');
	$query .= getQueryConstraintBody($box, $begin, $end, $insts, $instcats, $ppl, $prjs, $progs, $deps, $awards, $params, $paramcats, $platforms);
	$query .= getQuerySelectBody('count');
	$query .= getQueryFooter();
	$results = sparqlSelect($query);
	$count = 0;
	foreach ($results as $i => $info)
	{
		$count = $info['count'];
	}
	return $count;
}

function addContextLinks(&$results, $type) {
	 $osprey = "http://www.bco-dmo.org/";
	 $base = null;
	 if ($type == 'instruments') {
	    	    $base = $osprey . "instrument/";
	 } else if ($type == 'parameters') {
	   	    $base = $osprey . "parameter/";
	 } else if ($type == 'projects') {
	   	    $base = $osprey . "project/";
	 } else if ($type == 'programs') {
	   	    $base = $osprey . "program/";
	 } else if ($type == 'people') {
	   	    $base = $osprey . "person/";
	 } else if ($type == 'awards') {
	   	    $base = $osprey . "award/";
	 } else if ($type == 'deployments') {
	   	    $base = $osprey . "deployment/";
	 }
	 if ( $base != null ) {
	      	    foreach ( $results as $i => $info ) {
		    	       $results[$i]['context'] = $base . $results[$i]['id'];
		    }
	 }
}

function buildQuery($box, $begin, $end, $insts, $instcats, $ppl, $prjs, $progs, $deps, $awards, $params, $paramcats, $platforms, $type, $limit = null, $offset = 0, $sort = null) {
	$query = getQueryHeader($type);
	$query .= getQueryConstraintBody($box, $begin, $end, $insts, $instcats, $ppl, $prjs, $progs, $deps, $awards, $params, $paramcats, $platforms);
	$query .= getQuerySelectBody($type, $query);
	$query .= getQueryFooter($type, $limit, $offset, $sort);
	//echo $query;
	return $query;
}

function getOutput($results,$type,$limit = 0,$offset = 0,$count = 0) {
	if ($type == 'datasets') {
		header("Access-Control-Allow-Origin: *");
		header("Content-Type: text/html");
		$rcount = "";
		if ($count > 0)
		{
			$rcount = "<div><input type='hidden' name='startIndex' value='$offset'/><input type='hidden' name='itemsPerPage' value='$limit'/><input type='hidden' name='totalResults' value='$count'/></div>";
		}
		$html = $rcount."<table width=\"inherit\" border=\"1\"><tr><th>Dataset</th><th>Project</th><th>Deployment</th><th>Dataset Platform</th></tr>";
		foreach ($results as $i => $info) {
			$dcurl = @$info['dcurl'] ? $info['dcurl'] : "http://osprey.bcodmo.org/dataset.cfm?id=" . $info['dcid'] . '&flag=view';
			$dsurl = @$info['dsurl'] ? $info['dsurl'] : "http://osprey.bcodmo.org/datasetPlatform.cfm?dpid=" . $info['dsid'] . '&flag=view';
			$html .= "<tr><td><a target=\"_blank_\" href=\"$dcurl\">" . $info['dcname'] . '</a></td>';
			$html .= "<td><a target=\"_blank_\" href=\"http://osprey.bcodmo.org/project.cfm?id=" . $info['pid'] . "&flag=view\">" . $info['pname'] . "</a></td>";
			$html .= "<td><a target=\"_blank_\" href=\"http://osprey.bcodmo.org/platform.cfm?id=" . $info['depid'] . "&flag=view\">" . $info['depname'] . "</a></td>";
			$html .= "<td><a target=\"_blank_\" href=\"$dsurl\">get data</a></td></tr>";
		}
		$html .= "</table>";
		echo $html;
	} else if ($type == 'mapper') {
	    	header("Access-Control-Allow-Origin: *");
		header("Content-Type: application/json");
		$object = array();
		foreach ($results as $i => $info)
		{
			if (array_key_exists($info['deploymentId'],$object)) $object[$info['deploymentId']]['datasets'][] = array( "id" => $info['datasetId'], "name" => $info['datasetName'], "url" => $info['datasetUrl'] );
			else $object[$info['deploymentId']] = array("datasets" => array( array( "id" => $info['datasetId'], "name" => $info['datasetName'], "url" => $info['datasetUrl'] ) ), "name" => $info['deploymentName']);
		}
		echo json_encode($object);
	} else {
		addContextLinks($results, $type);
		header("Access-Control-Allow-Origin: *");
		header("Content-Type: application/json");
		echo json_encode($results);
	}
}

//Use this template to test
//getResponse(null,null,null,null,null,null,null,null,null,null,null,null,null,"paramcats",10,0);



