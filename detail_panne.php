<?php
session_start();
require 'connexion.php';
require 'crypt.php';
include('header.php');
$panne_id = $_GET['panne_id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
if($role == "admin"){
    $pdo->prepare(
        "UPDATE messages SET lu_admin = 1 
        WHERE panne_id = ? AND lu_admin = 0"
    )->execute([$panne_id]);
}else{
        $pdo->prepare(
        "UPDATE messages SET lu_user = 1 
        WHERE panne_id = ? AND lu_user = 0"
    )->execute([$panne_id]);
}
$sql = "SELECT p.*, c.nom AS camera, s.nom AS site, u.nom AS client
    FROM pannes p
    JOIN cameras c ON p.camera_id = c.id
    JOIN sites s ON c.site_id = s.id
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$panne_id]);
$panne = $stmt->fetch();
$sql = "SELECT m.*, u.nom AS auteur FROM messages m JOIN users u ON m.user_id = u.id WHERE panne_id = ? ORDER BY date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$panne_id]);
$messages = $stmt->fetchAll();
if ($role === 'admin' && isset($_POST['clore'])) {
    $pdo->prepare("UPDATE pannes SET statut='fermee' WHERE id=?")->execute([$panne_id]);
    header("Location: detail_panne.php?panne_id=".$panne_id);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = $_POST['message'];
    $pdo->prepare("INSERT INTO messages (panne_id, user_id, message) VALUES (?, ?, ?)")->execute([$panne_id, $user_id, encrypt_data($msg)]);

    // Récupérer le client du ticket (et non celui qui écrit le message)
    $stmt = $pdo->prepare("SELECT u.email, u.nom 
        FROM pannes p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?");
    $stmt->execute([$panne_id]);
    $client_info = $stmt->fetch();

    $client_email = $client_info['email'];
    $client_nom   = $client_info['nom'];

    require 'mail_send.php';

    $subject = "Nouvelle réponse à un ticket";
    $body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Notification Ticket - Infracity</title>
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
    <h2>Notification ticket Infracity</h2>
    <div class="content">
      Bonjour <b>$client_nom</b>,<br><br>
      Vous avez reçu une réponse à votre ticket.<br><br>
      <strong>Message :</strong><br>
      <blockquote style="background:#f6f7fb; border-left:4px solid #3498db; margin: 12px 0; padding: 8px 12px;">
        {$msg}
      </blockquote>
      <a href="https://gmao.dynag.co/" class="btn">Accéder à votre espace</a>
    </div>
    <div class="footer">
      &copy; Infracity / Dynag <?= date("Y") ?> — Ce mail est automatique. <br>
      <span style="font-size:0.92em;color:#aaa;">Pour toute question, contactez l’administrateur de votre site.</span>
    </div>
  </div>
</body>
</html>
HTML;


    send_mail($client_email, $subject, $body);

    // Notification à tous les admins
    $stmt = $pdo->query("SELECT email, nom FROM users WHERE role = 'admin'");
    while ($admin = $stmt->fetch()) {
        $subjectAdmin = "Nouvelle réponse à un ticket client";
        $bodyAdmin = "<p>Le client $client_nom vient de recevoir une réponse à son ticket.<br>Message : "
            . htmlspecialchars($msg) . "</p>";
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
<?php foreach ($messages as $msg): ?>
    <div class="message">
        <strong><?= htmlspecialchars(decrypt_data($msg['auteur'])) ?> :</strong> <?= htmlspecialchars(decrypt_data($msg['message'])) ?>
        <em><?= htmlspecialchars($msg['date']) ?></em>
    </div>
<?php endforeach; ?>
<form method="post">
    <textarea name="message" required></textarea>
    <button type="submit">Envoyer un message</button>
</form>
<a href="dashboard.php">Retour</a>
</main>
<?php include('footer.php'); ?>