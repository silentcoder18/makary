<?php

namespace App\Http\Controllers\Api;

use App\Api\Representation;
use App\Domaine\Demandes\Demandes;
use App\Domaine\Enums\BaseLegale;
use App\Domaine\Enums\StatutProjet;
use App\Domaine\Enums\TypeDemande;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\RegleMetierException;
use App\Domaine\Statistiques\Compteur;
use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Parametre;
use App\Portail\FichePdf;
use App\Portail\Lecture;
use App\Portail\Liens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * API publique v1 (contrat specs/api/openapi.yaml, opérations /public/…) : lecture des seules publications approuvées,
 * dépôt et suivi des demandes. Erreurs au format Problem Details (RFC 9457).
 */
class PubliqueController extends Controller
{
    private const TYPES_CONTENU = ['page', 'actualite', 'evenement', 'site_patrimoine', 'service', 'demarche', 'opportunite'];

    private const ORGANES = ['maire', 'executif', 'conseil_municipal', 'ancien_maire', 'ancien_executif'];

    public function __construct(private readonly Lecture $lecture, private readonly Representation $representation) {}

    // ---------------------------------------------------------------- projets

    public function projets(Request $request): JsonResponse
    {
        $filtres = $this->valider($request, [
            'statut' => ['nullable', Rule::enum(StatutProjet::class)],
            'secteur' => ['nullable', 'string', 'max:40'],
            'localite' => ['nullable', 'string', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
        ] + $this->reglesPagination());

        return $this->page($request, $this->lecture->projets([
            'statut' => $filtres['statut'] ?? null, 'secteur' => $filtres['secteur'] ?? null, 'localite' => $filtres['localite'] ?? null,
        ]), fn ($p) => $this->representation->projetResume($p));
    }

    public function projet(string $slug): JsonResponse
    {
        return response()->json($this->representation->projet($this->trouver($this->lecture->projet($slug))));
    }

    public function fichePdf(Request $request, string $slug, FichePdf $pdf, Compteur $compteur): Response
    {
        $projet = $this->trouver($this->lecture->projet($slug));
        $compteur->incrementer($request, 'telechargement_fiche', TypeObjet::Projet, $projet->objetId);

        return response($pdf->generer($projet, route('projets.show', $slug)), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="fiche-'.$projet['code'].'.pdf"',
        ]);
    }

    // ---------------------------------------------------------------- contenus, élus, documents

    public function contenus(Request $request): JsonResponse
    {
        $filtres = $this->valider($request, [
            'type' => ['required', Rule::in(self::TYPES_CONTENU)],
            'module' => ['nullable', 'regex:/^M(0[1-9]|1[0-2])$/'],
        ] + $this->reglesPagination());

        return $this->page($request, $this->lecture->contenus($filtres['type'], $filtres['module'] ?? null), fn ($c) => $this->representation->contenuResume($c));
    }

    public function contenu(string $type, string $slug): JsonResponse
    {
        abort_unless(in_array($type, self::TYPES_CONTENU, true), 404);

        return response()->json($this->representation->contenu($this->trouver($this->lecture->contenu($type, $slug))));
    }

    public function elus(Request $request): JsonResponse
    {
        $filtres = $this->valider($request, ['organe' => ['nullable', Rule::in(self::ORGANES)]]);

        return response()->json($this->lecture->elus(isset($filtres['organe']) ? [$filtres['organe']] : self::ORGANES)
            ->map(fn ($e) => $this->representation->elu($e))->values());
    }

    public function documents(Request $request): JsonResponse
    {
        $filtres = $this->valider($request, [
            'categorie' => ['nullable', 'string', 'max:40'],
            'annee' => ['nullable', 'integer', 'min:1960'],
        ] + $this->reglesPagination());

        return $this->page($request, $this->lecture->documents(['categorie' => $filtres['categorie'] ?? null, 'annee' => $filtres['annee'] ?? null]),
            fn ($d) => $this->representation->document($d));
    }

    public function document(string $slug): JsonResponse
    {
        return response()->json($this->representation->document($this->trouver($this->lecture->document($slug))));
    }

    // ---------------------------------------------------------------- territoire

    public function localites(): JsonResponse
    {
        return response()->json($this->lecture->localites()->map(fn ($l) => $this->representation->localite($l->donnees))->values());
    }

    /** Couches GeoJSON : seules des positions vérifiées et sourcées ; aucune n'est estimée. */
    public function carte(Request $request): JsonResponse
    {
        $couches = array_filter(explode(',', (string) $request->query('couches', 'localites,equipements,projets')));
        if (array_diff($couches, ['localites', 'equipements', 'projets'])) {
            throw ValidationException::withMessages(['couches' => 'Couches possibles : localites, equipements, projets.']);
        }
        $point = fn ($f) => $f['latitude'] !== null && $f['longitude'] !== null
            ? ['type' => 'Point', 'coordinates' => [(float) $f['longitude'], (float) $f['latitude']]] : null;
        $entites = collect();
        if (in_array('localites', $couches, true)) {
            $entites = $entites->merge($this->lecture->localites()->map(fn ($l) => [$point($l), 'localite', $l['nom'], route('decouvrir.localite', $l['slug'])]));
        }
        if (in_array('equipements', $couches, true)) {
            $entites = $entites->merge($this->lecture->equipements()->map(fn ($e) => [$point($e), 'equipement', $e['nom'], route('decouvrir.localite', $e->get('localite.slug'))]));
        }
        if (in_array('projets', $couches, true)) {
            $entites = $entites->merge($this->lecture->projets()->flatMap(fn ($p) => collect($p['geometries'] ?? [])
                ->map(fn ($g) => [$g['geojson'], 'projet', $p['titre'], route('projets.show', $p['slug'])])));
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $entites->filter(fn ($e) => $e[0] !== null)->map(fn ($e) => [
                'type' => 'Feature', 'geometry' => $e[0], 'properties' => ['nature' => $e[1], 'nom' => $e[2], 'lien' => $e[3]],
            ])->values(),
        ], 200, ['Content-Type' => 'application/geo+json']);
    }

    public function partenaires(): JsonResponse
    {
        return response()->json($this->lecture->partenaires()->map(fn ($p) => $this->representation->partenaire($p))->values());
    }

    /** Coordonnées validées par la Commune (paramètre éditorial) ; vides tant qu'elles ne sont pas fournies (C-003). */
    public function coordonnees(): JsonResponse
    {
        $valeur = Parametre::find('coordonnees')?->valeur ?? [];

        return response()->json([
            'adresse' => $valeur['adresse'] ?? null,
            'boite_postale' => $valeur['boite_postale'] ?? null,
            'horaires' => $valeur['horaires'] ?? null,
            'services' => array_values($valeur['services'] ?? []),
        ]);
    }

    public function recherche(Request $request): JsonResponse
    {
        $donnees = $this->valider($request, ['q' => ['required', 'string', 'min:2', 'max:100']] + $this->reglesPagination());
        $natures = ['contenu' => 'contenu', 'projet' => 'projet', 'document' => 'document', 'localite' => 'localite', 'elu' => 'elu'];

        return response()->json($this->lecture->rechercher($donnees['q'])
            ->map(fn ($r) => ['lien' => Liens::vers($r['type'], $r['fiche']), 'fiche' => $r['fiche'], 'nature' => $natures[$r['type']->value]])
            ->filter(fn ($r) => $r['lien'] !== null)
            ->map(fn ($r) => [
                'titre' => $r['fiche']['titre'] ?? $r['fiche']['nom'] ?? $r['fiche']['nom_affiche'],
                'lien' => $r['lien'], 'nature' => $r['nature'], 'extrait' => $r['fiche']['resume'],
            ])->forPage((int) ($donnees['page'] ?? 1), 20)->values());
    }

    // ---------------------------------------------------------------- demandes

    public function deposerDemande(Request $request, Demandes $demandes): JsonResponse
    {
        $donnees = $this->valider($request, [
            'type_demande' => ['required', Rule::enum(TypeDemande::class)],
            'objet' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
            'organisme' => ['nullable', 'string', 'max:160'],
            'pays' => ['nullable', 'string', 'max:80'],
            'projet_code' => ['nullable', 'string', 'regex:/^PRJ-[0-9]{4}-[0-9]{3,}$/'],
            'nom_contact' => ['nullable', 'string', 'max:120'],
            'courriel' => ['required', 'email:rfc', 'max:254'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'version_information' => ['required', 'string', Rule::in([config('makary.mention_information.version')])],
            'consentement' => ['required', 'accepted'],
        ], [
            'version_information.in' => 'La mention d’information affichée n’est plus à jour : recharger le formulaire.',
            'consentement.accepted' => 'Le consentement est requis pour traiter cette demande.',
        ]);
        $projet = isset($donnees['projet_code']) ? $this->lecture->projets()->first(fn ($p) => $p['code'] === $donnees['projet_code']) : null;

        try {
            $demande = $demandes->enregistrer(TypeDemande::from($donnees['type_demande']), array_filter([
                'objet' => $donnees['objet'], 'message' => $donnees['message'], 'organisme' => $donnees['organisme'] ?? null,
                'pays' => $donnees['pays'] ?? null, 'nom_contact' => $donnees['nom_contact'] ?? null, 'courriel' => mb_strtolower($donnees['courriel']),
                'telephone' => $donnees['telephone'] ?? null, 'projet_id' => $projet?->objetId,
            ], fn ($v) => $v !== null), BaseLegale::Consentement, true, $donnees['version_information']);
        } catch (RegleMetierException $e) {
            throw ValidationException::withMessages(['consentement' => $e->getMessage()]);
        }

        return response()->json([
            'numero_suivi' => $demande->numero_suivi,
            'recue_le' => $demande->recue_le->toIso8601String(),
            'prochaine_etape' => 'Votre demande sera qualifiée puis confiée à un agent ; suivez-la avec ce numéro et votre adresse électronique.',
            'conserver_jusqu_au' => $demande->conserver_jusqu_au->toDateString(),
        ], 201);
    }

    /** Réponse identique pour un numéro inconnu ou une adresse qui ne correspond pas. */
    public function suivreDemande(Request $request): JsonResponse
    {
        $donnees = $this->valider($request, ['numero_suivi' => ['required', 'string', 'max:20'], 'courriel' => ['required', 'email:rfc', 'max:254']]);
        $demande = Demande::where('numero_suivi', mb_strtoupper(trim($donnees['numero_suivi'])))
            ->where('courriel', mb_strtolower(trim($donnees['courriel'])))->whereNull('anonymisee_le')->first();
        abort_if($demande === null, 404, 'Aucune demande ne correspond à ce numéro et à cette adresse.');

        $etat = match ($demande->statut->value) {
            'recue' => 'recue', 'repondue' => 'repondue', 'cloturee', 'rejetee' => 'cloturee', default => 'en_traitement',
        };
        $derniere = $demande->evenements()->max('le');

        return response()->json([
            'numero_suivi' => $demande->numero_suivi,
            'etat' => $etat,
            'mise_a_jour' => ($derniere ? Carbon::parse($derniere) : $demande->recue_le)->toIso8601String(),
        ]);
    }

    // ---------------------------------------------------------------- outils

    /** @return array<string, list<mixed>> */
    private function reglesPagination(): array
    {
        return ['page' => ['nullable', 'integer', 'min:1'], 'par_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }

    /**
     * @param  array<string, list<mixed>>  $regles
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     */
    private function valider(Request $request, array $regles, array $messages = []): array
    {
        return validator($request->query() + $request->json()->all(), $regles, $messages)->validate();
    }

    private function page(Request $request, Collection $elements, callable $representer): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $parPage = (int) $request->query('par_page', 20);

        return response()->json([
            'elements' => $elements->forPage($page, $parPage)->map($representer)->values(),
            'pagination' => ['page' => $page, 'par_page' => $parPage, 'total' => $elements->count()],
        ]);
    }

    private function trouver(mixed $fiche): mixed
    {
        abort_if($fiche === null, 404, 'Ressource inconnue ou non publiée.');

        return $fiche;
    }
}
