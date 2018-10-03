<?php
/*
	element/element.php
	
	RadElement -- Display element information
	CEK 2016-05-15
*/

	include ('../config/open_db.php');
	
	// Get element ID
	extract ($_GET);
	if (! isset ($id) || ! ctype_digit ($id)) {
		header ("Location: /");
		exit;
	}
		
	$result = mysql_query ("SELECT * FROM Element WHERE id = $id LIMIT 1") 
			or die(mysql_error());
		
	if (mysql_num_rows ($result) == 0) {
		header ("Location: /");
		exit;
	}
	
	extract ($row = mysql_fetch_assoc ($result));
	$elementID = $id;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<?php require_once('../config/header.php'); ?>
<title><?php echo $name;?> | RadElement.org</title>
<meta name="description" content="<?php echo $name;?> | RadElement.org" />
<link rel="api" href="/api/v1/elements/RDE<?php echo $elementID;?>">
</head>

<body>
<?php 
include_once("../config/analyticstracking.php");
echo $topbar;
?>
<div class="container">
	<div class="content">

<?php
			
	print "<table>
	<tr><td width=60><img src='/images/RadElement_logo_50.png'></td><td><h2>$name</h2></td></tr>
	</table>
	<hr>
	<table>
	<tr><td align=right>Data Element ID:</td><td>RDE$elementID</td></tr>
	<tr><td align=right>Name:</td><td>$name</td></tr>
	<tr><td align=right>Definition:</td><td>$definition</td></tr>
";	

	// Type:  valueSet
	if (strcmp($valueType, "valueSet") == 0) {
		print "<tr><td align=right>Value:</td><td>Enumerated ";
		if (isset($valueMin)) {
			if (isset($valueMax)) {
				$s = ($valueMax > 1 ? 's' : '');
				if ($valueMin == $valueMax)
					print "(exactly $valueMin value$s)\n";
				else
					print "($valueMin - $valueMax value$s)\n";
			}
			else {
				$s = ($valueMin > 1 ? 's' : '');
				print "($valueMin or more values)\n";
			}
		}
		else {
			if (isset($valueMax)) {
				print "(0 - $valueMax value$s)\n";
			}
		}
		print "<p><ul>\n";
		$valueResult = mysql_query ("SELECT * FROM ElementValue 
						WHERE elementID = $elementID
						ORDER BY id");
		while ($valueRow = mysql_fetch_assoc ($valueResult)) {
			extract ($valueRow);
			print "<li>$code = $name</li>\n";
		}
		print "</ul></p>\n";
	}
	
	// Type:  integer, float, or date
	else /* if (strcmp ($valueType, "integer") == 0
				|| strcmp ($valueType, "float") == 0
				|| strcmp ($valueType, "date") == 0) */ {
		$value = ucfirst ($valueType);
		print "<tr><td align=right>Value:</td><td>$value<br>\n";
		print_info ('Minimum', $valueMin);
		print_info ('Maximum', $valueMax);
		print_info ('Step', $stepValue);
		print_info ('Units', $unit);
		// print_info ('Minimum cardinality', $minCardinality);
		// print_info ('Maximum cardinality', $maxCardinality);
	}
		
	// Display other metadata
	$column = array (
		'question',
		'instructions',
		'codes',
		'indexingCodes',
		'references',
		'version',
		'synonyms',
		'source',
		'status',
		);
		
	$row['version'] .= ($row['versionDate'] == '' ? '' : '&nbsp; ('.$row['versionDate'].')');
	$row['status'] .= ($row['statusDate'] == '' ? '' : '&nbsp; ('.$row['statusDate'].')');
	
	foreach ($column as $key) {
		if (($x = $row[$key]) != '') {
			$key = ucfirst ($key);
			print " <tr><td align=right>$key:</td><td>$x</td></tr>\n";
		}
	}
	
	// Display related sets
	$result = mysql_query ("SELECT setID, name
								FROM SetRef, Set
								WHERE elementID = $elementID
								AND setID = Set.id");
	if (mysql_num_rows ($result) > 0) {
		print "<tr><td>Sets:</td><td>\n";
		while ($row = mysql_fetch_assoc ($result)) {
			extract ($row);
			print "<a href=\"/set/$setID\">$name</a><br>\n";
		}
		print "</td></tr>\n";
	}
	// Display indexing codes
	$result = mysql_query (
				"SELECT system, code, display, codeURL 
					FROM CodeRef, Code, CodeSystem
					WHERE CodeRef.elementID = $elementID
					AND CodeRef.valueCode IS NULL
					AND CodeRef.codeID = Code.id 
					AND Code.system = CodeSystem.abbrev
					ORDER BY system, code");
	if (mysql_num_rows ($result) > 0) {
		print "<tr><td>Index codes:</td><td>\n";
		while ($row = mysql_fetch_assoc ($result)) {
			extract ($row);
			$href = preg_replace ('/\$code/', $code, $codeURL);
			print "<a target=\"code\" href=\"$href\">$display &ndash; $system::$code</a>&nbsp;&nbsp;<img src=\"/images/linkout.png\"><br>\n";
		}
		print "</td></tr>\n";
	}

	print "</table>\n";
?>

<?php  echo $footer; ?>
	</div></div>
</body>
</html>

<?php
	function print_info ($title, $value) {
		if ($value <> '')
			print "<br>$title: $value\n";
	}
?>