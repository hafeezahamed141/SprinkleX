<?php
// ---------------------------
// Enable error reporting
// ---------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ---------------------------
// Include database connection
// ---------------------------
include "db.php";

// ---------------------------
// Check POST data
// ---------------------------
if(!isset($_POST['crop_id'])){
    die("No crop ID provided.");
}

$crop_id = $_POST['crop_id'];
$crop_name = $_POST['crop_name'];

// ---------------------------
// 1️⃣ Update crops table
// ---------------------------
$growth_duration_weeks = $_POST['growth_duration_weeks'];
$root_depth_cm = $_POST['root_depth_cm'];
$ideal_temp_range = $_POST['ideal_temp_range'];
$soil_type = $_POST['soil_type'];

mysqli_query($connection, "UPDATE crops SET 
    growth_duration_weeks=$growth_duration_weeks,
    root_depth_cm=$root_depth_cm,
    ideal_temp_range='$ideal_temp_range',
    soil_type='$soil_type'
    WHERE id=$crop_id");

// ---------------------------
// 2️⃣ Update weekly moisture
// ---------------------------
$week_result = mysqli_query($connection, "SELECT * FROM weekly_moisture WHERE crop_id=$crop_id ORDER BY week_no ASC");
while($row = mysqli_fetch_assoc($week_result)) {
    $week_no = $row['week_no'];
    $min_smp = $_POST['min_smp_'.$week_no];
    $max_smp = $_POST['max_smp_'.$week_no];
    mysqli_query($connection, "UPDATE weekly_moisture SET min_smp=$min_smp, max_smp=$max_smp WHERE crop_id=$crop_id AND week_no=$week_no");
}

// ---------------------------
// 3️⃣ Update schedule times
// ---------------------------
$schedule_result = mysqli_query($connection, "SELECT * FROM schedule_times WHERE crop_id=$crop_id ORDER BY id ASC");
$i = 0;
while($row = mysqli_fetch_assoc($schedule_result)) {
    $time = $_POST['schedule_'.$i];
    mysqli_query($connection, "UPDATE schedule_times SET schedule_time='$time' WHERE id=".$row['id']);
    $i++;
}

// ---------------------------
// 4️⃣ Generate JSON file for ESP32
// ---------------------------

// Fetch updated crop data
$crop_result = mysqli_query($connection, "SELECT * FROM crops WHERE id=$crop_id");
$crop = mysqli_fetch_assoc($crop_result);

// Weekly moisture
$week_result = mysqli_query($connection, "SELECT week_no, min_smp, max_smp FROM weekly_moisture WHERE crop_id=$crop_id ORDER BY week_no ASC");
$weekly_data = [];
while($row = mysqli_fetch_assoc($week_result)) {
    $weekly_data[] = $row;
}

// Schedule times
$schedule_result = mysqli_query($connection, "SELECT schedule_time FROM schedule_times WHERE crop_id=$crop_id ORDER BY id ASC");
$schedule = [];
while($row = mysqli_fetch_assoc($schedule_result)) {
    $schedule[] = $row['schedule_time'];
}

// Build JSON array
$json_data = [
    "crop_name" => $crop['crop_name'],
    "growth_duration_weeks" => $crop['growth_duration_weeks'],
    "root_depth_cm" => $crop['root_depth_cm'],
    "ideal_temp_range" => $crop['ideal_temp_range'],
    "soil_type" => $crop['soil_type'],
    "weekly_moisture" => $weekly_data,
    "schedule_times" => $schedule,
    "current_week" => 1
];

// Ensure json folder exists
$json_folder = __DIR__ . "/json";
if(!is_dir($json_folder)){
    mkdir($json_folder, 0777, true);
}

// Save JSON to file
$json_file = $json_folder . "/current_crop.json";
if(file_put_contents($json_file, json_encode($json_data, JSON_PRETTY_PRINT))){
    echo "✅ JSON file created successfully at: $json_file<br>";
} else {
    die("❌ Failed to create JSON file! Check folder permissions.");
}

// ---------------------------
// 5️⃣ Redirect back to crop page
// ---------------------------
echo "Redirecting back to crop page...";
header("Refresh:2; url=crop.php?crop=".$crop_name);
exit();
?>
