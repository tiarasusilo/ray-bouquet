<?php 
 
$conn = mysqli_connect( 
    "localhost", 
    "root", 
    "", 
    "ray_bouquet_kms" 
); 
 
if (!$conn) { 
    die("Koneksi database KMS gagal: " . mysqli_connect_error()); 
} 
 
?>