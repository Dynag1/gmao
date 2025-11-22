<?php
session_start();
require 'connexion.php';
include('header.php');
require 'crypt.php';

$user_id = $_SESSION['user_id'];

// Récup info actuelle
$stmt = $pdo->prepare("SELECT email, nom FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$message = "";

if (isset($_POST['save'])) {
    $new_email = trim($_POST['email']);
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérif email (simple)
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="error">Email invalide</div>';
    } else {
        // Vérif mot de passe actuel si modif du mot de passe
        if (!empty($new_password)) {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $hashed = $stmt->fetchColumn();

            // Si le mot de passe hashé en base
            if (!password_verify($old_password, $hashed)) {
                $message = '<div class="error">Mot de passe actuel incorrect</div>';
            } elseif ($new_password !== $confirm_password) {
                $message = '<div class="error">Les nouveaux mots de passe ne correspondent pas</div>';
            } elseif (strlen($new_password) < 6) {
                $message = '<div class="error">Le nouveau mot de passe doit faire au moins 6 caractères</div>';
            } else {
                // Met à jour email + mot de passe
                $stmt = $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                $stmt->execute([
                    $new_email,
                    password_hash($new_password, PASSWORD_DEFAULT),
                    $user_id
                ]);
                $message = '<div class="succes">Informations mises à jour avec succès</div>';
                // Optionnel : refresh info
                $user['email'] = $new_email;
            }
        } else {
            // Met à jour seulement l’email
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$new_email, $user_id]);
            $message = '<div class="succes">Email mis à jour avec succès</div>';
            $user['email'] = $new_email;
        }
    }
}
?>
<main>
<h2>Mon compte</h2>
<?= $message ?>
<form method="post">
    <label>Nom :</label>
    <input type="text" name="nom" value="<?= htmlspecialchars(decrypt_data($user['nom'])) ?>" disabled>
    <label>Email :</label>
    <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
    <hr>
    <label>Mot de passe actuel :</label>
    <input type="password" name="old_password" placeholder="Obligatoire si changement de mot de passe">
    <label>Nouveau mot de passe :</label>
    <input type="password" name="new_password" placeholder="6 caractères minimum">
    <label>Confirmer le nouveau mot de passe :</label>
    <input type="password" name="confirm_password">
    <button type="submit" name="save">Enregistrer les modifications</button>
</form>
</main>
<?php include('footer.php'); ?>
</body>
</html>
