<?php include "db.php"; ?>

<html>
<body>
<h2>Select a Crop</h2>

<?php
// Fetch all crops from database
$result = mysqli_query($connection, "SELECT * FROM crops");

// Loop through each crop and create a button dynamically
while ($row = mysqli_fetch_assoc($result)) {
    $name = $row['crop_name'];
    // Use single quotes for href to avoid breaking double quotes in echo
    echo '<a href="crop.php?crop=' . $name . '"><button>' . ucfirst($name) . '</button></a> <br><br>';
}
?>
</body>
</html>
