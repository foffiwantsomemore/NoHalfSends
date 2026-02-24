<?php
$jsonPath = '../include/pages.json';

if (file_exists($jsonPath)) {
    $json = file_get_contents($jsonPath);
    $obj = json_decode($json);

    if ($obj === null) {
        die("Error: Failed to decode pages.json. Check the file for syntax errors.");
    }

    $pageName = basename($_SERVER['PHP_SELF']);

    if (in_array($pageName, $obj->loggedInPages)) {
        require __DIR__ . '/../header.php';
    }

    if (in_array($pageName, $obj->DBPages)) {
        require __DIR__ . '/../dbHandler.php';
    }

    // home-specific navbar (unique bar for home)
    if (property_exists($obj, 'homeOnly') && in_array($pageName, $obj->homeOnly)) {
        include __DIR__ . '/homeMenu.php';
    } else {
        if (in_array($pageName, $obj->userpages)) {
            include __DIR__ . '/userMenu.php';
        } elseif (in_array($pageName, $obj->adminpages)) {
            include __DIR__ . '/adminMenu.php';
        }
    }
} else {
    die("Error: pages.json file not found.");
}