<?php
function fmt_money($v) {
    if ($v === null || $v === '') return 'N/A';
    return '$' . number_format((float)$v, 2);
}

function photo_public_url($val) {
    if (empty($val)) return '';
    $v = trim((string)$val);

    if (preg_match('#^https?://#i', $v)) return $v;

    $v = ltrim($v, '/');
    if (stripos($v, 'uploads/') === 0) return '/' . $v;

    return '/uploads/' . $v;
}
