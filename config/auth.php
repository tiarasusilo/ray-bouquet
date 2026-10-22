<?php

function hasPrivilege($namaPrivilege)
{
    global $pdo_oas;

    $tipeAkun = $_SESSION['tipe_akun'] ?? null;

    if (!$tipeAkun) {
        return false;
    }

    $stmt = $pdo_oas->prepare("
        SELECT 1
        FROM role_privilege rp
        JOIN privilege p
            ON rp.id_privilege = p.id_privilege
        WHERE rp.tipe_akun = ?
          AND p.nama_privilege = ?
        LIMIT 1
    ");

    $stmt->execute([
        $tipeAkun,
        $namaPrivilege
    ]);

    return $stmt->fetch() !== false;
}