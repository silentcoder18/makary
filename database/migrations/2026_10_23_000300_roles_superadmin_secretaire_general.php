<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Insérer les 2 rôles souverains
        DB::table('role')->updateOrInsert(
            ['code' => 'superadmin'],
            ['libelle' => 'Super Administrateur', 'mfa_requise' => false]
        );
        DB::table('role')->updateOrInsert(
            ['code' => 'secretaire_general'],
            ['libelle' => 'Secrétaire Général', 'mfa_requise' => false]
        );

        foreach (config('permissions.roles') as $code => $role) {
            DB::table('role')->updateOrInsert(
                ['code' => $code],
                ['libelle' => $role['libelle'], 'mfa_requise' => $role['mfa']]
            );
        }

        // 2. Mettre à jour les utilisateurs existants vers les 2 nouveaux rôles
        DB::table('utilisateur_role')
            ->whereIn('role_code', ['admin_technique', 'lecteur_auditeur'])
            ->update(['role_code' => 'superadmin']);

        DB::table('utilisateur_role')
            ->whereNotIn('role_code', ['superadmin', 'secretaire_general'])
            ->update(['role_code' => 'secretaire_general']);

        // 3. Supprimer les anciens rôles devenus obsolètes
        DB::table('role')->whereNotIn('code', array_keys(config('permissions.roles')))->delete();
    }

    public function down(): void
    {
        // Pas de retour arrière nécessaire
    }
};
