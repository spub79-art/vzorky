-- Migrace F0: role Portfolio
-- Spusťte na své databázi (dev i produkce zvlášť).

ALTER TABLE users
    ADD COLUMN portfolio TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Role Portfolio — správa zákazníků, produktů, priority (bez nákupu)'
    AFTER kvalita;

-- Příklad: přiřazení role existujícímu uživateli (upravte login dle reality)
-- UPDATE users SET portfolio = 1, vyvoj = 0, cumil = 0 WHERE login = 'jana';
