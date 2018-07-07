<?php
//Implementation of A* pathfinding algorithm
//Summer 2018

require ('astar_node.php');
require ('astar_edge.php');


//removes the minimum valued (F) node from the array
//array will NOT be empty
function removeMin ($arr){
	$minNode = $arr[0];
	$i = 0;
	$min = 0;

	foreach ($arr as $node) {
    		if ($node->f < $minNode->f) {
			$minNode = $node;
			$min = $i;
		}
		$i++;
	}
	//REMOVE minimum node
	unset ($arr[$min]);
	return $minNode;
}

//follow path from end, via parent's back to start
function printPath ($target) {
  $path = array();
  for ($node = $target; $node != null; $node = $node->parent) {
    array_push($path, $node);
  }
  $pathReverse = array_reverse($path);
  print_r($pathReverse);
  echo "<br>";
}

//main implementation of the pathfinding algorithm
function AStarSearch (Node $source, Node $goal){
	$closedList = array();  //list of nodes visited
	$openList = array();    //list of unresolved (open) nodes

	//array_push($openList, $source);
	$openList["$source->nodeID"] = $source;

	while ( sizeof($openList) > 0 ) {
		$pq = removeMin($openList);
		echo "pq is $pq->nodeID.<br>";
		
		if ($pq->nodeID == $goal->nodeID){
			break;
		}
		
		//check every successor of pq
		foreach ($pq->adjacencies as $edge){
			echo "Node $pq->nodeID has edge.<br>";

			$successor = $edge->getOther($pq);
			echo "successor = $successor->nodeID.<br>";

      // Calculate g, f
      $temp_g = $pq->g + $edge->cost;
      echo "temp_g = $temp_g.<br>";
      $temp_f = $temp_g + $successor->getH($goal);
      echo "temp_f = $temp_f.<br>";

      if (in_array("$successor->nodeID",$openList)) {//two routes to this node exist
        $successor->parent = $pq;
        $successor->g = $temp_g;
        $successor->f = $temp_f;
        echo "New route to $successor is via $pq.<br>";
      }
      else if (!in_array("$successor->nodeID",$closedList)) {
        $successor->parent = $pq;
        $successor->g = $temp_g;
        $successor->f = $temp_f;
        echo "OpenList add $successor @ $successor->f.<br>";
        //array_push($openList, $successor);
        $openList["$successor->nodeID"] = $successor;
      }
		}
		//array_push ($closedList, $pq);
		$closedList["$pq->nodeID"] = $pq;
	}

}


/////////////INITIALIZATION CODE...TO BE REMOVED////////////////
echo "Starting...................\n";
$a = new Node("A",2,3); 
$b = new Node("B",8,3);
$c = new Node("C",2,7);
$d = new Node("D",6,6);
$e = new Node("E",8,7);
$f = new Node("F",12,4);
$g = new Node("G", 12, 9);

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

print_r ($a->toString());
echo "<br>";
AStarSearch ($a, $f);
printPath($f);

echo "DONE";