-- Souhrn: odložení priority (dlouhé dodání) — spustit jednorázově po nasazení
ALTER TABLE pozadavky
    ADD COLUMN souhrn_snooze_do DATETIME NULL DEFAULT NULL
        COMMENT 'Do kdy snížit prioritu v Souhrnu (Nákup)',
    ADD COLUMN souhrn_snooze_poznamka VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Důvod snížení (JP dodání, …)';
