<?php
session_start();
include('header.php');
require 'connexion.php';
require 'crypt.php';
require 'mail_send.php';

$user_id = $_SESSION['user_id'];

// Récupérer le client_id et le nom du client de l'utilisateur connecté
$stmt = $pdo->prepare("SELECT client_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$client_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT nom FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client_nom = $stmt->fetchColumn();

// Charger les sites du client
$sites_stmt = $pdo->prepare("SELECT * FROM sites WHERE client_id = ?");
$sites_stmt->execute([$client_id]);
$sites = $sites_stmt->fetchAll();

$site_id = null;
$cameras = [];

// Lorsque le site est choisi, charger ses caméras
if (isset($_POST['site_id']) && !isset($_POST['camera_id'])) {
    $site_id = $_POST['site_id'];
    $cameras_stmt = $pdo->prepare('SELECT * FROM cameras WHERE site_id = ?');
    $cameras_stmt->execute([$site_id]);
    $cameras = $cameras_stmt->fetchAll();
}

// Création de la panne + notification email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['camera_id'])) {
    $description = $_POST['description'];
    $site_id = $_POST['site_id'];
    $camera_id = $_POST['camera_id'];

    $sql = "INSERT INTO pannes (camera_id, user_id, client_id, description) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $camera_id,
        $user_id,
        $client_id,
        encrypt_data($description)
    ]);

    // Infos pour le mail
    $stmt_site = $pdo->prepare('SELECT nom FROM sites WHERE id = ?');
    $stmt_site->execute([$site_id]);
    $site_nom = htmlspecialchars(decrypt_data($stmt_site->fetchColumn()));

    $stmt_cam = $pdo->prepare('SELECT nom FROM cameras WHERE id = ?');
    $stmt_cam->execute([$camera_id]);
    $camera_nom = htmlspecialchars(decrypt_data($stmt_cam->fetchColumn()));

    $desc_html = nl2br(htmlspecialchars($description));
    $url_espace = $site;

    $subject = "Nouveau ticket créé - Client $client_nom";
    $body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Nouveau ticket client</title>
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
    <h2>Notification nouveau ticket client</h2>
    <div class="content">
      Bonjour,<br><br>
      <strong>Nouveau ticket pour le client :</strong> <b>$client_nom</b><br><br>
      <b>Site :</b> $site_nom <br>
      <b>Caméra :</b> $camera_nom<br>
      <b>Description :</b>
      <blockquote style="background:#f6f7fb; border-left:4px solid #3498db; margin: 12px 0; padding: 8px 12px;">
        $desc_html
      </blockquote>
      <a href="$url_espace" class="btn">Accéder à l'espace</a>
    </div>
    <div class="footer">
      &copy; $marque / Dynag <?= date("Y") ?> — Ce mail est automatique.<br>
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

    header("Location: dashboard.php");
    exit;
}
?>

<main>
<h2>Signaler une panne</h2>
<?php if (empty($site_id)): ?>
    <form method="post">
        <label>Site :</label>
        <select name="site_id" required>
            <option value="">Choisir…</option>
            <?php foreach ($sites as $site): ?>
                <option value="<?= $site['id'] ?>"><?= htmlspecialchars(decrypt_data($site['nom'])) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Valider</button>
    </form>
<?php elseif (!empty($cameras)): ?>
    <form method="post">
        <input type="hidden" name="site_id" value="<?= htmlspecialchars($site_id) ?>">
        <label>Caméra :</label>
        <select name="camera_id" required>
            <?php foreach ($cameras as $camera): ?>
                <option value="<?= $camera['id'] ?>"><?= htmlspecialchars(decrypt_data($camera['nom'])) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Description de la panne :</label>
        <textarea name="description" required></textarea>
        <button type="submit">Signaler</button>
    </form>
<?php else: ?>
    <div class="error">Aucune caméra trouvée pour ce site, veuillez choisir un autre site ou contacter un administrateur.</div>
<?php endif; ?>
</main>
<?php include('footer.php'); ?>
