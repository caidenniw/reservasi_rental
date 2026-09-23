<?php
/**
 * includes/master_crud.php
 * CRUD generik untuk halaman master data (units, drivers, customers, partners, includes).
 * Pola wajib: proses POST -> redirect -> baru output HTML.
 */

function mcrudHandle(array $cfg): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [];
    }
    verifyCsrfToken();

    $db    = getDB();
    $tabel = $cfg['tabel'];
    $aksi  = (string) ($_POST['aksi'] ?? 'simpan');
    $id    = (int) ($_POST['id'] ?? 0);

    if ($aksi === 'hapus') {
        if ($id <= 0) {
            setFlash('danger', 'Data tidak ditemukan.');
            redirect(BASE_URL . $cfg['url']);
        }
        if (!empty($cfg['soft_delete'])) {
            $stmt = $db->prepare("UPDATE `$tabel` SET deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if ($tabel === 'units') {
                /* unik nopol: beri suffix agar nopol yang sama bisa dipakai unit baru,
                   data lama (dan snapshot di order) tidak berubah */
                $st2 = $db->prepare("UPDATE units SET nopol = CONCAT(nopol, '#del', id) WHERE id = ?");
                $st2->bind_param('i', $id);
                $st2->execute();
            }
            setFlash('success', 'Data dinonaktifkan (data lama tetap tersimpan).');
        } else {
            $stmt = $db->prepare("DELETE FROM `$tabel` WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            setFlash('success', 'Data dihapus.');
        }
        redirect(BASE_URL . $cfg['url']);
    }

    $errors = [];
    $nama   = [];
    $nilai  = [];

    foreach ($cfg['kolom'] as $c) {
        $nama[] = $c['name'];
        $raw    = (string) ($_POST[$c['name']] ?? '');
        if ($c['tipe'] === 'number' || $c['tipe'] === 'rupiah') {
            $v = angka($raw);
            if (!empty($c['wajib']) && trim($raw) === '') {
                $errors[] = $c['label'] . ' wajib diisi.';
            }
        } else {
            $v = trim($raw);
            if ($c['tipe'] === 'tel' && $v !== '') {
                $v = normalisasiHp($v);
            }
            if (!empty($c['wajib']) && $v === '') {
                $errors[] = $c['label'] . ' wajib diisi.';
            }
            if (!empty($c['max']) && mb_strlen($v) > (int) $c['max']) {
                $errors[] = $c['label'] . ' maksimal ' . (int) $c['max'] . ' karakter.';
            }
        }
        $nilai[] = $v;
    }

    if ($errors) {
        return $errors;
    }

    try {
        if ($id > 0) {
            $set = [];
            foreach ($nama as $n) $set[] = "`$n` = ?";
            $sql = "UPDATE `$tabel` SET " . implode(', ', $set) . ' WHERE id = ?';
            $types = str_repeat('s', count($nama)) . 'i';
            $params = $nilai;
            $params[] = $id;
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            setFlash('success', 'Data berhasil diperbarui.');
        } else {
            $kol = '`' . implode('`, `', $nama) . '`';
            $ph  = implode(', ', array_fill(0, count($nama), '?'));
            $stmt = $db->prepare("INSERT INTO `$tabel` ($kol) VALUES ($ph)");
            $types = str_repeat('s', count($nama));
            $stmt->bind_param($types, ...$nilai);
            $stmt->execute();
            setFlash('success', 'Data berhasil disimpan.');
        }
    } catch (mysqli_sql_exception $e) {
        $pesan = 'Gagal menyimpan: ' . $e->getMessage();
        if (str_contains($e->getMessage(), 'Duplicate')) {
            $pesan = 'Gagal menyimpan: data dengan kode/nomor yang sama sudah ada.';
        }
        return [$pesan];
    }

    redirect(BASE_URL . $cfg['url']);
}

function mcrudList(array $cfg, string $cari = ''): array
{
    $db  = getDB();
    $sql = 'SELECT * FROM `' . $cfg['tabel'] . '`';
    $where = [];
    $params = [];
    $types = '';

    if (!empty($cfg['soft_delete'])) {
        $where[] = 'deleted_at IS NULL';
    }
    if ($cari !== '' && !empty($cfg['cari_kolom'])) {
        $or = [];
        foreach ($cfg['cari_kolom'] as $k) {
            $or[] = "`$k` LIKE ?";
            $params[] = '%' . $cari . '%';
            $types .= 's';
        }
        $where[] = '(' . implode(' OR ', $or) . ')';
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . ($cfg['order'] ?? 'id DESC');

    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function mcrudAmbil(array $cfg, int $id): ?array
{
    if ($id <= 0) return null;
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM `' . $cfg['tabel'] . '` WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ?: null;
}

function mcrudOpsi(array $c): array
{
    if (!empty($c['opsi'])) return $c['opsi'];
    if (!empty($c['opsi_sql'])) {
        $q = $c['opsi_sql'];
        $db = getDB();
        $sql = 'SELECT ' . $q['value'] . ' AS v, ' . $q['label'] . ' AS l FROM ' . $q['from'];
        if (!empty($q['where'])) $sql .= ' WHERE ' . $q['where'];
        $sql .= ' ORDER BY ' . ($q['order'] ?? $q['label']);
        $out = ['' => '-- pilih --'];
        $res = $db->query($sql);
        while ($r = $res->fetch_assoc()) $out[(string) $r['v']] = (string) $r['l'];
        return $out;
    }
    return [];
}

function mcrudField(array $c, $nilai): string
{
    $name  = e($c['name']);
    $wajib = !empty($c['wajib']) ? ' required' : '';
    $lebar = $c['lebar'] ?? 'col-md-6';
    $id    = 'f_' . $c['name'];
    $html  = '<div class="' . e($lebar) . '">';
    $html .= '<label class="form-label" for="' . $id . '">' . e($c['label']) . (!empty($c['wajib']) ? ' <span class="wajib">*</span>' : '') . '</label>';

    switch ($c['tipe']) {
        case 'select':
            $html .= '<select class="form-select" id="' . $id . '" name="' . $name . '"' . $wajib . '>';
            foreach (mcrudOpsi($c) as $v => $l) {
                $sel = ((string) $nilai === (string) $v) ? ' selected' : '';
                $html .= '<option value="' . e($v) . '"' . $sel . '>' . e($l) . '</option>';
            }
            $html .= '</select>';
            break;
        case 'textarea':
            $html .= '<textarea class="form-control" id="' . $id . '" name="' . $name . '" rows="2"' . $wajib . '>' . e($nilai) . '</textarea>';
            break;
        case 'rupiah':
            $html .= '<div class="input-group"><span class="input-group-text">Rp</span>'
                   . '<input type="text" class="form-control input-rupiah" id="' . $id . '" name="' . $name . '" value="' . ($nilai !== '' && $nilai !== null ? number_format((float) $nilai, 0, ',', '.') : '') . '"' . $wajib . '></div>';
            break;
        case 'number':
            $html .= '<input type="number" class="form-control" id="' . $id . '" name="' . $name . '" value="' . e((string) $nilai) . '"' . $wajib . '>';
            break;
        case 'date':
            $html .= '<input type="date" class="form-control" id="' . $id . '" name="' . $name . '" value="' . e((string) $nilai) . '"' . $wajib . '>';
            break;
        case 'tel':
            $html .= '<input type="text" class="form-control" id="' . $id . '" name="' . $name . '" value="' . e((string) $nilai) . '" placeholder="08xx / +62xx">';
            break;
        default:
            $html .= '<input type="text" class="form-control" id="' . $id . '" name="' . $name . '" value="' . e((string) $nilai) . '"' . $wajib . '>';
    }

    if (!empty($c['help'])) {
        $html .= '<div class="form-text">' . e($c['help']) . '</div>';
    }
    return $html . '</div>';
}

function mcrudTampilNilai(array $c, $nilai, array $row): string
{
    switch ($c['tipe']) {
        case 'rupiah':
            return '<span class="num">' . rupiah($nilai, false) . '</span>';
        case 'select':
            $o = mcrudOpsi($c);
            return e($o[(string) $nilai] ?? (string) $nilai);
        case 'tel':
            return $nilai !== '' && $nilai !== null ? '<a href="https://wa.me/' . e(preg_replace('/[^0-9]/', '', (string) $nilai)) . '" target="_blank" rel="noopener">' . e($nilai) . '</a>' : '-';
        default:
            return e((string) $nilai);
    }
}
