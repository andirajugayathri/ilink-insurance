<?php 
session_start(); 

if ($_GET['code'] === null || $_GET['code'] == ""){
    echo 'No Code';
}
else{
   $_SESSION['authcode']=$_GET['code'];
   echo "authcode is". $_SESSION['authcode'];
}
?>