<?php

use App\Models\Utilisateur;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $motDePasse = Hash::make('Makary@2026!');
        $perimetres = json_encode(config('permissions.regles.perimetres', []));

        // 1. S'assurer que les deux comptes d'administration existent et sont configurés
        $superadmin = Utilisateur::firstOrCreate(
            ['courriel' => 'superadmin@demo.makary.test'],
            [
                'nom_affiche' => 'Super Administrateur',
                'fonction' => 'Super Administrateur de la plateforme',
                'mot_de_passe' => $motDePasse,
                'actif' => true,
                'mfa_active' => false,
            ]
        );
        $superadmin->update([
            'mot_de_passe' => $motDePasse,
            'actif' => true,
            'mfa_active' => false,
            'mfa_secret' => null,
            'mfa_dernier_pas' => null,
        ]);

        $secretaire = Utilisateur::firstOrCreate(
            ['courriel' => 'secretaire.general@demo.makary.test'],
            [
                'nom_affiche' => 'Secrétaire Général',
                'fonction' => 'Secrétaire Général de la Commune',
                'mot_de_passe' => $motDePasse,
                'actif' => true,
                'mfa_active' => false,
            ]
        );
        $secretaire->update([
            'mot_de_passe' => $motDePasse,
            'actif' => true,
            'mfa_active' => false,
            'mfa_secret' => null,
            'mfa_dernier_pas' => null,
        ]);

        $sId = $superadmin->id;
        $gId = $secretaire->id;
        $gardes = [$sId, $gId];

        // 2. Donner tous les droits et tous les périmètres aux 2 administrateurs
        DB::table('role')->updateOrInsert(
            ['code' => 'superadmin'],
            ['libelle' => 'Super Administrateur', 'mfa_requise' => false]
        );
        DB::table('role')->updateOrInsert(
            ['code' => 'secretaire_general'],
            ['libelle' => 'Secrétaire Général', 'mfa_requise' => false]
        );

        DB::table('utilisateur_role')->updateOrInsert(
            ['utilisateur_id' => $sId, 'role_code' => 'superadmin'],
            ['perimetre' => $perimetres, 'attribue_le' => now()]
        );
        DB::table('utilisateur_role')->updateOrInsert(
            ['utilisateur_id' => $gId, 'role_code' => 'secretaire_general'],
            ['perimetre' => $perimetres, 'attribue_le' => now()]
        );

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::unprepared('DROP TRIGGER IF EXISTS publication_figee');
        DB::unprepared('DROP TRIGGER IF EXISTS validation_demande_figee');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_audit_sans_modification');

        // 3. Re-parentage des tables éditoriales et opérationnelles vers les 2 comptes
        DB::table('contenu')->whereNotIn('auteur_id', $gardes)->update(['auteur_id' => $sId]);
        DB::table('projet')->whereNotIn('responsable_id', $gardes)->update(['responsable_id' => $sId]);
        DB::table('projet_financement')->whereNotNull('valide_par')->whereNotIn('valide_par', $gardes)->update(['valide_par' => $gId]);
        DB::table('projet_geometrie')->whereNotNull('verifie_par')->whereNotIn('verifie_par', $gardes)->update(['verifie_par' => $gId]);

        // Projet transition : respecter la séparation valide_par <> demande_par
        DB::table('projet_transition')->whereNotIn('demande_par', $gardes)->update(['demande_par' => $sId]);
        DB::table('projet_transition')->whereNotNull('valide_par')->whereNotIn('valide_par', $gardes)->update(['valide_par' => $gId]);
        DB::table('projet_transition')->whereColumn('demande_par', 'valide_par')->update(['valide_par' => $gId]);

        DB::table('validation_demande')->whereNotIn('soumis_par', $gardes)->update(['soumis_par' => $sId]);
        DB::table('validation_demande')->whereNotNull('decide_par')->whereNotIn('decide_par', $gardes)->update(['decide_par' => $gId]);

        DB::table('publication')->whereNotIn('publie_par', $gardes)->update(['publie_par' => $sId]);
        DB::table('document_version')->whereNotIn('cree_par', $gardes)->update(['cree_par' => $sId]);
        DB::table('fichier')->whereNotIn('televerse_par', $gardes)->update(['televerse_par' => $sId]);
        DB::table('source')->whereNotIn('cree_par', $gardes)->update(['cree_par' => $sId]);
        DB::table('parametre')->whereNotNull('modifie_par')->whereNotIn('modifie_par', $gardes)->update(['modifie_par' => $sId]);
        DB::table('demande')->whereNotNull('affectee_a')->whereNotIn('affectee_a', $gardes)->update(['affectee_a' => $gId]);
        DB::table('demande_evenement')->whereNotIn('par', $gardes)->update(['par' => $sId]);
        DB::table('utilisateur_role')->whereNotNull('attribue_par')->whereNotIn('attribue_par', $gardes)->update(['attribue_par' => $sId]);

        // Journal audit : rattacher à superadmin
        DB::table('journal_audit')->whereNotNull('utilisateur_id')->whereNotIn('utilisateur_id', $gardes)->update(['utilisateur_id' => $sId]);

        // Déconnecter les sessions des anciens utilisateurs
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->whereNotIn('user_id', $gardes)->delete();
        }

        // 4. Supprimer tous les autres utilisateurs
        DB::table('utilisateur_role')->whereNotIn('utilisateur_id', $gardes)->delete();
        DB::table('utilisateur')->whereNotIn('id', $gardes)->delete();

        // Réactiver les déclencheurs d'intégrité
        DB::unprepared(<<<'SQL'
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
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER validation_demande_figee BEFORE UPDATE ON validation_demande FOR EACH ROW
            BEGIN
                IF NOT (NEW.instantane <=> OLD.instantane) OR NOT (NEW.objet_type <=> OLD.objet_type)
                   OR NOT (NEW.objet_id <=> OLD.objet_id) OR NOT (NEW.soumis_par <=> OLD.soumis_par) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'validation_demande : l''objet et l''instantané soumis ne se modifient pas';
                END IF;
                IF OLD.decision <> 'en_attente' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'validation_demande : la décision rendue est définitive';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_audit_sans_modification BEFORE UPDATE ON journal_audit FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_audit : modification interdite (ajout seul)';
            END
        SQL);

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(): void
    {
        // Pas de restauration des anciens comptes
    }
};
