<?php
session_start();

// Validate email format and require a non-empty password before querying.
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

// Unknown email sends the user to registration.
if ($sth->rowCount() === 0) {
    header('Location: registerForm.php');
    exit();
}

$row = $sth->fetch();
$hashedPassword = $row['password'];

if (password_verify($insertedPassword, $hashedPassword)) {
    $_SESSION['userId'] = $row['userid'];
    header('Location: ../userpages/feed.php');
    exit();
}

// Wrong password returns to the login form
header('Location: loginForm.php');
exit();
