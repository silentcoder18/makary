<?php

namespace App\Models;

use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\NatureInformation;
use App\Domaine\Enums\StatutLigneFinancement;
use App\Domaine\Enums\StatutProjet;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\EstPubliable;
use App\Domaine\ObjetsPubliables;
use App\Domaine\Publiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fiche projet ou réalisation (M03), avec les 14 champs du §8 (docs/conception/fiche_projet.md).
 * Le code et le statut sont attribués par la base : le statut ne change que par une transition approuvée.
 */
class Projet extends Model implements Publiable
{
    use EstPubliable;

    public const TYPE_OBJET = TypeObjet::Projet;

    protected $table = 'projet';

    public $timestamps = false;

    protected $guarded = ['id', 'code', 'statut', 'statut_mis_a_jour_le', 'etat', 'visible_catalogue', 'motif_retrait', 'cree_le'];

    protected function casts(): array
    {
        return [
            'statut' => StatutProjet::class,
            'etat' => EtatEditorial::class,
            'statut_mis_a_jour_le' => 'datetime',
            'description_publique' => 'boolean',
            'avancement_pct' => 'decimal:2',
            'avancement_date' => 'date',
            'visible_catalogue' => 'boolean',
            'cree_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Secteur, $this> */
    public function secteurReference(): BelongsTo
    {
        return $this->belongsTo(Secteur::class, 'secteur');
    }

    /** @return BelongsToMany<Localite, $this> */
    public function localites(): BelongsToMany
    {
        return $this->belongsToMany(Localite::class, 'projet_localite', 'projet_id', 'localite_id');
    }

    /** @return BelongsToMany<Media, $this> */
    public function medias(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'projet_media', 'projet_id', 'media_id')->withPivot('ordre')->orderBy('ordre');
    }

    /** @return HasMany<ProjetGeometrie, $this> */
    public function geometries(): HasMany
    {
        return $this->hasMany(ProjetGeometrie::class, 'projet_id');
    }

    /** @return HasMany<ProjetBeneficiaires, $this> */
    public function beneficiaires(): HasMany
    {
        return $this->hasMany(ProjetBeneficiaires::class, 'projet_id');
    }

    /** @return HasMany<ProjetEstimationCout, $this> */
    public function estimations(): HasMany
    {
        return $this->hasMany(ProjetEstimationCout::class, 'projet_id');
    }

    /** @return HasMany<ProjetFinancement, $this> */
    public function financements(): HasMany
    {
        return $this->hasMany(ProjetFinancement::class, 'projet_id');
    }

    /** @return HasMany<ProjetPiece, $this> */
    public function pieces(): HasMany
    {
        return $this->hasMany(ProjetPiece::class, 'projet_id');
    }

    /** @return HasMany<ProjetIndicateur, $this> */
    public function indicateurs(): HasMany
    {
        return $this->hasMany(ProjetIndicateur::class, 'projet_id');
    }

    /** @return HasMany<ProjetTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(ProjetTransition::class, 'projet_id')->orderBy('id');
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'responsable_id');
    }

    /** @return BelongsTo<Source, $this> */
    public function besoinSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'besoin_source_id');
    }

    /** @return BelongsTo<Source, $this> */
    public function avancementSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'avancement_source_id');
    }

    public function perimetres(): array
    {
        return ['M03'];
    }

    /** Estimation de coût affichée : la plus récente, hors informations « à confirmer » (fiche projet, champ 7). */
    public function estimationCourante(): ?ProjetEstimationCout
    {
        return $this->estimations()->where('nature', '<>', NatureInformation::AConfirmer->value)
            ->orderByDesc('date_estimation')->orderByDesc('id')->first();
    }

    /**
     * Financement, dans la devise du coût affiché. Seules les lignes « acquis » sont additionnées dans l'acquis ;
     * le sollicité n'est ni présenté comme acquis ni déduit du besoin (§8, fiche projet §1 et §7).
     * Les montants sont des chaînes décimales exactes (bcmath) ; aucune devise n'est convertie ni additionnée à une autre.
     *
     * @return array{devise: ?string, cout: ?string, acquis: string, sollicite: string, recherche: ?string, acquis_autres_devises: array<string, string>}
     */
    public function syntheseFinanciere(): array
    {
        $estimation = $this->estimationCourante();
        $devise = $estimation?->devise ?? $this->financements()->value('devise');
        $totaux = ['acquis' => [], 'sollicite' => []];
        foreach ($this->financements()->whereIn('statut_ligne', ['acquis', 'sollicite'])->get() as $ligne) {
            $cle = $ligne->statut_ligne->value;
            $totaux[$cle][$ligne->devise] = bcadd($totaux[$cle][$ligne->devise] ?? '0', $ligne->montant, 2);
        }
        $acquis = $totaux['acquis'][$devise] ?? '0.00';
        $cout = $estimation?->montant;
        $recherche = $cout === null ? null : (bccomp($cout, $acquis, 2) > 0 ? bcsub($cout, $acquis, 2) : '0.00');

        return [
            'devise' => $devise,
            'cout' => $cout,
            'acquis' => bcadd($acquis, '0', 2),
            'sollicite' => bcadd($totaux['sollicite'][$devise] ?? '0', '0', 2),
            'recherche' => $recherche,
            'acquis_autres_devises' => array_diff_key($totaux['acquis'], [$devise => true]),
        ];
    }

    /** Les champs publics de la fiche (colonne « Vis. » de la fiche projet) ; jamais les lignes sollicitées ni le compte du responsable. */
    public function instantanePublic(): array
    {
        $estimation = $this->estimationCourante();
        $synthese = $this->syntheseFinanciere();
        $confirme = fn ($requete) => $requete->where('nature', '<>', NatureInformation::AConfirmer->value);

        return [
            'code' => $this->code,
            'slug' => $this->slug,
            'titre' => $this->titre,
            'secteur' => ['code' => $this->secteur, 'libelle' => $this->secteurReference->libelle],
            'resume' => $this->resume,
            'description_technique' => $this->description_publique ? $this->description_technique : null,
            'besoin' => $this->besoin ? ['texte' => $this->besoin, 'source' => ObjetsPubliables::source($this->besoinSource)] : null,
            'localites' => $this->localites()->orderBy('nom')->get()->map->reference()->all(),
            'geometries' => $this->geometries->map(fn (ProjetGeometrie $g) => ['geojson' => $g->geojson, 'verifie_le' => $g->verifie_le->toDateString()])->all(),
            'beneficiaires' => $this->beneficiaires()->tap($confirme)->with('source')->get()->map(fn (ProjetBeneficiaires $b) => [
                'nombre' => $b->nombre, 'categorie' => $b->categorie, 'methode_estimation' => $b->methode_estimation,
                'date_estimation' => $b->date_estimation->toDateString(), 'nature' => $b->nature->value, 'source' => ObjetsPubliables::source($b->source),
            ])->all(),
            'cout' => $estimation ? [
                'montant' => $estimation->montant, 'devise' => $estimation->devise, 'date_estimation' => $estimation->date_estimation->toDateString(),
                'nature_cout' => $estimation->nature_cout, 'nature' => $estimation->nature->value, 'source' => ObjetsPubliables::source($estimation->source),
            ] : null,
            'financement' => [
                'devise' => $synthese['devise'],
                'acquis' => $synthese['acquis'],
                'recherche' => $synthese['recherche'],
                'calcule_le' => now()->toDateString(),
                'lignes_acquises' => $this->financements()->where('statut_ligne', StatutLigneFinancement::Acquis->value)->with('partenaire')->orderBy('date_information')->get()
                    ->map(fn (ProjetFinancement $l) => [
                        'montant' => $l->montant, 'devise' => $l->devise, 'date' => $l->date_information->toDateString(),
                        'financeur' => $l->partenaire?->nomCitable(),
                    ])->all(),
            ],
            'pieces' => $this->pieces()->with('source')->get()->map(fn (ProjetPiece $p) => [
                'type_piece' => $p->type_piece, 'intitule' => $p->source->intitule, 'date' => $p->source->date_source->toDateString(), 'diffusion' => $p->diffusion->value,
            ])->all(),
            'statut' => [
                'code' => $this->statut->value,
                'libelle' => $this->statut->libelle(),
                'mis_a_jour_le' => $this->statut_mis_a_jour_le->toIso8601String(),
                'motif_suspension' => $this->statut === StatutProjet::Suspendu ? $this->motif_suspension : null,
            ],
            'avancement' => $this->avancement_pct === null ? null : [
                'pourcentage' => $this->avancement_pct, 'date' => $this->avancement_date->toDateString(), 'source' => ObjetsPubliables::source($this->avancementSource),
            ],
            'service_responsable' => $this->service_responsable,
            'medias' => $this->medias->map(fn (Media $m) => Media::versionPublique($m))->filter()->values()->all(),
            'indicateurs' => $this->indicateurs()->with('source')->orderBy('id')->get()->map(fn (ProjetIndicateur $i) => [
                'libelle' => $i->libelle, 'unite' => $i->unite, 'valeur_reference' => $i->valeur_reference, 'cible' => $i->cible,
                'valeur_atteinte' => $i->valeur_atteinte, 'date_mesure' => $i->date_mesure?->toDateString(),
                'methode_mesure' => $i->methode_mesure, 'source' => ObjetsPubliables::source($i->source),
            ])->all(),
        ];
    }
}
