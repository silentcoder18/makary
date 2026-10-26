<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Editorial\Saisie;
use App\Domaine\Enums\TypeObjet;
use App\Http\Controllers\Controller;
use App\Models\Contenu;
use App\Models\Demande;
use App\Models\Document;
use App\Models\Elu;
use App\Models\Equipement;
use App\Models\Localite;
use App\Models\Media;
use App\Models\Parametre;
use App\Models\Partenaire;
use App\Models\Projet;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * « Rubriques du portail » (MKY-P3-08) : pour chacune des 12 rubriques du §3.1, ce qui l'alimente — pages attendues
 * et fiches —, leur état et l'accès à leur gestion. Seuls les éléments que l'agent peut consulter sont affichés.
 */
class RubriquesController extends Controller
{
    /** Pages éditoriales (contenus de type « page ») lues par le portail : adresse => [titre, module]. */
    public const PAGES = [
        'presentation' => ['Présentation de la commune', 'M01'],
        'histoire' => ['Histoire de Makary', 'M01'],
        'territoire' => ['Le territoire', 'M01'],
        'economie' => ['Économie locale', 'M01'],
        'mot-du-maire' => ['Le mot du Maire', 'M02'],
        'vision-et-priorites' => ['Vision et priorités', 'M02'],
        'organigramme' => ['Organigramme', 'M02'],
        's-implanter' => ['S’implanter à Makary', 'M04'],
        'diaspora' => ['La diaspora et la commune', 'M06'],
        'contribuer' => ['Contribuer depuis la diaspora', 'M06'],
        'culture-et-histoire' => ['Culture et histoire', 'M08'],
        'informations-pratiques' => ['Informations pratiques', 'M08'],
    ];

    public const TYPES_AU_PLURIEL = ['page' => 'Pages', 'actualite' => 'Actualités', 'evenement' => 'Événements', 'site_patrimoine' => 'Sites du patrimoine',
        'service' => 'Services municipaux', 'demarche' => 'Démarches', 'opportunite' => 'Opportunités d’investissement'];

    /** Les 12 rubriques du portail (§3.1) : [titre, route du portail, pages, éléments]. */
    public const RUBRIQUES = [
        ['Accueil', 'accueil', [], ['coordonnees', 'contenus:actualite']],
        ['Découvrir Makary', 'decouvrir', ['presentation', 'histoire', 'territoire', 'economie'], ['localites', 'equipements']],
        ['Le Maire et l’action municipale', 'maire', ['mot-du-maire', 'vision-et-priorites', 'organigramme'], ['elus', 'contenus:service', 'contenus:demarche']],
        ['Projets et réalisations', 'projets', [], ['projets', 'medias']],
        ['Investir à Makary', 'investir', ['s-implanter'], ['contenus:opportunite']],
        ['Partenariats et bailleurs', 'partenaires', [], ['partenaires', 'projets']],
        ['Diaspora', 'diaspora', ['diaspora', 'contribuer'], ['demandes']],
        ['Patrimoine et tourisme', 'patrimoine', ['culture-et-histoire', 'informations-pratiques'], ['contenus:site_patrimoine', 'medias']],
        ['Actualités et événements', 'actualites', [], ['contenus:actualite', 'contenus:evenement']],
        ['Centre documentaire', 'documents', [], ['documents']],
        ['Cartographie', 'carte', [], ['localites', 'equipements', 'projets']],
        ['Contact', 'contact', [], ['coordonnees', 'demandes']],
    ];

    public function __invoke(Request $request): View
    {
        $agent = $request->user();
        abort_unless(Gate::any(['contenu.lire_brouillons', 'projet.lire_interne', 'partenaire.modifier', 'document.gerer', 'media.televerser',
            'referentiel.modifier', 'parametre.modifier_editorial']), 403);

        $pages = Contenu::where('type_contenu', 'page')->whereIn('slug', array_keys(self::PAGES))->get()->keyBy('slug');
        $rubriques = array_map(fn (array $r) => [
            'titre' => $r[0],
            'portail' => route($r[1]),
            'pages' => Gate::allows('contenu.lire_brouillons') ? array_map(fn (string $slug) => $this->page($slug, $pages->get($slug), $agent), $r[2]) : [],
            'elements' => array_values(array_filter(array_map(fn (string $cle) => $this->element($cle, $agent), $r[3]))),
        ], self::RUBRIQUES);

        return view('admin.rubriques', ['rubriques' => $rubriques]);
    }

    private function page(string $slug, ?Contenu $page, Utilisateur $agent): array
    {
        [$titre, $module] = self::PAGES[$slug];

        return [
            'titre' => $titre, 'etat' => $page?->etat,
            'lien' => $page ? route('admin.contenus.modifier', $page)
                : (Gate::forUser($agent)->allows('contenu.creer') ? route('admin.contenus.creer', ['type' => 'page', 'module' => $module, 'slug' => $slug, 'titre' => $titre]) : null),
        ];
    }

    /** Élément d'une rubrique, ou null si l'agent ne peut pas le consulter. */
    private function element(string $cle, Utilisateur $agent): ?array
    {
        $peut = fn (string ...$permissions) => Gate::forUser($agent)->any($permissions);
        $etats = fn ($requete) => $requete->selectRaw('etat, COUNT(*) AS n')->groupBy('etat')->pluck('n', 'etat')->all();
        $objet = fn (string $section, string $libelle, TypeObjet $type, $requete) => $peut('contenu.lire_brouillons', ...array_values(Saisie::PERMISSIONS[$type->value]))
            ? ['libelle' => $libelle, 'lien' => route("admin.$section.index"), 'etats' => $etats($requete),
                'nouveau' => Saisie::peutCreer($agent, $type) ? route("admin.$section.creer") : null]
            : null;

        if (str_starts_with($cle, 'contenus:')) {
            $type = substr($cle, 9);

            return $peut('contenu.lire_brouillons') ? [
                'libelle' => self::TYPES_AU_PLURIEL[$type], 'lien' => route('admin.contenus.index', ['type' => $type]),
                'etats' => $etats(Contenu::where('type_contenu', $type)),
                'nouveau' => $peut('contenu.creer') ? route('admin.contenus.creer', ['type' => $type]) : null,
            ] : null;
        }

        return match ($cle) {
            'elus' => $objet('elus', 'Élus', TypeObjet::Elu, Elu::query()),
            'localites' => $objet('localites', 'Localités', TypeObjet::Localite, Localite::query()),
            'equipements' => $objet('equipements', 'Équipements', TypeObjet::Equipement, Equipement::query()),
            'partenaires' => $objet('partenaires', 'Partenaires', TypeObjet::Partenaire, Partenaire::query()),
            'documents' => $objet('documents', 'Documents', TypeObjet::Document, Document::query()),
            'medias' => $peut('contenu.lire_brouillons', 'media.televerser') ? [
                'libelle' => 'Médias (photos)', 'lien' => route('admin.medias.index'),
                'note' => Media::count().' photo(s), dont '.Media::where('droit', '!=', 'a_verifier')->count().' au droit d’usage vérifié',
                'nouveau' => $peut('media.televerser') ? route('admin.medias.creer') : null,
            ] : null,
            'projets' => $peut('projet.lire_interne') ? [
                'libelle' => 'Fiches projets', 'lien' => route('admin.projets.index'), 'etats' => $etats(Projet::query()),
                'nouveau' => $peut('projet.creer') ? route('admin.projets.creer') : null,
            ] : null,
            'coordonnees' => $peut('parametre.modifier_editorial') ? [
                'libelle' => 'Coordonnées officielles', 'lien' => route('admin.coordonnees.modifier'),
                'note' => filled(Parametre::find('coordonnees')?->valeur['adresse'] ?? null) ? 'renseignées' : 'à saisir',
            ] : null,
            'demandes' => $peut('demande.lire') ? [
                'libelle' => 'Demandes reçues', 'lien' => route('admin.demandes.index'),
                'note' => Demande::whereIn('statut', ['recue', 'qualifiee', 'affectee', 'en_traitement'])->count().' en cours',
            ] : null,
            default => null,
        };
    }
}
