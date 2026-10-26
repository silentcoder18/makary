-- Fichier GÉNÉRÉ par « php artisan makary:schema » depuis specs/donnees/migrations/0002_instantane_soumis.sql.
-- Ne pas modifier à la main : modifier la spécification, puis régénérer.
-- =====================================================================================
-- Plateforme numérique de la Commune de Makary — garanties du circuit éditorial
-- Migration 0002 · ticket MKY-P2-04 · réécrite pour MySQL/MariaDB (MKY-P3-05, décision D13) · PROPOSITION
--
-- Le principe 1 du modèle de données (« un instantané des seuls champs publics, figé au moment de
-- l'approbation ») n'était imposé par la base qu'en partie. Cette migration le complète :
--   * l'instantané est figé dès la SOUMISSION : le validateur approuve exactement ce qui sera publié ;
--   * une publication reprend à l'identique l'instantané approuvé, et une validation ne sert qu'une fois ;
--   * une décision rendue et une publication ne se modifient plus (seul le retrait reste possible) ;
--   * une seule demande en attente à la fois par objet, et une seule transition en attente par projet
--     (MySQL n'a pas d'index partiel : colonne calculée nulle hors attente, et index unique).
-- =====================================================================================

ALTER TABLE validation_demande
    ADD COLUMN instantane JSON NOT NULL,
    ADD COLUMN attente_cle VARCHAR(40) AS (IF(decision = 'en_attente', CONCAT(objet_type, ':', objet_id), NULL)) STORED,
    ADD UNIQUE KEY validation_une_seule_en_attente (attente_cle);

ALTER TABLE publication ADD UNIQUE KEY publication_validation_unique (validation_id);

ALTER TABLE projet_transition
    ADD COLUMN attente_projet BIGINT UNSIGNED AS (IF(decision = 'en_attente', projet_id, NULL)) STORED,
    ADD UNIQUE KEY projet_transition_une_seule_en_attente (attente_projet);

DELIMITER $$
-- Une décision rendue est définitive ; l'objet et l'instantané soumis ne changent jamais.
CREATE TRIGGER validation_demande_figee BEFORE UPDATE ON validation_demande FOR EACH ROW
BEGIN
    IF NOT (NEW.instantane <=> OLD.instantane) OR NOT (NEW.objet_type <=> OLD.objet_type)
       OR NOT (NEW.objet_id <=> OLD.objet_id) OR NOT (NEW.soumis_par <=> OLD.soumis_par) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'validation_demande : l''objet et l''instantané soumis ne se modifient pas';
    END IF;
    IF OLD.decision <> 'en_attente' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'validation_demande : la décision rendue est définitive';
    END IF;
END$$

-- Une publication = l'instantané approuvé, une seule fois.
DROP TRIGGER IF EXISTS publication_controle_insertion$$
CREATE TRIGGER publication_controle_insertion BEFORE INSERT ON publication FOR EACH ROW
BEGIN
    DECLARE v_decision VARCHAR(20);
    DECLARE v_type VARCHAR(20);
    DECLARE v_objet BIGINT UNSIGNED;
    DECLARE v_instantane JSON;
    SELECT decision, objet_type, objet_id, instantane INTO v_decision, v_type, v_objet, v_instantane
      FROM validation_demande WHERE id = NEW.validation_id;
    IF NOT (v_decision <=> 'approuve') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication refusée : la validation n''est pas approuvée';
    END IF;
    IF v_type <> NEW.objet_type OR v_objet <> NEW.objet_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication refusée : la validation porte sur un autre objet';
    END IF;
    IF NOT (NEW.instantane <=> v_instantane) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication refusée : l''instantané diffère de celui qui a été approuvé';
    END IF;
    IF NEW.objet_type = 'media' AND EXISTS (SELECT 1 FROM media WHERE id = NEW.objet_id AND droit = 'a_verifier') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication refusée : droit d''usage du média à vérifier';
    END IF;
    IF NEW.objet_type = 'document' AND EXISTS (SELECT 1 FROM document WHERE id = NEW.objet_id AND diffusion <> 'public') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication refusée : le document n''est pas diffusable publiquement';
    END IF;
END$$

-- Seul le retrait d'une publication en ligne est permis ; le reste est figé.
DROP TRIGGER IF EXISTS publication_controle_modification$$
CREATE TRIGGER publication_figee BEFORE UPDATE ON publication FOR EACH ROW
BEGIN
    IF NOT (NEW.objet_type <=> OLD.objet_type) OR NOT (NEW.objet_id <=> OLD.objet_id) OR NOT (NEW.version <=> OLD.version)
       OR NOT (NEW.instantane <=> OLD.instantane) OR NOT (NEW.validation_id <=> OLD.validation_id)
       OR NOT (NEW.publie_par <=> OLD.publie_par) OR NOT (NEW.publie_le <=> OLD.publie_le) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication : seul le retrait est permis, le contenu publié ne se modifie pas';
    END IF;
    IF OLD.retire_le IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication : déjà retirée';
    END IF;
END$$
DELIMITER ;
