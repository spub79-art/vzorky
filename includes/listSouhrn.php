<?php
if (empty($_SESSION['username'])) exit;

include_once(__DIR__ . '/digest_helpers.php');
include_once(__DIR__ . '/mail.php');

$channels = digest_channels_for_user($perms);
if (empty($channels)) {
    echo '<div class="alert alert-warning" style="margin:20px;">Pro vaši roli není k dispozici žádný souhrn.</div>';
    return;
}

$base_url = digest_base_url();
$datum_cs = date('j.n.Y H:i');
$total_all = 0;
$digests = [];
foreach ($channels as $ch) {
    $d = digest_build($conn, $ch);
    $digests[] = $d;
    $total_all += $d['total'];
}
?>

<div class="digest-page">
    <div class="panel panel-default digest-page-head">
        <div class="panel-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <h3 style="margin:0 0 4px;">
                    <i class="glyphicon glyphicon-dashboard"></i> Můj souhrn
                </h3>
                <p class="text-muted" style="margin:0; font-size:13px;">
                    <?= htmlspecialchars($datum_cs) ?> · položek k řešení: <strong><?= (int)$total_all ?></strong>
                    · zobrazeno podle vaší role
                </p>
            </div>
            <?php if ($is_adm): ?>
            <div class="text-muted" style="font-size:12px;">
                <i class="glyphicon glyphicon-envelope"></i>
                Ranní e-maily: Nákup, Vývoj, Kvalita
                · <a href="cron_denni_souhrn.php?preview=1" target="_blank">náhled cron (admin)</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($total_all === 0): ?>
        <div class="alert alert-success">
            <i class="glyphicon glyphicon-ok"></i>
            <strong>Vše čisté</strong> — pro vaše oddělení momentálně nic nečeká na akci.
        </div>
    <?php endif; ?>

    <?php foreach ($digests as $d):
        $meta = $d['meta'];
        $panel_cls = $d['total'] > 0 ? 'panel-primary' : 'panel-default';
    ?>
    <div class="panel <?= $panel_cls ?> digest-panel">
        <div class="panel-heading" style="display:flex; justify-content:space-between; align-items:center;">
            <strong>
                <i class="glyphicon glyphicon-briefcase"></i>
                <?= htmlspecialchars($meta['label'] ?? $d['channel']) ?>
            </strong>
            <span class="badge" style="background:<?= $d['total'] > 0 ? '#d9534f' : '#999' ?>;">
                <?= (int)$d['total'] ?>
            </span>
        </div>
        <div class="panel-body digest-panel-body">
            <?php if ($d['total'] === 0): ?>
                <p class="text-muted" style="margin:0;">Žádné položky k řešení.</p>
            <?php else: ?>
                <?= buildDigestBody(
                    $meta['title'],
                    $meta['subtitle'],
                    $d['sections'],
                    $base_url
                ) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
