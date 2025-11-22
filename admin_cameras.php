<?php
session_start();
require 'connexion.php';
require 'crypt.php';
include('header.php');
if ($_SESSION['role'] !== 'admin') header('Location: dashboard.php');

// Liste des clients
$clients = $pdo->query("SELECT id, nom FROM clients")->fetchAll();

$client_id = isset($_POST['client_id']) ? $_POST['client_id'] : null;

// Liste des sites associés au client sélectionné
$sites = [];
if ($client_id) {
    $sites_stmt = $pdo->prepare("SELECT * FROM sites WHERE client_id = ?");
    $sites_stmt->execute([$client_id]);
    $sites = $sites_stmt->fetchAll();
}

// Ajout d'une caméra (site="client_id" est bien transmis, on prend le site)
if (isset($_POST['ajouter'])) {
    $stmt = $pdo->prepare("INSERT INTO cameras (site_id, nom, reference, localisation) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_POST['site_id'],
        encrypt_data($_POST['nom']),
        encrypt_data($_POST['reference']),
        encrypt_data($_POST['localisation'])
    ]);
}

// Suppression d'une caméra
if (isset($_POST['supprimer'])) {
    $stmt = $pdo->prepare("DELETE FROM cameras WHERE id=?");
    $stmt->execute([$_POST['id']]);
}

// Caméras liées à ce client (via ses sites)
$cameras = [];
if ($client_id) {
    $cameras_stmt = $pdo->prepare(
        "SELECT c.*, s.nom AS site_nom
         FROM cameras c
         JOIN sites s ON c.site_id = s.id
         WHERE s.client_id = ?"
    );
    $cameras_stmt->execute([$client_id]);
    $cameras = $cameras_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Gestion des Caméras</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main>
<h2>Gestion des Caméras</h2>
<!-- Sélection du client -->
<form method="post">
    <label>Choisir un client :</label>
    <select name="client_id" onchange="this.form.submit();" required>
        <option value="">Sélectionner…</option>
        <?php foreach ($clients as $client): ?>
            <option value="<?= $client['id'] ?>" <?= ($client_id == $client['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars(decrypt_data($client['nom'])) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<!-- Ajout d'une caméra (si client choisi et sites disponibles) -->
<?php if ($client_id && $sites): ?>
<form method="post">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <label>Site du client :</label>
    <select name="site_id" required>
        <option value="">Choisir un site…</option>
        <?php foreach ($sites as $site): ?>
            <option value="<?= $site['id'] ?>"><?= htmlspecialchars(decrypt_data($site['nom'])) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="nom" placeholder="Nom caméra" required>
    <input type="text" name="reference" placeholder="Référence">
    <input type="text" name="localisation" placeholder="Localisation">
    <button type="submit" name="ajouter">Ajouter la caméra</button>
</form>
<?php endif; ?>

<!-- Tableau des caméras du client sélectionné -->
<?php if ($client_id): ?>
<table>
    <tr>
        <th>Site</th>
        <th>Nom caméra</th>
        <th>Référence</th>
        <th>Localisation</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($cameras as $cam): ?>
    <tr>
        <td><?= htmlspecialchars(decrypt_data($cam['site_nom'])) ?></td>
        <td><?= htmlspecialchars(decrypt_data($cam['nom'])) ?></td>
        <td><?= htmlspecialchars(decrypt_data($cam['reference'])) ?></td>
        <td><?= htmlspecialchars(decrypt_data($cam['localisation'])) ?></td>
        <td>
            <form method="post" style="display:inline;">
                <input type="hidden" name="id" value="<?= $cam['id'] ?>">
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <button type="submit" name="supprimer">Supprimer</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
</main>
<?php include('footer.php'); ?>
</body>
</html>
