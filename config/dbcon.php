<?php
//connection to mysql database

$host = "sql301.infinityfree.com";  //database host
$username = "if0_43053898";  //database user
$password = "01pJ9o5QtX";    //database password
$database = "if0_43053898_pay2";  //database name

$con = mysqli_connect("$host","$username","$password","$database");

if(!$con)
{
    echo 'error in connection';
}

