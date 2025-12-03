<?php
include("db.php");
if($connection){
    echo "Database connected!";
}else{
    echo "Connection failed!";
}
?>
