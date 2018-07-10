<?php

//one edge in the A* algorithm
class Edge {
	public $cost;
	public $endPointA;  //node at one end
	public $endPointB;  //node at the other end

	function __construct ($a, $b, $cost)
  {
    $this->endPointA = $a;
    $this->endPointB = $b;
    $this->cost = $cost;
	}

	function getOther(Node $node){
	  if ($node->nodeID == $this->endPointA->nodeID) {
	    return $this->endPointB;
    }
    if ($node->nodeID == $this->endPointB->nodeID) {
	    return $this->endPointA;
    }
    return null;
  }
}