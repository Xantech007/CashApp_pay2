<?php
//connection to mysql database

$host = "sql100.infinityfree.com";  //database host
$username = "if0_39366363";  //database user
$password = "A3fCR45nHdCj";    //database password
$database = "if0_39366363_cashapp2";  //database name

$con = mysqli_connect("$host","$username","$password","$database");

if(!$con)
{
    echo 'error in connection';
}

