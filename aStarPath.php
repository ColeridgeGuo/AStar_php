<?php
// Implementation of A* pathfinding algorithm
// Summer 2018

require ('astar_node.php');
require ('astar_edge.php');

header('Content-type:application/json');

// Removes the minimum valued (f) node from the array
function removeMin (array &$arr){
  $minNode = reset($arr);
  $minKey = $minNode->nodeID;
  foreach ($arr as $nodeID => $node) {
    if ($node->f < $minNode->f) {
      $minKey = $nodeID;
      $minNode = $node;
    }
  }
  unset($arr[$minKey]);
  return $minNode;
}

// Follow path from end, via parent's back to start
function printPath (Node $target, $linkID, $jsonMessage, $userID) {
  $path = array();
  for ($node = $target; $node != null; $node = $node->parent) {
    array_push($path, $node);
  }
  $pathReverse = array_reverse($path);
  
  $jsonPath = array();
  
  // Put the path in the Paths table in db and output JSON
  $sqlClearPath = "DELETE FROM Paths WHERE userID = '$userID'";
  if (!mysqli_query($linkID, $sqlClearPath)) {
    $jsonMessage["status"] = ["status"=>"518", "statusMessage"=>"Error deleting old paths:" . mysqli_error($linkID)];
  }
  
  $prev = null;
  $i = 0;
  
  foreach ($pathReverse as $node) {
    $edgeID = 0;
    $sqlFindEdge = "SELECT *
                    FROM Edges 
                    WHERE (NodeA='$prev->nodeID' AND NodeB='$node->nodeID')
                    OR (NodeA='$node->nodeID' AND NodeB='$prev->nodeID')";
    $findEdge = mysqli_query($linkID, $sqlFindEdge);
    if (mysqli_num_rows($findEdge) > 0) {
      $edge = mysqli_fetch_assoc($findEdge);
      $edgeID = $edge['edgeID'];
    }
    
    $sqlInsertPath = "INSERT INTO Paths (userID, nodeID, edgeID)
                      VALUES ('$userID', $node->nodeID, $edgeID)";
    if (!mysqli_query($linkID, $sqlInsertPath)) {
      $jsonMessage["status"] = ["status"=>"519", "statusMessage"=>"Error inserting new paths: " . mysqli_error($linkID)];
    }
    $prev = $node;
    
    // Convert path into an array of JSON string 
    if ($i > 0) {//exclude the starting point
      $sqlNodeInfo = mysqli_query($linkID, "SELECT nodeID, I_E, Latitude, Longitude
                                            FROM Nodes
                                            WHERE nodeID='$node->nodeID'");
      $nodeInfo = mysqli_fetch_assoc($sqlNodeInfo);
      $sqlNodeAttributes = mysqli_query($linkID, 
          "SELECT NodeType, Location, RoomNumber, Floor, Description, DoorLocation, Swipe, OpenDirection
           FROM NodeAttributes
           WHERE nodeID='$node->nodeID'");
      $nodeAttributes = mysqli_fetch_assoc($sqlNodeAttributes);
      if (!$nodeAttributes == null) {
        $nodeInfo = array_merge($nodeInfo, $nodeAttributes);
      }
      
      $sqlEdgeAttributes = mysqli_query($linkID, "SELECT *
                                                  FROM EdgeAttributes
                                                  WHERE edgeID = '$edgeID'");
      $edgeAttributes = mysqli_fetch_assoc($sqlEdgeAttributes);
      
      $step = array("NodeInformation"=>$nodeInfo, "EdgeInformation"=>$edgeAttributes);
      array_push($jsonPath, $step);
    }
    $i++;
  }
  $jsonMessage["path"] = $jsonPath;
  return json_encode($jsonMessage);
}

// Main implementation of the pathfinding algorithm
function AStarSearch (Node &$source, Node &$goal, $linkID, $userID, &$jsonMessage){
	$closedList = array();  //list of nodes visited
	$openList = array();    //list of unresolved (open) nodes

	$openList[$source->nodeID] = $source;

  while ( sizeof($openList) > 0 ) {
	  $pq = removeMin($openList);
    buildAdjacencies($pq, $linkID, $userID, $jsonMessage);
    
    
    if ($pq->nodeID == $goal->nodeID){
      $goal = $pq;
      break;
    }
    
    $numEdges = sizeof($pq->adjacencies);
    //check every successor of pq
    for ($i = 0; $i < $numEdges; $i++){
      $edge = $pq->adjacencies[$i];
      $successor = $edge->getOther($pq);
      
			//calculate g, f
			$temp_g = $pq->g + $edge->cost;
			$temp_f = $temp_g + $successor->getH($goal);

      if (array_key_exists($successor->nodeID,$openList)) {//two routes to this node exist
				if ($temp_f <= $successor->f) {
					$successor->parent = $pq;
					$successor->g = $temp_g;
					$successor->f = $temp_f;
				}
      }
      else if (!array_key_exists($successor->nodeID,$closedList)) {
        $successor->parent = $pq;
        $successor->g = $temp_g;
        $successor->f = $temp_f;
        $openList[$successor->nodeID] = $successor;
      }
		}
		$closedList[$pq->nodeID] = $pq;
  }
}

// For each edge that the node is attached to, build the adjacency array
function buildAdjacencies (Node &$node, $linkID, $userID, &$jsonMessage) {
  
  // Select all the edges that have $node as nodeA
  $sqlA_Edges = "SELECT Edges.edgeID, NodeB, Cost, PathType, Obstacles
                 FROM Edges, EdgeAttributes
                 WHERE (Edges.edgeID=EdgeAttributes.edgeID) AND
                       (nodeA='$node->nodeID' AND (tempID='$userID' OR tempID=-1))";
  $A_Edges = mysqli_query($linkID, $sqlA_Edges);
  if (!$A_Edges) {
    $jsonMessage["status"] = ["status"=>"411", "statusMessage"=>"No edges found that have node as nodeA."];
  }
  $numA_Edges = mysqli_num_rows($A_Edges);
  
  if ($numA_Edges > 0) {
    for ($i=0; $i < $numA_Edges; $i++) {
      
      //for each edge, get the info of the other endpoint
      $A_Edge = mysqli_fetch_assoc($A_Edges);
      extract($A_Edge);
      
      if ($PathType != "Exit only" || $Obstacles == "A to B") {
        $sqlNodeB = "SELECT Latitude, Longitude
                     FROM Nodes 
                     WHERE nodeID='$NodeB' AND (Temporary='$userID' OR Temporary='')";
        $nodeBInfo = mysqli_query($linkID, $sqlNodeB);
        if (!$nodeBInfo) {
          $jsonMessage["status"] = ["status"=>"412", "statusMessage"=>"No node info found for the other endpoint."];
        }
        $nodeB = mysqli_fetch_assoc($nodeBInfo);
        extract($nodeB);
        
        $newNode = new Node($NodeB, $Latitude, $Longitude);
        
        //create an Edge and put it on the adjacencies of $node
        $newEdge = new Edge($node, $newNode, $Cost);
        array_push($node->adjacencies, $newEdge);
      }
    }
  }

  // Select all the edges that have $node as nodeB
  $sqlB_Edges = "SELECT Edges.edgeID, NodeA, Cost, PathType, Obstacles
                 FROM Edges, EdgeAttributes
                 WHERE (Edges.edgeID=EdgeAttributes.edgeID) AND
                       (nodeB='$node->nodeID' AND (tempID='$userID' OR tempID=-1))";
  $B_Edges = mysqli_query($linkID, $sqlB_Edges);
  if (!$B_Edges) {
    $jsonMessage["status"] = ["status"=>"413", "statusMessage"=>"No edges found that have node as nodeB."];
  }
  $numB_Edges = mysqli_num_rows($B_Edges);
  
  if ($numB_Edges > 0) {
    for ($i=0; $i < $numB_Edges; $i++) {
      
      //for each edge, get the info of the other endpoint
      $B_Edge = mysqli_fetch_assoc($B_Edges);
      extract($B_Edge);
      
      if ($PathType != "Exit only" || $Obstacles == "B to A") {
        $sqlNodeA = "SELECT Latitude, Longitude
                     FROM Nodes 
                     WHERE nodeID='$NodeA' AND (Temporary='$userID' OR Temporary='')";
        $nodeAInfo = mysqli_query($linkID, $sqlNodeA);
        if (!$nodeAInfo) {
          $jsonMessage["status"] = ["status"=>"414", "statusMessage"=>"No node info found for the other endpoint."];
        }
        $nodeA = mysqli_fetch_assoc($nodeAInfo);
        extract($nodeA);
        
        $newNode = new Node($NodeA, $Latitude, $Longitude);
        
        //create an Edge and put it on the adjacencies of $node
        $newEdge = new Edge($newNode, $node, $Cost);
        array_push($node->adjacencies, $newEdge);
      }
    }
  }
}

// Logs the starting point in db
function logStartingPoints($node, $linkID, &$jsonMessage, $userID) {
  $sqlLogStartingPoint = "INSERT INTO StartingPointsLog (userID, Latitude, Longitude) 
                          VALUES ('$userID', '$node->latitude', '$node->longitude')";
  if (!mysqli_query($linkID, $sqlLogStartingPoint)) {
    $jsonMessage["status"] = ["status"=>"517", "statusMessage"=>"Error logging starting point."];
  }
}

/** 
 * With lat/lon given, using nearest edge algorithm to create a 
 * starting point and a node on the nearest edge to start the a* algorithm
 */
function createStart ($linkID, &$jsonMessage, $userID) {
  $startLat = $_POST['startLat'];
  $startLon = $_POST['startLon'];
  array_push($jsonMessage["debug"], ["Start"=>"($startLat, $startLon)"]);
  if ((!$startLat) OR (!$startLon)) {
    $jsonMessage["status"] = ["status"=>"401","statusMessage"=>"starting lat lon empty."];
  }
  
  //add the current position node to DB
  $sqlCurrentPos = "INSERT INTO Nodes (Temporary, Latitude, Longitude) 
                      VALUES ('$userID', '$startLat', '$startLon')";
  if (!mysqli_query($linkID, $sqlCurrentPos)) {
    $jsonMessage["status"] = ["status"=>"505",
        "statusMessage"=>"Error inserting the temp current position."];
  }

  $sqlCurrentPosID = "SELECT nodeID FROM Nodes WHERE Latitude='$startLat' AND Longitude='$startLon'";
  $currentPosID = mysqli_query($linkID, $sqlCurrentPosID);
  if (!$currentPosID) {
    $jsonMessage["status"] = ["status"=>"402",
        "statusMessage"=>"Error getting the nodeID of the current position."];
  }
  $currentPosID = mysqli_fetch_assoc($currentPosID);
  $currentPosID = $currentPosID["nodeID"];
  
  $currentPos = new Node($currentPosID, $startLat, $startLon);
  
  //find minimum distance and closest edge
  $minEdge = null;
  $minDist = nearestEdge($currentPos, $linkID, $jsonMessage, $minEdge, $userID);
  
  //log the starting point if it is more than 10m away from an edge
  if ($minDist > 0.0001) {
    logStartingPoints($currentPos, $linkID, $jsonMessage, $userID);
  }
  
  //add the closest point on edge to DB
  $closestPoint = null;
  $closestThingIsNode = false;
  $x = $currentPos->distance2segment($minEdge->endPointA, $minEdge->endPointB, $closestPoint);
  
  //if closest point is endpoint A, add the edge in between to DB
  if ($closestPoint->nodeID == $minEdge->endPointA->nodeID) {
    
    $sqlInsertEdge = "INSERT INTO Edges (tempID, NodeA, NodeB, Cost) 
                      VALUES ('$userID', '$currentPosID', '$closestPoint->nodeID', '$minDist')";
    if (!mysqli_query($linkID, $sqlInsertEdge)) {
      $jsonMessage["status"] = ["status"=>"506",
        "statusMessage"=>"Error inserting the temp edge between the current position and endpoint A."];
    }
    $tempEdgeID = mysqli_query($linkID, "SELECT edgeID 
                                         FROM Edges 
                                         WHERE tempID='$userID' AND NodeA='$currentPosID' AND NodeB='$closestPoint->nodeID'");
    if (!$tempEdgeID) {
      $jsonMessage["status"] = ["status"=>"403",
          "statusMessage"=>"Error getting the edgeID of the temp edge."];
    }
    $tempEdgeID = mysqli_fetch_assoc($tempEdgeID);
    $tempEdgeID = $tempEdgeID["edgeID"];
    
    $sqlTempEdgeAttr = "INSERT INTO EdgeAttributes (edgeID, Location)
                        VALUES ($tempEdgeID, 'Begin path.')";
    if (!mysqli_query($linkID, $sqlTempEdgeAttr)) {
      $jsonMessage["status"] = ["status"=>"507",
          "statusMessage"=>"Error inserting the attributes of the temp edge."];
    }
  }
  //if closest point is endpoint B, add the edge in between to DB
  else if ($closestPoint->nodeID == $minEdge->endPointB->nodeID) {
    
    $sqlInsertEdge = "INSERT INTO Edges (tempID, NodeA, NodeB, Cost) 
                      VALUES ('$userID', '$currentPosID', '$closestPoint->nodeID', '$minDist')";
    if (!mysqli_query($linkID, $sqlInsertEdge)) {
      $jsonMessage["status"] = ["status"=>"508",
        "statusMessage"=>"Error inserting the temp edge between the current position and endpoint B."];
    }
    $tempEdgeID = mysqli_query($linkID, "SELECT edgeID 
                                         FROM Edges 
                                         WHERE tempID='$userID' AND NodeA='$currentPosID' AND NodeB='$closestPoint->nodeID'");
    if (!$tempEdgeID) {
      $jsonMessage["status"] = ["status"=>"404",
          "statusMessage"=>"Error getting the edgeID of the temp edge."];
    }
    $tempEdgeID = mysqli_fetch_assoc($tempEdgeID);
    $tempEdgeID = $tempEdgeID["edgeID"];
    
    $sqlTempEdgeAttr = "INSERT INTO EdgeAttributes (edgeID, Location)
                        VALUES ($tempEdgeID, 'Begin path.')";
    if (!mysqli_query($linkID, $sqlTempEdgeAttr)) {
      $jsonMessage["status"] = ["status"=>"509",
          "statusMessage"=>"Error inserting the attributes of the temp edge."];
    }
  }
  //if closest point lies on the edge, add it to DB along with three temp edges
  else {
    
    $sqlClosestPoint = "INSERT INTO Nodes (Temporary, Latitude, Longitude) 
                        VALUES ('$userID', '$closestPoint->latitude', '$closestPoint->longitude')";
    if (!mysqli_query($linkID, $sqlClosestPoint)) {
      $jsonMessage["status"] = ["status"=>"510",
          "statusMessage"=>"Error inserting the temp closest point on edge."];
    }
    $sqlClosestPointID = "SELECT nodeID FROM Nodes WHERE Latitude='$closestPoint->latitude' AND Longitude='$closestPoint->longitude'";
    $closestPointID = mysqli_query($linkID, $sqlClosestPointID);
    if (!$closestPointID) {
      $jsonMessage["status"] = ["status"=>"405",
          "statusMessage"=>"Error getting the nodeID of the closest point on edge."];
    }
    $closestPointID = mysqli_fetch_assoc($closestPointID);
    $closestPointID = $closestPointID["nodeID"];
    
    // Add three temp edges to DB
    //add two sub-edges of the original edge  
    $dist1 = sqrt(pow($minEdge->endPointA->latitude - $closestPoint->latitude,2)
        + pow($minEdge->endPointA->longitude - $closestPoint->longitude,2));
    $dist2 = sqrt(pow($minEdge->endPointB->latitude - $closestPoint->latitude,2)
        + pow($minEdge->endPointB->longitude - $closestPoint->longitude,2));
      
    $sqlEdge1 = "INSERT INTO Edges (tempID, NodeA, NodeB, Cost)
                 VALUES ('$userID', '$closestPointID', '{$minEdge->endPointA->nodeID}', '$dist1')";
    $sqlEdge2 = "INSERT INTO Edges (tempID, NodeA, NodeB, Cost)
                 VALUES ('$userID', '$closestPointID', '{$minEdge->endPointB->nodeID}', '$dist2')";
    if (!mysqli_query($linkID, $sqlEdge1)) {
      $jsonMessage["status"] = ["status"=>"511",
          "statusMessage"=>"Error inserting the edge between one endpoint of the edge and the closest point on the edge."];
    }
    if (!mysqli_query($linkID, $sqlEdge2)) {
      $jsonMessage["status"] = ["status"=>"512",
          "statusMessage"=>"Error inserting the edge between the other endpoint of the edge and the closest point on the edge."];
    }
  
    $edge1ID = mysqli_query($linkID, "SELECT edgeID FROM Edges 
                  WHERE tempID='$userID' AND NodeA='$closestPointID' AND NodeB='{$minEdge->endPointA->nodeID}'");
    $edge2ID = mysqli_query($linkID, "SELECT edgeID FROM Edges 
                  WHERE tempID='$userID' AND NodeA='$closestPointID' AND NodeB='{$minEdge->endPointB->nodeID}'");
    if (!$edge1ID) {
      $jsonMessage["status"] = ["status"=>"406",
        "statusMessage"=>"Error getting the edgeID of the edge1."];
    }
    if (!$edge2ID) {
      $jsonMessage["status"] = ["status"=>"407",
        "statusMessage"=>"Error getting the edgeID of the edge2."];
    }
    $edge1ID = mysqli_fetch_assoc($edge1ID);
    $edge2ID = mysqli_fetch_assoc($edge2ID);
    $id1 = $edge1ID["edgeID"];
    $id2 = $edge2ID["edgeID"];
    
    $sqlEdge1Attr = "INSERT INTO EdgeAttributes (edgeID, Location, PathType, StairNum, Curb, Traffic, Obstacles, ExtraCost) 
                     SELECT '$id1', Location, PathType, StairNum, Curb, Traffic, Obstacles, ExtraCost FROM EdgeAttributes 
                     WHERE edgeID = (SELECT edgeID FROM Edges 
                                     WHERE (NodeA='{$minEdge->endPointA->nodeID}' AND NodeB='{$minEdge->endPointB->nodeID}') 
                                        OR (NodeA='{$minEdge->endPointB->nodeID}' AND NodeB='{$minEdge->endPointA->nodeID}'))";
    $sqlEdge2Attr = "INSERT INTO EdgeAttributes (edgeID, Location, PathType, StairNum, Curb, Traffic, Obstacles, ExtraCost) 
                     SELECT '$id2', Location, PathType, StairNum, Curb, Traffic, Obstacles, ExtraCost FROM EdgeAttributes 
                     WHERE edgeID = (SELECT edgeID FROM Edges 
                                     WHERE (NodeA='{$minEdge->endPointA->nodeID}' AND NodeB='{$minEdge->endPointB->nodeID}') 
                                        OR (NodeA='{$minEdge->endPointB->nodeID}' AND NodeB='{$minEdge->endPointA->nodeID}'))";
    if(!mysqli_query($linkID, $sqlEdge1Attr)) {
      $jsonMessage["status"] = ["status"=>"513",
        "statusMessage"=>"Error inserting the edge attributes of edge1."];
    }
    if(!mysqli_query($linkID, $sqlEdge2Attr)) {
      $jsonMessage["status"] = ["status"=>"514",
        "statusMessage"=>"Error inserting the edge attributes of edge2."];
    }
    
    //add the perpendicular edge
    $sqlPerpEdge = "INSERT INTO Edges (tempID, NodeA, NodeB, Cost) 
                    VALUES ('$userID', '$currentPosID', '$closestPointID', '$minDist')";
    if (!mysqli_query($linkID, $sqlPerpEdge)) {
      $jsonMessage["status"] = ["status"=>"515",
          "statusMessage"=>"Error inserting the edge between the current position and the closest node on the edge."];
    }
    $perpEdgeID = mysqli_query($linkID, "SELECT edgeID 
                                         FROM Edges 
                                         WHERE tempID='$userID' AND NodeA='$currentPosID' AND NodeB='$closestPointID'");
    if (!$perpEdgeID) {
      $jsonMessage["status"] = ["status"=>"408",
          "statusMessage"=>"Error getting the edgeID of the perp edge."];
    }
    $perpEdgeID = mysqli_fetch_assoc($perpEdgeID);
    $perpEdgeID = $perpEdgeID["edgeID"];
    
    $sqlPerpEdgeAttr = "INSERT INTO EdgeAttributes (edgeID, Location)
                        VALUES ($perpEdgeID, 'Begin path.')";
    if (!mysqli_query($linkID, $sqlPerpEdgeAttr)) {
      $jsonMessage["status"] = ["status"=>"516",
          "statusMessage"=>"Error inserting the attributes of the temp perp edge."];
    }
  }

  array_push($jsonMessage["debug"], ["nodeDistance"=>"$minDist"]);
  
  return $currentPos;
}

// Clears any temporary nodes or edges in the database
function clearTempNodesNEdges($linkID, $userID, &$jsonMessage) {
  
  //clear temporary nodes
  $sqlTempNodes = "SELECT * FROM Nodes WHERE Temporary='$userID'";
  $tempNodes = mysqli_query($linkID, $sqlTempNodes);
  if (mysqli_num_rows($tempNodes) <= 0){
    array_push($jsonMessage["debug"], ["message"=>"No temporary nodes found for user $userID."]);
  }
  else {//if temp nodes exist in db
    $sqlClearTempNodes = "DELETE FROM Nodes WHERE Temporary='$userID'";
    if (!mysqli_query($linkID, $sqlClearTempNodes)) {
      $jsonMessage["status"] = ["status"=>"502", "statusMessage"=>"Error deleting temp nodes."];
    }
  }
  
  //clear temporary edges
  $sqlTempEdgeIDs = "SELECT edgeID FROM Edges WHERE tempID='$userID'";
  $tempEdgeIDs = mysqli_query($linkID, $sqlTempEdgeIDs);
  if (mysqli_num_rows($tempEdgeIDs) <= 0) {
    array_push($jsonMessage["debug"], ["message"=>"No temporary edges found for user $userID."]);
  } 
  else {
    while($row = mysqli_fetch_assoc($tempEdgeIDs)){
      $edgeIDs[] = $row;
    }
    foreach($edgeIDs as $id){
      foreach ($id as $key => $value) {
        $IDs[] = $value;
      }
    }
    $IDs = implode(",", $IDs);
    
    $sqlClearTempEdges = "DELETE FROM Edges WHERE tempID='$userID'";
    if (!mysqli_query($linkID, $sqlClearTempEdges)) {
      $jsonMessage["status"] = ["status"=>"503", "statusMessage"=>"Error deleting temp edges."];
    }
    //clear temporary edge attributes
    $sqlClearTempEdgeAttr = "DELETE FROM EdgeAttributes WHERE edgeID IN ($IDs)";
    if (!mysqli_query($linkID, $sqlClearTempEdgeAttr)) {
      $jsonMessage["status"] = ["status"=>"504", "statusMessage"=>"Error deleting temp edge attributes."];
    }
  }
}

// Look at top 5 closest nodes, iterate thru their adjacencies and find the closest edge
function nearestEdge(&$p, $linkID, &$jsonMessage, &$minEdge, $userID) {
  $sqlTopNodes = "SELECT nodeID, Latitude, Longitude, 
      SQRT(POW($p->latitude - Latitude, 2)+POW($p->longitude - Longitude, 2)) AS distance
      FROM Nodes
      WHERE Temporary=''
      ORDER BY distance ASC
      LIMIT 5";
  $topNodes = mysqli_query($linkID, $sqlTopNodes);
  if (!$topNodes) {
    $jsonMessage["status"] = ["status"=>"415", "statusMessage"=>"No such nodes found."];
  }
  
  $totalRows = mysqli_num_rows($topNodes);
  $minDist = INF;
  
  //for each of the top 5 closest nodes, build its adjacency matrix
  for ($i = 0; $i < $totalRows; $i++) {
    $topNode = mysqli_fetch_assoc($topNodes);
    extract($topNode);
    
    //echo "Top 5 nodes #$i: $nodeID - ($Latitude, $Longitude)<br>";
    
    $endPoint = new Node($nodeID, $Latitude, $Longitude);
    buildAdjacencies($endPoint, $linkID, $userID, $jsonMessage);
    
    //for each edge connected to the node, calculate the distance from it to the current position
    foreach ($endPoint->adjacencies as $edge) {
      $otherEnd = $edge->getOther($endPoint);
      
      $dist = $p->distance2segment($endPoint, $otherEnd);
      
      if ($dist < $minDist) {
        $minDist = $dist;
        $minEdge = $edge;
      }
    }
  }
  return $minDist;
}

// Find the node with the given location and room number and returns it
function createTarget ($linkID, &$jsonMessage) {
  $building = $_POST['building'];
  $room = $_POST['room'];
  array_push($jsonMessage["debug"], ["Destination"=>"$building $room"]);
  if ((!$building )OR (!$room)) {
    $jsonMessage["status"] = ["status"=>"409","statusMessage"=>"destination building room number empty."];
  }
  
  $sqlTarget = "SELECT Nodes.nodeID, Nodes.Latitude, Nodes.Longitude
                FROM Nodes, NodeAttributes
                WHERE Nodes.nodeID = NodeAttributes.nodeID
                AND Location='$building' AND RoomNumber='$room'";
  $targetNodeInfo = mysqli_query($linkID, $sqlTarget);
  if (!$targetNodeInfo) {
    $jsonMessage["status"] = ["status"=>"410", "statusMessage"=>"No such destination found."];
  }
  $targetNode = mysqli_fetch_assoc($targetNodeInfo);
  extract($targetNode);
  $target = new Node($nodeID, $Latitude, $Longitude);
  return $target;
}

///////////////////////////////////////////////////////////
//////////////////// INITIALIZATION ///////////////////////
///////////////////////////////////////////////////////////

// Connect to MySQl database and select db
$servername = "localhost";
$username = "yguo";
$password = "Gyx199771";
$dbname = "yguo";

$linkID = mysqli_connect($servername, $username, $password, $dbname);
if (!$linkID) {
  $message = ["status"=>"501", "statusMessage"=>"Connection failed." . mysqli_error($linkID)];
}
$jsonMessage = array("debug"=>array());
$jsonMessage["status"] = ["status"=>"200", "statusMessage"=>"Success!"];
$userID = $_POST["userID"]; 

clearTempNodesNEdges($linkID, $userID, $jsonMessage);    // clear all temp nodes and edges

$start = createStart($linkID, $jsonMessage, $userID);    // create a starting point
$target = createTarget($linkID, $jsonMessage);           // create a destination

AStarSearch ($start, $target, $linkID, $userID, $jsonMessage); // run the astar to find shortest path
echo printPath($target, $linkID, $jsonMessage, $userID); // print the path along with any additional info in JSON
mysqli_close($linkID);