<?php

namespace App\Portail;

use App\Domaine\Enums\TypeObjet;
use Illuminate\Support\Str;

/** Adresses publiques des fiches publiées et des fichiers, selon l'arborescence P1. */
final class Liens
{
    public static function vers(TypeObjet $type, Fiche $fiche): ?string
    {
        return match ($type) {
            TypeObjet::Projet => route('projets.show', $fiche['slug']),
            TypeObjet::Document => route('documents.show', $fiche['slug']),
            TypeObjet::Localite => route('decouvrir.localite', $fiche['slug']),
            TypeObjet::Elu => route(in_array($fiche['organe'], ['ancien_maire', 'ancien_executif'], true) ? 'mairie.anciens'
                : ($fiche['organe'] === 'conseil_municipal' ? 'mairie.conseil' : 'mairie.executif')),
            TypeObjet::Contenu => self::contenu($fiche),
            default => null,
        };
    }

    public static function contenu(Fiche $contenu): ?string
    {
        return match ($contenu['type_contenu']) {
            'actualite' => route('actualites.article', [$contenu->premierePublication->year, $contenu['slug']]),
            'evenement' => route('evenements.show', $contenu['slug']),
            'site_patrimoine' => route('patrimoine.site', $contenu['slug']),
            'service' => route('mairie.service', $contenu['slug']),
            'demarche' => route('demarches.show', $contenu['slug']),
            'opportunite' => route('investir').'#'.$contenu['slug'],
            'page' => self::page($contenu['slug']),
            default => null,
        };
    }

    /** Pages éditoriales : chaque emplacement prévu par l'arborescence a son adresse. */
    public static function page(string $slug): ?string
    {
        $routes = [
            'presentation' => 'decouvrir', 'histoire' => 'decouvrir.histoire', 'territoire' => 'decouvrir.territoire', 'economie' => 'decouvrir.economie',
            'mot-du-maire' => 'maire', 'vision-et-priorites' => 'maire.vision', 'organigramme' => 'mairie.organigramme',
            's-implanter' => 'investir.implanter', 'diaspora' => 'diaspora', 'contribuer' => 'diaspora.contribuer',
            'culture-et-histoire' => 'patrimoine.culture', 'informations-pratiques' => 'patrimoine.pratique',
            'mentions-legales' => 'mentions-legales', 'confidentialite' => 'confidentialite', 'accessibilite' => 'accessibilite', 'credits' => 'credits',
        ];

        return isset($routes[$slug]) ? route($routes[$slug]) : null;
    }

    /** @param  array{id: int, type_mime?: string, nom?: string}  $fichier */
    public static function fichier(array $fichier, string $titre): string
    {
        $extension = match ($fichier['type_mime'] ?? '') {
            'image/svg+xml' => 'svg', 'image/webp' => 'webp', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf',
            default => 'bin',
        };

        return route('fichier', ['fichier' => $fichier['id'], 'nom' => $fichier['nom'] ?? Str::slug($titre).'.'.$extension]);
    }

    /** Adresse versionnée du jeu d'icônes (mise en cache longue durée côté navigateur). */
    public static function icones(): string
    {
        static $version = null;
        $version ??= substr(hash_file('sha256', public_path('icones.svg')), 0, 10);

        return asset('icones.svg').'?v='.$version;
    }
}
