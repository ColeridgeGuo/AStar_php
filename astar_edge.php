<?php

//one edge in the A* algorithm
class Edge {
	public $cost;
	public $endPointA;  //node at one end
	public $endPointB;  //node at the other end

  //todo: take out the cost cuz it will be updated in the database
  //todo: add the extra cost factor
	function __construct ($a, $b, $cost) {
		$this->endPointA = $a;
		$this->endPointB = $b;

		$this->cost = $cost;
	}

	function setCost($cost){
	  $this->cost = $cost;
  }

	function getOther($node){
	  if ($node->nodeID == $endPoinA->nodeID) {
	    return $endPointB;
    }
    else if ($node->nodeID == $endPointB->nodeID) {
	    return $endPoinA;
    }
    else {
	    echo "None of the endpoints match.";
	    return null;
    }
  }
}