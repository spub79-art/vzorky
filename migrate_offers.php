<?php
// migrate_offers.php
include_once("includes/db_connect.php");

echo "<h2>Migrace starých textů nabídek do nové historie</h2>";

$q = mysqli_query($conn, "SELECT id, id_pozadavek, poznamka_cena FROM pozadavky_nabidky WHERE IFNULL(poznamka_cena, '') != ''");
$uspesno = 0;

while ($row = mysqli_fetch_assoc($q)) {
    $id_nab = (int)$row['id'];
    $id_poz = (int)$row['id_pozadavek'];
    $text = trim($row['poznamka_cena']);

    if (empty($text)) continue;

    // Rozsekáme ten velký text na jednotlivé řádky (podle [Jméno - Datum]:)
    $lines = explode("\n", $text);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $typ = 'komentar';
        $jmeno = 'Systém (Historie)';
        $datum = date('Y-m-d H:i:s'); // Fallback

        // Pokusíme se vyparsovat Jméno a Datum ze starého formátu: [Karel - 24.3. 10:15]: Text
        // nebo ⚙️ Systém: [Karel - 24.3. 10:15]: Systémová akce: Text
        $je_systemovy = false;
        if (strpos($line, 'Systémová akce:') !== false || strpos($line, 'Systém:') !== false) {
            $je_systemovy = true;
            $typ = 'status';
        }

        if (preg_match('/\[(.*?)\s*-\s*([0-9]+\.[0-9]+\.\s*[0-9]+:[0-9]+)\]:\s*(.*)/', $line, $matches)) {
            $jmeno = trim($matches[1]);
            // Zkusíme přeložit české datum na MySQL (hrubý odhad pro letošek)
            $datum_raw = trim($matches[2]);
            $d_parts = explode('.', $datum_raw);
            if (count($d_parts) >= 2) {
                $time_parts = explode(' ', $d_parts[2] ?? '00:00');
                $rok = date('Y');
                $mesic = str_pad(trim($d_parts[1]), 2, '0', STR_PAD_LEFT);
                $den = str_pad(trim($d_parts[0]), 2, '0', STR_PAD_LEFT);
                $cas = trim($time_parts[1] ?? '00:00') . ':00';
                $datum = "$rok-$mesic-$den $cas";
            }
            $line_text = trim($matches[3]);
            if ($je_systemovy) {
                $line_text = str_replace('Systémová akce: ', '', $line_text);
            }
        } else {
            $line_text = $line;
        }

        $esc_text = mysqli_real_escape_string($conn, $line_text);
        $esc_jmeno = mysqli_real_escape_string($conn, $jmeno);

        $sql = "INSERT INTO historie_pozadavku (id_pozadavek, id_nabidka, typ_zaznamu, id_user, jmeno_user, text_hodnota, vytvoreno) 
                VALUES ($id_poz, $id_nab, '$typ', 0, '$esc_jmeno', '$esc_text', '$datum')";

        mysqli_query($conn, $sql);
    }
    $uspesno++;
}

echo "Hotovo. Zpracováno $uspesno nabídek.<br>";
// Na konci vyprázdníme starý sloupec, ať to nemáme dvakrát!
mysqli_query($conn, "UPDATE pozadavky_nabidky SET poznamka_cena = ''");
echo "Stará textová pole byla vyčištěna.";
?>