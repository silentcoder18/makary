<?php

namespace App\Portail;

/**
 * Arborescence du portail (docs/conception/arborescence.md) : source unique de l'en-tête, du pied de page,
 * du plan du site et des sous-navigations. Les 12 rubriques du §3.1 sont à un clic depuis l'en-tête.
 */
final class Navigation
{
    /** Navigation principale : la mission (§14). @var array<string, array{libelle: string, route: string, icone: string, chapeau: string, sous: array<string, string>}> */
    public const PRINCIPALE = [
        'decouvrir' => ['libelle' => 'Découvrir Makary', 'route' => 'decouvrir', 'icone' => 'boussole',
            'chapeau' => 'Histoire, territoire, localités, économie et équipements de la commune.',
            'sous' => ['decouvrir.histoire' => 'Histoire', 'decouvrir.territoire' => 'Territoire', 'decouvrir.localites' => 'Localités',
                'decouvrir.economie' => 'Économie', 'decouvrir.equipements' => 'Équipements']],
        'maire' => ['libelle' => 'Le Maire', 'route' => 'maire', 'icone' => 'personne',
            'chapeau' => 'Le mot du Maire, sa vision, ses priorités et le bilan des réalisations du mandat.',
            'sous' => ['maire.vision' => 'Vision et priorités', 'maire.bilans' => 'Bilans', 'mairie.executif' => 'Exécutif municipal',
                'mairie.conseil' => 'Conseil municipal', 'mairie.anciens' => 'Anciens maires', 'mairie.organigramme' => 'Organigramme',
                'mairie.services' => 'Services municipaux']],
        'projets' => ['libelle' => 'Projets et réalisations', 'route' => 'projets', 'icone' => 'chantier',
            'chapeau' => 'Chaque projet de la commune, son statut, son coût, son financement et ses sources.',
            'sous' => ['projets' => 'Tous les projets', 'projets.realisations' => 'Réalisations']],
        'investir' => ['libelle' => 'Investir', 'route' => 'investir', 'icone' => 'graphique',
            'chapeau' => 'Secteurs porteurs, opportunités et démarches pour s’implanter à Makary.',
            'sous' => ['investir.secteurs' => 'Secteurs', 'investir.implanter' => 'S’implanter', 'investir.interet' => 'Manifester mon intérêt']],
        'partenaires' => ['libelle' => 'Partenaires et bailleurs', 'route' => 'partenaires', 'icone' => 'poignee',
            'chapeau' => 'Les besoins de financement de la commune et ses partenaires.',
            'sous' => ['partenaires.besoins' => 'Besoins de financement', 'partenaires.liste' => 'Nos partenaires', 'partenaires.devenir' => 'Devenir partenaire']],
        'diaspora' => ['libelle' => 'Diaspora', 'route' => 'diaspora', 'icone' => 'globe',
            'chapeau' => 'Contribuer au développement de Makary depuis l’extérieur : expertise, projets, investissement.',
            'sous' => ['diaspora.contribuer' => 'Contribuer', 'diaspora.interet' => 'Déclarer mon intérêt', 'demande.suivi' => 'Suivre ma demande']],
    ];

    /** Barre de service : rubriques d'usage courant. @var array<string, array{libelle: string, route: string, icone: string}> */
    public const SERVICE = [
        'actualites' => ['libelle' => 'Actualités', 'route' => 'actualites', 'icone' => 'journal'],
        'patrimoine' => ['libelle' => 'Patrimoine et tourisme', 'route' => 'patrimoine', 'icone' => 'appareil'],
        'documents' => ['libelle' => 'Documents', 'route' => 'documents', 'icone' => 'document'],
        'carte' => ['libelle' => 'Carte', 'route' => 'carte', 'icone' => 'lieu'],
        'demarches' => ['libelle' => 'Démarches', 'route' => 'demarches', 'icone' => 'acte'],
        'contact' => ['libelle' => 'Contact', 'route' => 'contact', 'icone' => 'enveloppe'],
    ];

    /** Pages transversales du pied de page. @var array<string, string> */
    public const INFORMATIONS = [
        'mentions-legales' => 'Mentions légales',
        'confidentialite' => 'Confidentialité',
        'accessibilite' => 'Accessibilité',
        'credits' => 'Crédits',
        'plan-du-site' => 'Plan du site',
    ];

    /**
     * Rubrique à laquelle appartient une route, pour l'état actif de l'en-tête et la sous-navigation.
     *
     * @return array{cle: string, libelle: string, route: string, icone: string, chapeau?: string, sous?: array<string, string>}|null
     */
    public static function rubrique(?string $route): ?array
    {
        if ($route === null) {
            return null;
        }
        $racine = explode('.', $route)[0];
        $racine = match ($racine) {
            'mairie' => 'maire',
            'evenements' => 'actualites',
            default => $racine,
        };
        if ($route === 'demande.suivi') {
            $racine = 'diaspora';
        }
        foreach ([self::PRINCIPALE, self::SERVICE] as $liste) {
            if (isset($liste[$racine])) {
                return ['cle' => $racine, ...$liste[$racine]];
            }
        }

        return null;
    }
}
