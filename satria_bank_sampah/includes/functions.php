<?php
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rupiah($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Token CSRF tidak valid.');
    }
}
function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }
function flash_show(): string {
    if (empty($_SESSION['flash'])) return '';
    [$t, $m] = $_SESSION['flash']; unset($_SESSION['flash']);
    return '<div class="alert alert-' . e($t) . ' alert-dismissible fade show">' . e($m) .
           '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
function redirect(string $to): never { header('Location: ' . $to); exit; }

const TIPE_LABEL = [
    'setor' => 'Setor Sampah', 'jual' => 'Beli Sampah (Pengepul)',
    'tarik' => 'Tarik Saldo',  'topup' => 'Top Up',
];
function badge_status(string $s): string {
    $c = ['menunggu' => 'warning text-dark', 'disetujui' => 'success', 'ditolak' => 'danger'][$s] ?? 'secondary';
    return '<span class="badge bg-' . $c . '">' . e(ucfirst($s)) . '</span>';
}
