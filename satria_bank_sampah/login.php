<?php
require __DIR__ . '/cek_session.php';
if (!empty($_SESSION['role'])) redirect($_SESSION['role'] . '/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = $pdo->prepare("SELECT * FROM user WHERE username=?");
    $st->execute([trim($_POST['username'] ?? '')]);
    $u = $st->fetch();

    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['id_user'] = (int)$u['id_user'];
        $_SESSION['nama']    = $u['nama'];
        $_SESSION['role']    = $u['role'];
        $_SESSION['kategori'] = $u['kategori'];
        redirect($u['role'] . '/dashboard.php');
    }
    $error = 'Username atau password salah.';
}
?>
<!doctype html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Bank Sampah Satria</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-success-subtle d-flex align-items-center min-vh-100">
<div class="container"><div class="row justify-content-center"><div class="col-md-5 col-lg-4">
  <div class="card shadow border-0"><div class="card-body p-4">
    <h4 class="text-center text-success fw-bold mb-1">♻ Bank Sampah Satria</h4>
    <p class="text-center text-muted small mb-4">Silakan masuk untuk melanjutkan</p>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-success w-100">Masuk</button>
    </form>
  </div></div>
</div></div></div>
</body></html>
