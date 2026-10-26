<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Comptes nominatifs (§7.1 : comptes partagés proscrits) et sessions — MySQL/MariaDB (D13).
 * Table « utilisateur » conforme au modèle de données (docs/conception/modele_donnees.md).
 * Dates en DATETIME (et non TIMESTAMP, limité à 2038), à l'heure de Douala.
 * Pas de réinitialisation libre-service du mot de passe en v1 : l'administrateur technique réinitialise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateur', function (Blueprint $table) {
            $table->id();
            $table->string('nom_affiche');
            $table->string('courriel', 254)->unique();
            $table->string('fonction')->nullable();
            $table->string('mot_de_passe');                  // haché par l'application
            $table->text('mfa_secret')->nullable();          // chiffré par l'application, jamais en clair
            $table->boolean('mfa_active')->default(false);
            $table->unsignedBigInteger('mfa_dernier_pas')->nullable(); // anti-rejeu des codes TOTP
            $table->boolean('actif')->default(true);
            $table->dateTime('desactive_le')->nullable();
            $table->dateTime('derniere_connexion')->nullable();
            $table->date('conserver_jusqu_au')->nullable();  // fixée à la désactivation (E-04)
            $table->datetimes();
        });

        DB::statement("ALTER TABLE utilisateur ADD CONSTRAINT chk_utilisateur_courriel CHECK (courriel REGEXP '^[^@[:space:]]+@[^@[:space:]]+[.][^@[:space:]]+$')");
        DB::statement('ALTER TABLE utilisateur ADD CONSTRAINT chk_utilisateur_desactivation CHECK (actif OR (desactive_le IS NOT NULL AND conserver_jusqu_au IS NOT NULL))');

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('utilisateur');
    }
};
