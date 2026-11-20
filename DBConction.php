<?php
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli("localhost", "root", "", "lintalaedir");

if (!$conn->connect_error) {
    $conn->set_charset("utf8mb4");
}
