<?php
//Implementation of A* pathfinding algorithm
//Summer 2018

require ('astar_node.php');
require ('astar_edge.php');

//returns the minimum valued (F) node key from the array
// you can't wrap 'unset()' in a user-defined function 
// because it only unsets the reference not the actual copy

function returnMinKey ($arr){
	$minNode = reset($arr);
	$minKey = $minNode->nodeID;
	foreach ($arr as $nodeID => $node) {
    if ($node->f < $minNode->f) {
			$minKey = $nodeID;
		}
	}
	return $minKey;
}

//follow path from end, via parent's back to start
function printPath ($target) {
  $path = array();
  for ($node = $target; $node != null; $node = $node->parent) {
    array_push($path, $node);
  }
  $pathReverse = array_reverse($path);
	echo "Path: ";
	foreach ($pathReverse as $node) {
		echo "$node->nodeID";
	}
  echo "<br>";
}

//main implementation of the pathfinding algorithm
function AStarSearch (Node $source, Node $goal){
	$closedList = array();  //list of nodes visited
	$openList = array();    //list of unresolved (open) nodes

	$openList[$source->nodeID] = $source;

	while ( sizeof($openList) > 0 ) {
		$minKey = returnMinKey($openList);
		
		$pq = $openList[$minKey];
		unset ($openList[$minKey]);
		//echo "pq = $pq->nodeID.<br><br>";
		
		if ($pq->nodeID == $goal->nodeID){
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
					echo "New route to node $successor->nodeID is via node $pq->nodeID.<br>";
				}
      }
      else if (!array_key_exists($successor->nodeID,$closedList)) {
        $successor->parent = $pq;
        $successor->g = $temp_g;
        $successor->f = $temp_f;				
        echo "OpenList add node $successor->nodeID @ $successor->f.<br>";
        $openList[$successor->nodeID] = $successor;
      }
		}
		$closedList[$pq->nodeID] = $pq;
	}
}


/////////////INITIALIZATION CODE...TO BE REMOVED////////////////
echo "Starting...................<br>";
$a = new Node("A",2,3); 
$b = new Node("B",8,3);
$c = new Node("C",2,7);
$d = new Node("D",6,6);
$e = new Node("E",8,7);
$f = new Node("F",12,4);
$g = new Node("G",12,9);

$ab = new Edge($a,$b);
$ac = new Edge($a,$c);
$bd = new Edge($b,$d);
$be = new Edge($b,$e);
$cd = new Edge($c,$d);
$de = new Edge($d,$e);
$ef = new Edge($e,$f);
$cg = new Edge($c,$g);
$fg = new Edge($f,$g);

array_push($a->adjacencies, $ab,$ac);
array_push($b->adjacencies, $ab,$bd,$be);
array_push($c->adjacencies, $ac,$cd,$cg);
array_push($d->adjacencies, $bd,$cd,$de);
array_push($e->adjacencies, $de,$be,$ef);
array_push($f->adjacencies, $ef,$fg);
array_push($g->adjacencies, $cg,$fg);

$start = $a;
$destination = $f;

AStarSearch ($start, $destination);
echo "<br>";
printPath($destination);
echo "Cost: " . $destination->g . "<br>";

echo "DONE...................<br>";