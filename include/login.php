<?php
session_start();

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$insertedPassword = isset($_POST['password']) ? $_POST['password'] : '';

if ($email === false || $email === null || $insertedPassword === '') {
    header('Location: loginForm.php');
    exit();
}

$sql = "SELECT userid, password FROM User WHERE email = :email";
$sth = DBHandler::getPDO()->prepare($sql);
$sth->bindParam(':email', $email, PDO::PARAM_STR);
$sth->execute();

//email not found
if ($sth->rowCount() === 0) {
    header('Location: registerForm.php');
    exit();
}

//email found
$row = $sth->fetch();
$hashedPassword = $row['password'];

if (password_verify($insertedPassword, $hashedPassword)) {
    $_SESSION['userId'] = $row['userid'];
    header('Location: ../userpages/feed.php');
    exit();
}

//wrong pw
header('Location: loginForm.php');
exit();