<?php

namespace Tests\Feature\BackOffice;

use App\Models\Contenu;
use App\Models\Document;
use App\Models\Elu;
use App\Models\Equipement;
use App\Models\Fichier;
use App\Models\Localite;
use App\Models\Media;
use App\Models\Parametre;
use App\Models\Partenaire;
use App\Models\Projet;
use App\Models\ValidationDemande;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDOException;

/** Gestion de chaque rubrique du portail depuis le back-office (MKY-P3-08, anomalie R-12). */
class GestionPortailTest extends BackOfficeTestCase
{
    private const ACTE = ['acte_type' => 'piece_officielle', 'acte_date' => '2026-01-15', 'acte_intitule' => 'Procès-verbal fictif d’installation'];

    public function test_pages_par_role(): void
    {
        $fiches = ['elus' => Elu::first(), 'localites' => Localite::first(), 'equipements' => Equipement::first(),
            'partenaires' => Partenaire::first(), 'documents' => Document::first(), 'medias' => Media::first()];
        $attendus = [
            'admin.editorial' => ['/admin/rubriques' => 200, '/admin/elus/nouveau' => 200, '/admin/localites/nouveau' => 200, '/admin/equipements/nouveau' => 200,
                '/admin/medias/nouveau' => 200, '/admin/coordonnees' => 200, '/admin/partenaires' => 200, '/admin/partenaires/nouveau' => 403, '/admin/documents/nouveau' => 403],
            'redacteur' => ['/admin/rubriques' => 200, '/admin/elus/nouveau' => 200, '/admin/documents/nouveau' => 200, '/admin/medias/nouveau' => 200,
                '/admin/localites/nouveau' => 403, '/admin/coordonnees' => 403],
            'gestionnaire.partenaires' => ['/admin/partenaires' => 200, '/admin/partenaires/nouveau' => 200, '/admin/elus' => 403, '/admin/documents' => 403],
            'responsable.projets' => ['/admin/documents/nouveau' => 200, '/admin/partenaires/nouveau' => 200, '/admin/elus/nouveau' => 403],
            // Le lecteur/auditeur lit les fiches projets internes : il voit la page des rubriques, réduite aux projets.
            'lecteur.auditeur' => ['/admin/rubriques' => 200, '/admin/elus' => 403, '/admin/medias' => 403],
            'admin.technique' => ['/admin/rubriques' => 403, '/admin/coordonnees' => 403],
        ];
        foreach ($fiches as $section => $fiche) {
            $attendus['admin.editorial']["/admin/$section"] = 200;
            $attendus['admin.editorial']["/admin/$section/{$fiche->id}"] = 200;
        }
        foreach ($attendus as $agent => $pages) {
            foreach ($pages as $adresse => $statut) {
                $reponse = $this->en($agent)->get($adresse);
                $this->assertSame($statut, $reponse->status(), "$agent : $adresse");
                if ($statut === 200) {
                    $this->assertSame(1, substr_count($reponse->getContent(), '<h1'), "$agent : $adresse — un seul titre");
                    $this->assertStringNotContainsString('style="', $reponse->getContent(), "$agent : $adresse — aucun style en ligne (CSP)");
                }
            }
        }
    }

    public function test_les_douze_rubriques_et_leurs_pages(): void
    {
        $reponse = $this->en('admin.editorial')->get('/admin/rubriques')->assertOk();
        foreach (['Accueil', 'Découvrir Makary', 'Le Maire et l’action municipale', 'Projets et réalisations', 'Investir à Makary', 'Partenariats et bailleurs',
            'Diaspora', 'Patrimoine et tourisme', 'Actualités et événements', 'Centre documentaire', 'Cartographie', 'Contact'] as $rubrique) {
            $reponse->assertSee($rubrique);
        }
        // Une page attendue mais absente se rédige en un clic, avec son type, son module et son adresse.
        $this->assertSame(0, Contenu::where('type_contenu', 'page')->where('slug', 'histoire')->count());
        $reponse->assertSee(e(route('admin.contenus.creer', ['type' => 'page', 'module' => 'M01', 'slug' => 'histoire', 'titre' => 'Histoire de Makary'])), false);
        $this->en('admin.editorial')->get('/admin/contenus/nouveau?type=page&module=M01&slug=histoire&titre=Histoire+de+Makary')
            ->assertOk()->assertSee('value="histoire"', false)->assertSee('Histoire de Makary');
        // Menu organisé comme le portail.
        $this->en('admin.editorial')->get('/admin')->assertSee('Contenus du portail')->assertSee('href="'.route('admin.elus.index').'"', false);
    }

    public function test_cycle_d_un_elu_jusqu_au_portail(): void
    {
        $this->en('admin.editorial')->post('/admin/elus', ['nom_affiche' => 'Élu fictif de test', 'fonction' => 'Adjoint fictif', 'organe' => 'executif',
            'mandat_debut' => '2026-02-01'])->assertSessionHasErrors(['acte_type', 'acte_date', 'acte_intitule']);

        $this->en('admin.editorial')->post('/admin/elus', ['nom_affiche' => 'Élu fictif de test', 'fonction' => 'Adjoint fictif', 'organe' => 'executif',
            'mandat_debut' => '2026-02-01', ...self::ACTE])->assertRedirect();
        $elu = Elu::where('nom_affiche', 'Élu fictif de test')->sole();
        $this->assertSame('Procès-verbal fictif d’installation', $elu->source->intitule);
        $this->assertSame($this->compte('admin.editorial')->id, $elu->auteurId(), 'auteur tracé par l’historique de la base');

        $this->en('admin.editorial')->post("/admin/elus/{$elu->id}/soumettre")->assertRedirect()->assertSessionHas('statut');
        $validation = ValidationDemande::where('objet_type', 'elu')->where('objet_id', $elu->id)->sole();
        // Séparation des tâches : l'administrateur éditorial n'approuve pas.
        $this->en('admin.editorial')->post("/admin/validation/{$validation->id}/approuver")->assertForbidden();
        $this->en('responsable.validation')->post("/admin/validation/{$validation->id}/approuver")->assertRedirect();
        $this->en('responsable.validation')->post("/admin/validation/{$validation->id}/publier")->assertRedirect();

        $this->get('/mairie/executif')->assertOk()->assertSee('Élu fictif de test');
    }

    public function test_une_position_exige_sa_source(): void
    {
        $base = ['nom' => 'Localité fictive de test', 'slug' => 'localite-fictive-de-test', 'categorie' => 'village',
            'source_type' => 'piece_officielle', 'source_date' => '2026-01-10', 'source_intitule' => 'Liste fictive des localités'];

        $this->en('admin.editorial')->post('/admin/localites', [...$base, 'latitude' => '12.5', 'longitude' => '14.4'])
            ->assertSessionHasErrors(['releve_type' => 'Indiquez la source de la position (relevé GPS, carte officielle…).']);
        $this->en('admin.editorial')->post('/admin/localites', [...$base, 'latitude' => '12.5', 'longitude' => '14.4',
            'releve_type' => 'autre', 'releve_date' => '2026-01-12', 'releve_intitule' => 'Relevé GPS fictif'])->assertRedirect();

        $localite = Localite::where('slug', 'localite-fictive-de-test')->sole();
        $this->assertSame('Relevé GPS fictif', $localite->coordonneesSource->intitule);
        $this->en('redacteur')->post('/admin/localites', $base)->assertForbidden();
    }

    public function test_un_partenaire_n_est_cite_qu_avec_son_accord_ecrit(): void
    {
        $base = ['nom' => 'Partenaire fictif de test', 'type_partenaire' => 'ong'];

        $this->en('gestionnaire.partenaires')->post('/admin/partenaires', [...$base, 'accepte_citation' => '1'])
            ->assertSessionHasErrors(['accord_type' => 'Joignez la pièce de l’accord écrit du partenaire.']);
        $this->en('gestionnaire.partenaires')->post('/admin/partenaires', [...$base, 'accepte_citation' => '0'])->assertRedirect();
        $partenaire = Partenaire::where('nom', 'Partenaire fictif de test')->sole();

        $this->en('gestionnaire.partenaires')->from("/admin/partenaires/{$partenaire->id}")->post("/admin/partenaires/{$partenaire->id}/soumettre")
            ->assertSessionHas('erreur', 'Le partenaire n\'a pas donné son accord écrit pour être cité.');
        $this->en('gestionnaire.partenaires')->put("/admin/partenaires/{$partenaire->id}", [...$base, 'accepte_citation' => '1',
            'accord_type' => 'convention', 'accord_date' => '2026-03-01', 'accord_intitule' => 'Lettre fictive d’accord'])->assertRedirect();
        $this->assertTrue($partenaire->fresh()->accepte_citation);
        $this->en('gestionnaire.partenaires')->post("/admin/partenaires/{$partenaire->id}/soumettre")->assertSessionHas('statut');
    }

    public function test_televersement_d_une_photo_et_droit_d_usage(): void
    {
        config(['makary.antivirus.executable' => null]);
        $photo = UploadedFile::fake()->image('photo-fictive.jpg', 3000, 1500);
        $champs = ['titre' => 'Photo fictive de test', 'texte_alternatif' => 'Une photo fictive', 'auteur' => 'Agent fictif'];

        $this->en('redacteur')->post('/admin/medias', [...$champs, 'fichier' => $photo])->assertRedirect();
        $media = Media::where('titre', 'Photo fictive de test')->sole();
        $fichier = $media->fichier;
        $this->assertSame('image/jpeg', $fichier->type_mime);
        $this->assertNull($fichier->analyse_ok, 'sans antivirus : non analysé');
        $this->assertTrue($fichier->prive);
        [$largeur] = getimagesizefromstring(Storage::disk('local')->get($fichier->chemin_stockage));
        $this->assertSame(2400, $largeur, 'image recompressée et bornée');
        $this->en('redacteur')->get("/admin/fichiers/{$fichier->id}")->assertOk()->assertHeader('Content-Security-Policy');

        // Un faux fichier image est refusé d'après son contenu, pas son extension.
        $faux = UploadedFile::fake()->createWithContent('piege.jpg', '<?php echo "x";');
        $this->en('redacteur')->post('/admin/medias', [...$champs, 'titre' => 'Faux', 'fichier' => $faux])
            ->assertSessionHas('erreur', 'Type de fichier refusé : seuls les fichiers JPEG, PNG ou WebP sont acceptés.');

        // Droit d'usage : confirmé par un validateur, jamais par le rédacteur ; une licence exige sa pièce.
        $this->en('redacteur')->post("/admin/medias/{$media->id}/droit", ['droit' => 'propriete_commune'])->assertForbidden();
        $this->en('responsable.validation')->post("/admin/medias/{$media->id}/droit", ['droit' => 'licence'])->assertSessionHasErrors('justificatif_type', null, 'droit');
        $this->en('responsable.validation')->post("/admin/medias/{$media->id}/droit", ['droit' => 'propriete_commune'])->assertSessionHas('statut');
        $this->assertSame('propriete_commune', $media->fresh()->droit->value);

        // Non analysé : il ne peut pas être soumis à la publication.
        $this->en('redacteur')->post("/admin/medias/{$media->id}/soumettre")->assertSessionHas('erreur');
        $this->assertSame(0, ValidationDemande::where('objet_type', 'media')->where('objet_id', $media->id)->count());
    }

    public function test_antivirus_sain_ou_menace(): void
    {
        config(['makary.antivirus.executable' => 'clamdscan']);
        Process::fake(['*' => Process::result(exitCode: 0)]);
        $this->en('redacteur')->post('/admin/medias', ['titre' => 'Photo saine', 'texte_alternatif' => 'Photo', 'auteur' => 'Agent fictif',
            'fichier' => UploadedFile::fake()->image('saine.png', 400, 300)])->assertRedirect();
        $this->assertTrue(Media::where('titre', 'Photo saine')->sole()->fichier->analyse_ok);

        Process::fake(['*' => Process::result(exitCode: 1)]);
        $this->en('redacteur')->post('/admin/medias', ['titre' => 'Photo menaçante', 'texte_alternatif' => 'Photo', 'auteur' => 'Agent fictif',
            'fichier' => UploadedFile::fake()->image('menace.png', 401, 300)])->assertSessionHas('erreur', 'Le fichier a été refusé par l’analyse antivirus.');
        $this->assertSame(0, Media::where('titre', 'Photo menaçante')->count());
        $this->assertSame(1, DB::table('journal_audit')->where('action', 'fichier.menace')->count());

        // Fichiers déposés sans antivirus, analysés ensuite.
        $attente = Fichier::create(['chemin_stockage' => 'televersements/attente.pdf', 'nom_original' => 'attente.pdf', 'type_mime' => 'application/pdf',
            'taille_octets' => 10, 'empreinte_sha256' => str_repeat('b', 64), 'analyse_ok' => null]);
        Storage::disk('local')->put('televersements/attente.pdf', '%PDF-1.4 fictif');
        Process::fake(['*' => Process::result(exitCode: 0)]);
        $this->artisan('makary:analyser-fichiers')->assertSuccessful();
        $this->assertTrue($attente->fresh()->analyse_ok);
    }

    public function test_document_versions_diffusion_et_archivage(): void
    {
        config(['makary.antivirus.executable' => 'clamdscan']);
        Process::fake(['*' => Process::result(exitCode: 0)]);
        $pdf = fn (string $nom) => UploadedFile::fake()->createWithContent($nom, "%PDF-1.4\n% document fictif $nom\n%%EOF");

        $this->en('responsable.projets')->post('/admin/documents', ['titre' => 'Rapport fictif de test', 'slug' => 'rapport-fictif-de-test', 'categorie' => 'rapport',
            'date_document' => '2026-04-01', 'fichier' => $pdf('rapport-v1.pdf')])->assertRedirect();
        $document = Document::where('slug', 'rapport-fictif-de-test')->sole();
        $this->assertSame(1, $document->versions()->count());
        $this->en('responsable.projets')->post("/admin/documents/{$document->id}/versions", ['fichier' => $pdf('rapport-v2.pdf'), 'note' => 'Corrections fictives'])->assertSessionHas('statut');
        $this->assertSame(2, $document->fresh()->derniereVersion->numero);

        // Diffusion : le référent juridique seul ; « public » exige sa pièce.
        $this->en('responsable.projets')->post("/admin/documents/{$document->id}/diffusion", ['diffusion' => 'public'])->assertForbidden();
        $this->en('referent.juridique')->post("/admin/documents/{$document->id}/diffusion", ['diffusion' => 'public'])->assertSessionHasErrors('accord_type', null, 'diffusion');
        $this->en('referent.juridique')->post("/admin/documents/{$document->id}/diffusion", ['diffusion' => 'public',
            'accord_type' => 'piece_officielle', 'accord_date' => '2026-04-02', 'accord_intitule' => 'Accord fictif de diffusion'])->assertSessionHas('statut');
        $this->assertSame('public', $document->fresh()->diffusion->value);
        $this->assertNull($document->fresh()->empechementPublication());

        $this->en('referent.juridique')->post("/admin/documents/{$document->id}/archiver", ['motif_archivage' => 'Remplacé par une version fictive'])->assertSessionHas('statut');
        $this->assertTrue($document->fresh()->archive);
        $this->assertSame(2, $document->versions()->count(), 'rien n’est détruit');
    }

    public function test_photos_d_une_fiche_projet(): void
    {
        $projet = Projet::where('slug', 'forage-du-village-fictif-a')->sole();
        $media = Media::where('titre', '!=', '')->orderByDesc('id')->first();

        $this->en('responsable.editorial')->post("/admin/projets/{$projet->id}/photos", ['media_id' => $media->id])->assertForbidden();
        $this->en('responsable.projets')->post("/admin/projets/{$projet->id}/photos", ['media_id' => $media->id])->assertSessionHas('statut');
        $this->assertTrue($projet->medias()->whereKey($media->id)->exists());
        $this->en('responsable.projets')->get("/admin/projets/{$projet->id}")->assertOk()->assertSee($media->titre);

        $this->en('responsable.projets')->post("/admin/projets/{$projet->id}/photos/{$media->id}/retirer")->assertSessionHas('statut');
        $this->assertFalse($projet->medias()->whereKey($media->id)->exists());
        $this->assertNotNull(Media::find($media->id), 'la photo reste dans la médiathèque');
    }

    public function test_coordonnees_officielles(): void
    {
        $this->en('redacteur')->put('/admin/coordonnees', ['adresse' => 'x'])->assertForbidden();
        $this->en('admin.editorial')->put('/admin/coordonnees', [
            'adresse' => 'Adresse fictive de test', 'telephone' => '+237 000 000 000', 'horaires' => 'Horaires fictifs',
            'services' => [['service' => 'Service fictif', 'courriel' => 'service@exemple.invalid'], ['service' => '', 'telephone' => '1']],
        ])->assertSessionHas('statut');

        $valeur = Parametre::find('coordonnees')->valeur;
        $this->assertSame([['service' => 'Service fictif', 'telephone' => null, 'courriel' => 'service@exemple.invalid']], $valeur['services']);
        $this->get('/')->assertSee('Adresse fictive de test')->assertSee('Horaires fictifs');
        $this->getJson('/api/v1/public/coordonnees')->assertOk()->assertJsonPath('adresse', 'Adresse fictive de test')->assertJsonPath('services.0.service', 'Service fictif');
        $this->assertSame(1, DB::table('journal_audit')->where('action', 'parametre.coordonnees')->count());
    }

    public function test_champs_propres_a_chaque_type_de_contenu(): void
    {
        $base = ['module' => 'M02', 'resume' => 'Résumé fictif'];
        $this->en('redacteur')->post('/admin/contenus', [...$base, 'type_contenu' => 'demarche', 'titre' => 'Démarche fictive de test', 'slug' => 'demarche-fictive-de-test',
            'pieces_a_fournir' => "Pièce fictive A\n\nPièce fictive B\n", 'delai' => '3 jours', 'cout' => 'gratuit', 'service' => 'État civil', 'secteur' => 'eau'])->assertRedirect();
        $this->assertSame(['pieces_a_fournir' => ['Pièce fictive A', 'Pièce fictive B'], 'delai' => '3 jours', 'cout' => 'gratuit', 'service' => 'État civil'],
            Contenu::where('slug', 'demarche-fictive-de-test')->sole()->attributs, 'seuls les champs du type sont conservés');

        $this->en('redacteur')->post('/admin/contenus', ['module' => 'M10', 'type_contenu' => 'actualite', 'titre' => 'Alerte fictive', 'slug' => 'alerte-fictive',
            'alerte_niveau' => 'Vigilance'])->assertSessionHasErrors(['alerte_jusqu_au' => 'Indiquez jusqu’à quand l’alerte est affichée.']);
        $this->en('redacteur')->post('/admin/contenus', ['module' => 'M04', 'type_contenu' => 'opportunite', 'titre' => 'Opportunité fictive', 'slug' => 'opportunite-fictive-test',
            'secteur' => 'commerce', 'conditions' => 'Conditions fictives'])->assertRedirect();
        $this->assertSame('commerce', Contenu::where('slug', 'opportunite-fictive-test')->sole()->attributs['secteur']);
    }

    public function test_un_refus_de_la_base_est_explique_a_l_agent(): void
    {
        $pdo = new PDOException('SQLSTATE[45000]: <<Unknown error>>: 1644 publication refusée : la validation n\'est pas approuvée');
        $pdo->errorInfo = ['45000', 1644, 'publication refusée : la validation n\'est pas approuvée'];
        $requete = request()->create('/admin/validation/1/publier', 'POST');
        $requete->setLaravelSession($this->app['session.store']);
        $reponse = $this->app->make(ExceptionHandler::class)
            ->render($requete, new QueryException('mysql', 'insert into publication …', [], $pdo));

        $this->assertSame(302, $reponse->getStatusCode());
        $this->assertSame('Opération refusée par la base de données : publication refusée : la validation n\'est pas approuvée', session('erreur'));
    }
}
