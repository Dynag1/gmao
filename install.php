<?php
// Fichier à placer à la racine, puis supprimer après installation !

require 'connexion.php'; // Doit contenir $pdo = new PDO(...);

// 1. Création des tables
if (isset($_POST['install'])) {
    $schema = <<<SQL
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('client','admin') NOT NULL,
    client_id INT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    localisation VARCHAR(255),
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

CREATE TABLE IF NOT EXISTS cameras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    reference VARCHAR(100),
    localisation VARCHAR(255),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

CREATE TABLE IF NOT EXISTS pannes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camera_id INT NOT NULL,
    client_id INT NOT NULL,
    user_id INT NOT NULL,
    date_signalement DATETIME DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    statut ENUM('ouverte','fermee') DEFAULT 'ouverte',
    FOREIGN KEY (camera_id) REFERENCES cameras(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    panne_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_lu TINYINT(1) DEFAULT 0,
    client_lu TINYINT(1) DEFAULT 0,
    FOREIGN KEY (panne_id) REFERENCES pannes(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
SQL;
    require 'crypt.php';
    // Multi-query (si MySQL), sinon découper en explode(';', ...)
    foreach (explode(';', $schema) as $sql) {
        $sql = trim($sql);
        if ($sql) $pdo->exec($sql);
    }

    // 2. Création du premier client et admin
    $nom_client  = trim($_POST['client_nom']);
    $nom_admin   = trim($_POST['admin_nom']);
    $email_admin = trim($_POST['admin_email']);
    $mdp_admin   = $_POST['admin_mdp'];

    $pdo->prepare("INSERT INTO clients (nom) VALUES (?)")->execute([$nom_client]);
    $client_id = $pdo->lastInsertId();

    // Hash et insertion de l'admin
    $hashed = password_hash($mdp_admin, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (nom, email, mot_de_passe, role, client_id) VALUES (?, ?, ?, 'admin', ?)");
    $stmt->execute([encrypt_data($nom_admin), $email_admin, $hashed, $client_id]);

    echo "<div style='padding:2em;font-size:1.2em;color:green;'>Installation terminée !<br>Client et admin créés.<br><br>Supprimez install.php pour la sécurité.</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Installation GMAO</title>
    <style>
        body {font-family:Arial,sans-serif;background:#fafbfc;}
        main {max-width:420px;margin:auto;margin-top:60px;padding:2em;background:#fff;border-radius:8px;box-shadow:0 2px 12px #eef;}
        label {display:block;margin-top:20px;}
        input,button {margin-top:8px;padding:8px;width:100%;box-sizing:border-box;}
        h2 {text-align:center;}
    </style>
</head>
<body>
<main>
<h2>Installation GMAO</h2>
<form method="post">
    <label>Nom du client principal :</label>
    <input type="text" name="client_nom" required>

    <label>Nom du premier administrateur :</label>
    <input type="text" name="admin_nom" required>

    <label>Email de l'administrateur :</label>
    <input type="email" name="admin_email" required>

    <label>Mot de passe administrateur :</label>
    <input type="password" name="admin_mdp" required>

    <button name="install">Installer et créer l'admin</button>
</form>
</main>
</body>
</html>
