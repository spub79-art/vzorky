<?php
/**
 * Centrální oprávnění — role vs. rozšířený přístup k nákupu.
 */

function userCanNakup() {
    return !empty($_SESSION['adm'])
        || !empty($_SESSION['orders'])
        || !empty($_SESSION['nakup_pristup']);
}

/** Převzetí/uvolnění fronty požadavků — jen tým Nákup (ne samotné Nák+). */
function userCanClaimNakup() {
    return !empty($_SESSION['adm']) || !empty($_SESSION['orders']);
}

/** Sekce Portfolio a správa zákazníků/produktů — portfolio, vývoj, admin. */
function userCanPortfolio() {
    return !empty($_SESSION['adm'])
        || !empty($_SESSION['portfolio'])
        || !empty($_SESSION['vyvoj']);
}

function loadSessionPermissions() {
    $is_adm = !empty($_SESSION['adm']);
    $is_vyvoj = !empty($_SESSION['vyvoj']);
    $is_portfolio = !empty($_SESSION['portfolio']);
    $is_orders = !empty($_SESSION['orders']);
    $is_kvalita = !empty($_SESSION['kvalita']);
    $is_nakup_pristup = !empty($_SESSION['nakup_pristup']);
    $can_nakup = $is_adm || $is_orders || $is_nakup_pristup;
    $can_claim_nakup = $is_adm || $is_orders;
    $can_portfolio = userCanPortfolio();

    return [
        'is_adm' => $is_adm,
        'is_vyvoj' => $is_vyvoj,
        'is_portfolio' => $is_portfolio,
        'is_orders' => $is_orders,
        'is_kvalita' => $is_kvalita,
        'is_nakup_pristup' => $is_nakup_pristup,
        'can_nakup' => $can_nakup,
        'can_claim_nakup' => $can_claim_nakup,
        'can_portfolio' => $can_portfolio,
        'current_uid' => (int)($_SESSION['uid'] ?? 0),
    ];
}

function requirePortfolioAccess() {
    if (!userCanPortfolio()) {
        die('Nepovolený přístup.');
    }
}

function requireNakupAccess() {
    if (!userCanNakup()) {
        die('Nepovolený přístup.');
    }
}

function requireClaimNakupAccess() {
    if (!userCanClaimNakup()) {
        die('Nepovolený přístup.');
    }
}
