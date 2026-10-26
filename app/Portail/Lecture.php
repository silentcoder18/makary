<?php

namespace App\Portail;

use App\Domaine\Enums\StatutProjet;
use App\Domaine\Enums\TypeObjet;
use App\Models\CategorieDocument;
use App\Models\Publication;
use App\Models\Secteur;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lecture du portail public : uniquement les publications en ligne (instantanés approuvés),
 * jamais les objets du back-office (modèle de données, principe 1).
 */
class Lecture
{
    /** Publications en ligne d'un type, avec la date de première publication (adresses stables des actualités). */
    public function requete(TypeObjet $type): Builder
    {
        return Publication::courantes($type)->select('publication.*')->selectRaw(
            '(SELECT min(p1.publie_le) FROM publication p1 WHERE p1.objet_type = publication.objet_type AND p1.objet_id = publication.objet_id) AS premiere_publication'
        );
    }

    /** @return Collection<int, Fiche> */
    private function fiches(Builder $requete): Collection
    {
        return $requete->get()->map(fn (Publication $p) => Fiche::depuis($p));
    }

    private function premiere(Builder $requete): ?Fiche
    {
        $publication = $requete->first();

        return $publication ? Fiche::depuis($publication) : null;
    }

    // ---- Contenus éditoriaux ------------------------------------------------------------------

    /** @return Collection<int, Fiche> */
    public function contenus(string $type, ?string $module = null, ?int $limite = null): Collection
    {
        return $this->fiches($this->requete(TypeObjet::Contenu)->where('instantane->type_contenu', $type)
            ->when($module, fn ($q) => $q->where('instantane->module', $module))
            ->orderByDesc('publie_le')->when($limite, fn ($q) => $q->limit($limite)));
    }

    public function contenu(string $type, string $slug): ?Fiche
    {
        return $this->premiere($this->requete(TypeObjet::Contenu)->where('instantane->type_contenu', $type)->where('instantane->slug', $slug));
    }

    /** Page de rubrique publiée ; null si la Commune ne l'a pas encore fournie. */
    public function page(string $slug): ?Fiche
    {
        return $this->contenu('page', $slug);
    }

    public function actualites(?int $annee = null, int $parPage = 9): LengthAwarePaginator
    {
        return $this->requete(TypeObjet::Contenu)->where('instantane->type_contenu', 'actualite')
            ->when($annee, fn ($q) => $q->whereRaw(
                'YEAR((SELECT min(p1.publie_le) FROM publication p1 WHERE p1.objet_type = publication.objet_type AND p1.objet_id = publication.objet_id)) = ?', [$annee]
            ))
            ->orderByDesc('premiere_publication')->paginate($parPage)->withQueryString()
            ->through(fn (Publication $p) => Fiche::depuis($p));
    }

    public function actualite(int $annee, string $slug): ?Fiche
    {
        $fiche = $this->contenu('actualite', $slug);

        return $fiche && $fiche->premierePublication->year === $annee ? $fiche : null;
    }

    /** @return list<int> années ayant au moins une actualité publiée */
    public function anneesActualites(): array
    {
        return $this->contenus('actualite')->map(fn (Fiche $f) => $f->premierePublication->year)->unique()->sortDesc()->values()->all();
    }

    /** Alertes en cours : actualités portant un niveau d'alerte, dont la date de fin n'est pas passée. */
    public function alertes(): Collection
    {
        return $this->contenus('actualite')->filter(fn (Fiche $f) => $f->get('attributs.alerte.niveau')
            && (! $f->get('attributs.alerte.jusqu_au') || now()->startOfDay()->lte($f->get('attributs.alerte.jusqu_au'))))->values();
    }

    /**
     * Événements, du plus proche au plus lointain.
     *
     * @param  'cette-semaine'|'ce-mois-ci'|'a-venir'|'passes'|null  $periode
     */
    public function evenements(?string $periode = 'a-venir', ?int $limite = null): Collection
    {
        [$debut, $fin] = match ($periode) {
            'cette-semaine' => [now()->startOfDay(), now()->endOfWeek()],
            'ce-mois-ci' => [now()->startOfDay(), now()->endOfMonth()],
            'passes' => [null, now()],
            default => [now()->startOfDay(), null],
        };
        $evenements = $this->contenus('evenement')
            ->filter(function (Fiche $f) use ($debut, $fin) {
                $date = $f->get('attributs.date_debut') ? Carbon::parse($f->get('attributs.date_debut')) : null;

                return $date && (! $debut || $date->gte($debut)) && (! $fin || $date->lte($fin));
            })
            ->sortBy(fn (Fiche $f) => $f->get('attributs.date_debut'), descending: $periode === 'passes')->values();

        return $limite ? $evenements->take($limite) : $evenements;
    }

    // ---- Projets (M03, M05) -------------------------------------------------------------------

    /** Projets visibles au catalogue (vue projet_public) : une fiche retirée du catalogue n'est jamais affichée. */
    private function requeteProjets(): Builder
    {
        return $this->requete(TypeObjet::Projet)->whereExists(fn ($q) => $q->select(DB::raw(1))->from('projet')
            ->whereColumn('projet.id', 'publication.objet_id')->where('projet.visible_catalogue', true));
    }

    /**
     * @param  array{statut?: string|list<string>|null, secteur?: string|null, localite?: string|null}  $filtres
     * @return Collection<int, Fiche>
     */
    public function projets(array $filtres = []): Collection
    {
        $statuts = array_filter((array) ($filtres['statut'] ?? []));

        return $this->fiches($this->requeteProjets()
            ->when($statuts, fn ($q) => $q->whereIn('instantane->statut->code', $statuts))
            ->when($filtres['secteur'] ?? null, fn ($q, $secteur) => $q->where('instantane->secteur->code', $secteur))
            ->when($filtres['localite'] ?? null, fn ($q, $localite) => $q->whereJsonContains('instantane->localites', [['slug' => $localite]]))
            ->orderByDesc('instantane->statut->mis_a_jour_le'));
    }

    public function projet(string $slug): ?Fiche
    {
        return $this->premiere($this->requeteProjets()->where('instantane->slug', $slug));
    }

    /** Besoins de financement (M05) : projets prêts à être soumis ou en recherche de financement. */
    public function besoinsDeFinancement(): Collection
    {
        return $this->projets(['statut' => [StatutProjet::FinancementRecherche->value, StatutProjet::PretAEtreSoumis->value]]);
    }

    // ---- Territoire, mairie, partenaires, documents --------------------------------------------

    public function localites(): Collection
    {
        return $this->fiches($this->requete(TypeObjet::Localite)->orderBy('instantane->nom'));
    }

    public function localite(string $slug): ?Fiche
    {
        return $this->premiere($this->requete(TypeObjet::Localite)->where('instantane->slug', $slug));
    }

    public function equipements(?string $localite = null): Collection
    {
        return $this->fiches($this->requete(TypeObjet::Equipement)
            ->when($localite, fn ($q) => $q->where('instantane->localite->slug', $localite))
            ->orderBy('instantane->type_equipement')->orderBy('instantane->nom'));
    }

    /** @param  list<string>  $organes */
    public function elus(array $organes): Collection
    {
        $ordre = ['maire' => 0, 'executif' => 1, 'conseil_municipal' => 2, 'ancien_maire' => 3, 'ancien_executif' => 4];

        return $this->fiches($this->requete(TypeObjet::Elu)->whereIn('instantane->organe', $organes))
            ->sortBy([fn ($a, $b) => $ordre[$a['organe']] <=> $ordre[$b['organe']], fn ($a, $b) => strcmp((string) $b['mandat_debut'], (string) $a['mandat_debut'])])
            ->values();
    }

    public function maire(): ?Fiche
    {
        return $this->elus(['maire'])->first();
    }

    public function partenaires(): Collection
    {
        return $this->fiches($this->requete(TypeObjet::Partenaire)->orderBy('instantane->nom'));
    }

    /** @param  array{categorie?: string|null, annee?: int|null}  $filtres */
    public function documents(array $filtres = []): Collection
    {
        return $this->fiches($this->requete(TypeObjet::Document)
            ->when($filtres['categorie'] ?? null, fn ($q, $c) => $q->where('instantane->categorie->code', $c))
            ->when($filtres['annee'] ?? null, fn ($q, $a) => $q->whereYear('instantane->date_document', $a))
            ->orderByDesc('instantane->date_document'));
    }

    public function document(string $slug): ?Fiche
    {
        return $this->premiere($this->requete(TypeObjet::Document)->where('instantane->slug', $slug));
    }

    /** Médias publiés pour eux-mêmes (galerie du patrimoine). */
    public function medias(): Collection
    {
        return $this->fiches($this->requete(TypeObjet::Media)->orderByDesc('publie_le'));
    }

    /** @return Collection<string, string> code => libellé */
    public function categoriesDocuments(): Collection
    {
        return CategorieDocument::orderBy('libelle')->pluck('libelle', 'code');
    }

    /** @return Collection<string, string> code => libellé */
    public function secteurs(): Collection
    {
        return Secteur::orderBy('libelle')->pluck('libelle', 'code');
    }

    // ---- Recherche et chiffres ----------------------------------------------------------------

    /** Recherche simple dans les titres et résumés publiés. */
    public function rechercher(string $terme): Collection
    {
        $motif = '%'.addcslashes(mb_strtolower(trim($terme)), '%_\\').'%';
        $condition = fn ($q) => $q->whereRaw('LOWER(COALESCE('.self::texte('titre').', '.self::texte('nom').', '.self::texte('nom_affiche').", '')) LIKE ?", [$motif])
            ->orWhereRaw('LOWER(COALESCE('.self::texte('resume').", '')) LIKE ?", [$motif]);
        $resultats = collect();
        foreach ([TypeObjet::Contenu, TypeObjet::Projet, TypeObjet::Document, TypeObjet::Localite, TypeObjet::Elu] as $type) {
            $requete = $type === TypeObjet::Projet ? $this->requeteProjets() : $this->requete($type);
            foreach ($this->fiches($requete->where($condition)->limit(20)) as $fiche) {
                $resultats->push(['type' => $type, 'fiche' => $fiche]);
            }
        }

        return $resultats;
    }

    /** Valeur texte d'une clé de l'instantané, ou NULL (clé absente, null JSON ou valeur non textuelle). */
    private static function texte(string $cle): string
    {
        $valeur = "JSON_EXTRACT(instantane, '$.$cle')";

        return "IF(JSON_TYPE($valeur) = 'STRING', JSON_UNQUOTE($valeur), NULL)";
    }

    /**
     * Chiffres calculés à partir des seules données publiées, avec leur source et leur date (P8 : jamais de chiffre sans source).
     *
     * @return list<array{valeur: int, libelle: string, source: string}>
     */
    public function chiffresDuCatalogue(): array
    {
        $projets = $this->projets();
        $source = 'Catalogue publié sur cette plateforme, au '.now()->translatedFormat('j F Y');

        return array_values(array_filter([
            ['valeur' => $projets->count(), 'libelle' => 'projets publiés', 'source' => $source],
            ['valeur' => $projets->where(fn ($f) => $f->get('statut.code') === 'acheve')->count(), 'libelle' => 'réalisations achevées', 'source' => $source],
            ['valeur' => $this->besoinsDeFinancement()->count(), 'libelle' => 'projets ouverts au financement', 'source' => $source],
            ['valeur' => $this->localites()->count(), 'libelle' => 'localités référencées', 'source' => $source],
        ], fn (array $c) => $c['valeur'] > 0));
    }
}
