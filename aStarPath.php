<?php
// Implementation of A* pathfinding algorithm
// Summer 2018

require ('astar_node.php');
require ('astar_edge.php');

header('Content-type:application/json');

// Removes the minimum valued (F) node from the array
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
function printPath (Node $target, $linkID, $jsonMessage) {
  $path = array();
  for ($node = $target; $node != null; $node = $node->parent) {
    array_push($path, $node);
  }
  $pathReverse = array_reverse($path);
  
  $jsonPath = array();
  
  // Put the path in the Paths table in db and output JSON
  $userID = $_POST["userID"];
  $sqlClearPath = "DELETE FROM Paths WHERE userID = '$userID'";
  if (!mysqli_query($linkID, $sqlClearPath)) {
    $jsonMessage["status"] = ["status"=>"502", "statusMessage"=>"Error deleting old paths:" . mysqli_error($linkID)];
  }
  
  $visited = 0;
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
    
    $sqlInsertPath = "INSERT INTO Paths (userID, nodeID, edgeID, Visited)
                      VALUES ('$userID', $node->nodeID, $edgeID, $visited)";
    if (!mysqli_query($linkID, $sqlInsertPath)) {
      $jsonMessage["status"] = ["status"=>"503", "statusMessage"=>"Error inserting new paths: " . mysqli_error($linkID)];
    }
    $prev = $node;
    
    // Convert path into an array of JSON string 
    if ($i > 0) {//exclude the starting point
      $sqlNodeInfo = mysqli_query($linkID, "SELECT nodeID, Latitude, Longitude, Description
                                            FROM Nodes
                                            WHERE nodeID='$node->nodeID'");
      $nodeInfo = mysqli_fetch_assoc($sqlNodeInfo);
      $sqlNodeAttributes = mysqli_query($linkID, "SELECT Location, NodeType, Door Swipe, OpenDirection, DoorLocation
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
      
      $step = array("NodeInformation"=>$nodeInfo, 
                    "EdgeInformation"=>$edgeAttributes);
      array_push($jsonPath, $step);
    }
    $i++;
  }
  $jsonMessage["path"] = $jsonPath;
  return json_encode($jsonMessage);
}

// Main implementation of the pathfinding algorithm
function AStarSearch (Node &$source, Node &$goal, $linkID){
	$closedList = array();  //list of nodes visited
	$openList = array();    //list of unresolved (open) nodes

	$openList[$source->nodeID] = $source;

  while ( sizeof($openList) > 0 ) {
	  $pq = removeMin($openList);
    buildAdjacencies($pq, $linkID);
    
    
    if ($pq->nodeID == $goal->nodeID){
      $goal = $pq;
      break;
    }
    
    $numEdges = sizeof($pq->adjacencies);
    //check every successor of pq
    for ($i = 0; $i < $numEdges; $i++){
      //echo "$i: Node $pq->nodeID has edge.<br>";
      
      $edge = $pq->adjacencies[$i];
      $successor = $edge->getOther($pq);
      //echo "successor = $successor->nodeID.<br>";
      
			// Calculate g, f
			$temp_g = $pq->g + $edge->cost;
			$temp_f = $temp_g + $successor->getH($goal);

      if (array_key_exists($successor->nodeID,$openList)) {//two routes to this node exist
				if ($temp_f <= $successor->f) {
					$successor->parent = $pq;
					$successor->g = $temp_g;
					$successor->f = $temp_f;
					//echo "New route to node $successor->nodeID is via node $pq->nodeID.<br>";
				}
      }
      else if (!array_key_exists($successor->nodeID,$closedList)) {
        $successor->parent = $pq;
        $successor->g = $temp_g;
        $successor->f = $temp_f;				
        //echo "OpenList add node $successor->nodeID @ $successor->f.<br>";
        $openList[$successor->nodeID] = $successor;
      }
		}
		$closedList[$pq->nodeID] = $pq;
  }
}

// For each edge that the node is attached to, build the adjacency array
function buildAdjacencies (Node &$node, $linkID) {
  // Select all the edges that have $node as nodeA
  $sqlA_Edges = "SELECT edgeID, NodeB, Cost
                 FROM Edges
                 WHERE nodeA='$node->nodeID'";
  $A_Edges = mysqli_query($linkID, $sqlA_Edges);
  $numA_Edges = mysqli_num_rows($A_Edges);
  
  if ($numA_Edges > 0) {
    for ($i=0; $i < $numA_Edges; $i++) {
      // For each edge, get the info of the other endpoint
      $A_Edge = mysqli_fetch_assoc($A_Edges);
      extract($A_Edge);
      $sqlNodeB = "SELECT Latitude, Longitude
                   FROM Nodes 
                   WHERE nodeID='$NodeB'";
      $nodeBInfo = mysqli_query($linkID, $sqlNodeB);
      $nodeB = mysqli_fetch_assoc($nodeBInfo);
      extract($nodeB);
      
      $newNode = new Node($NodeB, $Latitude, $Longitude);

      //Create an Edge and put it on the adjacencies of $node
      $newEdge = new Edge($node, $newNode, $Cost);
      array_push($node->adjacencies, $newEdge);
    }
  }

  // Select all the edges that have $node as nodeB
  $sqlB_Edges = "SELECT edgeID, NodeA, Cost 
                 FROM Edges 
                 WHERE nodeB='$node->nodeID'";
  $B_Edges = mysqli_query($linkID, $sqlB_Edges);
  $numB_Edges = mysqli_num_rows($B_Edges);
  
  if ($numB_Edges > 0) {
    for ($i=0; $i < $numB_Edges; $i++) {
      // For each edge, get the info of the other endpoint
      $B_Edge = mysqli_fetch_assoc($B_Edges);
      extract($B_Edge);
      $sqlNodeA = "SELECT Latitude, Longitude
                   FROM Nodes 
                   WHERE nodeID='$NodeA'";
      $nodeAInfo = mysqli_query($linkID, $sqlNodeA);
      $nodeA = mysqli_fetch_assoc($nodeAInfo);
      extract($nodeA);
      
      $newNode = new Node($NodeA, $Latitude, $Longitude);

      //Create an Edge and put it on the adjacencies of $node
      $newEdge = new Edge($newNode, $node, $Cost);
      array_push($node->adjacencies, $newEdge);
    }
  }
}

// Creates a starting node with lat/long
function createStart ($linkID, &$jsonMessage, $userID) {
  $startLat = $_POST['startLat'];
  $startLon = $_POST['startLon'];
  
  // Add the current position node to DB
  $sqlCurrentPos = "INSERT INTO Nodes (Temporary, Description, Latitude, Longitude) 
                      VALUES ('$userID', 'temp current position', '$startLat', '$startLon')";
  if (!mysqli_query($linkID, $sqlCurrentPos)) {
    $jsonMessage["status"] = ["status"=>"504",
        "statusMessage"=>"Error inserting the temp current position."];
  }

  $sqlCurrentPosID = "SELECT nodeID FROM Nodes WHERE Latitude='$startLat' AND Longitude='$startLon'";
  $currentPosID = mysqli_query($linkID, $sqlCurrentPosID);
  if (!$currentPosID) {
    $jsonMessage["status"] = ["status"=>"405",
        "statusMessage"=>"Error getting the nodeID of the current position."];
  }
  $currentPosID = mysqli_fetch_assoc($currentPosID);
  $currentPosID = $currentPosID["nodeID"];
  //echo "current position nodeID: $currentPosID<br>";
  
  $currentPos = new Node($currentPosID, $startLat, $startLon);
  
  // Minimum distance and closest edge
  $minEdge = null;
  $minDist = nearestEdge($currentPos, $linkID, $jsonMessage, $minEdge);
  //echo "Min Distance: $minDist, {$minEdge->endPointA->nodeID}-> {$minEdge->endPointB->nodeID}<br>";
  
  // Add the closest point on edge to DB
  $closestPoint = null;
  $x = $currentPos->distance2segment($minEdge->endPointA, $minEdge->endPointB, $closestPoint);
  //echo "closest point on edge: ($closestPoint->latitude, $closestPoint->longitude)<br>";
  
  $sqlClosestPoint = "INSERT INTO Nodes (Temporary, Description, Latitude, Longitude) 
                      VALUES ('$userID', 'temp closest point on edge', '$closestPoint->latitude', '$closestPoint->longitude')";
  if (!mysqli_query($linkID, $sqlClosestPoint)) {
    $jsonMessage["status"] = ["status"=>"508",
        "statusMessage"=>"Error inserting the temp closest point on edge."];
  }
  $sqlClosestPointID = "SELECT nodeID FROM Nodes WHERE Latitude='$closestPoint->latitude' AND Longitude='$closestPoint->longitude'";
  $closestPointID = mysqli_query($linkID, $sqlClosestPointID);
  if (!$closestPointID) {
    $jsonMessage["status"] = ["status"=>"406",
        "statusMessage"=>"Error getting the nodeID of the closest point on edge."];
  }
  $closestPointID = mysqli_fetch_assoc($closestPointID);
  $closestPointID = $closestPointID["nodeID"];
  //echo "closest point nodeID: $closestPointID<br>";

  // Add three temp edges to DB
  $sqlPerpEdge = "INSERT INTO Edges (tempID, NodeA, NodeB, Cost) 
                  VALUES ('$userID', '$currentPosID', '$closestPointID', '$minDist')";
  if (!mysqli_query($linkID, $sqlPerpEdge)) {
    $jsonMessage["status"] = ["status"=>"408",
        "statusMessage"=>"Error inserting the edge between the current position and the closest node on the edge."];
  }

  $dist1 = sqrt(pow($minEdge->endPointA->latitude - $closestPoint->latitude,2)
      + pow($minEdge->endPointA->longitude - $closestPoint->longitude,2));
  $dist2 = sqrt(pow($minEdge->endPointB->latitude - $closestPoint->latitude,2)
      + pow($minEdge->endPointB->longitude - $closestPoint->longitude,2));
  $sqlEdge1 = "INSERT INTO Edges (tempID, NodeA, NodeB, Cost)
               VALUES ('$userID', '$closestPointID', '{$minEdge->endPointA->nodeID}', '$dist1')";
  $sqlEdge2 = "INSERT INTO Edges (tempID, NodeA, NodeB, Cost)
               VALUES ('$userID', '$closestPointID', '{$minEdge->endPointB->nodeID}', '$dist2')";
  if (!mysqli_query($linkID, $sqlEdge1)) {
    $jsonMessage["status"] = ["status"=>"506",
        "statusMessage"=>"Error inserting the edge between one endpoint of the edge and the closest point on the edge."];
  }
  if (!mysqli_query($linkID, $sqlEdge2)) {
    $jsonMessage["status"] = ["status"=>"507",
        "statusMessage"=>"Error inserting the edge between the other endpoint of the edge and the closest point on the edge."];
  }

  $jsonMessage["debug"] = ["nodeDistance"=>"$minDist"];
  
  return $currentPos;
}

// Clears any temporary nodes or edges in the database
function clearTempNodesNEdges($linkID, $userID, &$jsonMessage) {
  $sqlClearTempNodes = "DELETE FROM Nodes WHERE Temporary='$userID'";
  if (!mysqli_query($linkID, $sqlClearTempNodes)) {
    $jsonMessage["status"] = ["status"=>"509", "statusMessage"=>"Erro deleting temp nodes."];
  }
  
  $sqlClearTempEdges = "DELETE FROM Edges WHERE tempID='$userID'";
  if (!mysqli_query($linkID, $sqlClearTempEdges)) {
    $jsonMessage["status"] = ["status"=>"510", "statusMessage"=>"Erro deleting temp edges."];
  }
}

// Finds the nearest edge to the node
function nearestEdge(&$p, $linkID, &$jsonMessage, &$minEdge) {
  $sqlTopNodes = "SELECT nodeID, Latitude, Longitude, 
                    SQRT(POW($p->latitude - Latitude, 2)+POW($p->longitude - Longitude, 2)) AS distance
                  FROM Nodes
                  ORDER BY distance ASC
                  LIMIT 5";
  $topNodes = mysqli_query($linkID, $sqlTopNodes);
  if (!$topNodes) {
    $jsonMessage["status"] = ["status"=>"404", "statusMessage"=>"No such nodes found."];
  }
  
  $totalRows = mysqli_num_rows($topNodes);
  $minDist = INF;
  
  for ($i = 0; $i < $totalRows; $i++) {
    $topNode = mysqli_fetch_assoc($topNodes);
    extract($topNode);
    
    $endPoint = new Node($nodeID, $Latitude, $Longitude);
    buildAdjacencies($endPoint, $linkID);

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

// Creates a target node with the info given
function createTarget ($linkID, &$jsonMessage) {
  $building = $_POST['building'];
  $room = $_POST['room'];
  $sqlTarget = "SELECT nodeID, Latitude, Longitude
                FROM Nodes
                WHERE Location='$building' AND RoomNumber='$room'";
  $targetNodeInfo = mysqli_query($linkID, $sqlTarget);
  if (!$targetNodeInfo) {
    $jsonMessage["status"] = ["status"=>"402", "statusMessage"=>"No such destination found."];
  }
  $targetNode = mysqli_fetch_assoc($targetNodeInfo);
  if ($targetNode == null) {
    $jsonMessage["status"] = ["status"=>"403", "statusMessage"=>"target node null."];
  }
  extract($targetNode);
  $target = new Node($nodeID, $Latitude, $Longitude);
  return $target;
}

///////////////////////////////////////////////////////////
//////////////////// INITIALIZATION ///////////////////////
///////////////////////////////////////////////////////////

// Connect to MySQl database and select db
$servername = "localhost";
$username = "mmallick";
$password = "39ptcm!!x";
$dbname = "FU_Navigate";

$linkID = mysqli_connect($servername, $username, $password, $dbname);
if (!$linkID) {
  $message = ["status"=>"501", "statusMessage"=>"Connection failed." . mysqli_error($linkID)];
}
$jsonMessage = array();
$jsonMessage["status"] = ["status"=>"200", "statusMessage"=>"Success!"];
$userID = $_POST["userID"];

clearTempNodesNEdges($linkID, $userID, $jsonMessage);

$start = createStart($linkID, $jsonMessage, $userID);
$target = createTarget($linkID, $jsonMessage);

AStarSearch ($start, $target, $linkID);
echo printPath($target, $linkID, $jsonMessage, $userID);
mysqli_close($linkID);