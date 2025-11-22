<?php
session_start();
include('header.php');
require 'connexion.php';
require 'crypt.php';
$user_id = $_SESSION['user_id'];

// Récupère l'user et son client
$stmt = $pdo->prepare("SELECT client_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$client_id = $stmt->fetchColumn();

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

// Création de la panne
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['camera_id'])) {
    $sql = "INSERT INTO pannes (camera_id, user_id, client_id, description) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['camera_id'],
        $user_id,
        $client_id,
        encrypt_data($_POST['description'])
    ]);
    require 'mail_send.php';

    // Optionnel : notification au(x) manager(s) du client ou à tous les admins
    $stmt = $pdo->query("SELECT email FROM users WHERE role='admin'");
    foreach ($stmt as $admin) {
        send_mail($admin['email'], "Nouveau ticket client", "<p>Un nouveau ticket client vient d’être créé.</p>");
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
