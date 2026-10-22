<?php

$host = 'localhost';
$user = 'root';
$pass = '';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {

    // Database TPS
    $pdo = new PDO(
        "mysql:host=$host;dbname=ray_bouquet_tps;charset=utf8mb4",
        $user,
        $pass,
        $options
    );

    // Database OAS
    $pdo_oas = new PDO(
        "mysql:host=$host;dbname=ray_bouquet_oas;charset=utf8mb4",
        $user,
        $pass,
        $options
    );

} catch (PDOException $e) {
    die('Koneksi database gagal. Cek pengaturan di koneksi.php');
}