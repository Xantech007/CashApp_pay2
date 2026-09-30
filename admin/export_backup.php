<?php
session_start();

// Optionally include your database connection if $con is defined in an inc file:
// include('inc/db.php'); 

if (isset($_POST['download_backup'])) {
    
    // Ensure $con exists from your included session/header files or configure parameters:
    if (!isset($con)) {
        // Fallback to include header/db configuration if $con isn't defined yet
        include('inc/header.php'); 
    }

    // Set backup file name
    $fileName = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';

    // Start SQL output buffer
    $sqlScript = "-- MySQL Database Dump\n";
    $sqlScript .= "-- Host: Localhost | Date: " . date('Y-m-d H:i:s') . "\n";
    $sqlScript .= "-- ------------------------------------------------------\n\n";
    $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // Get all tables
    $tables = array();
    $result = mysqli_query($con, 'SHOW TABLES');
    while ($row = mysqli_fetch_row($result)) {
        $tables[] =$row[0];
    }

    // Cycle through all tables
    foreach ($tables as $table) {                  // 1. Get Table Schema (Structure)$sqlScript .= "-- ------------------------------------------------------\n";
        $sqlScript .= "-- Table structure for `$table`\n";
        $sqlScript .= "-- ------------------------------------------------------\n";
        $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
        
        $row2 = mysqli_fetch_row(mysqli_query($con, 'SHOW CREATE TABLE `' . $table . '`'));
        $sqlScript .= "\n\n" . $row2[1] . ";\n\n";

        // 2. Get Table Data
        $resultData = mysqli_query($con, 'SELECT * FROM `' . $table . '`');
        $columnCount = mysqli_num_fields($resultData);

        $sqlScript .= "-- Data for `$table`\n";

        while ($row = mysqli_fetch_row($resultData)) {$sqlScript .= "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                if (isset($row[$j])) {
                    // Escape special characters for SQL security
                    $row[$j] = mysqli_real_escape_string($con, $row[$j]);
                    // Handle line breaks
                    $row[$j] = str_replace("\n", "\\n", $row[$j]);$sqlScript .= '"' . $row[$j] . '"';
                } else {
                    $sqlScript .= 'NULL';
                }

                if ($j < ($columnCount - 1)) {$sqlScript .= ',';
                }
            }
            $sqlScript .= ");\n";
        }
        $sqlScript .= "\n\n";
    }

    $sqlScript .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // Force browser file download headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($sqlScript));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output content to browser download stream
    echo $sqlScript;
    exit;
} else {
    // Redirect if accessed directly
    header('Location: backup.php');
    exit;
}
