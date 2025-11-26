<?php
//connection to mysql database

$host = "sql212.infinityfree.com";  //database host
$username = "if0_40198523";  //database user
$password = "Pdluefrv57ySr";    //database password
$database = "if0_40198523_pay2";  //database name

$con = mysqli_connect("$host","$username","$password","$database");

if(!$con)
{
    echo 'error in connection';
}

