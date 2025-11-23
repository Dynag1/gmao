<?php
session_start();
require 'connexion.php';
require 'crypt.php';
include('header.php');

$panne_id = $_GET['panne_id'];
$user_id  = $_SESSION['user_id'];
$role     = $_SESSION['role'];

// Marquer les messages comme lus
if ($role == "admin") {
    $pdo->prepare("UPDATE messages SET lu_admin = 1 WHERE panne_id = ? AND lu_admin = 0")->execute([$panne_id]);
} else {
    $pdo->prepare("UPDATE messages SET lu_user = 1 WHERE panne_id = ? AND lu_user = 0")->execute([$panne_id]);
}

// Infos du ticket/panne
$sql = "SELECT p.*, c.nom AS camera, s.nom AS site
    FROM pannes p
    JOIN cameras c ON p.camera_id = c.id
    JOIN sites s ON c.site_id = s.id
    WHERE p.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$panne_id]);
$panne = $stmt->fetch();

// Liste des messages du ticket
$sql = "SELECT m.*, u.nom AS auteur FROM messages m JOIN users u ON m.user_id = u.id WHERE panne_id = ? ORDER BY date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$panne_id]);
$messages = $stmt->fetchAll();

// Clôture : admin seulement
if ($role === 'admin' && isset($_POST['clore'])) {
    $pdo->prepare("UPDATE pannes SET statut='fermee' WHERE id=?")->execute([$panne_id]);

    // Infos pour le mail
    $client_id = $panne['client_id'];
    $stmt_client = $pdo->prepare("SELECT nom FROM clients WHERE id = ?");
    $stmt_client->execute([$client_id]);
    $client_nom = $stmt_client->fetchColumn();
    $client_nom = decrypt_data($client_nom);

    $site_nom = htmlspecialchars(decrypt_data($panne['site'], $encryption));
    $camera_nom = htmlspecialchars(decrypt_data($panne['camera'], $encryption));
    $desc_html = nl2br(htmlspecialchars(decrypt_data($panne['description'], $encryption)));
    $url_espace = "https://gmao.dynag.co/";

    require 'mail_send.php';

    $subject = "Clôture du ticket - Client $client_nom";
    $body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Ticket clôturé - $client_nom</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f7f9fa; color: #222; }
    .container { max-width: 600px; margin: auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px #e8f0fe; padding: 24px; }
    h2 { color: #27ae60; margin-bottom: 16px; }
    .content { margin-bottom: 20px; }
    .footer { color: #888; font-size: 0.95em; padding-top: 16px; border-top: 1px dotted #d9dee3; text-align: center; }
    .btn {
      background: #27ae60; color: #fff; text-decoration: none;
      padding: 8px 20px; border-radius: 16px;
      display: inline-block; margin-top: 20px;
      font-weight: bold; font-size: 1em;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Clôture d'un ticket client</h2>
    <div class="content">
      Bonjour,<br><br>
      <strong>Le ticket pour le client :</strong> <b>$client_nom</b> a été clôturé.<br><br>
      <b>Site :</b> $site_nom <br>
      <b>Caméra :</b> $camera_nom<br>
      <b>Description :</b>
      <blockquote style="background:#f6f7fb; border-left:4px solid #27ae60; margin: 12px 0; padding: 8px 12px;">
        $desc_html
      </blockquote>
      <a href="$url_espace" class="btn">Accéder à l'espace</a>
    </div>
    <div class="footer">
      &copy; Infracity / Dynag <?= date("Y") ?> — Ce mail est automatique.<br>
      <span style="font-size:0.92em;color:#aaa;">Pour toute question, contactez l’administrateur ou le support.</span>
    </div>
  </div>
</body>
</html>
HTML;

    // Mail à tous les users du client
    $stmt_users = $pdo->prepare("SELECT email FROM users WHERE client_id = ?");
    $stmt_users->execute([$client_id]);
    foreach ($stmt_users as $user) {
        send_mail($user['email'], $subject, $body);
    }

    // Mail à tous les admins
    $stmt_admins = $pdo->query("SELECT email FROM users WHERE role='admin'");
    foreach ($stmt_admins as $admin) {
        send_mail($admin['email'], $subject, $body);
    }

    header("Location: detail_panne.php?panne_id=".$panne_id);
    exit;
}


// Ajout réponse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = $_POST['message'];
    $pdo->prepare("INSERT INTO messages (panne_id, user_id, message) VALUES (?, ?, ?)")->execute([$panne_id, $user_id, encrypt_data($msg)]);

    // Récupérer le client lié au ticket
    $stmt = $pdo->prepare("SELECT client_id FROM pannes WHERE id = ?");
    $stmt->execute([$panne_id]);
    $client_id = $stmt->fetchColumn();

    // Récupérer tous les users rattachés à ce client
    $stmt = $pdo->prepare("SELECT email, nom FROM users WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $client_users = $stmt->fetchAll();

    require 'mail_send.php';

    $subject = "Nouvelle réponse à un ticket";
    $body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Notification Ticket - $marque</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f7f9fa; color: #222; }
    .container { max-width: 600px; margin: auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px #e8f0fe; padding: 24px; }
    h2 { color: #3498db; margin-bottom: 16px; }
    .content { margin-bottom: 20px; }
    .footer { color: #888; font-size: 0.95em; padding-top: 16px; border-top: 1px dotted #d9dee3; text-align: center; }
    .btn {
      background: #3498db; color: #fff; text-decoration: none;
      padding: 8px 20px; border-radius: 16px;
      display: inline-block; margin-top: 20px;
      font-weight: bold; font-size: 1em;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Notification ticket $marque</h2>
    <div class="content">
      Bonjour,<br><br>
      Vous avez reçu une réponse à votre ticket.<br><br>
      <strong>Message :</strong><br>
      <blockquote style="background:#f6f7fb; border-left:4px solid #3498db; margin: 12px 0; padding: 8px 12px;">
        {$msg}
      </blockquote>
      <a href="$site" class="btn">Accéder à votre espace</a>
    </div>
    <div class="footer">
      &copy; $marque / Dynag <?= date("Y") ?> — Ce mail est automatique. <br>
      <span style="font-size:0.92em;color:#aaa;">Pour toute question, contactez l’administrateur de votre site.</span>
    </div>
  </div>
</body>
</html>
HTML;




    // Envoi à tous les utilisateurs rattachés au client
    foreach ($client_users as $user) {
        send_mail($user['email'], $subject, $body);
    }

    // Notification à tous les admins
$stmt = $pdo->query("SELECT email, nom FROM users WHERE role = 'admin'");
$subjectAdmin = "Nouvelle réponse à un ticket client";
$bodyAdmin = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Notification Ticket - $marque</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f7f9fa; color: #222; }
    .container { max-width: 600px; margin: auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px #e8f0fe; padding: 24px; }
    h2 { color: #e74c3c; margin-bottom: 16px; }
    .content { margin-bottom: 20px; }
    .footer { color: #888; font-size: 0.95em; padding-top: 16px; border-top: 1px dotted #d9dee3; text-align: center; }
    .btn {
      background: #e74c3c; color: #fff; text-decoration: none;
      padding: 8px 20px; border-radius: 16px;
      display: inline-block; margin-top: 20px;
      font-weight: bold; font-size: 1em;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Notification ticket client</h2>
    <div class="content">
      Bonjour Administrateur,<br><br>
      Un ticket client vient de recevoir une nouvelle réponse.<br><br>
      <strong>Message :</strong><br>
      <blockquote style="background:#f6f7fb; border-left:4px solid #e74c3c; margin: 12px 0; padding: 8px 12px;">
        {$msg}
      </blockquote>
      <a href="$marque" class="btn">Accéder à l’espace admin</a>
    </div>
    <div class="footer">
      &copy; $marque / Dynag <?= date("Y") ?> — Ce mail est automatique. <br>
      <span style="font-size:0.92em;color:#aaa;">Pour toute question, contactez le super administrateur.</span>
    </div>
  </div>
</body>
</html>
HTML;

while ($admin = $stmt->fetch()) {
    send_mail($admin['email'], $subjectAdmin, $bodyAdmin);
}


    header("Location: detail_panne.php?panne_id=".$panne_id);
    exit;
}
?>

<main>
<h2>Panne: <?= htmlspecialchars(decrypt_data($panne['site'])) ?> / <?= htmlspecialchars(decrypt_data($panne['camera'])) ?></h2>
<p>Description: <?= htmlspecialchars(decrypt_data($panne['description'])) ?></p>
<p>Statut: <?= htmlspecialchars($panne['statut']) ?></p>
<?php if ($role === 'admin' && $panne['statut']=='ouverte'): ?>
<form method="post"><button name="clore">Clore la panne</button></form>
<?php endif; ?>
<hr>
<h3>Messages de suivi</h3>
<?php foreach ($messages as $m): ?>
    <div class="message">
        <strong><?= htmlspecialchars(decrypt_data($m['auteur'])) ?> :</strong> <?= htmlspecialchars(decrypt_data($m['message'])) ?>
        <em><?= htmlspecialchars($m['date']) ?></em>
    </div>
<?php endforeach;

// Empêcher ajout de message si la panne est fermée
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    if ($panne['statut'] === 'fermee') {
        // Optionnel : afficher un message d'erreur
        echo "<div class='error'>Impossible d'ajouter un message : ce ticket est clôturé.</div>";
    } else {
        $msg = $_POST['message'];
        $pdo->prepare("INSERT INTO messages (panne_id, user_id, message) VALUES (?, ?, ?)")->execute([$panne_id, $user_id, encrypt_data($msg)]);
        // ... notification email etc.
        header("Location: detail_panne.php?panne_id=".$panne_id);
        exit;
    }
}
?>

<?php if ($panne['statut'] === 'ouverte'): ?>
    <form method="post">
        <textarea name="message" required></textarea>
        <button type="submit">Envoyer un message</button>
    </form>
<?php else: ?>
    <div class="error">Ce ticket est clôturé, vous ne pouvez plus ajouter de message.</div>
<?php endif; ?>

<a href="dashboard.php" class="btn">Retour</a>
</main>
<?php include('footer.php'); ?>
