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
	$mini = 0;

	foreach ($arr as $node) {
    		if ($node->f < $minNode->f) {
			$minNode = $node;
			$mini = $i;
		}
		$i++;
	}
	//REMOVE minimum node
	unset ($arr[$mini]);
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

	array_push($openList, $source);

	while ( sizeof($openList) > 0 ) {
		$pq = removeMin($openList);
		
		if ($pq->nodeID == $goal->nodeID){
			break;
		}
		
		//check every successor of pq
		foreach ($pq->adjacencies as $edge){
			echo "Node $pq->nodeID has edge";

			$successor = $edge->getOther($pq);
      // Calculate g, f
      $temp_g = $pq->g + $edge->cost;
      $temp_f = $temp_g + $successor->getH($goal);

      if (!is_null($openList["$successor"])) {//two routes to this node exist
        $successor->parent = $pq;
        $successor->g = $temp_g;
        $successor->f = $temp_f;
        echo "New route to $successor is via $pq.<br>";
      }
      else if (is_null($closedList["$successor"])) {
        $successor->parent = $pq;
        $successor->g = $temp_g;
        $successor->f = $temp_f;
        echo "OpenList add $successor @ $successor->f";
        array_push($openList, $successor);
      }
		}
		array_push ($closedList, $pq);
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

$ab = new Edge($a,$b,6);
$ac = new Edge($a,$c,4);
$bd = new Edge($b,$d,3.6056);
$be = new Edge($b,$e,4);
$cd = new Edge($c,$d,4.1231);
$de = new Edge($d,$e,2.2361);
$ef = new Edge($e,$f,5);
$cg = new Edge($c,$g,10.1980);
$fg = new Edge($f,$g,5);

array_push($a->adjacencies, $ab,$ac);
array_push($b->adjacencies, $ab,$bd,$be);
array_push($c->adjacencies, $ac,$cd);
array_push($d->adjacencies, $bd,$cd,$de);
array_push($e->adjacencies, $de,$be,$ef);
array_push($f->adjacencies, $ef);


print_r ($a->toString());
echo "<br>";
AStarSearch ($a, $f);
printPath($f);

echo "DONE";