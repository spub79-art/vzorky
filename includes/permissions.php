<?php
/**
 * Centrální oprávnění — role vs. rozšířený přístup k nákupu.
 */

function userCanNakup() {
    return !empty($_SESSION['adm'])
        || !empty($_SESSION['orders'])
        || !empty($_SESSION['nakup_pristup']);
}

function loadSessionPermissions() {
    $is_adm = !empty($_SESSION['adm']);
    $is_vyvoj = !empty($_SESSION['vyvoj']);
    $is_orders = !empty($_SESSION['orders']);
    $is_kvalita = !empty($_SESSION['kvalita']);
    $is_nakup_pristup = !empty($_SESSION['nakup_pristup']);
    $can_nakup = $is_adm || $is_orders || $is_nakup_pristup;

    return [
        'is_adm' => $is_adm,
        'is_vyvoj' => $is_vyvoj,
        'is_orders' => $is_orders,
        'is_kvalita' => $is_kvalita,
        'is_nakup_pristup' => $is_nakup_pristup,
        'can_nakup' => $can_nakup,
        'current_uid' => (int)($_SESSION['uid'] ?? 0),
    ];
}

function requireNakupAccess() {
    if (!userCanNakup()) {
        die('Nepovolený přístup.');
    }
}
