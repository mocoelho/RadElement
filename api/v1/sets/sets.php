<?php
/*
    /api/v1/sets/sets.php
*/

    // Specify JSON output
    header ('Content-type: application/json');

    // Define API base address
    $baseURL = 'http://radelement.org/api/v1';

    // Connect to database
    require ('../../../config/open_db.php');

    // Get web service parameters
    //      /sets           List all sets
    //      /sets?top       List all top-level sets (those with no parent set)
    //      /sets?name={x}
    //      /sets/RDES3         Show set 3, including parent, children, and data elements

    extract ($_GET);

    if (isset($top)) {
        $response ['query']['top'] = true;
        $query_string = 'parentID IS NULL';
        $queryURL = '?top';
    }
    else {
        $query_string = '1';
        $queryURL = '';
    }
    if (isset($name)) {
        $response ['query']['name'] = $name;
        $xname = mysql_real_escape_string ($name);
        $query_string .= " AND name LIKE '%$xname%'";
        $queryURL .= "?name=" . urlencode($name);
    }
    else {
        $xquery = '';
        $query_string .= '';
        $queryURL .= '';
    }


    // If a set has been specified, show its details
    if ($_id <> '') {
        $id = mysql_real_escape_string ($_id);

        // Retrieve set details
        $result = mysql_query (
                "SELECT * FROM ElementSet WHERE id = $id LIMIT 1")
            or die(mysql_error());

        if (mysql_num_rows ($result) == 0) {
            $response = array (
                'id' => null,
                'msg' => "ERROR - no such ID number"
                );
            print (json_encode ($response));
            exit;
        }

        $row = mysql_fetch_assoc ($result);
        extract ($row);
        unset ($row['parentID']);
        $row['id'] = 'RDES' . $row['id'];
        $response ['set'] = $row;

        // Include the set's parent only if one exists
        if ($parentID) {

            $result = mysql_query (
                    "SELECT CONCAT('RDES',id) AS id, name,
                            CONCAT('$baseURL/sets/RDES',id) AS url
                     FROM ElementSet
                     WHERE id = $parentID
                     LIMIT 1");

            $response ['set'] ['parent'] = mysql_fetch_assoc ($result);

        }


        // List set's children
        $result = mysql_query (
                "SELECT CONCAT('RDES',id) AS id, name,
                        CONCAT('$baseURL/sets/RDES',id) AS url
                 FROM ElementSet
                 WHERE parentID = $id
                 ORDER BY id") or die(mysql_error());

        while ($row = mysql_fetch_assoc ($result)) {
            $children [] = $row;
        }
        $response ['set'] ['children'] = $children;

        // Retrieve associated data elements
        $numElements = mysql_num_rows (mysql_query (
                        "SELECT * FROM ElementSetRef WHERE elementSetID = $id"));
        $response ['elements'] ['count'] = $numElements;
        $response ['elements'] ['url'] = ($numElements > 0 ? "$baseURL/sets/RDES$id/elements" : null);

        $code_result = mysql_query (
                       "SELECT system, code, display,
                               CONCAT('$baseURL/codes/', system, '/', code) AS url
                        FROM IndexCodeElementSetRef, IndexCode
                        WHERE elementSetID = $id
                        AND IndexCodeElementSetRef.codeID = IndexCode.id
                        GROUP BY system, code");
        while ($code_row = mysql_fetch_assoc ($code_result)) {
            $codes [] = $code_row;
        }
        $response ['index_codes'] = $codes;
    }

    // Else (no set specified), list all top-level sets
    else {
        $limit = (ctype_digit($limit) ? min(0+$limit,100) : 20);
        $offset = (ctype_digit($offset) ? 0+$offset : 0);

        $result = mysql_query (
                "SELECT CONCAT('RDES',id) AS id, name, description,
                        CONCAT('$baseURL/sets/RDES',id) as url
                FROM ElementSet
                WHERE $query_string
                ORDER BY id LIMIT $limit OFFSET $offset")
                or die(mysql_error());

        // TODO - Add Organization and Person

        $response ['query']['limit'] = $limit;
        $response ['query']['offset'] = $offset;
        $response ['query']['count'] = mysql_num_rows ($result);

        while ($row = mysql_fetch_assoc ($result)) {
            $set [] = $row;
        }
        $response ['sets'] = $set;

        // Tally total number of elements
        $totalCount = mysql_num_rows (mysql_query ("SELECT * FROM ElementSet WHERE $query_string"));
        $response ['query']['totalCount'] = $totalCount;

        // Point to next set of elements
        $offset += $limit;
        $response ['query']['next'] = ($totalCount > $offset ? "$baseURL/sets$queryURL&limit=$limit&offset=$offset" : null);

    }

    print json_encode($response);

?>