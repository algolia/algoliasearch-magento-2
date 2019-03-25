<?php

try {
    $conn = new PDO("mysql:host=127.0.0.1", 'root', '');
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected 1 successfully\n";
} catch (PDOException $e) {
    echo "Connection 1 failed: " . $e->getMessage() . "\n";
}

try {
    $conn = new PDO("mysql:host=localhost", 'root', '');
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected 2 successfully\n";
} catch (PDOException $e) {
    echo "Connection 2 failed: " . $e->getMessage() . "\n";
}

try {
    $conn = new PDO("mysql:host=localhost", 'magento2', 'P4ssw0rd');
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected 3 successfully\n";
} catch (PDOException $e) {
    echo "Connection 3 failed: " . $e->getMessage() . "\n";
}

try {
    $conn = new PDO("mysql:host=127.0.0.1", 'magento2', 'P4ssw0rd');
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected 4 successfully\n";
} catch (PDOException $e) {
    echo "Connection 4 failed: " . $e->getMessage() . "\n";
}
