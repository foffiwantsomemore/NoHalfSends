<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once('loggedin.php');
    require_once('DBHandler.php');