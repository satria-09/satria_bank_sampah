<?php /* butuh: $judul, $warna (opsional) */ $warna = $warna ?? 'success'; ?>
<!doctype html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> - Bank Sampah Satria</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-<?= e($warna) ?> mb-4">
  <div class="container">
    <span class="navbar-brand fw-bold">♻ Bank Sampah Satria — <?= e($judul) ?></span>
    <div class="d-flex align-items-center text-white gap-3">
      <span class="small"><?= e($_SESSION['nama']) ?> (<?= e($_SESSION['kategori'] ?? $_SESSION['role']) ?>)</span>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">Keluar</a>
    </div>
  </div>
</nav>
<div class="container pb-5">
<?= flash_show() ?>
