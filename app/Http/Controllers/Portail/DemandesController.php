<?php

namespace App\Http\Controllers\Portail;

use App\Domaine\Demandes\Demandes;
use App\Domaine\Enums\BaseLegale;
use App\Domaine\Enums\TypeDemande;
use App\Models\Demande;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Formulaires de demande (M04, M05, M06, M11) : mention d'information et consentement non précoché (E-01),
 * champ piège et limitation du débit sans service tiers (E-06), accusé avec numéro, suivi sans compte.
 */
class DemandesController extends PortailController
{
    public const FORMULAIRES = [
        'contact' => ['adresse' => 'contact', 'route' => 'contact', 'type' => TypeDemande::Contact,
            'titre' => 'Contacter la mairie', 'chapeau' => 'Une question, une démarche, un signalement : écrivez-nous, nous vous répondrons.'],
        'investisseur' => ['adresse' => 'investir/manifester-mon-interet', 'route' => 'investir.interet', 'type' => TypeDemande::Investisseur,
            'titre' => 'Manifester mon intérêt', 'chapeau' => 'Présentez votre projet d’investissement : un interlocuteur de la mairie vous recontacte.'],
        'partenaire' => ['adresse' => 'partenaires/devenir-partenaire', 'route' => 'partenaires.devenir', 'type' => TypeDemande::Partenaire,
            'titre' => 'Devenir partenaire', 'chapeau' => 'Financement, assistance technique, partenariat institutionnel : proposez votre concours.'],
        'diaspora' => ['adresse' => 'diaspora/declarer-mon-interet', 'route' => 'diaspora.interet', 'type' => TypeDemande::Diaspora,
            'titre' => 'Déclarer mon intérêt', 'chapeau' => 'Expertise, parrainage d’un projet, investissement : dites-nous comment vous souhaitez contribuer.'],
    ];

    public const OBJETS_CONTACT = ['Demande de renseignement', 'Démarche administrative', 'Signalement', 'Autre'];

    public const TYPES_ORGANISME = ['Bailleur bilatéral', 'Bailleur multilatéral', 'ONG', 'Fondation', 'Entreprise', 'Collectivité', 'Association de la diaspora', 'Autre'];

    public const NATURES_INTERET = [
        'partenaire' => ['Financement', 'Assistance technique', 'Partenariat institutionnel', 'Autre'],
        'diaspora' => ['Expertise', 'Parrainage d’un projet', 'Investissement', 'Autre'],
    ];

    public function formulaire(string $formulaire): View
    {
        return view('portail.demandes.formulaire', [
            'cle' => $formulaire,
            'formulaire' => self::FORMULAIRES[$formulaire],
            'projets' => $this->lecture->projets()->sortBy('titre')->values(),
            'secteurs' => $this->lecture->secteurs(),
            'mention' => config('makary.mention_information'),
            'conservationMois' => intdiv((int) config('makary.conservation.demande_jours'), 30),
        ]);
    }

    public function envoyer(Request $request, string $formulaire, Demandes $demandes): RedirectResponse
    {
        $definition = self::FORMULAIRES[$formulaire];
        $donnees = $request->validate($this->regles($formulaire), [
            'site_web.prohibited' => 'Ce champ doit rester vide.',
            'consentement.accepted' => 'Votre accord est nécessaire pour que nous traitions votre demande.',
        ]);

        $projet = filled($donnees['projet'] ?? null) ? $this->lecture->projet($donnees['projet']) : null;
        $precisions = array_filter([
            $donnees['objet'] ?? null,
            isset($donnees['type_organisme']) ? 'Organisme : '.$donnees['type_organisme'] : null,
            isset($donnees['nature']) ? 'Intérêt : '.$donnees['nature'] : null,
            isset($donnees['secteur']) ? 'Secteur : '.$this->lecture->secteurs()->get($donnees['secteur']) : null,
        ]);
        $demande = $demandes->enregistrer($definition['type'], array_filter([
            'objet' => mb_substr($definition['titre'].($precisions ? ' — '.implode(' · ', $precisions) : ''), 0, 300),
            'message' => $donnees['message'],
            'nom_contact' => $donnees['nom_contact'] ?? null,
            'courriel' => mb_strtolower($donnees['courriel']),
            'telephone' => $donnees['telephone'] ?? null,
            'organisme' => $donnees['organisme'] ?? null,
            'pays' => $donnees['pays'] ?? null,
            'projet_id' => $projet?->objetId,
        ], fn ($valeur) => $valeur !== null && $valeur !== ''), BaseLegale::Consentement, true, config('makary.mention_information.version'));

        return redirect()->route('demande.accuse')->with('demande', [
            'numero' => $demande->numero_suivi, 'titre' => $definition['titre'], 'courriel' => $demande->courriel,
        ]);
    }

    public function accuse(Request $request): View|RedirectResponse
    {
        $demande = $request->session()->get('demande');

        return $demande ? view('portail.demandes.accuse', ['demande' => $demande]) : redirect()->route('accueil');
    }

    public function suivi(): View
    {
        return view('portail.demandes.suivi', ['resultat' => null]);
    }

    /** Suivi par numéro ET adresse électronique : le numéro seul ne suffit pas à consulter une demande. */
    public function suivre(Request $request): View
    {
        $donnees = $request->validate([
            'numero' => ['required', 'string', 'max:20'],
            'courriel' => ['required', 'email:rfc', 'max:254'],
        ]);
        $demande = Demande::where('numero_suivi', mb_strtoupper(trim($donnees['numero'])))
            ->where('courriel', mb_strtolower(trim($donnees['courriel'])))->whereNull('anonymisee_le')->first();
        if (! $demande) {
            throw ValidationException::withMessages(['numero' => 'Aucune demande ne correspond à ce numéro et à cette adresse.']);
        }

        return view('portail.demandes.suivi', ['resultat' => $demande]);
    }

    /** @return array<string, list<mixed>> */
    private function regles(string $formulaire): array
    {
        $regles = [
            'site_web' => ['prohibited'],
            'courriel' => ['required', 'email:rfc', 'max:254'],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'projet' => ['nullable', 'string', 'max:160'],
            'consentement' => ['accepted'],
        ];

        return $regles + match ($formulaire) {
            'contact' => [
                'objet' => ['required', Rule::in(self::OBJETS_CONTACT)],
                'nom_contact' => ['nullable', 'string', 'max:120'],
            ],
            'investisseur' => [
                'organisme' => ['required', 'string', 'max:160'],
                'nom_contact' => ['required', 'string', 'max:120'],
                'pays' => ['required', 'string', 'max:80'],
                'secteur' => ['required', Rule::in($this->lecture->secteurs()->keys())],
            ],
            'partenaire' => [
                'organisme' => ['required', 'string', 'max:160'],
                'type_organisme' => ['required', Rule::in(self::TYPES_ORGANISME)],
                'nom_contact' => ['required', 'string', 'max:120'],
                'pays' => ['required', 'string', 'max:80'],
                'nature' => ['required', Rule::in(self::NATURES_INTERET['partenaire'])],
            ],
            'diaspora' => [
                'nom_contact' => ['required', 'string', 'max:120'],
                'pays' => ['required', 'string', 'max:80'],
                'nature' => ['required', Rule::in(self::NATURES_INTERET['diaspora'])],
            ],
        };
    }
}
