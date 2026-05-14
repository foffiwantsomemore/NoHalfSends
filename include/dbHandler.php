<?php

class DBHandler {
    private static $pdo; // pointer to the PDO object

    // Static-only class: prevent creating DBHandler objects.
    private function __construct() {
    }

    public static function getPDO(){
        if(self::$pdo == null){
            self::connect_database();
        }
        return self::$pdo;        
    }

    private static function connect_database() {
        define('USER', 'root');
        define('PASSWORD', '');

        try {
            $connection_string = 'mysql:host=localhost;dbname=nhs;charset=utf8';
            $connection_array = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            );
            self::$pdo = new PDO($connection_string, USER, PASSWORD, $connection_array);
        }
        catch(PDOException $e) {
            self::$pdo = null;
        }
    }   
}
?>
