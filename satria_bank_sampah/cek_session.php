<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/transaksi_handler.php';

function require_role(string $role): void {
    $root = dirname($_SERVER['SCRIPT_NAME'], 2);
    if (empty($_SESSION['id_user'])) redirect($root . '/login.php');
    if ($_SESSION['role'] !== $role) redirect($root . '/' . $_SESSION['role'] . '/dashboard.php');
}
