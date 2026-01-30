<?php
// Εμφάνιση όλων των σφαλμάτων PHP (χρήσιμο κατά την ανάπτυξη)
ini_set('display_errors', 1); //Ενεργοποιεί την εμφάνιση σφαλμάτων στην οθόνη  και ρυθμίζει την αναφορά όλων των τύπων σφαλμάτων
error_reporting(E_ALL);

//Στοιχεία για σύνδεση με τη βάση δεδομένων
$db     = 'kseri'; 
$user   = 'iee2019004'; 
$pass   = '@Malister23Kostas16'; 
$socket = '/home/student/iee/2019/iee2019004/mysql/run/mysql.sock';


// Δημιουργία PDO αντικειμένου για σύνδεση με τη βάση
try {
    $pdo = new PDO(
        "mysql:dbname=$db;charset=utf8;unix_socket=$socket",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode([
        "error" => "Database connection failed",
        "message" => $e->getMessage()
    ]);
    exit;
}

