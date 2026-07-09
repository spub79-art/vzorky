-- Opt-in pro denní e-mailový souhrn (Nákup / Vývoj / Kvalita podle role)
-- Spusťte na dev i produkci.

ALTER TABLE users
    ADD COLUMN souhrn_email TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = posílat denní e-mailový souhrn (vyžaduje vyplněný email)'
    AFTER email;

-- Příklad: zapnout existujícímu nákupčímu
-- UPDATE users SET souhrn_email = 1 WHERE login = 'jan' AND TRIM(email) != '';
