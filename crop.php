<?php
include "db.php";

// Get crop name from URL
$crop_name = isset($_GET['crop']) ? $_GET['crop'] : 'tomato';

// Fetch crop info
$crop_result = mysqli_query($connection, "SELECT * FROM crops WHERE crop_name='$crop_name'");
if(mysqli_num_rows($crop_result) == 0){
    die("Crop not found");
}
$crop = mysqli_fetch_assoc($crop_result);
$crop_id = $crop['id'];

// Fetch weekly moisture
$week_result = mysqli_query($connection, "SELECT * FROM weekly_moisture WHERE crop_id=$crop_id ORDER BY week_no ASC");
$weekly_data = [];
while($row = mysqli_fetch_assoc($week_result)) {
    $weekly_data[] = $row;
}

// Fetch schedule
$schedule_result = mysqli_query($connection, "SELECT * FROM schedule_times WHERE crop_id=$crop_id ORDER BY id ASC");
$schedule = [];
while($row = mysqli_fetch_assoc($schedule_result)) {
    $schedule[] = $row['schedule_time'];
}
?>

<html>
<body>
<h2><?php echo ucfirst($crop_name); ?> Details</h2>

<form method="post" action="update_crop.php">

    <input type="hidden" name="crop_id" value="<?php echo $crop_id; ?>">
    <input type="hidden" name="crop_name" value="<?php echo $crop_name; ?>">

    <h3>General Info</h3>
    Growth Duration (weeks): <input type="number" name="growth_duration_weeks" value="<?php echo $crop['growth_duration_weeks']; ?>"><br>
    Root Depth (cm): <input type="number" name="root_depth_cm" value="<?php echo $crop['root_depth_cm']; ?>"><br>
    Ideal Temperature: <input type="text" name="ideal_temp_range" value="<?php echo $crop['ideal_temp_range']; ?>"><br>
    Soil Type: <input type="text" name="soil_type" value="<?php echo $crop['soil_type']; ?>"><br><br>

    <h3>Weekly Moisture SMT VALUE (%)</h3>
    <?php foreach($weekly_data as $w): ?>
        Week <?php echo $w['week_no']; ?>: Min <input type="number" name="min_smp_<?php echo $w['week_no']; ?>" value="<?php echo $w['min_smp']; ?>"> , Max <input type="number" name="max_smp_<?php echo $w['week_no']; ?>" value="<?php echo $w['max_smp']; ?>"><br>
    <?php endforeach; ?>
    <br>

    <h3>Schedule Times (HH:MM)</h3>
    <?php foreach($schedule as $i => $s): ?>
        Time <?php echo $i+1; ?>: <input type="time" name="schedule_<?php echo $i; ?>" value="<?php echo $s; ?>"><br>
    <?php endforeach; ?>
    <br>

    <button type="submit">Update JSON</button>
</form>

<p>ESP32 JSON URL: <a href="api/get_crop_data.php?crop=<?php echo $crop_name; ?>" target="_blank">
<?php echo "api/get_crop_data.php?crop=" . $crop_name; ?></a></p>

</body>
</html>
