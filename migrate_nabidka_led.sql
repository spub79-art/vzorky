-- Nabídka odložena k ledu (alternativa u vyhrávajícího požadavku)
-- Spustit jednorázově na dev/prod.

ALTER TABLE pozadavky_nabidky
    ADD COLUMN status_pred_ledem INT NULL DEFAULT NULL
        COMMENT 'Původní stav před odložením (pro oživení)';

INSERT INTO ciselnik_statusu (id, nazev, barva_hex, poradi)
VALUES (15, 'Odloženo k ledu', '#95a5a6', 95)
ON DUPLICATE KEY UPDATE nazev = VALUES(nazev), barva_hex = VALUES(barva_hex);
