<?php
require __DIR__ . '/../cek_session.php';
require_role('pengepul');
$idUser = (int)$_SESSION['id_user'];
$idPengepul = ambil_id_pengepul($pdo, $idUser);
if (!$idPengepul) exit('Profil pengepul belum dibuat. Hubungi admin.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['aksi'] ?? '') === 'topup') {
            // Top up langsung: saldo bertambah & transaksi tercatat 'disetujui' tanpa acc admin
            topup_langsung($pdo, $idUser, (float)$_POST['jumlah']);
            flash('success', 'Top up berhasil, saldo sudah bertambah.');
        } elseif (($_POST['aksi'] ?? '') === 'beli') {
            $s = ambil_sampah($pdo, (int)$_POST['id_sampah']);
            $berat = (float)$_POST['berat'];
            if (!$s || $berat <= 0) throw new RuntimeException('Data pembelian tidak valid.');
            if ($berat > $s['stok_kg']) throw new RuntimeException('Stok tidak mencukupi (tersedia ' . $s['stok_kg'] . ' kg).');
            $total = $berat * $s['harga_per_kg'];
            if ($total > ambil_saldo($pdo, $idUser)) throw new RuntimeException('Saldo tidak cukup. Silakan top up dahulu.');
            buat_transaksi($pdo, ['id_user' => $idUser, 'id_pengepul' => $idPengepul, 'id_sampah' => $s['id_sampah'],
                'tipe' => 'jual', 'berat_kg' => $berat, 'total_rp' => $total]);
            flash('success', 'Pembelian diajukan, menunggu persetujuan admin.');
        }
    } catch (Throwable $e) { flash('danger', $e->getMessage()); }
    redirect('dashboard.php');
}

$saldo   = ambil_saldo($pdo, $idUser);
$sampah  = $pdo->query("SELECT * FROM sampah ORDER BY jenis_sampah")->fetchAll();
$riwayat = riwayat_user($pdo, $idUser);

$judul = 'Dashboard Pengepul'; $warna = 'primary';
require __DIR__ . '/../includes/header.php';
?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
  <div class="text-muted small">Saldo Anda</div><div class="display-6 fw-bold text-primary"><?= rupiah($saldo) ?></div>
</div></div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body">
  <h5>Stok Sampah Tersedia</h5>
  <div class="table-responsive"><table class="table table-sm">
    <thead class="table-light"><tr><th>Jenis</th><th class="text-end">Harga/kg</th><th class="text-end">Stok (kg)</th></tr></thead><tbody>
    <?php foreach ($sampah as $s): ?>
      <tr><td><?= e($s['jenis_sampah']) ?></td><td class="text-end"><?= rupiah($s['harga_per_kg']) ?></td><td class="text-end"><?= e($s['stok_kg']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div></div>

<div class="row g-4 mb-4">
  <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h5>Beli Sampah</h5>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="beli">
      <div class="mb-2"><label class="form-label small">Jenis Sampah</label>
        <select name="id_sampah" class="form-select" required>
          <?php foreach ($sampah as $s): ?><option value="<?= $s['id_sampah'] ?>"><?= e($s['jenis_sampah']) ?> (stok <?= e($s['stok_kg']) ?> kg)</option><?php endforeach; ?>
        </select></div>
      <div class="mb-3"><label class="form-label small">Berat (kg)</label>
        <input name="berat" type="number" step="0.01" min="0.01" class="form-control" required></div>
      <button class="btn btn-primary w-100">Ajukan Pembelian</button>
    </form></div></div></div>
  <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h5>Top Up Saldo</h5>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="topup">
      <div class="mb-3"><label class="form-label small">Jumlah (Rp)</label>
        <input name="jumlah" type="number" min="1" class="form-control" required></div>
      <button class="btn btn-warning w-100">Top Up Sekarang</button>
    </form></div></div></div>
</div>

<div class="card border-0 shadow-sm"><div class="card-body">
  <h5>Riwayat Transaksi</h5>
  <div class="table-responsive"><table class="table table-sm">
    <thead class="table-light"><tr><th>Tanggal</th><th>Tipe</th><th>Sampah</th><th class="text-end">Berat</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($riwayat as $t): ?>
      <tr><td><?= e($t['tanggal']) ?></td><td><?= e(TIPE_LABEL[$t['tipe']]) ?></td><td><?= e($t['jenis_sampah'] ?? '-') ?></td>
        <td class="text-end"><?= $t['berat_kg'] > 0 ? e($t['berat_kg']) . ' kg' : '-' ?></td>
        <td class="text-end"><?= rupiah($t['total_rp']) ?></td><td><?= badge_status($t['status']) ?></td></tr>
    <?php endforeach; if (!$riwayat): ?><tr><td colspan="6" class="text-center text-muted">Belum ada transaksi.</td></tr><?php endif; ?>
    </tbody></table></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php';
