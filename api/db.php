<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$db     = 'kseri'; 
$user   = 'iee2019004'; 
$pass   = '@Malister23Kostas16'; 
$socket = '/home/student/iee/2019/iee2019004/mysql/run/mysql.sock';

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

