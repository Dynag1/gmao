<?php
session_start();
require '../connexion.php';
require '../crypt.php';
include('../header.php');
echo('<link rel="stylesheet" href="../style.css">');


if ($_SESSION['role'] !== 'admin') header('Location: dashboard.php');

// ----- AJOUT CLIENT -------
if (isset($_POST['ajout_client'])) {
    $stmt = $pdo->prepare("INSERT INTO clients (nom) VALUES (?)");
    $stmt->execute([encrypt_data($_POST['client_nom'])]);
    header("Location: admin_users.php");
    exit;
}

// ----- AJOUT USER -------
if (isset($_POST['ajouter'])) {
    $hashed = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (nom, email, mot_de_passe, role, client_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        encrypt_data($_POST['nom']),
        $_POST['email'],
        $hashed,
        $_POST['role'],
        $_POST['client_id']
    ]);
    header("Location: admin_users.php");
    exit;
}

// ----- SUPPRESSION USER -------
if (isset($_POST['delete_id'])) {
    $user_id_to_delete = $_POST['delete_id'];
    // (optionnel : supprimer les tickets/messages liés à cet user)
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id_to_delete]);
    header("Location: admin_users.php");
    exit;
}

// ----- SUPPRESSION CLIENT -------
if (isset($_POST['delete_client_id'])) {
    $client_id_to_delete = $_POST['delete_client_id'];
    // Cascade : supprimer tout ce qui concerne le client
    $pdo->prepare("DELETE FROM messages WHERE panne_id IN (SELECT id FROM pannes WHERE client_id = ?)")->execute([$client_id_to_delete]);
    $pdo->prepare("DELETE FROM pannes WHERE client_id = ?")->execute([$client_id_to_delete]);
    $pdo->prepare("DELETE FROM sites WHERE client_id = ?")->execute([$client_id_to_delete]);
    $pdo->prepare("DELETE FROM users WHERE client_id = ?")->execute([$client_id_to_delete]);
    $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$client_id_to_delete]);
    header("Location: admin_users.php");
    exit;
}

// ----- EDITION : USER -------
$edit_user = null;
if (isset($_POST['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_POST['edit_id']]);
    $edit_user = $stmt->fetch();
}
if (isset($_POST['update'])) {
    $new_nom = encrypt_data($_POST['nom']);
    $new_email = $_POST['email'];
    $new_role = $_POST['role'];
    $new_client_id = $_POST['client_id'];
    $update_sql = "UPDATE users SET nom=?, email=?, role=?, client_id=? WHERE id=?";
    $update_params = [$new_nom, $new_email, $new_role, $new_client_id, $_POST['user_id']];
    if (!empty($_POST['mot_de_passe'])) {
        $update_sql = "UPDATE users SET nom=?, email=?, role=?, client_id=?, mot_de_passe=? WHERE id=?";
        $hashed = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
        $update_params = [$new_nom, $new_email, $new_role, $new_client_id, $hashed, $_POST['user_id']];
    }
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute($update_params);
    header("Location: admin_users.php");
    exit;
}

// ----- EDITION : CLIENT -------
$edit_client = null;
if (isset($_POST['edit_client_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$_POST['edit_client_id']]);
    $edit_client = $stmt->fetch();
}
if (isset($_POST['update_client'])) {
    $stmt = $pdo->prepare("UPDATE clients SET nom=? WHERE id=?");
    $stmt->execute([encrypt_data($_POST['nom']), $_POST['client_id']]);
    header("Location: admin_users.php");
    exit;
}

// ----- AFFICHAGE LISTE -----
$clients = $pdo->query("SELECT * FROM clients")->fetchAll();
$users = $pdo->query("SELECT * FROM users")->fetchAll();
?>

<main>
<h2>Gestion des Clients</h2>
<?php if ($edit_client): ?>
<form method="post">
    <input type="hidden" name="client_id" value="<?= $edit_client['id'] ?>">
    <input type="text" name="nom" value="<?= htmlspecialchars(decrypt_data($edit_client['nom'])) ?>" required>
    <button name="update_client">Mettre à jour le client</button>
    <a href="admin_users.php" class="btn-cancel">Annuler</a>
</form>
<?php else: ?>
<form method="post">
    <input type="text" name="client_nom" placeholder="Nom du client" required>
    <button name="ajout_client">Créer un client</button>
</form>
<?php endif; ?>

<table>
    <tr><th>Nom</th><th>Actions</th></tr>
    <?php foreach($clients as $cl): ?>
    <tr>
        <td><?= htmlspecialchars(decrypt_data($cl['nom'])) ?></td>
        <td>
            <form method="post" style="display:inline;">
                <input type="hidden" name="delete_client_id" value="<?= $cl['id'] ?>">
                <button type="submit" onclick="return confirm('Supprimer le client et toutes ses données ?');">Supprimer</button>
            </form>
            <form method="post" style="display:inline;">
                <input type="hidden" name="edit_client_id" value="<?= $cl['id'] ?>">
                <button type="submit">Modifier</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<hr>
<h2>Gestion des Utilisateurs</h2>
<?php if ($edit_user): ?>
<form method="post" class="edit-form">
    <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
    <input type="text" name="nom" value="<?= htmlspecialchars(decrypt_data($edit_user['nom'])) ?>" required>
    <input type="email" name="email" value="<?= htmlspecialchars($edit_user['email']) ?>" required>
    <input type="password" name="mot_de_passe" placeholder="Nouveau mot de passe (laisser vide si inchangé)">
    <select name="role" required>
        <option value="client" <?= ($edit_user['role']=='client'?'selected':'') ?>>Client</option>
        <option value="admin" <?= ($edit_user['role']=='admin'?'selected':'') ?>>Admin</option>
    </select>
    <select name="client_id" required>
        <?php foreach ($clients as $cl): ?>
        <option value="<?= $cl['id'] ?>" <?= ($edit_user['client_id']==$cl['id']?'selected':'') ?>><?= htmlspecialchars(decrypt_data($cl['nom'])) ?></option>
        <?php endforeach; ?>
    </select>
    <button name="update">Mettre à jour</button>
    <a href="admin_users.php" class="btn-cancel">Annuler</a>
</form>
<?php else: ?>
<form method="post">
    <input type="text" name="nom" placeholder="Nom" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
    <select name="role" required>
        <option value="">Role</option>
        <option value="client">Client</option>
        <option value="admin">Admin</option>
    </select>
    <select name="client_id" required>
        <option value="">Client…</option>
        <?php foreach ($clients as $cl): ?>
        <option value="<?= $cl['id'] ?>"><?= htmlspecialchars(decrypt_data($cl['nom'])) ?></option>
        <?php endforeach; ?>
    </select>
    <button name="ajouter">Créer l'utilisateur</button>
</form>
<?php endif; ?>

<table>
    <tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Client</th><th>Actions</th></tr>
    <?php foreach($users as $u): ?>
    <tr>
        <td><?= htmlspecialchars(decrypt_data($u['nom'])) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= $u['role'] ?></td>
        <td>
            <?php
            $client = array_filter($clients, function($cl) use ($u) { return $cl['id'] == $u['client_id']; });
            if ($client) echo htmlspecialchars(decrypt_data(reset($client)['nom']));
            ?>
        </td>
        <td>
            <form method="post" style="display:inline;">
                <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                <button type="submit" onclick="return confirm('Confirmer la suppression ?');">Supprimer</button>
            </form>
            <form method="post" style="display:inline;">
                <input type="hidden" name="edit_id" value="<?= $u['id'] ?>">
                <button type="submit">Modifier</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</main>
<?php include('../footer.php'); ?>
