<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\ConnexionController;
use App\Http\Controllers\Portail;
use Illuminate\Support\Facades\Route;

/*
| Portail public (MKY-P3-01) : adresses de l'arborescence P1 (docs/conception/arborescence.md),
| en français, sans accents ni identifiant technique, stables quand un statut change.
*/
Route::get('/', Portail\AccueilController::class)->name('accueil');

// M01 — Découvrir Makary
Route::prefix('decouvrir')->name('decouvrir')->controller(Portail\TerritoireController::class)->group(function () {
    Route::get('/', 'index')->name('');
    Route::get('histoire', 'histoire')->name('.histoire');
    Route::get('territoire', 'territoire')->name('.territoire');
    Route::get('localites', 'localites')->name('.localites');
    Route::get('localites/{localite}', 'localite')->name('.localite');
    Route::get('economie', 'economie')->name('.economie');
    Route::get('equipements', 'equipements')->name('.equipements');
});

// M02 — Le Maire et l'action municipale
Route::controller(Portail\MairieController::class)->group(function () {
    Route::get('le-maire', 'leMaire')->name('maire');
    Route::get('le-maire/vision-et-priorites', 'vision')->name('maire.vision');
    Route::get('le-maire/bilans', 'bilans')->name('maire.bilans');
    Route::get('mairie/executif', 'executif')->name('mairie.executif');
    Route::get('mairie/conseil-municipal', 'conseil')->name('mairie.conseil');
    Route::get('mairie/anciens-maires', 'anciens')->name('mairie.anciens');
    Route::get('mairie/organigramme', 'organigramme')->name('mairie.organigramme');
    Route::get('mairie/services', 'services')->name('mairie.services');
    Route::get('mairie/services/{service}', 'service')->name('mairie.service');
});
Route::controller(Portail\DemarchesController::class)->group(function () {
    Route::get('demarches', 'index')->name('demarches');
    Route::get('demarches/{demarche}', 'show')->name('demarches.show');
});

// M03 — Projets et réalisations (un seul catalogue ; les réalisations en sont une vue filtrée)
Route::prefix('projets')->name('projets')->controller(Portail\ProjetsController::class)->group(function () {
    Route::get('/', 'index')->name('');
    Route::get('realisations', 'realisations')->name('.realisations');
    Route::get('{projet}/fiche.pdf', 'fichePdf')->name('.pdf');
    Route::get('{projet}', 'show')->name('.show');
});

// M04 — Investir à Makary
Route::prefix('investir')->name('investir')->controller(Portail\InvestirController::class)->group(function () {
    Route::get('/', 'index')->name('');
    Route::get('secteurs', 'secteurs')->name('.secteurs');
    Route::get('secteurs/{secteur}', 'secteur')->name('.secteur');
    Route::get('s-implanter', 'implanter')->name('.implanter');
});

// M05 — Partenariats et bailleurs
Route::prefix('partenaires')->name('partenaires')->controller(Portail\PartenairesController::class)->group(function () {
    Route::get('/', 'index')->name('');
    Route::get('besoins-de-financement', 'besoins')->name('.besoins');
    Route::get('nos-partenaires', 'liste')->name('.liste');
});

// M06 — Diaspora
Route::prefix('diaspora')->name('diaspora')->controller(Portail\DiasporaController::class)->group(function () {
    Route::get('/', 'index')->name('');
    Route::get('contribuer', 'contribuer')->name('.contribuer');
});

// Formulaires de demande (M04, M05, M06, M11) : accusé avec numéro de suivi, suivi sans compte.
Route::controller(Portail\DemandesController::class)->group(function () {
    foreach (Portail\DemandesController::FORMULAIRES as $cle => $formulaire) {
        Route::get($formulaire['adresse'], 'formulaire')->name($formulaire['route'])->defaults('formulaire', $cle);
        Route::post($formulaire['adresse'], 'envoyer')->middleware('throttle:formulaires')->defaults('formulaire', $cle);
    }
    Route::get('demande/accuse-de-reception', 'accuse')->name('demande.accuse');
    Route::get('diaspora/suivre-ma-demande', 'suivi')->name('demande.suivi');
    Route::post('diaspora/suivre-ma-demande', 'suivre')->middleware('throttle:connexion');
});

// M08 — Patrimoine et tourisme
Route::prefix('patrimoine-tourisme')->name('patrimoine')->controller(Portail\PatrimoineController::class)->group(function () {
    Route::get('/', 'index')->name('');
    Route::get('culture-et-histoire', 'culture')->name('.culture');
    Route::get('informations-pratiques', 'pratique')->name('.pratique');
    Route::get('{site}', 'site')->name('.site');
});

// M10 — Actualités et événements
Route::controller(Portail\ActualitesController::class)->group(function () {
    Route::get('actualites', 'index')->name('actualites');
    Route::get('actualites/{annee}', 'annee')->whereNumber('annee')->name('actualites.annee');
    Route::get('actualites/{annee}/{article}', 'article')->whereNumber('annee')->name('actualites.article');
    Route::get('evenements', 'evenements')->name('evenements');
    Route::get('evenements/{evenement}', 'evenement')->name('evenements.show');
});

// M07 — Centre documentaire
Route::prefix('documents')->name('documents')->controller(Portail\DocumentsController::class)->group(function () {
    Route::get('/', 'index')->name('');
    Route::get('{document}/telecharger', 'telecharger')->name('.telecharger');
    Route::get('{element}', 'show')->name('.show');
});

// M09 — Cartographie ; M11 — Contact (formulaire ci-dessus)
Route::get('carte', Portail\CarteController::class)->name('carte');

// Pages transversales
Route::controller(Portail\PagesController::class)->group(function () {
    Route::get('recherche', 'recherche')->name('recherche');
    Route::get('plan-du-site', 'planDuSite')->name('plan-du-site');
    foreach (['mentions-legales', 'confidentialite', 'accessibilite', 'credits'] as $page) {
        Route::get($page, 'information')->name($page)->defaults('page', $page);
    }
});

// Fichiers publiés (images, documents) : servis seulement s'ils figurent dans une publication en ligne.
Route::get('fichiers/{fichier}/{nom}', Portail\FichiersController::class)->whereNumber('fichier')->name('fichier');

// Back-office (M01 à M12) : servi sous /admin en développement, sur une adresse distincte en production.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('connexion', [ConnexionController::class, 'formulaire'])->name('connexion');
        Route::post('connexion', [ConnexionController::class, 'connecter'])->middleware('throttle:connexion');
        Route::get('connexion/mfa', [ConnexionController::class, 'formulaireMfa'])->name('mfa');
        Route::post('connexion/mfa', [ConnexionController::class, 'verifierMfa'])->middleware('throttle:mfa');
    });

    // Chaque action vérifie sa permission dans le service métier appelé (contrôle côté serveur, §7.1).
    Route::middleware(['auth', 'mfa'])->group(function () {
        Route::get('/', Admin\TableauDeBordController::class)->name('tableau-de-bord');
        Route::post('deconnexion', [ConnexionController::class, 'deconnecter'])->name('deconnexion');

        // Gestion du portail, rubrique par rubrique (MKY-P3-08)
        Route::get('rubriques', Admin\RubriquesController::class)->name('rubriques');
        Route::get('fichiers/{fichier}', Admin\FichiersController::class)->whereNumber('fichier')->name('fichiers.show');
        Route::get('coordonnees', [Admin\CoordonneesController::class, 'modifier'])->name('coordonnees.modifier');
        Route::match(['post', 'put'], 'coordonnees', [Admin\CoordonneesController::class, 'enregistrer'])->name('coordonnees.enregistrer');
        foreach ([
            'elus' => Admin\ElusController::class, 'localites' => Admin\LocalitesController::class, 'equipements' => Admin\EquipementsController::class,
            'partenaires' => Admin\PartenairesController::class, 'documents' => Admin\DocumentsController::class, 'medias' => Admin\MediasController::class,
        ] as $section => $controleur) {
            Route::controller($controleur)->prefix($section)->name("$section.")->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('nouveau', 'creer')->name('creer');
                Route::post('/', 'enregistrer')->name('enregistrer');
                Route::get('{objet}', 'modifier')->whereNumber('objet')->name('modifier');
                Route::put('{objet}', 'mettreAJour')->whereNumber('objet')->name('mettre-a-jour');
                Route::post('{objet}/soumettre', 'soumettre')->whereNumber('objet')->name('soumettre');
                Route::post('{objet}/publier', 'publier')->whereNumber('objet')->name('publier');
                Route::post('{objet}/depublier', 'depublier')->whereNumber('objet')->name('depublier');
                Route::post('{objet}/retirer', 'retirer')->whereNumber('objet')->name('retirer');
                Route::delete('{objet}', 'detruire')->whereNumber('objet')->name('detruire');
            });
        }
        Route::post('medias/{objet}/droit', [Admin\MediasController::class, 'confirmerDroit'])->whereNumber('objet')->name('medias.droit');
        Route::post('documents/{objet}/versions', [Admin\DocumentsController::class, 'ajouterVersion'])->whereNumber('objet')->name('documents.version');
        Route::post('documents/{objet}/diffusion', [Admin\DocumentsController::class, 'diffusion'])->whereNumber('objet')->name('documents.diffusion');
        Route::post('documents/{objet}/archiver', [Admin\DocumentsController::class, 'archiver'])->whereNumber('objet')->name('documents.archiver');

        Route::controller(Admin\ContenusController::class)->prefix('contenus')->name('contenus.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('nouveau', 'creer')->name('creer');
            Route::post('/', 'enregistrer')->name('enregistrer');
            Route::get('{contenu}', 'modifier')->name('modifier');
            Route::put('{contenu}', 'mettreAJour')->name('mettre-a-jour');
            Route::post('{contenu}/soumettre', 'soumettre')->name('soumettre');
            Route::post('{contenu}/publier', 'publier')->name('publier');
            Route::post('{contenu}/depublier', 'depublier')->name('depublier');
            Route::post('{contenu}/retirer', 'retirer')->name('retirer');
            Route::delete('{contenu}', 'detruire')->name('detruire');
        });

        Route::controller(Admin\ValidationController::class)->prefix('validation')->name('validation.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{validation}', 'show')->name('show');
            Route::post('{validation}/approuver', 'approuver')->name('approuver');
            Route::post('{validation}/approuver-et-publier', 'approuverEtPublier')->name('approuver-publier');
            Route::post('{validation}/renvoyer', 'renvoyer')->name('renvoyer');
            Route::post('{validation}/publier', 'publier')->name('publier');
            Route::post('{validation}/retirer', 'retirer')->name('retirer');
        });

        Route::controller(Admin\ProjetsController::class)->name('projets.')->group(function () {
            Route::get('projets', 'index')->name('index');
            Route::get('projets/nouveau', 'creer')->name('creer');
            Route::post('projets', 'enregistrer')->name('enregistrer');
            Route::get('projets/{projet}', 'show')->name('show');
            Route::put('projets/{projet}', 'mettreAJour')->name('mettre-a-jour');
            Route::post('projets/{projet}/elements/{relation}', 'ajouterElement')->whereIn('relation', ['estimations', 'beneficiaires', 'indicateurs', 'pieces'])->name('element');
            Route::post('projets/{projet}/financements', 'ajouterLigne')->name('ligne');
            Route::post('financements/{ligne}/acquis', 'validerAcquis')->name('acquis');
            Route::post('projets/{projet}/transitions', 'demanderTransition')->name('transition');
            Route::post('transitions/{transition}/approuver', 'approuverTransition')->name('transition.approuver');
            Route::post('transitions/{transition}/refuser', 'refuserTransition')->name('transition.refuser');
            Route::post('projets/{projet}/soumettre', 'soumettre')->name('soumettre');
            Route::post('projets/{projet}/publier', 'publier')->name('publier');
            Route::post('projets/{projet}/depublier', 'depublier')->name('depublier');
            Route::post('projets/{projet}/catalogue', 'catalogue')->name('catalogue');
            Route::post('projets/{projet}/photos', 'ajouterPhoto')->name('photo');
            Route::post('projets/{projet}/photos/{media}/retirer', 'retirerPhoto')->name('photo.retirer');
        });

        Route::controller(Admin\DemandesController::class)->prefix('demandes')->name('demandes.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{demande}', 'show')->name('show');
            Route::post('{demande}/{action}', 'agir')->whereIn('action', ['qualifier', 'affecter', 'commencer', 'repondre', 'cloturer', 'rejeter'])->name('agir');
        });

        Route::controller(Admin\UtilisateursController::class)->prefix('utilisateurs')->name('utilisateurs.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('nouveau', 'creer')->name('creer');
            Route::post('/', 'enregistrer')->name('enregistrer');
            Route::get('{utilisateur}/modifier', 'modifier')->whereNumber('utilisateur')->name('modifier');
            Route::put('{utilisateur}', 'mettreAJour')->whereNumber('utilisateur')->name('mettre-a-jour');
            Route::post('{utilisateur}/statut', 'basculerStatut')->whereNumber('utilisateur')->name('statut');
            Route::delete('{utilisateur}', 'detruire')->whereNumber('utilisateur')->name('detruire');
        });

        Route::get('journal', [Admin\JournalController::class, 'index'])->name('journal.index');
    });
});

// Anciennes adresses du site actuel : 301 vers la nouvelle page, 410 pour les contenus non repris (arborescence §5).
require __DIR__.'/redirections.php';
