<?php
// read pages.json into $json which is a string
/*echo $_SERVER['PHP_SELF'];
echo substr_count($_SERVER['PHP_SELF'], '/');
if(substr_count($_SERVER['PHP_SELF'], '/') == 4){
    echo 'dentro lo if!!!!';*/
$json = file_get_contents('../pages.json');

// get the name of the current page
$pageName = basename($_SERVER['PHP_SELF']);

$obj = json_decode($json);

// in_array(el, arr) checks if el is in array arr
if (in_array($pageName, $obj->loggedInPages)) {
    require '../header.php';
}

if (in_array($pageName, $obj->DBPages)) {
    require '../DBHandler.php';
}

if (in_array($pageName, $obj->userpages)) {
    include '/userMenu.php';
    // include ad.php;
} elseif (in_array($pageName, $obj->adminpages)) {
    include '/adminMenu.php';
}
//}