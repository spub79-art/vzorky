<?php
session_start();
include_once("db_connect.php");
include_once("telegram.php"); // PŘIPOJENÍ NAŠÍ NOVÉ CENTRÁLY!

if (!isset($_POST['action'])) exit;

$action = $_POST['action'];
$is_adm = (!empty($_SESSION['adm']) || !empty($_SESSION['vyvoj']));

// 1. PŘIDÁNÍ NOVÉHO POŽADAVKU
if ($action == 'add') {
    $text = trim($_POST['text'] ?? '');
    if (empty($text)) { echo "Prázdný text!"; exit; }

    $autor = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Neznámý');
    $stmt = $conn->prepare("INSERT INTO dev_pozadavky (autor, text_pozadavku) VALUES (?, ?)");
    $stmt->bind_param("ss", $autor, $text);

    if ($stmt->execute()) {
        echo "OK";

        // --- UNIVERZÁLNÍ ODESLÁNÍ DO TELEGRAMU ---
        $msg = "💡 *Nový požadavek na úpravu (DEV)*\n\n";
        $msg .= "👤 *Od:* " . $autor . "\n";
        $msg .= "🕒 *Čas:* " . date('j.n. H:i') . "\n";
        $msg .= "💬 *Text:*\n" . $text;

        // Zavoláme funkci a řekneme jí, ať to pošle do kanálu 'dev'
        sendTelegram($msg, 'dev');
        // ----------------------------------------

    } else {
        echo "Chyba DB.";
    }
    exit;
}

if ($action == 'close') {
    if (!$is_adm) { echo "Nemáte oprávnění!"; exit; }
    $id = (int)$_POST['id'];
    mysqli_query($conn, "UPDATE dev_pozadavky SET stav = 1 WHERE id = $id");
    echo "OK";
    exit;
}

if ($action == 'load') {
    $res = mysqli_query($conn, "SELECT * FROM dev_pozadavky ORDER BY stav ASC, vytvoreno DESC");
    if (mysqli_num_rows($res) == 0) {
        echo "<div class='feedback-msg'>Zatím tu nejsou žádné nápady. Buďte první!</div>";
        exit;
    }

    while ($r = mysqli_fetch_assoc($res)) {
        $is_closed = ($r['stav'] == 1);
        $status_class = $is_closed ? 'is-closed' : 'is-open';

        echo "<div class='feedback-item $status_class'>";
        echo "  <div class='feedback-item-header'>";
        echo "      <strong class='feedback-item-author'><i class='glyphicon glyphicon-user'></i> ".htmlspecialchars($r['autor'])." <span class='feedback-item-time'>(".date('j.n. H:i', strtotime($r['vytvoreno'])).")</span></strong>";

        if (!$is_closed && $is_adm) {
            echo "      <button class='btn btn-xs btn-success btn-close-dev-task btn-feedback-resolve' data-id='".$r['id']."'>✔ Vyřešit</button>";
        } elseif ($is_closed) {
            echo "      <span class='feedback-item-resolved-text'>✔ VYŘEŠENO</span>";
        }
        echo "  </div>";
        echo "  <div class='feedback-item-text $status_class'>".nl2br(htmlspecialchars($r['text_pozadavku']))."</div>";
        echo "</div>";
    }
    exit;
}
?>