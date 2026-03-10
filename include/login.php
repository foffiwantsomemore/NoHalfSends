<?php
session_start();

require_once 'dbHandler.php';

// Controllo e sanitizzazione dati inviati dal form
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$insertedPassword = isset($_POST['password']) ? $_POST['password'] : '';

// Email non valida o password vuota
if ($email === false || $email === null || $insertedPassword === '') {
    header('Location: loginForm.php');
    exit();
}

$sql = "SELECT userid, password FROM User WHERE email = :email";
$sth = DBHandler::getPDO()->prepare($sql);
$sth->bindParam(':email', $email, PDO::PARAM_STR);
$sth->execute();

// Email non trovata: reindirizza alla registrazione
if ($sth->rowCount() === 0) {
    header('Location: registerForm.php');
    exit();
}

// Email esistente: controllo password
$row = $sth->fetch();
$hashedPassword = $row['password'];

if (password_verify($insertedPassword, $hashedPassword)) {
    $_SESSION['userId'] = $row['userid'];
    header('Location: ../userpages/feed.php');
    exit();
}

// Password errata
header('Location: loginForm.php');
exit();