<?php
if(!isset($_SESSION['userId'])){
    header('Location: ../include/loginForm.php');
    exit;
}