<?php
$conn = mysqli_connect("localhost", "root", "", "virendra");

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}else{
    echo"successful";
}
?>