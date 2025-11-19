<?php
//connection to mysql database

$host = "sql210.infinityfree.com";  //database host
$username = "if0_40199478";  //database user
$password = "uR8pb2DxIhn";    //database password
$database = "if0_40199478_pay2";  //database name

$con = mysqli_connect("$host","$username","$password","$database");

if(!$con)
{
    echo 'error in connection';
}

