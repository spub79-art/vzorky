-- Migrace: rozšířený přístup k nákupu + přiřazení nákupčího k požadavku
-- Spusťte na své databázi (dev i produkce zvlášť).

ALTER TABLE users
    ADD COLUMN nakup_pristup TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Rozšířený přístup k modulu Nákup (nezávisle na roli orders)'
    AFTER orders;

ALTER TABLE pozadavky
    ADD COLUMN id_nakupci INT NULL DEFAULT NULL
        COMMENT 'Přiřazený nákupčí (users.id)'
    AFTER id_status;

-- Volitelný index pro rychlejší filtry
CREATE INDEX idx_pozadavky_nakupci ON pozadavky (id_nakupci);

-- Příklad: Tereza — vývojářka s rozšířeným nákupním přístupem (upravte login/id dle reality)
-- UPDATE users SET vyvoj = 1, nakup_pristup = 1 WHERE login = 'tereza';
