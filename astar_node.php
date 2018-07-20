<?php
//one node in the A* algorithm network
class Node {
	public $nodeID;
	public $g;
	public $f;
	private $h;
	public $latitude;
	public $longitude;
	public $parent;
	public $adjacencies = array(); //of edges

	// constructor	
	function __construct($id, $lat, $lon){
		$this->nodeID = $id;
		$this->g = 0;
		$this->f = 0;
		$this->h = -1;
		$this->latitude = $lat;
		$this->longitude = $lon;
	}
	
	//get the heuristic cost to the goal node
	function getH ($goal){
		if ($this->h < 0) {
			$this->h = sqrt(pow($this->latitude - $goal->latitude,2)
				 + pow($this->longitude - $goal->longitude,2));
		}
		return $this->h;
	}
	
	function sqr($x)
	{
		return $x * $x;
	}
	
	//NOT distance...since it doesn't take squareroot
	function dist2(Node $p1, Node $p2) {
		//return  endPointA^2 + endPointB^2
		return $this->sqr($p1->latitude - $p2->latitude) + 
			$this->sqr($p1->longitude - $p2->longitude);
	}
	
	function distance2segment(Node $endPointA, Node $endPointB, &$projectedPointOnEdge = NULL) {
		$line_distance = $this->dist2($endPointA,$endPointB);
		
		//if endPointA=endPointB, just do distance to the current node
		if ($line_distance == 0) {
			return $this->dist2($this,$endPointA);
		}
		
		//projectedPercent within the line segment will be between 0-1
		$projectedPercent = (($this->latitude - $endPointA->latitude)*($endPointB->latitude - $endPointA->latitude) + ($this->longitude - $endPointA->longitude)*($endPointB->longitude - $endPointA->longitude)) / $line_distance;
		
		//if the projection of the node lies outside of the edge on A side
		if ($projectedPercent <= 0) {
			$projectedPointOnEdge = new Node($endPointA->nodeID, $endPointA->latitude, $endPointA->longitude);
			return sqrt($this->dist2($this,$endPointA));
		}
		
		//if the projection of the node lies outside of the edge on B side
		if ($projectedPercent >= 1) {
			$projectedPointOnEdge = new Node($endPointB->nodeID, $endPointB->latitude, $endPointB->longitude);
			return sqrt($this->dist2($this,$endPointB));
		}
		
		//the node projected ON that edge segment
		$projectedPointX = $endPointA->latitude + $projectedPercent*($endPointB->latitude - $endPointA->latitude);
		$projectedPointY = $endPointA->longitude + $projectedPercent * ($endPointB->longitude - $endPointA->longitude);
		
		$projectedPointOnEdge = new Node(null, $projectedPointX, $projectedPointY);
				
		return sqrt($this->dist2($this, $projectedPointOnEdge));
	}

	function toString () {
		$result = "$this->nodeID ($this->latitude,$this->longitude)";
		$result = $result . "<br>";

		return $result;
	}
}