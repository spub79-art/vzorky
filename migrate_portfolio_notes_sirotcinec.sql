-- Portfolio: poznámky, diskuze produktů, vazba požadavků (sirotčinec)
-- Spusťte po migrate_portfolio_produkty.sql

ALTER TABLE produkty
    ADD COLUMN poznamka TEXT NULL DEFAULT NULL
        COMMENT 'Poznámka k vývojovému produktu'
    AFTER ukonceno_datum;

CREATE TABLE IF NOT EXISTS produkty_komentare (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produkt INT NOT NULL,
    id_user INT NOT NULL DEFAULT 0,
    jmeno_user VARCHAR(120) NOT NULL DEFAULT '',
    text_hodnota TEXT NOT NULL,
    vytvoreno DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pk_produkt (id_produkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pozadavky_produkty (
    id_pozadavek INT NOT NULL,
    id_produkt INT NOT NULL,
    PRIMARY KEY (id_pozadavek, id_produkt),
    INDEX idx_pp_produkt (id_produkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
