<?php


function encrypt_data($plaintext) {
    require 'conf/conf.php';
    $cipher = "AES-256-CBC";
    // Génère la clé (32 bytes pour AES-256)
    $key = hash('sha256', $passphrase, true);
    // Génère un IV unique pour chaque data (16 bytes)
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    // Chiffre
    $encrypted = openssl_encrypt($plaintext, $cipher, $key, 0, $iv);
    // On stocke l'IV avec la donnée (base64 pour transport)
    return base64_encode($iv . $encrypted);
}

function decrypt_data($encrypted_data) {
    require 'conf/conf.php';
    $cipher = "AES-256-CBC";
    $key = hash('sha256', $passphrase, true);
    $iv_len = openssl_cipher_iv_length($cipher);
    $b64 = base64_decode($encrypted_data);
    // Découpe correctement IV (16 bytes)
    $iv = substr($b64, 0, $iv_len);
    $ciphertext = substr($b64, $iv_len);
    return openssl_decrypt($ciphertext, $cipher, $key, 0, $iv);
}
?>