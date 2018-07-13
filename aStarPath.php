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
  
  // The JSON string that has the status message and the path details
  //$jsonMessage = array();
  //$jsonMessage["status"] = ["status"=>"200", "statusMessage"=>"Success!"];
  $jsonPath = array();
  
  // Put the path in the Paths table in db and output JSON
  $userID = $_POST["userID"];
  $sqlClearPath = "DELETE FROM Paths WHERE userID = '$userID'";
  if (!mysqli_query($linkID, $sqlClearPath)) {
    $jsonMessage["status"] = ["status"=>"500", "statusMessage"=>"Error deleting old paths:" . mysqli_error($linkID)];
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
                      VALUES ($userID, $node->nodeID, $edgeID, $visited)";
    if (!mysqli_query($linkID, $sqlInsertPath)) {
      $jsonMessage["status"] = ["status"=>"500", "statusMessage"=>"Error inserting new paths: " . mysqli_error($linkID)];
    }
    $prev = $node;
    
    // Convert path into an array of JSON string 
    if ($i > 0) {//exclude the starting point
      $sqlNodeInfo = mysqli_query($linkID, "SELECT nodeID, Latitude, Longitude, Description
                                            FROM Nodes
                                            WHERE nodeID='$node->nodeID'");
      $nodeInfo = mysqli_fetch_assoc($sqlNodeInfo);
      //extract($nodeInfo);
      
      $sqlNodeAttributes = mysqli_query($linkID, "SELECT Location, NodeType, Door Swipe, OpenDirection, DoorLocation
                                                  FROM NodeAttributes
                                                  WHERE nodeID='$node->nodeID'");
      $nodeAttributes = mysqli_fetch_assoc($sqlNodeAttributes);
      //extract($nodeAttributes);
      $nodeInfo = array_merge($nodeInfo, $nodeAttributes);
      
      $sqlEdgeAttributes = mysqli_query($linkID, "SELECT *
                                                  FROM EdgeAttributes
                                                  WHERE edgeID = '$edgeID'");
      $edgeAttributes = mysqli_fetch_assoc($sqlEdgeAttributes);
      //extract($edgeAttributes);
      
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
      
      //echo "Edge {$newEdge->endPointA->nodeID} -> {$newEdge->endPointB->nodeID} @$newEdge->cost<br>";
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
      $newEdge = new Edge($node, $newNode, $Cost);
      array_push($node->adjacencies, $newEdge);
    }
  }
}

// Creates a starting node with lat/long
// todo: modify this using nearest-edge algorithm to find a starting point
function createStart ($linkID, &$jsonMessage) {
  $startLat = $_POST['startLat'];
  $startLon = $_POST['startLon'];
  $sqlStart = "SELECT nodeID, Latitude, Longitude, SQRT(POW($startLat - Latitude, 2)+POW($startLon - Longitude, 2)) AS distance
               FROM Nodes
               ORDER BY distance ASC
               LIMIT 1";
  $startNodeInfo = mysqli_query($linkID, $sqlStart);
  if (!$startNodeInfo) {
    $jsonMessage["status"] = ["status"=>"400", "statusMessage"=>"No such starting point found."];
    echo json_encode($jsonMessage);
    exit;
  }
  $startNode = mysqli_fetch_assoc($startNodeInfo);
  extract($startNode);
  $start = new Node($nodeID, $Latitude, $Longitude);
  
  $jsonMessage["debug"] = ["nodeDistance"=>"$distance"];
  
  return $start;
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
    $jsonMessage["status"] = ["status'=>'400", "statusMessage"=>"No such destination found."];
    echo json_encode($jsonMessage);
    exit;
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
$username = "mmallick";
$password = "39ptcm!!x";
$dbname = "FU_Navigate";

$linkID = mysqli_connect($servername, $username, $password, $dbname);
if (!$linkID) {
  $message = ["status"=>"500", "statusMessage"=>"Connection failed." . mysqli_error($linkID)];
  echo json_encode($message);
  exit;
}
$jsonMessage = array();
$jsonMessage["status"] = ["status"=>"200", "statusMessage"=>"Success!"];

$start = createStart($linkID, $jsonMessage);
$target = createTarget($linkID, $jsonMessage);

AStarSearch ($start, $target, $linkID);
echo printPath($target, $linkID, $jsonMessage);
mysqli_close($linkID);