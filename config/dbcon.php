<?php
//connection to mysql database

$host = "sql100.infinityfree.com";  //database host
$username = "if0_39973380";  //database user
$password = "AoGzAigzXNqu";    //database password
$database = "if0_39973380_cashapp2";  //database name

$con = mysqli_connect("$host","$username","$password","$database");

if(!$con)
{
    echo 'error in connection';
}

