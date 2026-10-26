<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Editorial\Droits;
use App\Domaine\Editorial\Versions;
use App\Domaine\Enums\DroitDiffusion;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\Fichiers\Televersement;
use App\Models\CategorieDocument;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Securite\Cible;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Centre documentaire (M07) : fiche, versions successives, diffusion accordée par le référent juridique,
 * archivage au lieu de la destruction (E-14).
 */
class DocumentsController extends ObjetsController
{
    public const DIFFUSIONS = ['public' => 'Public (téléchargeable par tous)', 'sur_demande' => 'Sur demande', 'interne' => 'Interne (jamais en ligne)'];

    protected function type(): TypeObjet
    {
        return TypeObjet::Document;
    }

    protected function section(): string
    {
        return 'documents';
    }

    protected function textes(): array
    {
        return ['liste' => 'Documents', 'nouveau' => 'Nouveau document', 'vide' => 'Aucun document dans le centre documentaire.',
            'chapeau' => 'Plan communal de développement, budgets, délibérations, rapports et études. Un document n’est téléchargeable qu’après validation de son accord de diffusion.'];
    }

    protected function filtres(): array
    {
        return ['categorie' => ['Catégorie', CategorieDocument::orderBy('libelle')->pluck('libelle', 'code')->all()]];
    }

    protected function requete(Request $request): Builder
    {
        return Document::with('categorieDocument')->when($request->query('categorie'), fn ($q, $c) => $q->where('categorie', $c))->orderByDesc('date_document');
    }

    protected function colonnes(): array
    {
        return [
            'Titre' => fn (Document $d) => $d->titre.($d->archive ? ' (archivé)' : ''),
            'Catégorie' => fn (Document $d) => $d->categorieDocument?->libelle,
            'Date' => fn (Document $d) => $d->date_document->format('d/m/Y'),
            'Diffusion' => fn (Document $d) => $d->diffusion->libelle(),
        ];
    }

    protected function intitule(Model $objet): string
    {
        return $objet->titre;
    }

    protected function lienPublic(Model $objet): ?string
    {
        return route('documents.show', $objet->slug);
    }

    protected function champs(Model $objet): array
    {
        return [
            ['type' => 'text', 'nom' => 'titre', 'libelle' => 'Titre', 'requis' => true, 'max' => 255, 'valeur' => $objet->titre, 'pleine' => true],
            ['type' => 'text', 'nom' => 'slug', 'libelle' => 'Adresse de la page', 'requis' => true, 'max' => 160, 'valeur' => $objet->slug,
                'aide' => 'Minuscules sans accents, chiffres et tirets ; elle ne change plus après publication.'],
            ['type' => 'select', 'nom' => 'categorie', 'libelle' => 'Catégorie', 'requis' => true, 'options' => CategorieDocument::orderBy('libelle')->pluck('libelle', 'code')->all(), 'valeur' => $objet->categorie],
            ['type' => 'date', 'nom' => 'date_document', 'libelle' => 'Date du document', 'requis' => true, 'valeur' => $objet->date_document?->toDateString()],
            ['type' => 'text', 'nom' => 'reference_officielle', 'libelle' => 'Référence officielle (numéro de délibération, d’arrêté…)', 'max' => 160, 'valeur' => $objet->reference_officielle],
            ['type' => 'textarea', 'nom' => 'resume', 'libelle' => 'Résumé', 'max' => 1000, 'valeur' => $objet->resume],
            ...($objet->exists ? [] : [['type' => 'fichier', 'nom' => 'fichier', 'libelle' => 'Fichier de la première version (PDF)', 'accept' => 'application/pdf',
                'aide' => 'PDF, '.config('makary.televersement.document_mo').' Mo au plus. Vous pourrez aussi l’ajouter plus tard.']]),
        ];
    }

    protected function regles(Request $request, ?Model $objet): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('document', 'slug')->ignore($objet?->id)],
            'categorie' => ['required', 'exists:categorie_document,code'],
            'date_document' => ['required', 'date'],
            'reference_officielle' => ['nullable', 'string', 'max:160'],
            'resume' => ['nullable', 'string', 'max:1000'],
            ...($objet ? [] : ['fichier' => ['nullable', 'file']]),
        ];
    }

    protected function attributs(array $valide, Request $request, ?Model $objet): array
    {
        return collect($valide)->only(['titre', 'slug', 'categorie', 'date_document', 'reference_officielle', 'resume'])->all();
    }

    /** Création : la fiche, puis sa première version si un fichier est joint. */
    public function enregistrer(Request $request): RedirectResponse
    {
        $reponse = parent::enregistrer($request);
        if ($request->hasFile('fichier')) {
            $document = Document::where('slug', $request->input('slug'))->sole();
            $this->ajouter($request, $document, 'Première version');
        }

        return $reponse;
    }

    protected function panneaux(Model $objet): array
    {
        return ['admin.documents.panneaux' => ['document' => $objet->load('versions.fichier', 'versions.auteur')]];
    }

    public function ajouterVersion(Request $request, string $objet): RedirectResponse
    {
        $document = $this->trouver($objet);
        Gate::authorize('document.gerer', $document);
        $request->validateWithBag('version', ['fichier' => ['required', 'file'], 'note' => ['nullable', 'string', 'max:500']],
            ['fichier.required' => 'Choisissez le fichier de la nouvelle version.']);
        $version = $this->ajouter($request, $document, $request->input('note'));

        return back()->with('statut', "Version {$version->numero} ajoutée. Soumettez le document à la revue pour la publier.");
    }

    /** Diffusion du document : « public » exige la pièce de l'accord du référent juridique. */
    public function diffusion(Request $request, Droits $droits, string $objet): RedirectResponse
    {
        $document = $this->trouver($objet);
        Gate::authorize('document.valider_diffusion', new Cible(['juridique']));
        $valide = $request->validateWithBag('diffusion', [
            'diffusion' => ['required', Rule::in(array_keys(self::DIFFUSIONS))],
            ...$this->reglesSource('accord', false),
            'accord_type' => [Rule::requiredIf(fn () => $request->input('diffusion') === 'public'), 'nullable', Rule::in(array_keys(ProjetsController::TYPES_SOURCE))],
        ], ['accord_type.required' => 'La diffusion publique exige la pièce de l’accord du référent juridique.']);
        $droits->accorderDiffusion($document, $request->user(), DroitDiffusion::from($valide['diffusion']), $this->creerSource($valide, 'accord', $request->user()));

        return back()->with('statut', 'Diffusion du document enregistrée.');
    }

    /** Archivage motivé : le document quitte le portail mais n'est jamais détruit (E-14). */
    public function archiver(Request $request, Droits $droits, string $objet): RedirectResponse
    {
        $motif = $request->validateWithBag('archivage', ['motif_archivage' => ['required', 'string', 'max:2000']],
            ['motif_archivage.required' => 'Le motif de l’archivage est obligatoire.'])['motif_archivage'];
        $droits->archiverDocument($this->trouver($objet), $request->user(), $motif);

        return back()->with('statut', 'Document archivé : il n’est plus en ligne, mais il est conservé avec toutes ses versions.');
    }

    private function ajouter(Request $request, Document $document, ?string $note): DocumentVersion
    {
        $fichier = app(Televersement::class)->enregistrer($request->file('fichier'), $request->user(), 'document');

        return app(Versions::class)->ajouter($document, $request->user(), $fichier, $note);
    }
}
