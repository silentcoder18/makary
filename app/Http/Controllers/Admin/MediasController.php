<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Editorial\Droits;
use App\Domaine\Editorial\Saisie;
use App\Domaine\Enums\DroitUsage;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\Fichiers\Televersement;
use App\Models\Contenu;
use App\Models\Elu;
use App\Models\Media;
use App\Securite\Journal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Médiathèque (E-12) : photos téléversées avec leur auteur, leur date, leur lieu et une description pour les personnes aveugles.
 * Le droit d'usage est confirmé ensuite par un validateur ; un média non vérifié ou non analysé n'est jamais publié.
 */
class MediasController extends ObjetsController
{
    public const DROITS = ['propriete_commune' => 'Propriété de la Commune (photo d’un agent dans ses fonctions)', 'licence' => 'Licence',
        'autorisation_ecrite' => 'Autorisation écrite de l’auteur'];

    protected function type(): TypeObjet
    {
        return TypeObjet::Media;
    }

    protected function section(): string
    {
        return 'medias';
    }

    protected function textes(): array
    {
        return ['liste' => 'Médias', 'nouveau' => 'Téléverser une photo', 'vide' => 'Aucun média dans la médiathèque.',
            'chapeau' => 'Photothèque officielle de la Commune de Makary : illustrations des actualités, des projets, des élus et du patrimoine.'];
    }

    protected function requete(Request $request): Builder
    {
        return Media::with('fichier')->orderByDesc('id');
    }

    protected function colonnes(): array
    {
        return [
            'Titre' => fn (Media $m) => $m->titre,
            'Auteur' => fn (Media $m) => $m->auteur,
            'Droit d’usage' => fn (Media $m) => $m->droit->libelle(),
            'Analyse antivirus' => fn (Media $m) => match ($m->fichier->analyse_ok) {
                true => 'sain', false => 'refusé', null => 'en attente'
            },
        ];
    }

    protected function intitule(Model $objet): string
    {
        return $objet->titre;
    }

    protected function champs(Model $objet): array
    {
        $peutValider = auth()->user()?->can('media.valider_droits', $objet->exists ? $objet : new Media);

        return [
            ...($objet->exists ? [] : [
                ['type' => 'fichier', 'nom' => 'fichier', 'libelle' => 'Photo', 'requis' => true, 'accept' => 'image/jpeg,image/png,image/webp',
                    'aide' => 'JPEG, PNG ou WebP, '.config('makary.televersement.image_mo').' Mo au plus. Le fichier d’origine, non recadré : ses métadonnées (dont la position GPS) sont supprimées.'],
                ...($peutValider ? [['type' => 'select', 'nom' => 'droit_initial', 'libelle' => 'Droit d’usage initial', 'options' => ['propriete_commune' => 'Propriété de la Commune (directement publiable)', 'a_verifier' => 'À vérifier ultérieurement'], 'valeur' => 'propriete_commune', 'aide' => 'Si « Propriété de la Commune », la photo pourra être publiée sans étape supplémentaire.']] : []),
            ]),
            ['type' => 'text', 'nom' => 'titre', 'libelle' => 'Titre', 'requis' => true, 'max' => 255, 'valeur' => $objet->titre, 'pleine' => true],
            ['type' => 'text', 'nom' => 'texte_alternatif', 'libelle' => 'Description pour les personnes aveugles', 'requis' => true, 'max' => 500,
                'valeur' => $objet->texte_alternatif, 'pleine' => true, 'aide' => 'Une phrase qui dit ce que montre la photo.'],
            ['type' => 'text', 'nom' => 'auteur', 'libelle' => 'Auteur de la photo', 'requis' => true, 'max' => 160, 'valeur' => $objet->auteur],
            ['type' => 'date', 'nom' => 'date_prise', 'libelle' => 'Date de prise de vue', 'valeur' => $objet->date_prise?->toDateString()],
            ['type' => 'text', 'nom' => 'lieu', 'libelle' => 'Lieu', 'max' => 160, 'valeur' => $objet->lieu],
        ];
    }

    protected function regles(Request $request, ?Model $objet): array
    {
        return [
            ...($objet ? [] : [
                'fichier' => ['required', 'file'],
                'droit_initial' => ['nullable', Rule::in(['propriete_commune', 'a_verifier'])],
            ]),
            'titre' => ['required', 'string', 'max:255'],
            'texte_alternatif' => ['required', 'string', 'max:500'],
            'auteur' => ['required', 'string', 'max:160'],
            'date_prise' => ['nullable', 'date', 'before_or_equal:today'],
            'lieu' => ['nullable', 'string', 'max:160'],
        ];
    }

    protected function attributs(array $valide, Request $request, ?Model $objet): array
    {
        $donnees = collect($valide)->only(['titre', 'texte_alternatif', 'auteur', 'date_prise', 'lieu'])->all();
        if (! $objet) {
            $donnees['fichier_id'] = app(Televersement::class)->enregistrer($request->file('fichier'), $request->user(), 'image')->id;
        }

        return $donnees;
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        abort_unless(Saisie::peutCreer($request->user(), $this->type()), 403);
        $valide = $request->validate($this->regles($request, null), $this->messages());
        $donnees = $this->attributs($valide, $request, null);
        $droitInitial = $valide['droit_initial'] ?? null;

        $objet = $this->saisie->creer($this->nouveau(), $request->user(), $donnees);
        if ($droitInitial === 'propriete_commune' && $request->user()?->can('media.valider_droits', $objet)) {
            $objet->forceFill(['droit' => DroitUsage::ProprieteCommune])->save();
        }

        $action = $request->input('action', 'brouillon');
        if ($action === 'publier' && $request->user()->can('contenu.publier', $objet)) {
            if ($empechement = $objet->empechementPublication()) {
                return redirect()->route("admin.{$this->section()}.modifier", $objet)
                    ->with('erreur', "Impossible de publier : {$empechement}");
            }
            $this->circuit->publierDirectement($objet, $request->user());

            return redirect()->route("admin.{$this->section()}.modifier", $objet)
                ->with('statut', 'Média téléversé et publié directement sur le portail.');
        }

        if ($action === 'soumettre' && $request->user()->can('contenu.soumettre', $objet)) {
            if ($empechement = $objet->empechementPublication()) {
                return redirect()->route("admin.{$this->section()}.modifier", $objet)
                    ->with('erreur', "Impossible de soumettre : {$empechement}");
            }
            $this->circuit->soumettre($objet, $request->user());

            return redirect()->route("admin.{$this->section()}.modifier", $objet)
                ->with('statut', 'Média téléversé et soumis à la validation du Super Administrateur ou du Secrétariat Général.');
        }

        return redirect()->route("admin.{$this->section()}.modifier", $objet)->with('statut', 'Fiche enregistrée en brouillon.');
    }

    protected function panneaux(Model $objet): array
    {
        return ['admin.medias.panneaux' => ['media' => $objet]];
    }

    /** Confirmation du droit d'usage par un validateur du périmètre (E-12). */
    public function confirmerDroit(Request $request, Droits $droits, string $objet): RedirectResponse
    {
        $media = $this->trouver($objet);
        Gate::authorize('media.valider_droits', $media);   // avant toute écriture, pour ne pas créer de source orpheline
        $valide = $request->validateWithBag('droit', [
            'droit' => ['required', Rule::in(array_keys(self::DROITS))],
            ...$this->reglesSource('justificatif', false),
            'justificatif_type' => [Rule::requiredIf(fn () => $request->input('droit') !== 'propriete_commune'), 'nullable', Rule::in(array_keys(ProjetsController::TYPES_SOURCE))],
        ], ['justificatif_type.required' => 'Une licence ou une autorisation écrite doit être jointe.']);
        $droits->confirmerDroitMedia($media, $request->user(), DroitUsage::from($valide['droit']), $this->creerSource($valide, 'justificatif', $request->user()));

        return back()->with('statut', 'Droit d’usage confirmé.');
    }

    public function detruire(Request $request, string $objet): RedirectResponse
    {
        $media = $this->trouver($objet);
        abort_unless(Saisie::peutModifier($request->user(), $media) || $request->user()->can('contenu.retirer', $media), 403);

        $titre = $media->titre;
        $fichier = $media->fichier;

        Contenu::where('image_id', $media->id)->update(['image_id' => null]);
        Elu::where('photo_id', $media->id)->update(['photo_id' => null]);
        DB::table('projet_media')->where('media_id', $media->id)->delete();

        $media->publications()->delete();
        $media->validations()->delete();
        $media->delete();

        if ($fichier && ! Media::where('fichier_id', $fichier->id)->exists()) {
            if ($fichier->chemin && Storage::disk('local')->exists($fichier->chemin)) {
                Storage::disk('local')->delete($fichier->chemin);
            }
            $fichier->delete();
        }

        Journal::consigner('media.suppression', null, [
            'titre' => $titre,
        ]);

        return redirect()->route('admin.medias.index')
            ->with('statut', "Le média « {$titre} » et son fichier ont été définitivement supprimés.");
    }
}
