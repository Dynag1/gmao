<?php
session_start();
require 'connexion.php';
require 'crypt.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    echo(encrypt_data($email));
    $password = $_POST['mot_de_passe'];
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header('Location: dashboard.php');
        exit;
    }
    $erreur = "Login incorrect";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Infracity - GMAO</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>Infracity</header>
<main>
<h2>Connexion</h2>
<form method="post">
    <input type="email" name="email" placeholder="Email" required><br>
    <input type="password" name="mot_de_passe" placeholder="Mot de passe" required><br>
    <button type="submit">Connexion</button>
    <div class="error"><?= $erreur ?? '' ?></div>
</form>
</main>
<?php include('footer.php'); ?>