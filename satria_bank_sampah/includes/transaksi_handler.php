<?php
/**
 * Database handler: semua logika saldo & stok ada di sini.
 * Setiap proses memakai transaksi DB + SELECT ... FOR UPDATE agar aman dari race condition.
 */

function buat_transaksi(PDO $pdo, array $d): int {
    $st = $pdo->prepare(
        "INSERT INTO transaksi (id_user,id_sampah,id_pengepul,tipe,berat_kg,total_rp,status,tanggal)
         VALUES (:u,:s,:p,:t,:b,:r,:st,CURDATE())"
    );
    $st->execute([
        ':u' => $d['id_user'], ':s' => $d['id_sampah'] ?? null, ':p' => $d['id_pengepul'] ?? null,
        ':t' => $d['tipe'], ':b' => $d['berat_kg'] ?? 0, ':r' => $d['total_rp'],
        ':st' => $d['status'] ?? 'menunggu',
    ]);
    return (int)$pdo->lastInsertId();
}

function ambil_sampah(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM sampah WHERE id_sampah=?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function ambil_id_pengepul(PDO $pdo, int $idUser): ?int {
    $st = $pdo->prepare("SELECT id_pengepul FROM pengepul WHERE id_user=?");
    $st->execute([$idUser]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int)$v;
}

function ubah_saldo(PDO $pdo, int $idUser, float $delta, string $label = 'Saldo'): void {
    $st = $pdo->prepare("SELECT saldo FROM user WHERE id_user=? FOR UPDATE");
    $st->execute([$idUser]);
    $saldo = $st->fetchColumn();
    if ($saldo === false) throw new RuntimeException('User tidak ditemukan.');
    if ($saldo + $delta < 0) throw new RuntimeException($label . ' tidak mencukupi.');
    $pdo->prepare("UPDATE user SET saldo = saldo + ? WHERE id_user=?")->execute([$delta, $idUser]);
}

function ubah_stok(PDO $pdo, int $idSampah, float $delta): void {
    $st = $pdo->prepare("SELECT stok_kg FROM sampah WHERE id_sampah=? FOR UPDATE");
    $st->execute([$idSampah]);
    $stok = $st->fetchColumn();
    if ($stok === false) throw new RuntimeException('Jenis sampah tidak ditemukan.');
    if ($stok + $delta < 0) throw new RuntimeException('Stok sampah tidak mencukupi.');
    $pdo->prepare("UPDATE sampah SET stok_kg = stok_kg + ? WHERE id_sampah=?")->execute([$delta, $idSampah]);
}

/** Admin menyetujui / menolak transaksi berstatus 'menunggu'. */
function proses_persetujuan(PDO $pdo, int $idTransaksi, string $aksi, int $idAdmin): string {
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT * FROM transaksi WHERE id_transaksi=? FOR UPDATE");
        $st->execute([$idTransaksi]);
        $t = $st->fetch();
        if (!$t) throw new RuntimeException('Transaksi tidak ditemukan.');
        if ($t['status'] !== 'menunggu') throw new RuntimeException('Transaksi sudah diproses.');

        if ($aksi === 'tolak') {
            $pdo->prepare("UPDATE transaksi SET status='ditolak' WHERE id_transaksi=?")->execute([$idTransaksi]);
            $pdo->commit();
            return 'Transaksi #' . $idTransaksi . ' ditolak.';
        }

        $total = (float)$t['total_rp'];
        $berat = (float)$t['berat_kg'];

        switch ($t['tipe']) {
            case 'setor':   // nasabah setor: saldo admin - (sumber dana), saldo nasabah +, stok +
                ubah_saldo($pdo, $idAdmin, -$total, 'Saldo admin');
                ubah_saldo($pdo, (int)$t['id_user'], +$total);
                ubah_stok($pdo, (int)$t['id_sampah'], +$berat);
                break;
            case 'jual':    // pengepul beli: stok -, saldo pengepul -
                ubah_stok($pdo, (int)$t['id_sampah'], -$berat);
                ubah_saldo($pdo, (int)$t['id_user'], -$total);
                break;
            case 'tarik':   // tarik tunai: saldo nasabah -, saldo admin - (sumber dana)
                ubah_saldo($pdo, (int)$t['id_user'], -$total);
                ubah_saldo($pdo, $idAdmin, -$total, 'Saldo admin');
                break;
            case 'topup':   // top up pengepul/user: saldo +
                ubah_saldo($pdo, (int)$t['id_user'], +$total);
                break;
            default: throw new RuntimeException('Tipe transaksi tidak dikenal.');
        }

        $pdo->prepare("UPDATE transaksi SET status='disetujui' WHERE id_transaksi=?")->execute([$idTransaksi]);
        $pdo->commit();
        return 'Transaksi #' . $idTransaksi . ' disetujui.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Admin top up langsung (otomatis disetujui). */
function topup_langsung(PDO $pdo, int $idUser, float $jumlah): void {
    if ($jumlah <= 0) throw new RuntimeException('Jumlah harus lebih dari 0.');
    $pdo->beginTransaction();
    try {
        ubah_saldo($pdo, $idUser, +$jumlah);
        buat_transaksi($pdo, [
            'id_user' => $idUser, 'tipe' => 'topup', 'total_rp' => $jumlah, 'status' => 'disetujui',
            'id_pengepul' => ambil_id_pengepul($pdo, $idUser),
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function riwayat_user(PDO $pdo, int $idUser, int $limit = 50): array {
    $st = $pdo->prepare(
        "SELECT t.*, s.jenis_sampah FROM transaksi t
         LEFT JOIN sampah s ON s.id_sampah=t.id_sampah
         WHERE t.id_user=? ORDER BY t.id_transaksi DESC LIMIT " . (int)$limit
    );
    $st->execute([$idUser]);
    return $st->fetchAll();
}

function ambil_saldo(PDO $pdo, int $idUser): float {
    $st = $pdo->prepare("SELECT saldo FROM user WHERE id_user=?");
    $st->execute([$idUser]);
    return (float)$st->fetchColumn();
}
