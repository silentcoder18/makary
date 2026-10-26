<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Editorial\CircuitEditorial;
use App\Domaine\Editorial\Redaction;
use App\Domaine\Enums\EtatEditorial;
use App\Http\Controllers\Controller;
use App\Models\Contenu;
use App\Models\Localite;
use App\Models\Media;
use App\Models\Secteur;
use App\Securite\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Contenus éditoriaux : rédaction, modification et soumission à la revue (le rédacteur ne valide ni ne publie). */
class ContenusController extends Controller
{
    public const TYPES = ['page' => 'Page', 'actualite' => 'Actualité', 'evenement' => 'Événement', 'site_patrimoine' => 'Site du patrimoine',
        'service' => 'Service municipal', 'demarche' => 'Démarche', 'opportunite' => 'Opportunité'];

    public const MODULES = ['M01' => 'M01 Accueil et territoire', 'M02' => 'M02 Maire et action municipale', 'M04' => 'M04 Investir', 'M06' => 'M06 Diaspora',
        'M08' => 'M08 Patrimoine et tourisme', 'M10' => 'M10 Actualités et événements', 'M11' => 'M11 Contact'];

    /** Niveaux d'une alerte affichée en tête de la page d'accueil (actualité). */
    public const NIVEAUX_ALERTE = ['Information' => 'Information', 'Vigilance' => 'Vigilance', 'Alerte' => 'Alerte'];

    public function __construct(private readonly Redaction $redaction, private readonly CircuitEditorial $circuit) {}

    public function index(Request $request): View
    {
        Gate::authorize('contenu.lire_brouillons');
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : null;

        return view('admin.contenus.index', [
            'contenus' => Contenu::with('auteur')->when($type, fn ($q) => $q->where('type_contenu', $type))->orderByDesc('modifie_le')->paginate(20)->withQueryString(),
            'type' => $type,
        ]);
    }

    /** Nouveau contenu ; la page « Rubriques du portail » pré-remplit le type, le module, l'adresse et le titre d'une page attendue. */
    public function creer(Request $request): View
    {
        Gate::authorize('contenu.creer');
        $type = array_key_exists((string) $request->query('type'), self::TYPES) ? $request->query('type') : 'actualite';
        $module = array_key_exists((string) $request->query('module'), self::MODULES) ? $request->query('module') : ($type === 'actualite' ? 'M10' : null);
        $slug = preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) $request->query('slug')) ? $request->query('slug') : null;

        return view('admin.contenus.formulaire', [
            'contenu' => new Contenu(['type_contenu' => $type, 'module' => $module, 'slug' => $slug, 'titre' => mb_substr((string) $request->query('titre'), 0, 160) ?: null]),
            ...$this->listes(),
        ]);
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request);
        $contenu = $this->redaction->creer($request->user(), $donnees);

        $action = $request->input('action', 'brouillon');
        if ($action === 'publier' && $request->user()->can('contenu.publier', $contenu)) {
            $this->circuit->publierDirectement($contenu, $request->user());

            return redirect()->route('admin.contenus.modifier', $contenu)
                ->with('statut', 'Contenu créé et publié immédiatement sur le portail public.');
        }

        if ($action === 'soumettre' && $request->user()->can('contenu.soumettre', $contenu)) {
            $this->circuit->soumettre($contenu, $request->user());

            return redirect()->route('admin.contenus.modifier', $contenu)
                ->with('statut', 'Contenu créé et soumis à la validation du Super Administrateur ou du Secrétariat Général.');
        }

        return redirect()->route('admin.contenus.modifier', $contenu)->with('statut', 'Brouillon enregistré.');
    }

    public function modifier(Contenu $contenu): View
    {
        Gate::authorize('contenu.lire_brouillons');

        return view('admin.contenus.formulaire', [
            'contenu' => $contenu,
            ...$this->listes(),
            'validation' => $contenu->validations()->with(['decidePar', 'soumisPar'])->latest('id')->first(),
            'publication' => $contenu->publicationCourante(),
            'lienPublic' => $this->lienPublic($contenu),
        ]);
    }

    public function mettreAJour(Request $request, Contenu $contenu): RedirectResponse
    {
        $donnees = $this->valider($request, $contenu);
        $this->redaction->modifier($contenu, $request->user(), $donnees);

        $action = $request->input('action');
        $estPublie = $contenu->etat === EtatEditorial::Publie || $contenu->etat?->value === 'publie';

        if ($action === 'depublier') {
            Gate::authorize('contenu.retirer', $contenu);
            $this->circuit->depublier($contenu, $request->user());

            return back()->with('statut', 'Contenu dépublié du portail et remis en brouillon.');
        }

        if ($action === 'publier' || ($estPublie && $action !== 'brouillon' && $action !== 'soumettre')) {
            if ($request->user()->can('contenu.publier', $contenu)) {
                $this->circuit->publierDirectement($contenu, $request->user());

                return back()->with('statut', 'Modifications enregistrées et publiées en direct sur le portail.');
            }
        }

        if ($action === 'soumettre' && $request->user()->can('contenu.soumettre', $contenu)) {
            $this->circuit->soumettre($contenu, $request->user());

            return back()->with('statut', 'Modifications enregistrées et soumises à la validation du Super Administrateur ou du Secrétariat Général.');
        }

        return back()->with('statut', 'Modifications enregistrées en brouillon.');
    }

    public function soumettre(Request $request, Contenu $contenu): RedirectResponse
    {
        $this->circuit->soumettre($contenu, $request->user());

        return back()->with('statut', 'Contenu soumis à la validation du Super Administrateur ou du Secrétariat Général.');
    }

    public function publier(Request $request, Contenu $contenu): RedirectResponse
    {
        Gate::authorize('contenu.publier');
        $this->circuit->publierDirectement($contenu, $request->user());

        return back()->with('statut', 'Contenu publié directement sur le portail public.');
    }

    public function depublier(Request $request, Contenu $contenu): RedirectResponse
    {
        Gate::authorize('contenu.retirer', $contenu);
        $this->circuit->depublier($contenu, $request->user());

        return back()->with('statut', 'Contenu dépublié du portail public et remis en brouillon.');
    }

    public function detruire(Request $request, Contenu $contenu): RedirectResponse
    {
        Gate::authorize('contenu.retirer');
        $titre = $contenu->titre;

        $contenu->publications()->delete();
        $contenu->validations()->delete();
        $contenu->delete();

        Journal::consigner('contenu.suppression', null, [
            'type' => $contenu->type_contenu,
            'titre' => $titre,
        ]);

        return redirect()->route('admin.contenus.index')
            ->with('statut', "Le contenu « {$titre} » a été définitivement supprimé.");
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, ?Contenu $contenu = null): array
    {
        $donnees = $request->validate([
            'type_contenu' => ['required', Rule::in(array_keys(self::TYPES))],
            'module' => ['required', Rule::in(array_keys(self::MODULES))],
            'titre' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('contenu')->where('type_contenu', $request->input('type_contenu'))->ignore($contenu?->id)],
            'resume' => ['nullable', 'string', 'max:500'],
            'corps' => ['nullable', 'string', 'max:50000'],
            'localite_id' => ['nullable', 'integer', 'exists:localite,id'],
            'image_id' => ['nullable', 'integer', 'exists:media,id'],
            'publication_programmee' => ['nullable', 'date', 'after:now'],
            // Champs propres au type (contenu.attributs) : ceux des autres types sont ignorés.
            'date_debut' => ['nullable', 'required_if:type_contenu,evenement', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'lieu' => ['nullable', 'string', 'max:160'],
            'alerte_niveau' => ['nullable', Rule::in(array_keys(self::NIVEAUX_ALERTE))],
            'alerte_jusqu_au' => ['nullable', 'required_with:alerte_niveau', 'date'],
            'pieces_a_fournir' => ['nullable', 'string', 'max:3000'],
            'delai' => ['nullable', 'string', 'max:160'],
            'cout' => ['nullable', 'string', 'max:160'],
            'service' => ['nullable', 'string', 'max:160'],
            'missions' => ['nullable', 'string', 'max:3000'],
            'horaires' => ['nullable', 'string', 'max:300'],
            'contact_generique' => ['nullable', 'string', 'max:300'],
            'acces' => ['nullable', 'string', 'max:1000'],
            'periode_conseillee' => ['nullable', 'string', 'max:300'],
            'informations_pratiques' => ['nullable', 'string', 'max:2000'],
            'secteur' => ['nullable', 'exists:secteur,code'],
            'conditions' => ['nullable', 'string', 'max:2000'],
            'interlocuteur' => ['nullable', 'string', 'max:300'],
        ], [
            'slug.regex' => 'L’adresse ne contient que des minuscules sans accents, des chiffres et des tirets.',
            'alerte_jusqu_au.required_with' => 'Indiquez jusqu’à quand l’alerte est affichée.',
        ]);

        $lignes = fn (?string $texte) => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $texte))));
        $attributs = array_filter(match ($donnees['type_contenu']) {
            'evenement' => [
                'date_debut' => Carbon::parse($donnees['date_debut'])->toIso8601String(),
                'date_fin' => isset($donnees['date_fin']) ? Carbon::parse($donnees['date_fin'])->toIso8601String() : null,
                'lieu' => $donnees['lieu'] ?? null,
            ],
            'actualite' => ['alerte' => isset($donnees['alerte_niveau'])
                ? ['niveau' => $donnees['alerte_niveau'], 'jusqu_au' => Carbon::parse($donnees['alerte_jusqu_au'])->toDateString()] : null],
            'demarche' => ['pieces_a_fournir' => $lignes($donnees['pieces_a_fournir'] ?? null) ?: null, 'delai' => $donnees['delai'] ?? null,
                'cout' => $donnees['cout'] ?? null, 'service' => $donnees['service'] ?? null],
            'service' => ['missions' => $lignes($donnees['missions'] ?? null) ?: null, 'horaires' => $donnees['horaires'] ?? null,
                'contact_generique' => $donnees['contact_generique'] ?? null],
            'site_patrimoine' => ['acces' => $donnees['acces'] ?? null, 'periode_conseillee' => $donnees['periode_conseillee'] ?? null,
                'informations_pratiques' => $donnees['informations_pratiques'] ?? null],
            'opportunite' => ['secteur' => $donnees['secteur'] ?? null, 'conditions' => $donnees['conditions'] ?? null, 'interlocuteur' => $donnees['interlocuteur'] ?? null],
            default => [],
        }, fn ($v) => $v !== null);

        return [...collect($donnees)->only(['type_contenu', 'module', 'titre', 'slug', 'resume', 'corps', 'localite_id', 'image_id', 'publication_programmee'])->all(),
            'attributs' => $attributs];
    }

    /** @return array{localites: mixed, medias: mixed, secteurs: mixed} */
    private function listes(): array
    {
        return [
            'localites' => Localite::orderBy('nom')->pluck('nom', 'id'),
            'medias' => Media::orderByDesc('id')->pluck('titre', 'id'),
            'secteurs' => Secteur::orderBy('libelle')->pluck('libelle', 'code'),
        ];
    }

    private function lienPublic(Contenu $contenu): ?string
    {
        try {
            return match ($contenu->type_contenu) {
                'actualite' => route('actualites.article', [
                    'annee' => ($contenu->publie_le ?? $contenu->cree_le ?? now())->format('Y'),
                    'article' => $contenu->slug,
                ]),
                'evenement' => route('evenements.show', ['evenement' => $contenu->slug]),
                'demarche' => route('demarches.show', ['demarche' => $contenu->slug]),
                'service' => route('mairie.service', ['service' => $contenu->slug]),
                'site_patrimoine' => route('patrimoine.site', ['site' => $contenu->slug]),
                'page' => url($contenu->slug),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
