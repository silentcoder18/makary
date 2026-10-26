<?php

namespace App\Api;

use App\Portail\Fiche;
use App\Portail\Format;
use App\Portail\Liens;
use Illuminate\Support\Carbon;

/**
 * Traduit les instantanés publiés vers les schémas du contrat (specs/api/openapi.yaml, MKY-P1-07).
 * N'utilise que des instantanés publics : aucun champ interne ne peut apparaître.
 */
class Representation
{
    public function __construct(private readonly Format $format) {}

    // ---------------------------------------------------------------- communs

    /** @param  array<string, mixed>|null  $media */
    public function media(?array $media): ?array
    {
        return $media ? [
            'url' => Liens::fichier($media['fichier'], $media['titre']),
            'texte_alternatif' => $media['texte_alternatif'],
            'auteur' => $media['auteur'],
            'date_prise' => $media['date_prise'] ?? null,
            'lieu' => $media['lieu'] ?? null,
        ] : null;
    }

    /** @param  array{intitule: string, reference?: ?string, date?: ?string}|null  $source */
    public function source(?array $source): ?array
    {
        return $source ? ['intitule' => $source['intitule'], 'date_source' => $source['date']] : null;
    }

    /** @param  array<string, mixed>  $localite */
    public function localite(array $localite): array
    {
        return [
            'slug' => $localite['slug'],
            'nom' => $localite['nom'],
            'categorie' => $localite['categorie'] ?? 'autre',
            'latitude' => isset($localite['latitude']) ? (float) $localite['latitude'] : null,
            'longitude' => isset($localite['longitude']) ? (float) $localite['longitude'] : null,
        ];
    }

    private function horodatage(?string $valeur): ?string
    {
        return $valeur ? Carbon::parse($valeur)->toIso8601String() : null;
    }

    // ---------------------------------------------------------------- projets

    public function projetResume(Fiche $projet): array
    {
        return [
            'code' => $projet['code'],
            'slug' => $projet['slug'],
            'titre' => $projet['titre'],
            'secteur' => $projet->get('secteur.code'),
            'localites' => array_map(fn ($l) => $this->localite($l), $projet['localites'] ?? []),
            'resume' => $projet['resume'],
            'statut' => $projet->get('statut.code'),
            'statut_mis_a_jour_le' => $this->horodatage($projet->get('statut.mis_a_jour_le')),
            'image' => $this->media($projet['medias'][0] ?? null),
        ];
    }

    /** Fiche publiée : le recherché est le besoin restant calculé ; jamais de ligne sollicitée ni d'identité d'agent. */
    public function projet(Fiche $projet): array
    {
        $financement = $projet['financement'];
        $positif = fn (?string $montant) => $montant !== null && bccomp($montant, '0', 2) > 0;
        $cout = $projet['cout'];

        return $this->projetResume($projet) + [
            'version' => $projet->version,
            'publie_le' => $projet->publieLe->toIso8601String(),
            'description_technique' => $projet['description_technique'],
            'besoin' => $projet->get('besoin.texte'),
            'beneficiaires' => array_map(fn ($b) => [
                'nombre' => $b['nombre'], 'categorie' => $b['categorie'], 'methode_estimation' => $b['methode_estimation'],
                'date_estimation' => $b['date_estimation'], 'nature' => $b['nature'],
            ], $projet['beneficiaires'] ?? []),
            'cout' => $cout ? [
                'montant' => (float) $cout['montant'], 'devise' => $cout['devise'], 'date_estimation' => $cout['date_estimation'],
                'nature_cout' => $cout['nature_cout'], 'nature' => $cout['nature'], 'source' => $this->source($cout['source']),
            ] : null,
            'financement' => [
                'acquis' => array_map(fn ($l) => [
                    'financeur' => $l['financeur'], 'montant' => (float) $l['montant'], 'devise' => $l['devise'], 'date_information' => $l['date'],
                ], $financement['lignes_acquises']),
                'total_acquis' => $positif($financement['acquis']) ? [['montant' => (float) $financement['acquis'], 'devise' => $financement['devise']]] : [],
                'recherche' => [
                    'montants' => $positif($financement['recherche']) ? [['montant' => (float) $financement['recherche'], 'devise' => $financement['devise']]] : [],
                    'calcule_le' => $financement['calcule_le'],
                ],
            ],
            'avancement' => $projet['avancement'] ? [
                'pourcentage' => (float) $projet->get('avancement.pourcentage'), 'date' => $projet->get('avancement.date'),
                'source' => $this->source($projet->get('avancement.source')),
            ] : null,
            'indicateurs' => array_map(fn ($i) => [
                'libelle' => $i['libelle'], 'unite' => $i['unite'], 'valeur_reference' => $i['valeur_reference'], 'cible' => $i['cible'],
                'valeur_atteinte' => $i['valeur_atteinte'], 'date_mesure' => $i['date_mesure'], 'methode_mesure' => $i['methode_mesure'],
            ], $projet['indicateurs'] ?? []),
            'pieces_disponibles' => array_map(fn ($p) => ['intitule' => $p['intitule'], 'type_piece' => $p['type_piece']], $projet['pieces'] ?? []),
            'medias' => array_values(array_filter(array_map(fn ($m) => $this->media($m), $projet['medias'] ?? []))),
            'geometrie' => $projet['geometries'][0]['geojson'] ?? null,
            'service_responsable' => $projet['service_responsable'],
        ];
    }

    // ---------------------------------------------------------------- contenus, élus, documents, partenaires

    public function contenuResume(Fiche $contenu): array
    {
        return [
            'type' => $contenu['type_contenu'],
            'slug' => $contenu['slug'],
            'titre' => $contenu['titre'],
            'resume' => $contenu['resume'],
            'image' => $this->media($contenu['image']),
            'publie_le' => $contenu->premierePublication->toIso8601String(),
        ];
    }

    public function contenu(Fiche $contenu): array
    {
        return $this->contenuResume($contenu) + [
            'corps_html' => $this->format->markdown($contenu['corps']),
            // Toujours un objet JSON, même vide (schéma : « attributs: object »).
            'attributs' => (object) ($contenu['attributs'] ?? []),
            'version' => $contenu->version,
            'mise_a_jour' => $contenu->publieLe->toIso8601String(),
        ];
    }

    public function elu(Fiche $elu): array
    {
        return [
            'nom_affiche' => $elu['nom_affiche'],
            'fonction' => $elu['fonction'],
            'organe' => $elu['organe'],
            'mandat_debut' => $elu['mandat_debut'],
            'mandat_fin' => $elu['mandat_fin'],
            'biographie_html' => $elu['biographie'] ? $this->format->markdown($elu['biographie']) : null,
            'photo' => $this->media($elu['photo']),
        ];
    }

    public function document(Fiche $document): array
    {
        $fichier = $document->get('version.fichier');

        return [
            'slug' => $document['slug'],
            'titre' => $document['titre'],
            'categorie' => $document->get('categorie.code'),
            'resume' => $document['resume'],
            'date_document' => $document['date_document'],
            'reference_officielle' => $document['reference_officielle'],
            'version' => (int) $document->get('version.numero'),
            'telechargement' => [
                'url' => route('documents.telecharger', $document['slug']),
                'type_mime' => $fichier['type_mime'],
                'taille_octets' => $fichier['taille_octets'],
                'empreinte_sha256' => $fichier['empreinte_sha256'],
            ],
        ];
    }

    public function partenaire(Fiche $partenaire): array
    {
        $site = $partenaire['site_web'];

        return [
            'nom' => $partenaire['nom'],
            'type_partenaire' => $partenaire['type_partenaire'],
            'pays' => $partenaire['pays'],
            // Seules les adresses web sont exposées comme liens.
            'site_web' => $site && preg_match('#^https?://#i', $site) ? $site : null,
            'logo' => $this->media($partenaire['logo']),
        ];
    }
}
