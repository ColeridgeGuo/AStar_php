# Summer 2018 Project

## InsertCSV

- First off, import `*_Edges.csv`, `*_Nodes.csv`, and `*_Connections.csv` into `tEdgeAttributes`, `tNodeAttributes`, and `tNode_Edge_Connections` tables, respectively (**skip the first row of the files, which are headers**).
  - All indices must start at 0.
  - `*_Edges.csv` contains all the edges and their attributes. 
    - `*_Edges.csv` has to have the columns in the order of: `edgeID`, `Location`, `PathType`, `StairNum`, `Curb`, `Traffic`, `Obstacles`, `ExtraCost`. Names of the columns are not required to be exactly the same as above. `edgeID` and `StairNum` are both of type `int`; `ExtraCost` is of type `double`; the rest of the columns are of type `varchar(100)`.
  - `*_Nodes.csv` contains all the nodes and their attributes. 
    - `*_Nodes.csv` has to have the columns in the order of: `nodeId`, `I_E`, `Latitude`, `Longitude`, `Altitude`, `NodeType`, `Location`, `RoomNumber`, `Floor`, `Description`, `DoorLocation`, `Swipe`, `OpenDirection`. Names of the columns are not required to be exactly the same as above. `nodeID` and `Floor` are both of type `int`; `Latitude`, `Longitude`, and `Altitude` are of type `double`; `I_E` is of type `char`; the rest of the columns are of type `varchar(100)`.
  - `*_Connections.csv` contains all the connections between nodes.
    - `*_Connections.csv` has to have the columns in the order of: `nodeID`, `Location`, `edgeID`, `Latitude`, `Longitude`, and `Altitude`. Names of the columns are not required to be exactly the same as above. `nodeID` and `edgeID` are both of type `int`; `Latitude`, `Longitude`, and `Altitude` are of type `double`; `Location` is of type `varchar(100)`.
- Then run  `importCSV.php`. It finds all the matches between nodes and their based on the connections. It skips any nodes that have been already inserted in the database so that no duplicates exist. The final `Nodes` and `Edges` tables contain all nodes and edges without duplicates, and `NodeAttributes`, and `EdgeAttributes` table contain any information about them. It then clears out all the "t" tables.
- **TODO**: Add connections between layers. Edges between the insides and the outsides of doors and edges that connect stairs and elevators have to be added into the database (**currently manually added**).
- Run `updateEdgeCost.php` after all the edges, including the manually added edges, are inserted into the database to calculate their lengths and extra costs.
- Thus we have established a database of all nodes and edges for path-finding.

## AStar_php

### astar_node.php

- Privately used by `aStarPath.php`.

- A `Node` object has attributes such as `id`, `latitude`, `longitude`, which are self-explanatory. The following attributes are used in the A* search algorithm:`g` is the current cost of the node; `h` is a heuristic (the Euclidean distance between the node and the target in this implementation); `f` is the current cost plus the heuristic; `parent` is the node from which the path comes to the current node; `adjacencies` is an array of all the edges connected to the current node.
- The method `distance2segment` projects the current node onto the edge bounded by `endPointA` and `endPointB`, finds the distance from the node to the edge, which is a line segment. It returns the distance between the node and one of the endpoints if the projection of the node lies outside of the edge. It also returns the closest point on edge to the node if required.

### astar_edge.php

- Privately used by `aStarPath.php`.

- An `Edge` object resembles an "edge" in the database. It has two attributes of type Node, `endPointA` and `endPointB`, along with the `cost` of the edge.
- The method `getOther` returns the other endpoint that is not the one passed as the parameter.

### aStarPath.php

- The main path-finding algorithm, using the A* shortest-path algorithm as the underlying structure.
- The function `createStart` gets the latitude and longitude of the user's current location from HTTP POST requests, creates a starting node, and stores it in the database. It then calls the `nearestEdge` method which returns the minimum distance between the current position and its nearest edge as well as the closest edge itself. The `distance2segment` method of the `Node` class get called to find the closest point on the edge. If the closest point is either endpoint of the edge, only the temporary edge in between the current position and the endpoint is added into the database; if the closest point lies inside the edge, then it gets added into the database along with three temporary edges: the edge between the current position and the closest point on edge, and the two edges between the closest point and the two endpoints of the edge.
- The function `nearestEdge` looks at the top five closest nodes of the parameter node, and looks at all edges of each node to find the nearest edge and distance between the parameter node and the edge.
  - If the current location is inside, it only looks for the top five interior nodes to find the closest node on edge; if it is outside, it looks at the top five exterior nodes.
- The function `createTarget` gets the building name and room number from HTTP POST requests, and creates a destination node.
- The function `AStarSearch` is the main implementation of the A* path-finding algorithm.
- The function `buildAdjacencies` builds the adjacency array of the node for every edge that the node is attached to. 
- The function `printPath` follows the path from destination, via the parent of each node, back to the start, and reverses it as the path. A JSON string with all the information of each node and edge on the path is returned for the APP's use.
- The function `clearTempNodesNEdges` clears the temporary edges and nodes for the current user.

## MySQL Database

### Nodes

- The `Nodes` table contains all the nodes in the map.

- The `Nodes` table contains `nodeID`, `I_E`, `Temporary`, `Latitude`, `Longitude`, `Altitude`, `MagX`, `MagY`, `MagZ`. The primary key is `nodeID`. `I_E` stands for "whether the node is an interior or exterior node" and is of type `char`. `Temporary` is an attribute for a temporary node created during the initialization of the search algorithm. `MagX`, `MagY`, and `MagZ` are three parameters for the magnetic measurements (To be used).

### Edges

- The `Edges` table contains all the edges in the map.
  - For exit-only edges, `PathType` has to be "Exit only", and `Obstacles` has to be either "A to B" or "B to A" depending on the direction of the one-way path.

### NodeAttributes

- The `NodeAttributes` table contains all the attributes of each node.	

### EdgeAttributes

- The `EdgeAttributes` table contains all the attributes of each edge.

### Paths

- The `Paths` table contains lists of the nodes in a path queried by all users.

### StartingPointsLog

- The `StartingPointsLog`  table contains a list of starting positions that are 0.0001 degrees away from their closest edges/nodes.

## InsertNode

- The file `insertNode.php` takes several inputs like `Latitude`, `Longitude`, `Description`, `Building`, `RoomNumber` and such attributes of a `node` and store them in the database as a new node.