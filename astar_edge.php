<?php

//one edge in the A* algorithm
class Edge {
	public $cost;
	public $endPointA;  //node at one end
	public $endPointB;  //node at the other end

  //todo: take out the cost cuz it will be updated in the database
  //todo: add the extra cost factor
	function __construct ($a, $b)
  {
    $this->endPointA = $a;
    $this->endPointB = $b;

    if ($a->nodeID == $b->nodeID) {
      $this->cost = 0;
    }
    else { //since edges are, by our definition straight, the cost is the euclidean + hazards
      $this->cost = sqrt(pow($a->latitude - $b->latitude,2)+
          pow($a->longitude - $b->longitutde, 2));
      $this->cost += 0.55;  //some hazard value  TBD
    }
	}

	function setCost($cost){
	  $this->cost = $cost;
  }

	function getOther($node){
	  if ($node->nodeID == $this->endPointA->nodeID) {
	    return $this->endPointB;
    }
    if ($node->nodeID == $this->endPointB->nodeID) {
	    return $this->endPointA;
    }
    return null;
  }
}