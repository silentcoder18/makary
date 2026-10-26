<?php

namespace Tests\Feature\Securite;

use App\Models\Utilisateur;
use App\Securite\Journal;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Journal d'audit en ajout seul (M12, §9) : la base refuse modification et suppression. */
class JournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_consigner_une_action(): void
    {
        $agent = Utilisateur::factory()->create();
        Journal::consigner('essai', $agent, ['cle' => 'valeur'], $agent->id);

        $ligne = DB::table('journal_audit')->where('action', 'essai')->first();
        $this->assertSame($agent->id, $ligne->utilisateur_id);
        $this->assertSame('utilisateur', $ligne->objet_type);
        $this->assertSame(['cle' => 'valeur'], json_decode($ligne->details, true));
        $this->assertSame(now()->addDays(365)->toDateString(), substr((string) $ligne->conserver_jusqu_au, 0, 10));
    }

    public function test_modification_refusee(): void
    {
        Journal::consigner('essai');
        $this->assertRefuse(fn () => DB::table('journal_audit')->update(['action' => 'falsifie']));
        $this->assertSame(1, DB::table('journal_audit')->where('action', 'essai')->count());
    }

    public function test_suppression_refusee(): void
    {
        Journal::consigner('essai');
        $this->assertRefuse(fn () => DB::table('journal_audit')->delete());
        $this->assertSame(1, DB::table('journal_audit')->count());
    }

    public function test_purge_possible_apres_la_fin_de_conservation(): void
    {
        DB::table('journal_audit')->insert(['action' => 'ancien', 'details' => '{}', 'conserver_jusqu_au' => now()->subDay()->toDateString()]);
        Journal::consigner('recent');
        DB::table('journal_audit')->where('conserver_jusqu_au', '<', now()->toDateString())->delete();
        $this->assertSame(['recent'], DB::table('journal_audit')->pluck('action')->all());
    }

    private function assertRefuse(callable $operation): void
    {
        try {
            // Point de sauvegarde : l'erreur annule seulement l'opération, pas la transaction du test.
            DB::transaction($operation);
            $this->fail("La base aurait dû refuser l'opération.");
        } catch (QueryException $e) {
            $this->assertStringContainsString('interdite', $e->getMessage());
        }
    }
}
