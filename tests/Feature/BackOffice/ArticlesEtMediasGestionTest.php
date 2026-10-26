<?php

namespace Tests\Feature\BackOffice;

use App\Models\Contenu;
use App\Models\Media;
use App\Models\Projet;
use Illuminate\Http\UploadedFile;

class ArticlesEtMediasGestionTest extends BackOfficeTestCase
{
    public function test_administrateur_peut_gerer_article_completement(): void
    {
        $admin = $this->compte('superadmin');

        // 1. Ajouter / Créer un article
        $reponse = $this->en($admin)->post(route('admin.contenus.enregistrer'), [
            'type_contenu' => 'actualite',
            'module' => 'M10',
            'titre' => 'Nouvelle passerelle inaugurée à Makary',
            'slug' => 'nouvelle-passerelle-inauguree-a-makary',
            'resume' => 'Inauguration officielle ce matin.',
            'corps' => 'Le maire et le conseil ont inauguré la nouvelle passerelle.',
        ]);

        $article = Contenu::where('slug', 'nouvelle-passerelle-inauguree-a-makary')->firstOrFail();
        $reponse->assertRedirect(route('admin.contenus.modifier', $article));
        $this->assertSame('brouillon', $article->etat->value);

        // 2. Modifier l'article
        $this->en($admin)->put(route('admin.contenus.mettre-a-jour', $article), [
            'type_contenu' => 'actualite',
            'module' => 'M10',
            'titre' => 'Nouvelle passerelle moderne inaugurée à Makary',
            'slug' => 'nouvelle-passerelle-inauguree-a-makary',
            'resume' => 'Inauguration officielle ce matin avec les citoyens.',
            'corps' => 'Le maire et le conseil ont inauguré la nouvelle passerelle.',
        ])->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame('Nouvelle passerelle moderne inaugurée à Makary', $article->titre);

        // 3. Publier directement l'article
        $this->en($admin)->post(route('admin.contenus.publier', $article))
            ->assertSessionHas('statut', 'Contenu publié directement sur le portail public.');

        $article->refresh();
        $this->assertSame('publie', $article->etat->value);
        $this->assertNotNull($article->publicationCourante());

        // 4. Supprimer l'article
        $this->en($admin)->delete(route('admin.contenus.detruire', $article))
            ->assertRedirect(route('admin.contenus.index'));

        $this->assertDatabaseMissing('contenu', ['id' => $article->id]);
    }

    public function test_administrateur_peut_gerer_media_completement(): void
    {
        $admin = $this->compte('superadmin');

        // 1. Ajouter / Téléverser une photo
        $fichier = UploadedFile::fake()->image('marche.jpg', 800, 600);
        $reponse = $this->en($admin)->post(route('admin.medias.enregistrer'), [
            'fichier' => $fichier,
            'titre' => 'Marché central de Makary',
            'texte_alternatif' => 'Vue animée des étals du marché',
            'auteur' => 'Service communication',
            'date_prise' => '2026-03-15',
            'lieu' => 'Makary centre',
            'droit_initial' => 'propriete_commune',
        ]);

        $reponse->assertSessionHasNoErrors();
        $media = Media::where('titre', 'Marché central de Makary')->firstOrFail();
        $reponse->assertRedirect(route('admin.medias.modifier', $media));

        // 2. Modifier le média
        $this->en($admin)->put(route('admin.medias.mettre-a-jour', $media), [
            'titre' => 'Marché central hebdomadaire de Makary',
            'texte_alternatif' => 'Vue animée des commerçants du marché',
            'auteur' => 'Service communication',
            'date_prise' => '2026-03-15',
            'lieu' => 'Makary centre',
        ])->assertSessionHasNoErrors();

        $media->refresh();
        $this->assertSame('Marché central hebdomadaire de Makary', $media->titre);

        // Valider l'analyse antivirus si requise pour autoriser la publication
        $media->fichier->update(['analyse_ok' => true]);

        // 3. Publier directement le média
        $this->en($admin)->post(route('admin.medias.publier', $media))
            ->assertSessionHas('statut');

        $media->refresh();
        $this->assertNotNull($media->publicationCourante());

        // 4. Supprimer le média
        $this->en($admin)->delete(route('admin.medias.detruire', $media))
            ->assertRedirect(route('admin.medias.index'));

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_publication_directe_a_la_creation(): void
    {
        $admin = $this->compte('superadmin');

        $reponse = $this->en($admin)->post(route('admin.contenus.enregistrer'), [
            'action' => 'publier',
            'type_contenu' => 'actualite',
            'module' => 'M10',
            'titre' => 'Publication immédiate test',
            'slug' => 'publication-immediate-test',
            'resume' => 'Résumé direct.',
            'corps' => 'Corps du contenu publié immédiatement.',
        ]);

        $article = Contenu::where('slug', 'publication-immediate-test')->firstOrFail();
        $reponse->assertRedirect(route('admin.contenus.modifier', $article));
        $reponse->assertSessionHas('statut', 'Contenu créé et publié immédiatement sur le portail public.');

        $this->assertSame('publie', $article->etat->value);
        $pub = $article->publicationCourante();
        $this->assertNotNull($pub);
        $this->assertSame(1, $pub->version);
        $this->assertNull($pub->retire_le);

        // Nettoyage
        $article->publications()->delete();
        $article->validations()->delete();
        $article->delete();
    }

    public function test_mise_a_jour_article_publie_met_a_jour_directement_le_portail(): void
    {
        $admin = $this->compte('superadmin');

        // Créer et publier
        $this->en($admin)->post(route('admin.contenus.enregistrer'), [
            'action' => 'publier',
            'type_contenu' => 'actualite',
            'module' => 'M10',
            'titre' => 'Titre initial v1',
            'slug' => 'titre-initial-v1',
            'resume' => 'Résumé initial.',
            'corps' => 'Corps v1.',
        ]);

        $article = Contenu::where('slug', 'titre-initial-v1')->firstOrFail();
        $pubV1 = $article->publicationCourante();
        $this->assertSame(1, $pubV1->version);

        // Modifier l'article déjà publié
        $this->en($admin)->put(route('admin.contenus.mettre-a-jour', $article), [
            'action' => 'publier',
            'type_contenu' => 'actualite',
            'module' => 'M10',
            'titre' => 'Titre mis a jour v2',
            'slug' => 'titre-initial-v1',
            'resume' => 'Résumé mis à jour.',
            'corps' => 'Corps v2 enrichi.',
        ])->assertSessionHas('statut', 'Modifications enregistrées et publiées en direct sur le portail.');

        $article->refresh();
        $pubV2 = $article->publicationCourante();
        $this->assertNotNull($pubV2);
        $this->assertSame(2, $pubV2->version);
        $this->assertSame('Titre mis a jour v2', $pubV2->instantane['titre']);

        // Vérifier que la v1 a été retirée au profit de la v2
        $pubV1->refresh();
        $this->assertNotNull($pubV1->retire_le);

        // Nettoyage
        $article->publications()->delete();
        $article->validations()->delete();
        $article->delete();
    }

    public function test_depublier_remet_en_brouillon_et_retire_du_portail(): void
    {
        $admin = $this->compte('superadmin');

        // Créer et publier
        $this->en($admin)->post(route('admin.contenus.enregistrer'), [
            'action' => 'publier',
            'type_contenu' => 'actualite',
            'module' => 'M10',
            'titre' => 'Article a depublier',
            'slug' => 'article-a-depublier',
            'resume' => 'Résumé.',
            'corps' => 'Corps.',
        ]);

        $article = Contenu::where('slug', 'article-a-depublier')->firstOrFail();
        $this->assertSame('publie', $article->etat->value);
        $this->assertNotNull($article->publicationCourante());

        // 1-clic Dépublier
        $this->en($admin)->post(route('admin.contenus.depublier', $article))
            ->assertSessionHas('statut', 'Contenu dépublié du portail public et remis en brouillon.');

        $article->refresh();
        $this->assertSame('brouillon', $article->etat->value);
        $this->assertNull($article->publicationCourante());

        // Nettoyage
        $article->publications()->delete();
        $article->validations()->delete();
        $article->delete();
    }

    public function test_secretaire_general_a_acces_complet_au_circuit_simplifie(): void
    {
        $sg = $this->compte('secretaire_general');

        // SG crée et publie directement
        $reponse = $this->en($sg)->post(route('admin.contenus.enregistrer'), [
            'action' => 'publier',
            'type_contenu' => 'actualite',
            'module' => 'M10',
            'titre' => 'Article rédigé par SG',
            'slug' => 'article-redige-par-sg',
            'resume' => 'Résumé par le secrétaire général.',
            'corps' => 'Corps officiel.',
        ]);

        $article = Contenu::where('slug', 'article-redige-par-sg')->firstOrFail();
        $reponse->assertRedirect(route('admin.contenus.modifier', $article));
        $this->assertSame('publie', $article->etat->value);

        // SG dépublie en 1 clic
        $this->en($sg)->post(route('admin.contenus.depublier', $article))
            ->assertSessionHas('statut', 'Contenu dépublié du portail public et remis en brouillon.');

        $article->refresh();
        $this->assertSame('brouillon', $article->etat->value);

        // SG republie en 1 clic
        $this->en($sg)->post(route('admin.contenus.publier', $article))
            ->assertSessionHas('statut', 'Contenu publié directement sur le portail public.');

        $article->refresh();
        $this->assertSame('publie', $article->etat->value);
        $this->assertNotNull($article->publicationCourante());

        // Nettoyage
        $article->publications()->delete();
        $article->validations()->delete();
        $article->delete();
    }

    public function test_administrateur_peut_approuver_et_publier_directement_sa_soumission(): void
    {
        $admin = $this->compte('superadmin');

        // Créer un média
        $fichier = UploadedFile::fake()->image('test_maire.png', 400, 400);
        $this->en($admin)->post(route('admin.medias.enregistrer'), [
            'fichier' => $fichier,
            'titre' => 'Portrait officiel test',
            'texte_alternatif' => 'Portrait du maire',
            'auteur' => 'Service comm',
            'droit_initial' => 'propriete_commune',
        ])->assertSessionHasNoErrors();

        $media = Media::where('titre', 'Portrait officiel test')->firstOrFail();
        $media->fichier->update(['analyse_ok' => true]);

        // Soumettre à la revue
        $this->en($admin)->post(route('admin.medias.soumettre', $media))->assertSessionHasNoErrors();

        $validation = $media->validationEnAttente();
        $this->assertNotNull($validation);
        $this->assertSame($admin->id, $validation->soumis_par);

        // L'admin peut approuver et publier en direct même s'il est le créateur
        $this->en($admin)->post(route('admin.validation.approuver-publier', $validation))
            ->assertRedirect(route('admin.validation.index'))
            ->assertSessionHas('statut');

        $media->refresh();
        $this->assertNotNull($media->publicationCourante());

        // Nettoyage
        $media->publications()->delete();
        $media->validations()->delete();
        $media->delete();
    }

    public function test_soumettre_article_a_la_creation_puis_validation_par_secretaire_general(): void
    {
        $superadmin = $this->compte('superadmin');
        $sg = $this->compte('secretaire_general');

        // Créer un article en choisissant l'action "soumettre"
        $reponse = $this->en($superadmin)->post(route('admin.contenus.enregistrer'), [
            'action' => 'soumettre',
            'type_contenu' => 'actualite',
            'module' => 'M10',
            'titre' => 'Projet solaire à Makary',
            'slug' => 'projet-solaire-a-makary',
            'resume' => 'Installation de panneaux solaires.',
            'corps' => 'Détails du projet solaire communal.',
        ]);

        $article = Contenu::where('slug', 'projet-solaire-a-makary')->firstOrFail();
        $reponse->assertRedirect(route('admin.contenus.modifier', $article));
        $reponse->assertSessionHas('statut', 'Contenu créé et soumis à la validation du Super Administrateur ou du Secrétariat Général.');

        $this->assertSame('en_revue', $article->etat->value);
        $demande = $article->validationEnAttente();
        $this->assertNotNull($demande);
        $this->assertSame('en_attente', $demande->decision->value);

        // Vérifier l'apparition dans le centre de validation
        $pageValidation = $this->en($sg)->get(route('admin.validation.index'));
        $pageValidation->assertOk();
        $pageValidation->assertSee('Projet solaire à Makary');
        $pageValidation->assertSee('Articles & Pages');

        // Le Secrétaire Général valide et publie en 1 clic
        $reponseValidation = $this->en($sg)->post(route('admin.validation.approuver-publier', $demande));
        $reponseValidation->assertRedirect(route('admin.validation.index'));
        $reponseValidation->assertSessionHas('statut');

        $article->refresh();
        $this->assertSame('publie', $article->etat->value);
        $this->assertNotNull($article->publicationCourante());
        $this->assertSame(1, $article->publicationCourante()->version);

        // Nettoyage
        $article->publications()->delete();
        $article->validations()->delete();
        $article->delete();
    }

    public function test_televersement_media_avec_action_soumettre_et_filtres_validation(): void
    {
        config(['makary.antivirus.requis' => false]);

        $superadmin = $this->compte('superadmin');
        $sg = $this->compte('secretaire_general');

        $fichier = UploadedFile::fake()->image('ecole.png', 600, 400);
        $reponse = $this->en($sg)->post(route('admin.medias.enregistrer'), [
            'action' => 'soumettre',
            'fichier' => $fichier,
            'titre' => 'École publique de Makary',
            'texte_alternatif' => 'Bâtiment rénové de l école',
            'auteur' => 'Direction technique',
            'droit_initial' => 'propriete_commune',
        ]);

        $media = Media::where('titre', 'École publique de Makary')->firstOrFail();
        $reponse->assertRedirect(route('admin.medias.modifier', $media));

        $demande = $media->validationEnAttente();
        $this->assertNotNull($demande);
        $this->assertSame('en_attente', $demande->decision->value);

        // Filtrer par médias dans le centre de validation
        $vueFiltre = $this->en($superadmin)->get(route('admin.validation.index', ['type' => 'media']));
        $vueFiltre->assertOk();
        $vueFiltre->assertSee('École publique de Makary');

        // Validation en 1 clic par le Superadmin
        $this->en($superadmin)->post(route('admin.validation.approuver-publier', $demande))
            ->assertRedirect(route('admin.validation.index'));

        $media->refresh();
        $this->assertNotNull($media->publicationCourante());

        // Nettoyage
        $media->publications()->delete();
        $media->validations()->delete();
        $media->delete();
    }

    public function test_administrateur_peut_publier_et_depublier_projet_directement(): void
    {
        $superadmin = $this->compte('superadmin');
        $projet = Projet::where('slug', 'forage-du-village-fictif-a')->firstOrFail();

        // 1. Publier directement le projet
        $this->en($superadmin)->post(route('admin.projets.publier', $projet))
            ->assertSessionHas('statut', 'Fiche projet publiée directement sur le portail.');

        $projet->refresh();
        $this->assertSame('publie', $projet->etat->value);
        $this->assertNotNull($projet->publicationCourante());

        // 2. Dépublier le projet
        $this->en($superadmin)->post(route('admin.projets.depublier', $projet))
            ->assertSessionHas('statut', 'Fiche projet retirée du portail et remise en brouillon.');

        $projet->refresh();
        $this->assertSame('brouillon', $projet->etat->value);
        $this->assertNull($projet->publicationCourante());
    }
}
