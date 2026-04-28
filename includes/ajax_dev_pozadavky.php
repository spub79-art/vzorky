<?php
session_start();
include_once("db_connect.php");
include_once("telegram.php");

if (!isset($_POST['action'])) exit;

$action = $_POST['action'];
$is_adm = (!empty($_SESSION['adm']) || !empty($_SESSION['vyvoj']));

// 1. PŘIDÁNÍ NOVÉHO POŽADAVKU
if ($action == 'add') {
    $text = trim($_POST['text'] ?? '');
    if (empty($text)) { echo "Prázdný text!"; exit; }

    $autor = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Neznámý');

    // ZMĚNA: Přidán prefix databáze vzorky.
    $stmt = $conn->prepare("INSERT INTO vzorky.dev_pozadavky (autor, text_pozadavku) VALUES (?, ?)");
    $stmt->bind_param("ss", $autor, $text);

    if ($stmt->execute()) {
        echo "OK";

        // Odeslání do Telegramu
        $msg = "💡 *Nový požadavek na úpravu (DEV)*\n\n";
        $msg .= "👤 *Od:* " . $autor . "\n";
        $msg .= "🕒 *Čas:* " . date('j.n. H:i') . "\n";
        $msg .= "💬 *Text:*\n" . $text;

        sendTelegram($msg, 'dev');

    } else {
        echo "Chyba DB.";
    }
    exit;
}

// 2. AKTUALIZACE STAVU (Vyřešeno / Zamítnuto)
if ($action == 'update_status') {
    if (!$is_adm) { echo "Nemáte oprávnění!"; exit; }
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];
    $reakce = trim($_POST['reakce'] ?? '');

    $stmt = $conn->prepare("UPDATE vzorky.dev_pozadavky SET stav = ?, reakce_admin = ? WHERE id = ?");
    $stmt->bind_param("isi", $status, $reakce, $id);
    if ($stmt->execute()) echo "OK"; else echo "Chyba uložení";
    exit;
}

// 3. NAČTENÍ SEZNAMU
if ($action == 'load') {
    // ZMĚNA: Přidán prefix databáze vzorky.
    $res = mysqli_query($conn, "SELECT * FROM vzorky.dev_pozadavky ORDER BY stav ASC, vytvoreno DESC");
    if (mysqli_num_rows($res) == 0) {
        echo "<div class='feedback-msg'>Zatím tu nejsou žádné nápady. Buďte první!</div>";
        exit;
    }

    while ($r = mysqli_fetch_assoc($res)) {
        $status = (int)$r['stav'];
        $is_open = ($status == 0);
        $is_closed = ($status == 1);
        $is_rejected = ($status == 2);

        // Pokud je to zavřené nebo zamítnuté, použijeme class pro zašednutí (který už máš v CSS)
        $status_class = ($is_closed || $is_rejected) ? 'is-closed' : 'is-open';

        echo "<div class='feedback-item $status_class'>";
        echo "  <div class='feedback-item-header'>";
        echo "      <strong class='feedback-item-author'><i class='glyphicon glyphicon-user'></i> ".htmlspecialchars($r['autor'])." <span class='feedback-item-time'>(".date('j.n. H:i', strtotime($r['vytvoreno'])).")</span></strong>";

        if ($is_open && $is_adm) {
            // ZMĚNA: Dvě tlačítka s novou třídou .btn-update-dev-task
            echo "      <div style='display:inline-block; float:right;'>";
            echo "          <button class='btn btn-xs btn-success btn-update-dev-task' style='margin-right: 4px;' data-id='".$r['id']."' data-status='1'>✔ Vyřešit</button>";
            echo "          <button class='btn btn-xs btn-danger btn-update-dev-task' data-id='".$r['id']."' data-status='2'>✖ Zamítnout</button>";
            echo "      </div>";
            // Clearfix pro plovoucí tlačítka
            echo "      <div style='clear:both;'></div>";
        } elseif ($is_closed) {
            echo "      <span class='feedback-item-resolved-text' style='color:#5cb85c;'>✔ VYŘEŠENO</span>";
        } elseif ($is_rejected) {
            echo "      <span class='feedback-item-resolved-text' style='color:#d9534f;'>✖ ZAMÍTNUTO</span>";
        }


        echo "  </div>";
        echo "  <div class='feedback-item-text $status_class'>".nl2br(htmlspecialchars($r['text_pozadavku']))."</div>";

        // ZMĚNA: Vykreslení reakce admina, pokud existuje
        if (!empty($r['reakce_admin'])) {
            $color = $is_rejected ? '#d9534f' : '#5cb85c';
            echo "  <div style='margin-top:8px; padding:8px; border-left:3px solid $color; background:#f9f9f9; font-size:12px; color:#333;'>";
            echo "      <strong>Vyjádření vývojáře:</strong><br>".nl2br(htmlspecialchars($r['reakce_admin']));
            echo "  </div>";
        }

        echo "</div>";
    }
    exit;
}
?>