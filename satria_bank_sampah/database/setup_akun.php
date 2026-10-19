<?php
/**
 * Jalankan SEKALI (browser: /satria/database/setup_akun.php atau CLI: php setup_akun.php),
 * lalu HAPUS file ini. Membuat akun nasabah kategori murid & guru.
 * - Murid : username satria / password satria123
 * - Guru  : username guru   / password guru123
 */
require __DIR__ . '/../koneksi.php';

// pastikan kolom kategori ada (untuk database lama)
$ada = $pdo->query("SHOW COLUMNS FROM user LIKE 'kategori'")->fetch();
if (!$ada) $pdo->exec("ALTER TABLE user ADD kategori ENUM('murid','guru') NULL DEFAULT NULL AFTER role");

$akun = [
    ['Satria (Murid)', 'satria', 'satria123', 'murid'],
    ['Guru',           'guru',   'guru123',   'guru'],
];
$cek = $pdo->prepare("SELECT 1 FROM user WHERE username=?");
$ins = $pdo->prepare("INSERT INTO user (nama,username,password,role,kategori,saldo) VALUES (?,?,?,'nasabah',?,0)");
header('Content-Type: text/plain; charset=utf-8');
foreach ($akun as [$nama, $user, $pass, $kat]) {
    $cek->execute([$user]);
    if ($cek->fetch()) { echo "Lewati: $user sudah ada\n"; continue; }
    $ins->execute([$nama, $user, password_hash($pass, PASSWORD_DEFAULT), $kat]);
    echo "Dibuat: $user ($kat)\n";
}
echo "\nSelesai. Hapus file setup_akun.php ini.\n";
