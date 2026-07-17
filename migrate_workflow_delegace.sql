-- Workflow delegace (odbočka): předat úkol jinému oddělení bez změny stavu nabídky
-- Např. Vývoj nemůže dát CENA OK (barva), ale cena je OK → předá Nákupu.
ALTER TABLE pozadavky_nabidky
    ADD COLUMN wf_delegace_komu VARCHAR(20) NULL DEFAULT NULL
        COMMENT 'nakup | kvalita — kdo má teď řešit',
    ADD COLUMN wf_delegace_od VARCHAR(20) NULL DEFAULT NULL
        COMMENT 'vyvoj | nakup | kvalita — kdo delegoval',
    ADD COLUMN wf_delegace_duvod VARCHAR(500) NULL DEFAULT NULL
        COMMENT 'Co brání / co je potřeba vyřešit',
    ADD COLUMN wf_delegace_vytvoreno DATETIME NULL DEFAULT NULL;
