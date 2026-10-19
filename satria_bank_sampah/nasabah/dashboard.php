<?php
require __DIR__ . '/../cek_session.php';
require_role('nasabah');
$idUser = (int)$_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['aksi'] ?? '') === 'setor') {
            $s = ambil_sampah($pdo, (int)$_POST['id_sampah']);
            $berat = (float)$_POST['berat'];
            if (!$s || $berat <= 0) throw new RuntimeException('Data setoran tidak valid.');
            buat_transaksi($pdo, ['id_user' => $idUser, 'id_sampah' => $s['id_sampah'], 'tipe' => 'setor',
                'berat_kg' => $berat, 'total_rp' => $berat * $s['harga_per_kg']]);
            flash('success', 'Setoran diajukan, menunggu persetujuan admin.');
        } elseif (($_POST['aksi'] ?? '') === 'tarik') {
            $jumlah = (float)$_POST['jumlah'];
            if ($jumlah <= 0) throw new RuntimeException('Jumlah tidak valid.');
            if ($jumlah > ambil_saldo($pdo, $idUser)) throw new RuntimeException('Saldo tidak mencukupi.');
            buat_transaksi($pdo, ['id_user' => $idUser, 'tipe' => 'tarik', 'total_rp' => $jumlah]);
            flash('success', 'Penarikan diajukan, menunggu persetujuan admin.');
        }
    } catch (Throwable $e) { flash('danger', $e->getMessage()); }
    redirect('dashboard.php');
}

$saldo   = ambil_saldo($pdo, $idUser);
$sampah  = $pdo->query("SELECT * FROM sampah ORDER BY jenis_sampah")->fetchAll();
$riwayat = riwayat_user($pdo, $idUser);

$judul = 'Dashboard Nasabah'; $warna = 'success';
require __DIR__ . '/../includes/header.php';
?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
  <div class="text-muted small">Saldo Anda</div><div class="display-6 fw-bold text-success"><?= rupiah($saldo) ?></div>
</div></div>

<div class="row g-4 mb-4">
  <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h5>Setor Sampah</h5>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="setor">
      <div class="mb-2"><label class="form-label small">Jenis Sampah</label>
        <select name="id_sampah" class="form-select" required>
          <?php foreach ($sampah as $s): ?><option value="<?= $s['id_sampah'] ?>"><?= e($s['jenis_sampah']) ?> — <?= rupiah($s['harga_per_kg']) ?>/kg</option><?php endforeach; ?>
        </select></div>
      <div class="mb-3"><label class="form-label small">Estimasi Berat (kg)</label>
        <input name="berat" type="number" step="0.01" min="0.01" class="form-control" required></div>
      <button class="btn btn-success w-100">Ajukan Setoran</button>
    </form></div></div></div>
  <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h5>Tarik Saldo</h5>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="tarik">
      <div class="mb-3"><label class="form-label small">Jumlah Penarikan (Rp)</label>
        <input name="jumlah" type="number" min="1" max="<?= (int)$saldo ?>" class="form-control" required></div>
      <button class="btn btn-primary w-100">Ajukan Penarikan</button>
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
