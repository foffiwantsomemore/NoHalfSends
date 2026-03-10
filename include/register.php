<?php
session_start();

require_once 'dbHandler.php';

// Recupero e sanitizzazione dati dal form
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$surname = filter_input(INPUT_POST, 'surname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$emailSanitized = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$email = $emailSanitized !== null ? filter_var($emailSanitized, FILTER_VALIDATE_EMAIL) : false;
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Controllo campi obbligatori e validità email
if ($name === null || $name === '' ||
	$surname === null || $surname === '' ||
	$email === false ||
	$password === '') {
	header('Location: registerForm.php');
	exit();
}

$pdo = DBHandler::getPDO();

// Verifico che l'email non sia già registrata
$checkSql = "SELECT userid FROM User WHERE email = :email";
$checkStmt = $pdo->prepare($checkSql);
$checkStmt->bindParam(':email', $email, PDO::PARAM_STR);
$checkStmt->execute();

if ($checkStmt->rowCount() > 0) {
	// Email già esistente
	header('Location: loginForm.php');
	exit();
}

// Hash della password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Inserisco il nuovo utente
$insertSql = "INSERT INTO User (name, surname, email, password, registrationdate) 
			  VALUES (:name, :surname, :email, :password, NOW())";
$insertStmt = $pdo->prepare($insertSql);
$insertStmt->bindParam(':name', $name, PDO::PARAM_STR);
$insertStmt->bindParam(':surname', $surname, PDO::PARAM_STR);
$insertStmt->bindParam(':email', $email, PDO::PARAM_STR);
$insertStmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);

if ($insertStmt->execute()) {
	// Registrazione riuscita: effettuo login automatico e vado al feed
	$_SESSION['userId'] = $pdo->lastInsertId();
	header('Location: ../userpages/feed.php');
	exit();
}

// In caso di errore generico torno al form
header('Location: registerForm.php');
exit();

