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
			$this->h = sqrt (pow ($this->latitude - $goal->latitude,2)
				 +   pow ($this->longitude - $goal->longitude,2));
		}
		return $this->h;
	}

	function toString () {
		$result = "llllll[".$this->nodeID;
		$result = $result . "\n<br/>$this->nodeID @$this->latitude,$this->longitude";
		$result = $result."]\n<br/>";

		return $result;
	}
}