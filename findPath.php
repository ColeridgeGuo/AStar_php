<html>
<head><title>Find the path</title></head>
<body>

<form method="POST" action="aStarPath.php">
  Please enter the user ID:
  <input type="text" name="userID">
  <br>
  
  Please enter the latitude of the starting point:
  <input type="text" name="startLat">
  <br>
  Please enter the longitude of the starting point:
  <input type="text" name="startLon">
  <br>
  Please enter whether you are inside or outside:
  <input type="text" name="I_E">
  ('I' for inside and 'E' for outside)
  <br>

  Please enter the building name of the destination:
  <input type="text" name="building">
  <br>
  Please enter the room number of the destination:
  <input type="text" name="room">
  <br>
  
  <input type="submit" value="Submit">
</form>
<a href="../index.php">Return to Homepage.</a>

</body>
</html>