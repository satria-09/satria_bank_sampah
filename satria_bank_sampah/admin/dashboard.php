<?php
require __DIR__ . '/../cek_session.php';
require_role('admin');
$idAdmin = (int)$_SESSION['id_user'];

/* ---------- Aksi POST (Post-Redirect-Get) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        switch ($_POST['aksi'] ?? '') {
            case 'tambah_sampah':
                $pdo->prepare("INSERT INTO sampah (jenis_sampah,harga_per_kg,stok_kg) VALUES (?,?,?)")
                    ->execute([trim($_POST['jenis']), max(0, (float)$_POST['harga']), max(0, (float)$_POST['stok'])]);
                flash('success', 'Jenis sampah ditambahkan.'); break;
            case 'edit_sampah':
                $pdo->prepare("UPDATE sampah SET jenis_sampah=?, harga_per_kg=?, stok_kg=? WHERE id_sampah=?")
                    ->execute([trim($_POST['jenis']), max(0, (float)$_POST['harga']), max(0, (float)$_POST['stok']), (int)$_POST['id']]);
                flash('success', 'Data sampah diperbarui.'); break;
            case 'hapus_sampah':
                try {
                    $pdo->prepare("DELETE FROM sampah WHERE id_sampah=?")->execute([(int)$_POST['id']]);
                    flash('success', 'Jenis sampah dihapus.');
                } catch (PDOException $e) {
                    flash('danger', 'Tidak bisa dihapus: sudah dipakai di transaksi.');
                }
                break;
            case 'topup':
                topup_langsung($pdo, $idAdmin, (float)$_POST['jumlah']);   // hanya saldo admin sendiri
                flash('success', 'Saldo admin berhasil ditambah.'); break;
            case 'setujui':
            case 'tolak':
                flash('success', proses_persetujuan($pdo, (int)$_POST['id'], $_POST['aksi'] === 'tolak' ? 'tolak' : 'setujui', $idAdmin));
                break;
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('dashboard.php' . (isset($_GET['range']) ? '?range=' . (int)$_GET['range'] : ''));
}

/* ---------- Data grafik ---------- */
$range = in_array((int)($_GET['range'] ?? 30), [1, 15, 30, 365], true) ? (int)($_GET['range'] ?? 30) : 30;
$fmt   = $range >= 365 ? '%Y-%m' : '%Y-%m-%d';           // whitelist, aman diinline
$since = date('Y-m-d', strtotime('-' . ($range - 1) . ' days'));
$st = $pdo->prepare(
    "SELECT DATE_FORMAT(tanggal,'$fmt') AS p, tipe, SUM(total_rp) AS total
     FROM transaksi WHERE status='disetujui' AND tanggal>=? GROUP BY p, tipe ORDER BY p"
);
$st->execute([$since]);
$rows = $st->fetchAll();
$labels = array_values(array_unique(array_column($rows, 'p')));
$sets = ['setor' => [], 'jual' => [], 'tarik' => [], 'topup' => []];
foreach ($sets as $tipe => &$arr) {
    foreach ($labels as $l) {
        $v = 0;
        foreach ($rows as $r) if ($r['p'] === $l && $r['tipe'] === $tipe) $v = (float)$r['total'];
        $arr[] = $v;
    }
}
unset($arr);
$warnaChart = ['setor' => '#198754', 'jual' => '#0d6efd', 'tarik' => '#dc3545', 'topup' => '#ffc107'];
$datasets = [];
foreach ($sets as $tipe => $data) $datasets[] = ['label' => TIPE_LABEL[$tipe], 'data' => $data, 'backgroundColor' => $warnaChart[$tipe]];

/* ---------- Data tabel ---------- */
$saldoAdmin = ambil_saldo($pdo, $idAdmin);
$sampah     = $pdo->query("SELECT * FROM sampah ORDER BY jenis_sampah")->fetchAll();
$menunggu   = $pdo->query(
    "SELECT t.*, u.nama, u.saldo, s.jenis_sampah FROM transaksi t
     JOIN user u ON u.id_user=t.id_user LEFT JOIN sampah s ON s.id_sampah=t.id_sampah
     WHERE t.status='menunggu' ORDER BY t.id_transaksi")->fetchAll();
$riwayat    = $pdo->query(
    "SELECT t.*, u.nama, s.jenis_sampah FROM transaksi t
     JOIN user u ON u.id_user=t.id_user LEFT JOIN sampah s ON s.id_sampah=t.id_sampah
     WHERE t.status<>'menunggu' ORDER BY t.id_transaksi DESC LIMIT 15")->fetchAll();

$judul = 'Dashboard Admin';
$warna = 'success';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">Saldo Utama Admin</div><div class="fs-4 fw-bold text-success"><?= rupiah($saldoAdmin) ?></div></div></div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">Menunggu Persetujuan</div><div class="fs-4 fw-bold text-warning"><?= count($menunggu) ?></div></div></div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">Total Stok Sampah</div><div class="fs-4 fw-bold"><?= number_format(array_sum(array_column($sampah, 'stok_kg')), 2, ',', '.') ?> kg</div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Statistik Transaksi (Disetujui)</h5>
    <div class="btn-group btn-group-sm">
      <?php foreach ([1 => '1 Hari', 15 => '15 Hari', 30 => '1 Bulan', 365 => '1 Tahun'] as $k => $v): ?>
        <a href="?range=<?= $k ?>" class="btn btn-outline-success <?= $range === $k ? 'active' : '' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if (!$labels): ?><p class="text-muted mb-0">Belum ada transaksi disetujui pada rentang ini.</p>
  <?php else: ?><canvas id="grafik" height="90"></canvas><?php endif; ?>
</div></div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body">
  <h5>Persetujuan Transaksi</h5>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead class="table-light"><tr><th>#</th><th>Tanggal</th><th>User</th><th>Tipe</th><th>Sampah</th><th class="text-end">Berat</th><th class="text-end">Total</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($menunggu as $t): ?>
      <tr>
        <td><?= $t['id_transaksi'] ?></td><td><?= e($t['tanggal']) ?></td><td><?= e($t['nama']) ?></td>
        <td><?= e(TIPE_LABEL[$t['tipe']]) ?></td><td><?= e($t['jenis_sampah'] ?? '-') ?></td>
        <td class="text-end"><?= $t['berat_kg'] > 0 ? e($t['berat_kg']) . ' kg' : '-' ?></td>
        <td class="text-end"><?= rupiah($t['total_rp']) ?></td>
        <td>
          <form method="post" class="d-flex gap-1">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $t['id_transaksi'] ?>">
            <button name="aksi" value="setujui" class="btn btn-success btn-sm" onclick="return confirm('Setujui transaksi ini?')">Setujui</button>
            <button name="aksi" value="tolak" class="btn btn-outline-danger btn-sm" onclick="return confirm('Tolak transaksi ini?')">Tolak</button>
          </form>
        </td>
      </tr>
    <?php endforeach; if (!$menunggu): ?><tr><td colspan="8" class="text-center text-muted">Tidak ada transaksi menunggu.</td></tr><?php endif; ?>
    </tbody></table></div>
</div></div>

<div class="row g-4 mb-4">
  <div class="col-lg-8"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h5>Manajemen Sampah</h5>
    <form method="post" class="row g-2 mb-3">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah_sampah">
      <div class="col-sm-4"><input name="jenis" class="form-control form-control-sm" placeholder="Jenis sampah" required></div>
      <div class="col-sm-3"><input name="harga" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="Harga/kg" required></div>
      <div class="col-sm-3"><input name="stok" type="number" step="0.01" min="0" value="0" class="form-control form-control-sm" placeholder="Stok kg"></div>
      <div class="col-sm-2"><button class="btn btn-success btn-sm w-100">Tambah</button></div>
    </form>
    <div class="table-responsive"><table class="table table-sm align-middle">
      <thead class="table-light"><tr><th>Jenis</th><th>Harga/kg</th><th>Stok (kg)</th><th></th></tr></thead><tbody>
      <?php foreach ($sampah as $s): $fid = 'f' . $s['id_sampah']; ?>
        <tr>
          <td><form id="<?= $fid ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id_sampah'] ?>"></form>
              <input form="<?= $fid ?>" name="jenis" value="<?= e($s['jenis_sampah']) ?>" class="form-control form-control-sm" required></td>
          <td><input form="<?= $fid ?>" name="harga" type="number" step="0.01" min="0" value="<?= e($s['harga_per_kg']) ?>" class="form-control form-control-sm"></td>
          <td><input form="<?= $fid ?>" name="stok" type="number" step="0.01" min="0" value="<?= e($s['stok_kg']) ?>" class="form-control form-control-sm"></td>
          <td class="text-nowrap">
            <button form="<?= $fid ?>" name="aksi" value="edit_sampah" class="btn btn-primary btn-sm">Simpan</button>
            <button form="<?= $fid ?>" name="aksi" value="hapus_sampah" class="btn btn-outline-danger btn-sm" onclick="return confirm('Hapus jenis sampah ini?')">Hapus</button>
          </td></tr>
      <?php endforeach; ?></tbody></table></div>
  </div></div></div>

  <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
    <h5>Tambah Saldo Admin</h5>
    <p class="small text-muted">Saldo saat ini: <strong><?= rupiah($saldoAdmin) ?></strong></p>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="topup">
      <div class="mb-3"><label class="form-label small">Jumlah (Rp)</label>
        <input name="jumlah" type="number" min="1" step="1" class="form-control form-control-sm" required></div>
      <button class="btn btn-warning btn-sm w-100">Tambah Saldo</button>
    </form>
  </div></div></div>
</div>

<div class="card border-0 shadow-sm"><div class="card-body">
  <h5>Riwayat Terakhir</h5>
  <div class="table-responsive"><table class="table table-sm">
    <thead class="table-light"><tr><th>#</th><th>Tanggal</th><th>User</th><th>Tipe</th><th>Sampah</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($riwayat as $t): ?>
      <tr><td><?= $t['id_transaksi'] ?></td><td><?= e($t['tanggal']) ?></td><td><?= e($t['nama']) ?></td>
        <td><?= e(TIPE_LABEL[$t['tipe']]) ?></td><td><?= e($t['jenis_sampah'] ?? '-') ?></td>
        <td class="text-end"><?= rupiah($t['total_rp']) ?></td><td><?= badge_status($t['status']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div></div>

<?php if ($labels): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('grafik'), {
  type: 'bar',
  data: { labels: <?= json_encode($labels) ?>, datasets: <?= json_encode($datasets) ?> },
  options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + v.toLocaleString('id-ID') } } } }
});
</script>
<?php endif;
require __DIR__ . '/../includes/footer.php';
