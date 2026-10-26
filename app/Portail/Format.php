<?php

namespace App\Portail;

use App\Domaine\Enums\NatureInformation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Mise en forme française des valeurs publiées (vues et fiche PDF) : dates, montants avec leur devise,
 * nature de l'information et sources. Une estimation est toujours affichée comme telle, avec sa date.
 */
class Format
{
    /** Espace fine insécable, séparateur des milliers en français. */
    private const ESPACE_FINE = "\u{202F}";

    public function date(CarbonInterface|string|null $date, string $format = 'j F Y'): string
    {
        return $date ? Carbon::parse($date)->locale('fr')->translatedFormat($format) : '';
    }

    public function nombre(int|float|string|null $nombre): string
    {
        if ($nombre === null || $nombre === '') {
            return '';
        }
        $decimales = fmod((float) $nombre, 1.0) === 0.0 ? 0 : 2;

        return number_format((float) $nombre, $decimales, ',', self::ESPACE_FINE);
    }

    /** Montant exact (chaîne décimale) avec sa devise ; jamais de conversion ni d'arrondi trompeur. */
    public function montant(?string $montant, ?string $devise): string
    {
        if ($montant === null) {
            return '—';
        }
        [$entier, $decimales] = array_pad(explode('.', $montant), 2, '00');
        $entier = ltrim($entier, '0') ?: '0';
        $tete = strlen($entier) % 3;
        $groupes = [...($tete ? [substr($entier, 0, $tete)] : []), ...str_split(substr($entier, $tete), 3)];
        $entier = implode(self::ESPACE_FINE, array_filter($groupes, fn (string $g) => $g !== ''));

        return $entier.(trim($decimales, '0') !== '' ? ','.$decimales : '')."\u{00A0}".($devise ?? '');
    }

    /** Part de l'acquis dans le coût, en pour cent entier (barre de progression), ou null si le coût est inconnu. */
    public function part(?string $acquis, ?string $cout): ?int
    {
        if ($acquis === null || $cout === null || bccomp($cout, '0', 2) <= 0) {
            return null;
        }

        return (int) min(100, floor((float) bcdiv(bcmul($acquis, '100', 2), $cout, 2)));
    }

    public function nature(?string $nature): string
    {
        return NatureInformation::tryFrom((string) $nature)?->libelle() ?? '';
    }

    public function natureCout(?string $nature): string
    {
        return match ($nature) {
            'estimation' => 'estimation', 'devis' => 'devis', 'marche' => 'montant du marché', 'cout_final' => 'coût final',
            default => (string) $nature,
        };
    }

    /** @param  array{intitule?: string, reference?: ?string, date?: ?string}|null  $source */
    public function source(?array $source): string
    {
        if (! $source) {
            return '';
        }

        return 'Source : '.$source['intitule'].($source['reference'] ? ', '.$source['reference'] : '').($source['date'] ? ', '.$this->date($source['date']) : '');
    }

    public function sourceEntreParentheses(?array $source): string
    {
        return $source ? ' ('.lcfirst($this->source($source)).')' : '';
    }

    /** Markdown publié rendu en HTML assaini : le HTML saisi est échappé, les liens dangereux sont refusés. */
    public function markdown(?string $texte): string
    {
        return $texte ? Str::markdown($texte, ['html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 10]) : '';
    }

    public function aFournir(string $quoi): string
    {
        return "[À fournir par la Commune : $quoi]";
    }

    /** Une fiche dont le statut n'a pas été mis à jour depuis six mois porte la mention « information datée » (fiche projet §3). */
    public function estDatee(?string $misAJourLe): bool
    {
        return $misAJourLe !== null && Carbon::parse($misAJourLe)->lt(now()->subMonths(6));
    }
}
