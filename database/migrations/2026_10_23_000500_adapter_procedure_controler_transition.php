<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS controler_transition');
        DB::unprepared("
CREATE PROCEDURE controler_transition(IN p_projet BIGINT UNSIGNED, IN p_de VARCHAR(30), IN p_vers VARCHAR(30),
                                      IN p_piece BIGINT UNSIGNED, IN p_decision VARCHAR(20), IN p_valide_par BIGINT UNSIGNED)
BEGIN
    DECLARE requise VARCHAR(30);
    DECLARE perim VARCHAR(20);
    DECLARE message VARCHAR(255);
    SELECT piece_requise, perimetre INTO requise, perim FROM transition_autorisee WHERE de_statut = p_de AND vers_statut = p_vers;
    IF p_decision <> 'en_attente' AND NOT EXISTS (
        SELECT 1 FROM utilisateur_role ur JOIN utilisateur u ON u.id = ur.utilisateur_id
         WHERE ur.utilisateur_id = p_valide_par
           AND (ur.role_code IN ('superadmin', 'secretaire_general') OR (ur.role_code = 'validateur' AND JSON_CONTAINS(ur.perimetre, JSON_QUOTE(perim))))
           AND u.actif) THEN
        SET message = LEFT(CONCAT('transition ', p_de, ' -> ', p_vers, ' : validateur du périmètre « ', perim, ' » requis'), 128);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
    END IF;
    IF requise IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM projet_piece WHERE id = p_piece AND projet_id = p_projet AND type_piece = requise) THEN
        SET message = LEFT(CONCAT('transition ', p_de, ' -> ', p_vers, ' : pièce « ', requise, ' » du projet exigée'), 128);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
    END IF;
    IF p_decision = 'approuve' AND p_vers = 'finance' AND NOT EXISTS (
        SELECT 1 FROM projet_financement WHERE projet_id = p_projet AND statut_ligne = 'acquis') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transition vers « financé » : aucune ligne de financement acquise et validée';
    END IF;
END
        ");
    }

    public function down(): void
    {
        // Pas de régression requise
    }
};
