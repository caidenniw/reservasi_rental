<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);
$order = $id > 0 ? ambilOrder($id) : null;
if ($id > 0 && !$order) {
    setFlash('danger', 'Pesanan tidak ditemukan.');
    redirect(BASE_URL . '/pages/pesanan_list.php');
}

/* data untuk dropdown */
$units = $db->query('SELECT id, nama_unit, nopol, harga_modal_default, harga_jual_default FROM units WHERE deleted_at IS NULL AND status <> "nonaktif" ORDER BY nama_unit')->fetch_all(MYSQLI_ASSOC);
$drivers = $db->query('SELECT id, nama, hp FROM drivers WHERE deleted_at IS NULL AND status = "aktif" ORDER BY nama')->fetch_all(MYSQLI_ASSOC);
$partners = $db->query('SELECT id, nama FROM partners WHERE deleted_at IS NULL ORDER BY nama')->fetch_all(MYSQLI_ASSOC);
$includes = $db->query('SELECT id, nama, is_default FROM includes ORDER BY urutan, nama')->fetch_all(MYSQLI_ASSOC);
$kotaList = $db->query('SELECT DISTINCT kota FROM orders WHERE deleted_at IS NULL ORDER BY kota')->fetch_all(MYSQLI_ASSOC);
$customerList = $db->query('SELECT id, nama_pesanan FROM customers WHERE deleted_at IS NULL ORDER BY nama_pesanan LIMIT 500')->fetch_all(MYSQLI_ASSOC);

/* nilai form: dari DB (edit) atau kosong (baru) */
$o = $order ?: [];
function fval(array $o, string $k, $d = '') { return $o[$k] ?? $d; }
$items = $order['items'] ?? [];
if (!$items) {
    $items = [['unit_id' => '', 'nopol' => '', 'upgrade' => '', 'driver_id' => '', 'nama_driver' => '', 'harga_modal_per_hari' => '', 'harga_jual_per_hari' => '', 'jumlah_hari' => '', 'catatan' => '', 'partner_id' => '']];
}
$includeTerpilih = [];
foreach (($order['includes'] ?? []) as $inc) {
    if ($inc['include_id']) $includeTerpilih[(int) $inc['include_id']] = (int) $inc['biaya'];
}
$biayaOrder = $order['biaya'] ?? [];
$adaInvoiceAktif = false;
if ($order) {
    foreach ($order['invoices'] as $iv) {
        if ($iv['status'] !== 'batal') { $adaInvoiceAktif = true; break; }
    }
}

$judulHalaman = $order ? 'Ubah Pesanan ' . $order['nomor_order'] : 'Input Pesanan Baru';
$menuAktif = 'input';
include __DIR__ . '/../includes/header.php';

/* satu blok unit (dipakai untuk baris yang sudah ada) */
function renderBlokUnit(array $it, int $i = 0, int $total = 1): void {
    global $units, $drivers, $partners;

    // Guard agar form tetap bersih kalau array item belum lengkap / berasal dari versi lama.
    $hargaModal = $it['harga_modal_per_hari'] ?? '';
    $hargaJual  = $it['harga_jual_per_hari'] ?? '';
?>
                <div class="item-unit">
                    <div class="item-head">
                        <span class="unit-no">Armada / Mobil <?= $i + 1 ?></span>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-unit" style="<?= $total > 1 ? '' : 'display:none;' ?>">Hapus unit</button>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label class="form-label">Driver <span class="wajib">*</span></label>
                            <select class="form-select pilih-driver" name="item_driver_id[]" required>
                                <option value="">-- pilih driver --</option>
                                <?php foreach ($drivers as $dv): ?>
                                    <option value="<?= (int) $dv['id'] ?>" data-nama="<?= e($dv['nama']) ?>" data-hp="<?= e($dv['hp']) ?>"
                                        <?= (int) ($it['driver_id'] ?? 0) === (int) $dv['id'] ? 'selected' : '' ?>>
                                        <?= e($dv['nama']) ?><?= $dv['hp'] ? ' - ' . e($dv['hp']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Nama Driver</label>
                            <input type="text" class="form-control" name="item_nama_driver[]" value="<?= e($it['nama_driver'] ?? '') ?>">
                            <div class="form-text">Terisi otomatis, bisa dikoreksi.</div>
                        </div>
                        <div>
                            <label class="form-label">HP Driver</label>
                            <input type="text" class="form-control" name="item_hp_driver[]" value="<?= e($it['hp_driver'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="form-label">Unit</label>
                            <select class="form-select pilih-unit" name="item_unit_id[]">
                                <option value="">-- pilih unit --</option>
                                <?php foreach ($units as $un): ?>
                                    <option value="<?= (int) $un['id'] ?>"
                                            data-nopol="<?= e($un['nopol']) ?>"
                                            data-modal="<?= (int) $un['harga_modal_default'] ?>"
                                            data-jual="<?= (int) $un['harga_jual_default'] ?>"
                                        <?= (int) ($it['unit_id'] ?? 0) === (int) $un['id'] ? 'selected' : '' ?>>
                                        <?= e($un['nama_unit']) ?> - <?= e($un['nopol']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Nama Unit</label>
                            <input type="text" class="form-control" name="item_nama_unit[]" value="<?= e($it['nama_unit'] ?? '') ?>">
                            <div class="form-text">Terisi otomatis, bisa diketik manual.</div>
                        </div>
                        <div>
                            <label class="form-label">Nomor Polisi</label>
                            <input type="text" class="form-control mono" name="item_nopol[]" value="<?= e($it['nopol'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="form-label">Upgrade</label>
                            <input type="text" class="form-control" name="item_upgrade[]" list="listUpgrade" value="<?= e($it['upgrade'] ?? '') ?>" placeholder="mis: Up Reborn">
                        </div>
                        <div>
                            <label class="form-label">Support By</label>
                            <select class="form-select" name="item_partner_id[]">
                                <option value="">-- tidak ada --</option>
                                <?php foreach ($partners as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($it['partner_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Harga Modal / Hari <span class="text-soft">(internal)</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control" name="item_harga_modal[]" value="<?= $hargaModal !== '' && $hargaModal !== null ? number_format((float) $hargaModal, 0, ',', '.') : '' ?>">
                            </div>
                            <div class="form-text">Subtotal modal: <span class="sub-modal">Rp 0</span></div>
                        </div>
                        <div>
                            <label class="form-label">Harga Jual / Hari</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control" name="item_harga_jual[]" value="<?= $hargaJual !== '' && $hargaJual !== null ? number_format((float) $hargaJual, 0, ',', '.') : '' ?>">
                            </div>
                            <div class="form-text">Subtotal jual: <span class="sub-jual">Rp 0</span></div>
                        </div>
                        <div>
                            <label class="form-label">Hari (per unit)</label>
                            <input type="number" class="form-control" name="item_jumlah_hari[]" value="<?= e($it['jumlah_hari'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="form-label">Catatan Unit</label>
                            <input type="text" class="form-control" name="item_catatan[]" value="<?= e($it['catatan'] ?? '') ?>">
                        </div>
                    </div>
                </div>
<?php
}
?>
<?php if ($adaInvoiceAktif): ?>
    <div class="alert alert-warning">
        Pesanan ini sudah punya <b>invoice yang terbit</b>. Mengubah harga atau jumlah hari di sini
        <b>tidak mengubah invoice yang sudah terbit</b>. Kalau perlu memperbarui dokumen, simpan dulu,
        lalu buka halaman detail dan klik <b>Revisi Invoice</b>.
    </div>
<?php endif; ?>
<form method="post" action="<?= BASE_URL ?>/pages/pesanan_proses.php" id="formPesanan">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="card-box">
        <div class="section-step"><div class="step-no">1</div><div class="step-title">Pelayanan</div></div>
        <div class="form-grid">
            <div>
                <label class="form-label" for="tipe_pelanggan">Tipe Pelanggan <span class="wajib">*</span></label>
                <select class="form-select" id="tipe_pelanggan" name="tipe_pelanggan">
                    <?php foreach (['retail' => 'Retail (Perorangan)', 'corporate' => 'Perusahaan / Instansi', 'RO' => 'Repeat Order'] as $k => $l): ?>
                        <option value="<?= $k ?>" <?= fval($o, 'tipe_pelanggan', 'retail') === $k ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="wilayah_pelayanan">Wilayah Pelayanan <span class="wajib">*</span></label>
                <select class="form-select" id="wilayah_pelayanan" name="wilayah_pelayanan">
                    <option value="dalam_kota" <?= fval($o, 'wilayah_pelayanan', 'dalam_kota') === 'dalam_kota' ? 'selected' : '' ?>>Dalam Kota</option>
                    <option value="luar_kota" <?= fval($o, 'wilayah_pelayanan') === 'luar_kota' ? 'selected' : '' ?>>Luar Kota</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="kota">Kota / Lokasi <span class="wajib">*</span></label>
                <input type="text" class="form-control" id="kota" name="kota" list="listKota" value="<?= e(fval($o, 'kota')) ?>" required>
                <datalist id="listKota">
                    <?php foreach ($kotaList as $kt): ?><option value="<?= e($kt['kota']) ?>"></option><?php endforeach; ?>
                </datalist>
                <div class="form-text">Contoh: "Gunung Sitoli (Nias)".</div>
            </div>
            <div>
                <label class="form-label" for="tujuan">Tujuan / Rute</label>
                <input type="text" class="form-control" id="tujuan" name="tujuan" value="<?= e(fval($o, 'tujuan')) ?>" placeholder="contoh: Bandara - Hotel - Kantor">
                <div class="form-text">Opsional; dipakai untuk kolom Rute di invoice.</div>
            </div>
            <div>
                <label class="form-label" for="tgl_mulai">Tanggal Mulai <span class="wajib">*</span></label>
                <input type="date" class="form-control" id="tgl_mulai" name="tgl_mulai" value="<?= e(fval($o, 'tgl_mulai')) ?>" required>
            </div>
            <div>
                <label class="form-label" for="tgl_finish">Tanggal Selesai <span class="wajib">*</span></label>
                <input type="date" class="form-control" id="tgl_finish" name="tgl_finish" value="<?= e(fval($o, 'tgl_finish')) ?>" required>
            </div>
            <div>
                <label class="form-label" for="jumlah_hari">Jumlah Hari</label>
                <input type="number" class="form-control" id="jumlah_hari" name="jumlah_hari" value="<?= e(fval($o, 'jumlah_hari')) ?>" readonly>
                <div class="form-text">Dihitung otomatis: tanggal selesai - tanggal mulai + 1 (inklusif).</div>
            </div>
            <div>
                <label class="form-label" for="jam">Jam</label>
                <input type="text" class="form-control" id="jam" name="jam" value="<?= e(fval($o, 'jam')) ?>" placeholder="contoh: 08.00 WIB">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="jam_koordinasi" name="jam_koordinasi" value="1" <?= (int) fval($o, 'jam_koordinasi', 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="jam_koordinasi">Koordinasi dengan user</label>
                </div>
            </div>
            <div>
                <label class="form-label" for="standby_point">Standby Point</label>
                <input type="text" class="form-control" id="standby_point" name="standby_point" value="<?= e(fval($o, 'standby_point')) ?>" placeholder="contoh: Bandara Binaka Gunung Sitoli">
            </div>
            <div>
                <label class="form-label" for="flight">Flight</label>
                <input type="text" class="form-control" id="flight" name="flight" value="<?= e(fval($o, 'flight')) ?>" placeholder="- bila tidak ada">
            </div>
        </div>
    </div>

    <div class="card-box">
        <div class="section-step"><div class="step-no">2</div><div class="step-title">Customer & PIC</div></div>
        <div class="form-grid">
            <div>
                <label class="form-label" for="nama_pesanan">Nama Pesanan / Instansi <span class="wajib">*</span></label>
                <input type="text" class="form-control" id="nama_pesanan" name="nama_pesanan" list="listCustomer" value="<?= e(fval($o, 'nama_pesanan')) ?>" required>
                <datalist id="listCustomer">
                    <?php foreach ($customerList as $cr): ?><option value="<?= e($cr['nama_pesanan']) ?>"></option><?php endforeach; ?>
                </datalist>
                <div class="form-text">Kalau belum ada di master customer, akan dibuat otomatis saat disimpan.</div>
            </div>
            <div>
                <label class="form-label" for="sumber">Sumber Order</label>
                <select class="form-select" id="sumber" name="sumber">
                    <?php foreach (['wa' => 'WhatsApp', 'telepon' => 'Telepon', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'website' => 'Website', 'referral' => 'Referral', 'lainnya' => 'Lainnya'] as $k => $l): ?>
                        <option value="<?= $k ?>" <?= fval($o, 'sumber', 'wa') === $k ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="nama_pic">Nama PIC</label>
                <input type="text" class="form-control" id="nama_pic" name="nama_pic" value="<?= e(fval($o, 'nama_pic')) ?>">
            </div>
            <div>
                <label class="form-label" for="hp_pic">HP / WA PIC</label>
                <input type="text" class="form-control" id="hp_pic" name="hp_pic" value="<?= e(fval($o, 'hp_pic')) ?>" placeholder="08xx / +62xx">
            </div>
            <div>
                <label class="form-label" for="data_tamu">Data Tamu <span class="text-soft">(internal)</span></label>
                <input type="text" class="form-control" id="data_tamu" name="data_tamu" value="<?= e(fval($o, 'data_tamu')) ?>" placeholder="mis: Imigrasi / Lapas / Ibu Triana">
                <div class="form-text">Pemesan vs tamu: tidak cetak di invoice, hanya internal.</div>
            </div>
            <div>
                <label class="form-label" for="keterangan">Keterangan</label>
                <input type="text" class="form-control" id="keterangan" name="keterangan" list="listKeterangan" value="<?= e(fval($o, 'keterangan')) ?>" placeholder="mis: Ketua Apkasi">
                <datalist id="listKeterangan">
                    <option value="Ketua Apkasi"></option><option value="Putri Otonomi"></option><option value="RTR"></option><option value="Corp"></option>
                </datalist>
            </div>
            <div>
                <label class="form-label" for="asal_user_raw">Asal User (arsip Excel)</label>
                <input type="text" class="form-control" id="asal_user_raw" name="asal_user_raw" list="listAsalUser" value="<?= e(fval($o, 'asal_user_raw')) ?>" placeholder="kosongkan jika input baru">
                <datalist id="listAsalUser">
                    <option value="RTR"></option><option value="Corp"></option><option value="RO"></option><option value="Apkasi"></option><option value="IG"></option><option value="Web"></option><option value="Bu Tika"></option>
                </datalist>
                <div class="form-text">Isi kalau mau samakan sheet lama. Jika diisi, Tipe Pelanggan & Sumber otomatis mengikuti. RTR sementara → corporate. Tanya reservasi untuk pastinya.</div>
            </div>
            <div>
                <label class="form-label" for="handle_by">Handle By</label>
                <input type="text" class="form-control" id="handle_by" name="handle_by" value="<?= e(fval($o, 'handle_by', namaUser())) ?>">
            </div>
            <div class="full">
                <label class="form-label" for="catatan">Catatan Internal</label>
                <textarea class="form-control" id="catatan" name="catatan" rows="2"><?= e(fval($o, 'catatan')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card-box">
        <div class="section-step"><div class="step-no">3</div><div class="step-title">Unit, Driver & Harga</div></div>

        <div id="wadahUnit">
            <?php foreach ($items as $idx => $it): ?>
                <?php renderBlokUnit($it, $idx, count($items)); ?>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btnTambahUnit">+ Tambah Mobil Lain (Rombongan)</button>
        <div class="form-text mt-1">Gunakan jika pesanan menyewa lebih dari satu kendaraan dalam satu tagihan/faktur.</div>

        <hr class="my-4">

        <div class="row g-4">
            <div class="col-md-6">
                <div class="form-label">Biaya Tambahan</div>
                <div id="wadahBiaya">
                    <?php foreach ($biayaOrder as $b): ?>
                        <div class="d-flex gap-2 mb-2 baris-biaya">
                            <input type="text" class="form-control form-control-sm" name="biaya_nama[]" value="<?= e($b['nama']) ?>" placeholder="nama biaya">
                            <input type="text" class="form-control form-control-sm" name="biaya_nominal[]" value="<?= number_format((float) $b['nominal'], 0, ',', '.') ?>" placeholder="nominal">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-biaya">x</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnTambahBiaya">+ Tambah biaya</button>
            </div>
            <div class="col-md-6">
                <div class="form-label">Include</div>
                <?php foreach ($includes as $inc): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="form-check mb-0 flex-grow-1">
                            <input class="form-check-input" type="checkbox" id="inc_<?= (int) $inc['id'] ?>" name="include_id[]" value="<?= (int) $inc['id'] ?>"
                                <?= (isset($includeTerpilih[(int) $inc['id']]) || (!$order && (int) $inc['is_default'] === 1)) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="inc_<?= (int) $inc['id'] ?>"><?= e($inc['nama']) ?></label>
                        </div>
                        <input type="text" class="form-control form-control-sm" style="max-width:130px" name="include_biaya[<?= (int) $inc['id'] ?>]"
                               value="<?= isset($includeTerpilih[(int) $inc['id']]) && $includeTerpilih[(int) $inc['id']] > 0 ? number_format((float) $includeTerpilih[(int) $inc['id']], 0, ',', '.') : '' ?>"
                               placeholder="biaya (opsional)">
                    </div>
                <?php endforeach; ?>
                <div class="form-text">Biaya include diisi hanya kalau dibebankan sebagai tambahan.</div>
            </div>
        </div>

        <div class="ringkas mt-4">
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-5">
                    <label class="form-label" for="panjar">Panjar / DP Awal (opsional)</label>
                    <div class="input-group input-group-sm"><span class="input-group-text">Rp</span><input type="text" class="form-control" id="panjar" name="panjar" value="<?= fval($o, 'panjar') !== '' && fval($o, 'panjar') !== null && (int)fval($o,'panjar')>0 ? number_format((float)fval($o,'panjar'),0,',','.') : '' ?>" placeholder="0"></div>
                    <div class="form-text">Kalau customer sudah transfer sebelum invoice terbit. Nanti otomatis jadi pembayaran DP di invoice — tidak perlu input dua kali. Kosongkan jika belum ada.</div>
                </div>
                <div class="col-md-7 text-end">
                    <div class="form-text">Sisa setelah panjar: <b id="rkSisa">Rp 0</b></div>
                </div>
            </div>
            <div class="ringkas-row"><span>Subtotal modal (internal)</span><span id="rkModal">Rp 0</span></div>
            <div class="ringkas-row"><span>Subtotal jual</span><span id="rkJual">Rp 0</span></div>
            <div class="ringkas-row"><span>Biaya tambahan</span><span id="rkTambahan">Rp 0</span></div>
            <div class="ringkas-row total"><span>Total Tagihan Customer</span><span id="rkTotal">Rp 0</span></div>
            <div class="ringkas-row"><span>Panjar</span><span id="rkPanjar">Rp 0</span></div>
            <div class="ringkas-row total"><span>Sisa Tagihan</span><span id="rkSisa2">Rp 0</span></div>
            <div class="ringkas-row margin"><span>Margin (internal)</span><span id="rkMargin">Rp 0</span></div>
        </div>
        <datalist id="listUpgrade">
            <?php foreach (daftarUpgrade() as $up): ?><option value="<?= e($up) ?>"></option><?php endforeach; ?>
        </datalist>
    </div>

    <div class="card-box">
        <div class="d-flex flex-wrap gap-3 align-items-end">
            <div style="min-width:220px">
                <label class="form-label" for="status">Status Pesanan</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach (['draft', 'inquiry', 'quoted', 'waiting_dp', 'booked', 'in_trip', 'completed'] as $s): ?>
                        <option value="<?= $s ?>" <?= fval($o, 'status', 'booked') === $s ? 'selected' : '' ?>><?= e(statusLabel($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="baris-aksi">
                <button type="submit" name="aksi" value="simpan" class="btn btn-primary btn-sm"><?= $order ? 'Simpan Perubahan' : 'Simpan Pesanan' ?></button>
                <button type="submit" name="aksi" value="draft" class="btn btn-outline-secondary btn-sm" formnovalidate>Simpan Draft</button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/pages/pesanan_list.php">Batal</a>
            </div>
        </div>
    </div>
</form>

<template id="tplUnit">
    <div class="item-unit">
        <div class="item-head">
            <span class="unit-no">Armada / Mobil</span>
            <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-unit">Hapus unit</button>
        </div>
        <div class="form-grid">
            <div>
                <label class="form-label">Driver <span class="wajib">*</span></label>
                <select class="form-select pilih-driver" name="item_driver_id[]" required>
                    <option value="">-- pilih driver --</option>
                    <?php foreach ($drivers as $dv): ?>
                        <option value="<?= (int) $dv['id'] ?>" data-nama="<?= e($dv['nama']) ?>" data-hp="<?= e($dv['hp']) ?>"><?= e($dv['nama']) ?><?= $dv['hp'] ? ' - ' . e($dv['hp']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="form-label">Nama Driver</label><input type="text" class="form-control" name="item_nama_driver[]"></div>
            <div><label class="form-label">HP Driver</label><input type="text" class="form-control" name="item_hp_driver[]"></div>
            <div>
                <label class="form-label">Unit</label>
                <select class="form-select pilih-unit" name="item_unit_id[]">
                    <option value="">-- pilih unit --</option>
                    <?php foreach ($units as $un): ?>
                        <option value="<?= (int) $un['id'] ?>" data-nopol="<?= e($un['nopol']) ?>" data-modal="<?= (int) $un['harga_modal_default'] ?>" data-jual="<?= (int) $un['harga_jual_default'] ?>"><?= e($un['nama_unit']) ?> - <?= e($un['nopol']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="form-label">Nama Unit</label><input type="text" class="form-control" name="item_nama_unit[]"></div>
            <div><label class="form-label">Nomor Polisi</label><input type="text" class="form-control mono" name="item_nopol[]"></div>
            <div><label class="form-label">Upgrade</label><input type="text" class="form-control" name="item_upgrade[]" list="listUpgrade" placeholder="mis: Up Reborn"></div>
            <div>
                <label class="form-label">Support By</label>
                <select class="form-select" name="item_partner_id[]">
                    <option value="">-- tidak ada --</option>
                    <?php foreach ($partners as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Harga Modal / Hari <span class="text-soft">(internal)</span></label>
                <div class="input-group"><span class="input-group-text">Rp</span><input type="text" class="form-control" name="item_harga_modal[]"></div>
                <div class="form-text">Subtotal modal: <span class="sub-modal">Rp 0</span></div>
            </div>
            <div>
                <label class="form-label">Harga Jual / Hari</label>
                <div class="input-group"><span class="input-group-text">Rp</span><input type="text" class="form-control" name="item_harga_jual[]"></div>
                <div class="form-text">Subtotal jual: <span class="sub-jual">Rp 0</span></div>
            </div>
            <div><label class="form-label">Hari (per unit)</label><input type="number" class="form-control" name="item_jumlah_hari[]"></div>
            <div><label class="form-label">Catatan Unit</label><input type="text" class="form-control" name="item_catatan[]"></div>
        </div>
    </div>
</template>

<template id="tplBiaya">
    <div class="d-flex gap-2 mb-2 baris-biaya">
        <input type="text" class="form-control form-control-sm" name="biaya_nama[]" placeholder="nama biaya">
        <input type="text" class="form-control form-control-sm" name="biaya_nominal[]" placeholder="nominal">
        <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-biaya">x</button>
    </div>
</template>
<?php include __DIR__ . '/../includes/footer.php'; ?>
