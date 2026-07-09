-- Migrace F1: vývojové produkty u zákazníka (Portfolio dashboard)
-- Spusťte po migrate_portfolio_role.sql (dev i produkce zvlášť).

ALTER TABLE produkty
    ADD COLUMN id_zakaznik INT NULL DEFAULT NULL
        COMMENT 'Zákazník (zakaznici.id) — vývojový produkt'
    AFTER id,
    ADD COLUMN priorita TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '0=normální, 1=urgentní'
    AFTER nazev,
    ADD COLUMN ukonceny TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1=ukončený vývoj produktu'
    AFTER priorita,
    ADD COLUMN ukonceno_datum DATETIME NULL DEFAULT NULL
        COMMENT 'Kdy byl produkt ukončen'
    AFTER ukonceny;

CREATE INDEX idx_produkty_zakaznik ON produkty (id_zakaznik);
CREATE INDEX idx_produkty_aktivni ON produkty (ukonceny, priorita);
