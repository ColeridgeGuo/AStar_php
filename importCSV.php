<?php
	// Connect to MySQl database and select db
	$servername = "localhost";
	$username = "yguo";
	$password = "Gyx199771";
	$dbname = "yguo";
	
	$linkID = mysqli_connect($servername, $username, $password, $dbname);
	
	// Build Node-Edge connections
	
	//find max nodeID
	$data = mysqli_query($linkID,
		"SELECT nodeID
		 FROM Nodes 
		 ORDER BY nodeID DESC LIMIT 1");
	$row = mysqli_fetch_assoc($data);
	if (!$row) {
		$currentIndex = 0;
	}
	else {
		$currentIndex = $row['nodeID'];
	}
	
	// Select all even nodes
	$sqlEvenNodes = 
		"SELECT * 
		 FROM tNode_Edge_Connections 
		 WHERE MOD(nodeID,2)=0";
	$evenNodes = mysqli_query($linkID, $sqlEvenNodes);
	if (!$evenNodes) {
		echo "Error getting the even nodes: " . mysqli_error($linkID) . "<br>";
		exit;
	}
	
	//the number of rows in the result
	$totalRowsEven = mysqli_num_rows($evenNodes);
	
	//for each row of the result
	for ($i=1; $i <= $totalRowsEven; $i++) {
		$evenNode = mysqli_fetch_assoc($evenNodes);
		extract($evenNode);
		
		//check the existence of the node in the DB
		$nodeExists = mysqli_query($linkID,
			"SELECT * 
			 FROM Nodes 
			 WHERE Latitude='$Latitude' AND Longitude='$Longitude' AND Altitude='$Altitude'");
		$existingNode = mysqli_fetch_assoc($nodeExists);
		
		if ($existingNode) {
			$effectiveNodeID = $existingNode['nodeID'];
		}
		else {
			$currentIndex += 1;
			$effectiveNodeID = $currentIndex;
			//add a Node with lat/long location info, new unique nodeID
			$sqlInsertEvenNodes = 
				"INSERT INTO Nodes (nodeID, Latitude, Longitude, Altitude) 
				 VALUES ($effectiveNodeID, '$Latitude', '$Longitude', '$Altitude')";
			
			if (!mysqli_query($linkID, $sqlInsertEvenNodes)) {
				echo "Error inserting even nodes: " . mysqli_error($linkID) . "<br>";
				exit;
			}
		}
		
		//add an Edge node1 with that nodeID and edgeID
		$sqlInsertEdges = 
			"INSERT INTO Edges (tempID, NodeA) 
			 VALUES ($edgeID, $effectiveNodeID)";
		mysqli_query($linkID, $sqlInsertEdges);
	}
	
	// Select all odd nodes
	$sqlOddNodes = 
		"SELECT * 
		 FROM tNode_Edge_Connections 
		 WHERE MOD(nodeID,2)=1";
	$oddNodes = mysqli_query($linkID, $sqlOddNodes);
	if (!$oddNodes) {
		echo "Error getting the odd nodes: " . mysqli_error($linkID) . "<br>";
		exit;
	}
	//the number of rows in the result
	$totalRowsOdd = mysqli_num_rows($oddNodes);
	
	//for each row of the result
	for ($i=1; $i <= $totalRowsOdd; $i++) {
		$oddNode = mysqli_fetch_assoc($oddNodes);
		extract($oddNode);
		
		//check the existence of the node in the DB
		$nodeExists = mysqli_query($linkID,
			"SELECT * FROM Nodes 
			 WHERE Latitude='$Latitude' AND Longitude='$Longitude' AND Altitude='$Altitude'");
		$existingNode = mysqli_fetch_assoc($nodeExists);
		
		if ($existingNode) {
			$effectiveNodeID = $existingNode['nodeID'];
		}
		else {
			$currentIndex += 1;
			$effectiveNodeID = $currentIndex;
			//add a Node with lat/long location info, new unique nodeID
			$sqlInsertOddNodes = 
				"INSERT INTO Nodes (nodeID, Latitude, Longitude, Altitude) 
				 VALUES ($effectiveNodeID, '$Latitude', '$Longitude', '$Altitude')";
			
			if (!mysqli_query($linkID, $sqlInsertOddNodes)) {
				echo "Error inserting odd nodes: " . mysqli_error($linkID) . "<br>";
				exit;
			}
		}
		
		//update the Edge node2 with that nodeID and edgeID
		$sqlUpdateEdges = 
			"UPDATE Edges 
			 SET NodeB='$effectiveNodeID' 
			 WHERE tempID='$edgeID'";
		mysqli_query($linkID, $sqlUpdateEdges);
	}
	
	// Match NodeAttributes with Nodes table
	$sqlNodeAttr = 
		"SELECT * 
		 FROM tNodeAttributes";
	$nodeAttr = mysqli_query($linkID, $sqlNodeAttr);
	$rowsNodeAttr = mysqli_num_rows($nodeAttr);
	
	if ($rowsNodeAttr <= 0) {
		echo "Error getting node attributes: " . mysqli_error($linkID) . "<br>";
		exit;
	}
	
	for ($i=1; $i<=$rowsNodeAttr; $i++) {
		$attr = mysqli_fetch_assoc($nodeAttr);
		extract($attr);
		// Room names and descriptions could have quotation marks in them
		$RoomNumber = addslashes($attr['RoomNumber']);
		$Description = addslashes($attr['Description']);
		
		$sqlNodeInfo = 
			"SELECT nodeID
		 	 FROM Nodes
		 	 WHERE Latitude='$Latitude' AND Longitude='$Longitude' AND Altitude='$Altitude'";
		$nodeInfo = mysqli_query($linkID, $sqlNodeInfo);
		
		if (!$nodeInfo) {
			echo "Error getting the nodeID: " . mysqli_error($linkID) . "<br>";
			exit;
		}
		
		$row = mysqli_fetch_assoc($nodeInfo);
		$nodeID = $row['nodeID'];
		
		$sqlInsertAttr = 
			"INSERT INTO NodeAttributes VALUES ('$nodeID', '$NodeType', '$Location', '$RoomNumber', '$Floor', '$Description', '$DoorLocation', '$Swipe', '$OpenDirection')";
		
		if (!mysqli_query($linkID, $sqlInsertAttr)) {
			echo "$nodeID: Error inserting node attributes: " . mysqli_error($linkID) . "<br>";
		}
		
		$sqlUpdateNodeAttr = "UPDATE Nodes 
													SET I_E='$I_E'
													WHERE nodeID='$nodeID'";
		if (!mysqli_query($linkID, $sqlUpdateNodeAttr)) {
			echo "Duplicate node found.<br>";	
		}
	}
	
	// Match EdgeAttributes with Edges table
	$sqlEdgeAttr = 
		"SELECT * 
		 FROM tEdgeAttributes";
	$edgeAttr = mysqli_query($linkID, $sqlEdgeAttr);
	$rowsEdgeAttr = mysqli_num_rows($edgeAttr);
	
	if ($rowsEdgeAttr <= 0) {
		echo "Error getting edge attributes: " . mysqli_error($linkID) . "<br>";
		exit;
	}
	
	for ($i=1; $i<=$rowsEdgeAttr; $i++) {
		$attr = mysqli_fetch_assoc($edgeAttr);
		extract($attr);
		
		$sqlEdgeInfo = 
			"SELECT edgeID
		 	 FROM Edges
		 	 WHERE tempID='$edgeID'";
		$edgeInfo = mysqli_query($linkID, $sqlEdgeInfo);
		
		if (!$edgeInfo) {
			echo "Error getting the edge with id $edgeID: " . mysqli_error($linkID) . "<br>";
			exit;
		}
		
		$row = mysqli_fetch_assoc($edgeInfo);
		$edgeID = $row['edgeID'];
		
		$sqlInsertAttr = 
			"INSERT INTO EdgeAttributes (edgeID, Location, PathType, StairNum, Curb, Traffic, Obstacles)
			 VALUES ('$edgeID', '$Location', '$PathType', '$StairNum', '$Curb', '$Traffic', '$Obstacles')";
					
		$insertAttr = mysqli_query($linkID, $sqlInsertAttr);
	}
	
	// Empty temporary tables
	$sqlEmptytEdgeAttr = "TRUNCATE tEdgeAttributes";
	$sqlEmptytNodeAttr = "TRUNCATE tNodeAttributes";
	$sqlEmptytNEConnections = "TRUNCATE tNode_Edge_Connections";
	$sqlClearTempID = "UPDATE Edges SET tempID='-1'";
	mysqli_query($linkID, $sqlEmptytEdgeAttr);
	mysqli_query($linkID, $sqlEmptytNodeAttr);
	mysqli_query($linkID, $sqlEmptytNEConnections);
	mysqli_query($linkID, $sqlClearTempID);
	
	echo "Inserting CSV done.<br>";
	
	mysqli_close($linkID);
?>