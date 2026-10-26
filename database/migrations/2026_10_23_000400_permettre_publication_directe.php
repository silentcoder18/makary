<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE validation_demande DROP CONSTRAINT chk_validation_separation');
        } catch (Throwable) {
            // Contrainte déjà retirée ou inexistante
        }
    }

    public function down(): void
    {
        // Pas de retour arrière
    }
};
