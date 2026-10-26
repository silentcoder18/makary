<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Editorial\CircuitEditorial;
use App\Domaine\Editorial\Saisie;
use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\ObjetsPubliables;
use App\Domaine\Publiable;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Source;
use App\Models\Utilisateur;
use App\Securite\Journal;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Écrans communs aux objets publiables du portail (MKY-P3-08) : liste, fiche, création, modification,
 * soumission à la revue et retrait. Chaque action vérifie sa permission côté serveur, dans le service appelé ;
 * aucun écran ne publie : la publication suit l'approbation par une autre personne (écran « Validation »).
 */
abstract class ObjetsController extends Controller
{
    public function __construct(protected readonly Saisie $saisie, protected readonly CircuitEditorial $circuit) {}

    abstract protected function type(): TypeObjet;

    /** Préfixe des routes : admin.{section}.* */
    abstract protected function section(): string;

    /** @return array{liste: string, nouveau: string, chapeau: string, vide: string} */
    abstract protected function textes(): array;

    /** @return Builder<Model> */
    abstract protected function requete(Request $request): Builder;

    /** @return array<string, Closure(Model): ?string> en-tête de colonne => valeur affichée */
    abstract protected function colonnes(): array;

    /**
     * Champs du formulaire, décrits pour la vue commune admin/objets/formulaire.
     *
     * @return list<array<string, mixed>>
     */
    abstract protected function champs(Model $objet): array;

    /** @return array<string, mixed> */
    abstract protected function regles(Request $request, ?Model $objet): array;

    /**
     * Données validées → attributs du modèle (sources et fichiers créés au passage).
     *
     * @param  array<string, mixed>  $valide
     * @return array<string, mixed>
     */
    abstract protected function attributs(array $valide, Request $request, ?Model $objet): array;

    abstract protected function intitule(Model $objet): string;

    /** Adresse de l'objet sur le portail, quand il y est publié. */
    protected function lienPublic(Model $objet): ?string
    {
        return null;
    }

    /** Panneaux propres à un type d'objet (versions d'un document, droits d'un média…), affichés sous le circuit. */
    protected function panneaux(Model $objet): array
    {
        return [];
    }

    /** Filtres de la liste : paramètre => [valeur => libellé]. */
    protected function filtres(): array
    {
        return [];
    }

    // ------------------------------------------------------------------------------------------ actions

    public function index(Request $request): View
    {
        $this->autoriserLecture($request->user());

        return view('admin.objets.index', [
            'section' => $this->section(), 'textes' => $this->textes(), 'colonnes' => $this->colonnes(), 'filtres' => $this->filtres(),
            'objets' => $this->requete($request)->paginate(25)->withQueryString(),
            'peutCreer' => Saisie::peutCreer($request->user(), $this->type()),
        ]);
    }

    public function creer(Request $request): View
    {
        abort_unless(Saisie::peutCreer($request->user(), $this->type()), 403);

        return $this->formulaire($this->nouveau(), $request->user());
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        abort_unless(Saisie::peutCreer($request->user(), $this->type()), 403);
        $valide = $request->validate($this->regles($request, null), $this->messages());
        $objet = $this->saisie->creer($this->nouveau(), $request->user(), $this->attributs($valide, $request, null));

        $action = $request->input('action', 'brouillon');
        if ($action === 'publier' && $request->user()->can('contenu.publier', $objet)) {
            if ($empechement = $objet->empechementPublication()) {
                return redirect()->route("admin.{$this->section()}.modifier", $objet)
                    ->with('erreur', "Impossible de publier : {$empechement}");
            }
            $this->circuit->publierDirectement($objet, $request->user());

            return redirect()->route("admin.{$this->section()}.modifier", $objet)
                ->with('statut', 'Fiche créée et publiée directement sur le portail.');
        }

        if ($action === 'soumettre' && $request->user()->can('contenu.soumettre', $objet)) {
            if ($empechement = $objet->empechementPublication()) {
                return redirect()->route("admin.{$this->section()}.modifier", $objet)
                    ->with('erreur', "Impossible de soumettre : {$empechement}");
            }
            $this->circuit->soumettre($objet, $request->user());

            return redirect()->route("admin.{$this->section()}.modifier", $objet)
                ->with('statut', 'Fiche créée et soumise à la validation du Super Administrateur ou du Secrétariat Général.');
        }

        return redirect()->route("admin.{$this->section()}.modifier", $objet)->with('statut', 'Fiche enregistrée en brouillon.');
    }

    public function modifier(Request $request, string $objet): View
    {
        $this->autoriserLecture($request->user());

        return $this->formulaire($this->trouver($objet), $request->user());
    }

    public function mettreAJour(Request $request, string $objet): RedirectResponse
    {
        $instance = $this->trouver($objet);
        abort_unless(Saisie::peutModifier($request->user(), $instance), 403);
        $valide = $request->validate($this->regles($request, $instance), $this->messages());
        $this->saisie->modifier($instance, $request->user(), $this->attributs($valide, $request, $instance));

        $action = $request->input('action');
        $estPublie = $instance->etat === EtatEditorial::Publie || $instance->etat?->value === 'publie' || $instance->publicationCourante() !== null;

        if ($action === 'depublier') {
            Gate::authorize('contenu.retirer', $instance);
            $this->circuit->depublier($instance, $request->user());

            return back()->with('statut', 'Fiche dépubliée du portail et remise en brouillon.');
        }

        if ($action === 'publier' || ($estPublie && $action !== 'brouillon' && $action !== 'soumettre' && $action !== 'sauvegarder')) {
            if ($request->user()->can('contenu.publier', $instance)) {
                if ($empechement = $instance->empechementPublication()) {
                    return back()->with('erreur', "Impossible de publier : {$empechement}");
                }
                $this->circuit->publierDirectement($instance, $request->user());

                return back()->with('statut', 'Modifications enregistrées et publiées en direct sur le portail.');
            }
        }

        if ($action === 'soumettre' && $request->user()->can('contenu.soumettre', $instance)) {
            if ($empechement = $instance->empechementPublication()) {
                return back()->with('erreur', "Impossible de soumettre : {$empechement}");
            }
            $this->circuit->soumettre($instance, $request->user());

            return back()->with('statut', 'Modifications enregistrées et soumises à la validation du Super Administrateur ou du Secrétariat Général.');
        }

        return back()->with('statut', 'Modifications enregistrées.');
    }

    public function soumettre(Request $request, string $objet): RedirectResponse
    {
        $this->circuit->soumettre($this->trouver($objet), $request->user());

        return back()->with('statut', 'Fiche soumise à la validation du Super Administrateur ou du Secrétariat Général.');
    }

    public function publier(Request $request, string $objet): RedirectResponse
    {
        $instance = $this->trouver($objet);
        Gate::authorize('contenu.publier', $instance);
        if ($empechement = $instance->empechementPublication()) {
            return back()->with('erreur', "Impossible de publier : {$empechement}");
        }
        $this->circuit->publierDirectement($instance, $request->user());

        return back()->with('statut', 'Fiche publiée directement sur le portail.');
    }

    public function depublier(Request $request, string $objet): RedirectResponse
    {
        $instance = $this->trouver($objet);
        Gate::authorize('contenu.retirer', $instance);
        $this->circuit->depublier($instance, $request->user());

        return back()->with('statut', 'Fiche dépubliée du portail et remise en brouillon.');
    }

    public function retirer(Request $request, string $objet): RedirectResponse
    {
        $motif = $request->validateWithBag('retrait', ['motif' => ['required', 'string', 'max:2000']], ['motif.required' => 'Le motif du retrait est obligatoire.'])['motif'];
        $retirees = $this->circuit->retirer($this->trouver($objet), $request->user(), $motif);

        return back()->with('statut', "Fiche retirée du portail ($retirees version(s)). Elle reste ici, avec son historique.");
    }

    public function detruire(Request $request, string $objet): RedirectResponse
    {
        $instance = $this->trouver($objet);
        abort_unless(Saisie::peutModifier($request->user(), $instance) || $request->user()->can('contenu.retirer', $instance), 403);

        $titre = $this->intitule($instance);
        $instance->publications()->delete();
        $instance->validations()->delete();
        $instance->delete();

        Journal::consigner("{$this->section()}.suppression", null, [
            'titre' => $titre,
        ]);

        return redirect()->route("admin.{$this->section()}.index")
            ->with('statut', "L’élément « {$titre} » a été définitivement supprimé.");
    }

    // ------------------------------------------------------------------------------------------ outils

    protected function nouveau(): Model&Publiable
    {
        $classe = ObjetsPubliables::MODELES[$this->type()->value];

        return new $classe;
    }

    protected function trouver(string $id): Model&Publiable
    {
        abort_unless(ctype_digit($id), 404);

        return ObjetsPubliables::trouver($this->type(), (int) $id);
    }

    /** Lecture des fiches : agents éditoriaux, et agents qui peuvent créer ce type d'objet. */
    protected function autoriserLecture(Utilisateur $agent): void
    {
        $permissions = array_values(array_unique(['contenu.lire_brouillons', ...array_values(Saisie::PERMISSIONS[$this->type()->value])]));
        abort_unless(Gate::forUser($agent)->any($permissions), 403);
    }

    protected function formulaire(Model&Publiable $objet, Utilisateur $agent): View
    {
        $existe = $objet->exists;

        return view('admin.objets.formulaire', [
            'section' => $this->section(), 'textes' => $this->textes(), 'objet' => $objet, 'champs' => $this->champs($objet),
            'titre' => $existe ? $this->intitule($objet) : $this->textes()['nouveau'],
            'modifiable' => $existe ? Saisie::peutModifier($agent, $objet) : true,
            'validation' => $existe ? $objet->validations()->with(['decidePar', 'soumisPar'])->latest('id')->first() : null,
            'publication' => $existe ? $objet->publicationCourante() : null,
            'lienPublic' => $existe ? $this->lienPublic($objet) : null,
            'panneaux' => $existe ? $this->panneaux($objet) : [],
            'formulaireMultipart' => collect($this->champs($objet))->contains(fn ($c) => $c['type'] === 'fichier'),
        ]);
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return ['slug.regex' => 'L’adresse ne contient que des minuscules sans accents, des chiffres et des tirets.'];
    }

    // ------------------------------------------------------------------------------------------ sources (§8)

    /**
     * Règles d'une source saisie avec la fiche : obligatoire à la création, facultative ensuite (elle remplace alors l'actuelle).
     *
     * @return array<string, mixed>
     */
    protected function reglesSource(string $prefixe, bool $requise): array
    {
        $exigence = $requise ? 'required' : "required_with:{$prefixe}_intitule";

        return [
            "{$prefixe}_type" => [$exigence, 'nullable', Rule::in(array_keys(ProjetsController::TYPES_SOURCE))],
            "{$prefixe}_date" => [$requise ? 'required' : "required_with:{$prefixe}_type", 'nullable', 'date', 'before_or_equal:today'],
            "{$prefixe}_intitule" => [$requise ? 'required' : "required_with:{$prefixe}_type", 'nullable', 'string', 'max:300'],
            "{$prefixe}_reference" => ['nullable', 'string', 'max:160'],
        ];
    }

    /** Crée la source saisie, ou renvoie null si aucune n'a été saisie. */
    protected function creerSource(array $valide, string $prefixe, Utilisateur $par): ?Source
    {
        if (empty($valide["{$prefixe}_type"])) {
            return null;
        }

        return Source::create([
            'type_source' => $valide["{$prefixe}_type"], 'intitule' => $valide["{$prefixe}_intitule"],
            'reference' => $valide["{$prefixe}_reference"] ?? null, 'date_source' => $valide["{$prefixe}_date"], 'cree_par' => $par->id,
        ]);
    }

    /** Champs de formulaire d'une source, avec la source actuelle pour mémoire. */
    protected function champSource(string $prefixe, string $legende, ?Source $actuelle, bool $requise): array
    {
        return ['type' => 'source', 'prefixe' => $prefixe, 'legende' => $legende, 'actuelle' => $actuelle, 'requise' => $requise];
    }

    /** Options d'un champ « média » : les médias de la médiathèque, du plus récent au plus ancien. */
    protected function optionsMedias(): array
    {
        return Media::orderByDesc('id')->pluck('titre', 'id')->all();
    }
}
