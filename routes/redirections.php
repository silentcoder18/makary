<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Anciennes adresses du site actuel (docs/conception/arborescence.md, §5).
| 301 : pages institutionnelles reprises. 410 : contenus non repris (kit de collecte, démonstration, faux textes),
| pour accélérer leur désindexation. Les articles et fiches à reprendre « après validation » (§5.3) seront ajoutés
| quand la Commune les aura validés.
*/
foreach ([
    'votre-mairie' => 'maire', 'maire-2' => 'maire', 'historique' => 'decouvrir.histoire', 'la-municipalite-vie-citoyenne' => 'decouvrir',
    'organigramme' => 'mairie.organigramme', 'anciens-dirigeants-1987-1996' => 'mairie.anciens', 'nos-services' => 'mairie.services',
    'nos-offres' => 'decouvrir.equipements', 'projets-realises' => 'projets.realisations', 'projets-envisages' => 'projets',
    'espace-diaspora' => 'diaspora', 'tourisme-culturel' => 'patrimoine', 'sites-touristiques' => 'patrimoine', 'formes-de-loisirs' => 'patrimoine',
    'etablissements-de-loisirs' => 'patrimoine', 'infrastructures-de-loisirs' => 'patrimoine', 'annonces' => 'actualites',
    'nos-evenements' => 'evenements', 'documentation' => 'documents', 'contactez-nous' => 'contact',
] as $ancienne => $route) {
    Route::get($ancienne, fn () => redirect()->route($route, status: 301));
}
foreach (['actes-de-naissances' => 'acte-de-naissance', 'actes-de-mariage' => 'acte-de-mariage', 'actes-de-deces' => 'acte-de-deces'] as $ancienne => $demarche) {
    Route::get($ancienne, fn () => redirect()->route('demarches.show', $demarche, 301));
}
Route::get('projets-en-cours', fn () => redirect()->route('projets', ['statut' => 'en-execution'], 301));

/** Motifs des adresses supprimées : 410 Gone. Toute autre adresse inconnue : 404. */
$contenusRetires = [
    '#^/(adjoints-au-maire|conseillers-municipaux|anciens-maires|annuaire-de-la-commune|identite-de-la-commune|services-offertes-par-la-commune'
        .'|offres-dans-la-commune|projets-de-la-commune|partenaires-de-la-commune|lieux-dattraction-sport-loisir|documentations'
        .'|evenements-de-la-commune|faq-de-la-commune|formulaire-ecole|formulaire-de-collecte-de-donnees)/?$#',
    '#^/(collection|category|tag|author|donations|product|career|artist|exhibition|ova_dep|ova_framework_hf_el)/#',
    '#^/(home-|shop|cart|checkout|my-account)#',
];

Route::fallback(function (Request $request) use ($contenusRetires) {
    foreach ($contenusRetires as $motif) {
        if (preg_match($motif, '/'.$request->path())) {
            abort(410);
        }
    }
    abort(404);
});
