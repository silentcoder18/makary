<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Rôles (§7.1), attribution des rôles avec périmètre, journal d'audit en ajout seul (M12) — MySQL/MariaDB (D13).
 * Les rôles sont repris de config/permissions.php, lui-même généré depuis specs/securite/permissions.yaml.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role', function (Blueprint $table) {
            $table->string('code', 40)->collation('utf8mb4_bin')->primary();
            $table->string('libelle', 120);
            $table->boolean('mfa_requise');
        });

        foreach (config('permissions.roles') as $code => $role) {
            DB::table('role')->insert(['code' => $code, 'libelle' => $role['libelle'], 'mfa_requise' => $role['mfa']]);
        }

        Schema::create('utilisateur_role', function (Blueprint $table) {
            $table->foreignId('utilisateur_id')->constrained('utilisateur')->cascadeOnDelete();
            $table->string('role_code', 40)->collation('utf8mb4_bin');
            $table->foreign('role_code')->references('code')->on('role');
            // Périmètre : modules (M01…M12) ou domaines (projets, realisations, juridique).
            // Vide = aucun périmètre (moindre privilège) : une permission « périmètre » n'est alors jamais accordée.
            $table->json('perimetre')->default(new Expression("('[]')"));
            $table->foreignId('attribue_par')->nullable()->constrained('utilisateur');
            $table->dateTime('attribue_le')->useCurrent();
            $table->primary(['utilisateur_id', 'role_code']);
        });

        Schema::create('journal_audit', function (Blueprint $table) {
            $table->id();
            $table->dateTime('horodatage')->useCurrent();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateur');
            $table->string('action', 80);
            $table->string('objet_type', 60)->nullable();
            $table->unsignedBigInteger('objet_id')->nullable();
            $table->json('details')->default(new Expression("('{}')"));
            $table->string('adresse_ip', 45)->nullable();
            $table->date('conserver_jusqu_au');   // durée à arbitrer par le référent juridique (E-04 / E-08)
            $table->index(['objet_type', 'objet_id']);
        });

        // Ajout seul : aucune modification ; suppression seulement après la fin de conservation.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_audit_sans_modification BEFORE UPDATE ON journal_audit FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_audit : modification interdite (ajout seul)';
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journal_audit_suppression_echue BEFORE DELETE ON journal_audit FOR EACH ROW
            BEGIN
                IF OLD.conserver_jusqu_au >= CURRENT_DATE THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_audit : suppression interdite avant la fin de conservation';
                END IF;
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS journal_audit_sans_modification');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_audit_suppression_echue');
        Schema::dropIfExists('journal_audit');
        Schema::dropIfExists('utilisateur_role');
        Schema::dropIfExists('role');
    }
};
