<?php

namespace Tests\Feature\Securite;

use App\Models\Utilisateur;
use App\Securite\Autorisations;
use App\Securite\ObjetAPerimetre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Portées « toutes », « propre » et « périmètre » (MKY-P1-05), contrôlées côté serveur. */
class AutorisationsTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<string>  $perimetres */
    private function objet(array $perimetres, ?int $auteurId = null): ObjetAPerimetre
    {
        return new class($perimetres, $auteurId) implements ObjetAPerimetre
        {
            public function __construct(private array $perimetres, private ?int $auteurId) {}

            public function perimetres(): array
            {
                return $this->perimetres;
            }

            public function auteurId(): ?int
            {
                return $this->auteurId;
            }
        };
    }

    public function test_portee_toutes(): void
    {
        $admin = Utilisateur::factory()->avecRole('admin_technique')->create();
        $this->assertTrue($admin->can('utilisateur.gerer'));
        $this->assertFalse($admin->can('contenu.approuver'), "l'administrateur technique ne valide pas les contenus");
    }

    public function test_portee_propre_limitee_aux_objets_de_l_auteur(): void
    {
        $redacteur = Utilisateur::factory()->avecRole('redacteur')->create();
        $this->assertTrue($redacteur->can('contenu.modifier'), 'sans objet : créer, lister');
        $this->assertTrue($redacteur->can('contenu.modifier', $this->objet(['M02'], $redacteur->id)));
        $this->assertFalse($redacteur->can('contenu.modifier', $this->objet(['M02'], $redacteur->id + 1)));
        $this->assertFalse($redacteur->can('contenu.publier'));
    }

    public function test_portee_perimetre(): void
    {
        $validateur = Utilisateur::factory()->avecRole('validateur', ['M02', 'projets'])->create();
        $this->assertTrue($validateur->can('contenu.approuver', $this->objet(['M02'])));
        $this->assertTrue($validateur->can('projet.valider_transition', $this->objet(['projets'])));
        $this->assertFalse($validateur->can('contenu.approuver', $this->objet(['M05'])));
        $this->assertFalse($validateur->can('projet.valider_transition', $this->objet(['realisations'])));
        $this->assertSame(['M02', 'projets'], app(Autorisations::class)->perimetresPour($validateur, 'contenu.approuver'));
    }

    public function test_perimetre_vide_ne_donne_aucun_droit(): void
    {
        $validateur = Utilisateur::factory()->avecRole('validateur')->create();
        $this->assertFalse($validateur->can('contenu.approuver'));
        $this->assertFalse($validateur->can('contenu.approuver', $this->objet(['M02'])));
        $this->assertSame([], app(Autorisations::class)->perimetresPour($validateur, 'contenu.approuver'));
    }

    public function test_cumul_de_roles(): void
    {
        $agent = Utilisateur::factory()->avecRole('redacteur')->avecRole('validateur', ['M03'])->create();
        $this->assertTrue($agent->can('contenu.modifier', $this->objet(['M03'], $agent->id)));
        $this->assertTrue($agent->can('contenu.approuver', $this->objet(['M03'])));
        $this->assertFalse($agent->can('contenu.approuver', $this->objet(['M04'])));
    }

    public function test_compte_desactive_ou_permission_inconnue(): void
    {
        $ancien = Utilisateur::factory()->desactive()->avecRole('admin_technique')->create();
        $this->assertFalse($ancien->can('utilisateur.gerer'));
        $admin = Utilisateur::factory()->avecRole('admin_technique')->create();
        $this->assertFalse(app(Autorisations::class)->peut($admin, 'permission.inexistante'));
    }
}
