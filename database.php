<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "mysql.progel.com.mx";
$user = "sistprogel";
$pass = "cMwO5ka7u6ZV";
$db   = "precios_grenetina";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Error de conexión BD: " . $e->getMessage());
}
