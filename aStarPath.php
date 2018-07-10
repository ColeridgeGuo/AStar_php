<?php
// Implementation of A* pathfinding algorithm
// Summer 2018

require ('astar_node.php');
require ('astar_edge.php');

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
function printPath (Node $target) {
  $path = array();
  for ($node = $target; $node != null; $node = $node->parent) {
    array_push($path, $node);
  }
  $pathReverse = array_reverse($path);
	echo "Path: ";
	foreach ($pathReverse as $node) {
		echo "$node->nodeID ";
	}
  echo "<br>";
}

// Main implementation of the pathfinding algorithm
function AStarSearch (Node &$source, Node &$goal, $linkID, &$allNodes){
	$closedList = array();  //list of nodes visited
	$openList = array();    //list of unresolved (open) nodes

	$openList[$source->nodeID] = $source;

  while ( sizeof($openList) > 0 ) {
	  $pq = removeMin($openList);
    buildAdjacencies($pq, $allNodes, $linkID);
    
    
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
      //echo "---------------------- for loop ------------------------<br>";
		}
		$closedList[$pq->nodeID] = $pq;
    //echo "---------------------- while loop ---------------------<br><br>";
  }
}

///////////////////////////////////////////////////////////
/// todo: Move the following to findPath.php once it works
///////////////////////////////////////////////////////////

// Builds the adjacency array for the node
function buildAdjacencies (Node &$node, &$allNodes, $linkID) {
  // Select all the edges that have $node as nodeA
  $sqlA_Edges = "SELECT edgeID, NodeB, Cost
                 FROM Edges
                 WHERE nodeA='$node->nodeID'";
  $A_Edges = mysqli_query($linkID, $sqlA_Edges);
  $numA_Edges = mysqli_num_rows($A_Edges);
  
  if ($numA_Edges <= 0) {
    echo "The node $node->nodeID is not connected to any edge as nodeA.<br>";
  }
  else {
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

      // Create a Node and put it on the allNodes array and an Edge
      $newNode = new Node($NodeB, $Latitude, $Longitude);
      $allNodes['$nodeB'] = $newNode;

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
  
  if ($numB_Edges <= 0) {
    echo "The node $node->nodeID is not connected to any edge as nodeB.<br>";
  }
  else {
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

      // Create a Node and put it on the allNodes array and an Edge
      $newNode = new Node($NodeA, $Latitude, $Longitude);
      $allNodes['$nodeA'] = $newNode;

      //Create an Edge and put it on the adjacencies of $node
      $newEdge = new Edge($node, $newNode, $Cost);
      array_push($node->adjacencies, $newEdge);
      
      //echo "Edge {$newEdge->endPointA->nodeID} -> {$newEdge->endPointB->nodeID} @$newEdge->cost<br>";
    }
  }
}

// Creates a starting node with lat/long
// todo: modify this using nearest-edge algorithm to find a starting point
function createStart ($linkID) {
//  todo: to be replaced with lat/lon
//  $lat = $_POST['startLat'];
//  $lon = $_POST['startLon'];
//  $sqlStart = "SELECT nodeID
//               FROM Nodes
//               WHERE Latitude='$lat' AND Longitude='$lon'";
  $startID = $_POST['startID'];
  $sqlStart = "SELECT Latitude, Longitude
               FROM Nodes
               WHERE nodeID='$startID'";
  $startNodeInfo = mysqli_query($linkID, $sqlStart);
  if (!$startNodeInfo) {
    die("No such starting point found.<br>");
  }
  $startNode = mysqli_fetch_assoc($startNodeInfo);
  $start = new Node($startID, $startNode['Latitude'], $startNode['Longitude']);
  return $start;
}

// Creates a target node with the info given
function createTarget ($linkID) {
  // todo: to be replaced with room number and building
  $targetID = $_POST['targetID'];
  $sqlTarget = "SELECT Latitude, Longitude
                FROM Nodes
                WHERE nodeID='$targetID'";
  $targetNodeInfo = mysqli_query($linkID, $sqlTarget);
  if (!$targetNodeInfo) {
    die("No such destination found.<br>");
  }
  $targetNode = mysqli_fetch_assoc($targetNodeInfo);
  extract($targetNode);
  $target = new Node($targetID, $targetNode['Latitude'], $targetNode['Longitude']);
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
  die("Connection failed: " . mysqli_connect_error() . "<br>");
}
echo "Connected successfully.<br>";
echo "<a href='getStartAndTarget.php'>Return to homepage.<br><br></a>";

$start = createStart($linkID);
$target = createTarget($linkID);

// An array of all the nodes
$allNodes = array();
$allNodes[$start->nodeID] = $start;
$allNodes[$target->nodeID] = $target;

AStarSearch ($start, $target, $linkID, $allNodes);
echo "<br>";
printPath($target);
echo "Cost: " . $target->g . "<br>";
mysqli_close($linkID);