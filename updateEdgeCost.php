<?php
$servername = "localhost";
$username = "yguo";
$password = "Gyx199771";
$dbname = "yguo";

$linkID = mysqli_connect($servername, $username, $password, $dbname);
if (!$linkID) {
  echo "Connection failed. " . mysqli_error($linkID);
  exit;
}

// constants for calculating extra costs
define("STAIR_COST", 0.01); // weight of each stair
define("CURB_COST", 0.1); // weight of each curb
define("ELEVATOR_COST", 0.1); // weight of an elevator
define("TRAFFIC_COST", 0.2); // weight of crossing traffic
define("STANTIONS_COST", 0.1); // weight of stantions
define("DOOR_COST", 0.1); // weight of each door
define("METER_2_DEGREE", 0.0001/11); // conversion factor between meter and degree (GPS)
  
$sqlAllEdges = "SELECT * FROM Edges";
$allEdges = mysqli_query($linkID, $sqlAllEdges);
$numRows = mysqli_num_rows($allEdges);
  
if ($numRows > 0) {
  for ($i=1; $i <= $numRows; $i++) {
    $edge = mysqli_fetch_assoc($allEdges);
    extract($edge);
    
    $sqlNodeAInfo = "SELECT Latitude AS latA, Longitude AS lonA, Altitude AS altA 
                     FROM Nodes WHERE nodeID='$NodeA'";
    $sqlNodeBInfo = "SELECT Latitude AS latB, Longitude AS lonB, Altitude AS altB
                     FROM Nodes WHERE nodeID='$NodeB'";
    $nodeAInfo = mysqli_query($linkID, $sqlNodeAInfo);
    $nodeBInfo = mysqli_query($linkID, $sqlNodeBInfo);
    
    $nodeAInfo = mysqli_fetch_assoc($nodeAInfo);
    $nodeBInfo = mysqli_fetch_assoc($nodeBInfo);
    extract($nodeAInfo);
    extract($nodeBInfo);
    
    $altA *= METER_2_DEGREE; //convert altitude to degrees
    $altB *= METER_2_DEGREE;
    
    $length = sqrt(pow($latA - $latB, 2) + pow($lonA - $lonB, 2) + pow($altA - $altB, 2));
    
    // calculate total cost with weights
    $multiplier = 0;
    $sqlExtraCostInfo = "SELECT PathType, StairNum, Curb, Traffic, Obstacles FROM EdgeAttributes WHERE edgeID='$edgeID'";
    $extraCostInfo = mysqli_query($linkID, $sqlExtraCostInfo);
    $extraCostInfo = mysqli_fetch_assoc($extraCostInfo);
    extract($extraCostInfo);
    
    // update mulitplier based on each attribute
    if ($PathType == "Elevator") {
      $multiplier += ELEVATOR_COST;
    }
    if ($Curb == "Yes") {
      $multiplier += CURB_COST;
    }
    if ($Traffic == "Crosses traffic") {
      $multiplier += TRAFFIC_COST;
    }
    if ($Obstacles == "Stantions may be in road") {
      $multiplier += STANTIONS_COST;
    }
    if ($PathType == "Exit/entrance" || $PathType == "Exit only"){
      $multiplier += DOOR_COST;
    }
    $multiplier += $StairNum * STAIR_COST;
    
    $extraCost = $length * $multiplier;
    
    // put the euclidean distance in Edges and the extra cost in EdgeAttributes
    $sqlUpdateEdgeLength = "UPDATE Edges SET Distance='$length' WHERE edgeID='$edgeID'";
    if (!mysqli_query($linkID, $sqlUpdateEdgeLength)) {
      echo "Error updating euclidean distance: " . mysqli_error($linkID) . "<br>";
    }
    
    $sqlUpdateEdgeCost = "UPDATE EdgeAttributes SET ExtraCost='$extraCost' WHERE edgeID='$edgeID'";
    if (!mysqli_query($linkID, $sqlUpdateEdgeCost)) {
      echo "Error updating edge cost: " . mysqli_error($linkID) . "<br>";
    }
  }
} 
else {
  echo "No edges in the Edges db.";
}

echo "Updating edge cost done.";
mysqli_close($linkID);