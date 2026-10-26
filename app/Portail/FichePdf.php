<?php

namespace App\Portail;

/**
 * Fiche de financement téléchargeable (fiche projet §4) : générée à partir de la fiche publiée,
 * jamais saisie à la main ; les douze rubriques prévues, dans l'ordre.
 */
class FichePdf
{
    public function __construct(private readonly Format $format = new Format) {}

    public function generer(Fiche $projet, string $adresse): string
    {
        $f = $this->format;
        $pdf = new Pdf('Commune de Makary · '.$projet['code'].' · version '.$projet->version);
        // Logo de la Commune (outils-dev/deriver-logo.php), en tête de la première page.
        if (is_file($logo = public_path('images/logo-makary-pdf.jpg'))) {
            $pdf->image((string) file_get_contents($logo), 64);
        }
        if (config('makary.donnees_fictives')) {
            $pdf->paragraphe('DONNÉES FICTIVES — DÉMONSTRATION', 10, true, 6);
        }

        // 1. En-tête ; 2. titre, secteur, localités
        $pdf->texte('Commune de Makary · Fiche de financement · '.$projet['code'].' · version '.$projet->version.' · générée le '.$f->date(now()), 9)
            ->titre($projet['titre'])
            ->texte('Secteur : '.$projet->get('secteur.libelle').' · Localités : '.(collect($projet['localites'])->pluck('nom')->implode(', ') ?: 'non renseignées'));
        if ($projet['resume']) {
            $pdf->texte($projet['resume']);
        }

        // 3. Besoin et bénéficiaires
        $pdf->intertitre('Besoin et bénéficiaires');
        $pdf->texte($projet->get('besoin.texte')
            ? $projet->get('besoin.texte').$f->sourceEntreParentheses($projet->get('besoin.source'))
            : 'Besoin non encore renseigné.');
        foreach ($projet['beneficiaires'] ?? [] as $b) {
            $pdf->texte("{$f->nombre($b['nombre'])} {$b['categorie']} — méthode : {$b['methode_estimation']} ({$f->nature($b['nature'])} au {$f->date($b['date_estimation'])})");
        }

        // 4. Description technique et indicateurs cibles
        if ($projet['description_technique']) {
            $pdf->intertitre('Description technique')->texte($projet['description_technique']);
        }
        if ($projet['indicateurs']) {
            $pdf->intertitre('Indicateurs');
            foreach ($projet['indicateurs'] as $i) {
                $pdf->texte("{$i['libelle']} : cible {$f->nombre($i['cible'])} {$i['unite']} — mesure : {$i['methode_mesure']}");
            }
        }

        // 5. Coût ; 6. financement acquis ; 7. financement recherché
        $pdf->intertitre('Coût estimatif');
        $cout = $projet['cout'];
        $pdf->texte($cout
            ? "{$f->montant($cout['montant'], $cout['devise'])} — {$f->natureCout($cout['nature_cout'])}, {$f->nature($cout['nature'])} au {$f->date($cout['date_estimation'])}{$f->sourceEntreParentheses($cout['source'])}"
            : 'Coût non encore estimé.');
        $financement = $projet['financement'];
        $pdf->intertitre('Financement acquis')
            ->texte('Total acquis : '.$f->montant($financement['acquis'], $financement['devise']));
        foreach ($financement['lignes_acquises'] as $ligne) {
            $pdf->texte('— '.$f->montant($ligne['montant'], $ligne['devise']).' le '.$f->date($ligne['date']).' : '.($ligne['financeur'] ?? 'financeur non cité à sa demande'));
        }
        $pdf->intertitre('Financement recherché')
            ->texte($financement['recherche'] === null ? 'Non calculé : coût non estimé.'
                : 'Besoin restant : '.$f->montant($financement['recherche'], $financement['devise']).', calculé le '.$f->date($financement['calcule_le']).'.');

        // 8. Statut ; 9. pièces ; 10. contact
        $pdf->intertitre('Statut')
            ->texte($projet->get('statut.libelle').' — mis à jour le '.$f->date($projet->get('statut.mis_a_jour_le')));
        if ($projet['pieces']) {
            $pdf->intertitre('Pièces disponibles sur demande');
            foreach ($projet['pieces'] as $piece) {
                $pdf->texte("— {$piece['intitule']} ({$f->date($piece['date'])})");
            }
        }
        $pdf->intertitre('Contact')->texte(($projet['service_responsable'] ?: 'Service des projets').' · Commune de Makary · '.$f->aFournir('adresse générique du service des projets'));

        // 11. Mention fixe ; 12. adresse de la fiche en ligne
        $pdf->intertitre('Mentions')
            ->texte('Les montants indiqués sont des estimations à la date mentionnée. Ce document ne vaut pas engagement de la Commune.')
            ->texte('Version à jour : '.$adresse);

        return $pdf->rendu();
    }
}
