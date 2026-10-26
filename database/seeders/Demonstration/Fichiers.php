<?php

namespace Database\Seeders\Demonstration;

use App\Models\Fichier;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Storage;

/**
 * Fichiers FICTIFS générés par l'application pour la démonstration : images SVG et PDF d'une page,
 * chacun portant la mention « DONNÉES FICTIVES — DÉMONSTRATION ». Générés ici et non téléversés,
 * ils n'ont pas à passer par l'analyse antivirus du téléversement (analyse_ok = vrai).
 */
final class Fichiers
{
    public const MENTION = 'DONNÉES FICTIVES — DÉMONSTRATION';

    public static function image(string $titre, ?Utilisateur $par = null): Fichier
    {
        $texte = htmlspecialchars($titre, ENT_XML1);
        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" role="img" aria-label="$texte">
              <rect width="1200" height="800" fill="#EBE4C2"/>
              <path d="M0 560 Q300 470 600 560 T1200 560 V800 H0 Z" fill="#1D52AF"/>
              <circle cx="960" cy="200" r="90" fill="#F6E331"/>
              <text x="60" y="500" font-family="sans-serif" font-size="40" fill="#181528">$texte</text>
              <text x="60" y="760" font-family="sans-serif" font-size="32" fill="#FBF9E9">DONNÉES FICTIVES — DÉMONSTRATION</text>
            </svg>
            SVG;

        return self::enregistrer(str($titre)->slug().'.svg', 'image/svg+xml', $svg, $par);
    }

    /** Portrait FICTIF, au format vertical (4:5) : une silhouette, jamais le visage d'une personne réelle. */
    public static function portrait(string $nom, ?Utilisateur $par = null): Fichier
    {
        $texte = htmlspecialchars($nom, ENT_XML1);
        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" role="img" aria-label="Portrait fictif : $texte">
              <rect width="800" height="1000" fill="#EBE4C2"/>
              <circle cx="560" cy="250" r="120" fill="#F6E331" opacity=".55"/>
              <circle cx="400" cy="400" r="150" fill="#473399"/>
              <path d="M130 1000c0-190 120-330 270-330s270 140 270 330z" fill="#473399"/>
              <path d="M0 930l40-60 40 60 40-60 40 60 40-60 40 60 40-60 40 60 40-60 40 60 40-60 40 60 40-60 40 60 40-60 40 60 40-60 40 60 40-60 40 60v70H0z" fill="#BC1A2A" opacity=".85"/>
              <text x="400" y="975" text-anchor="middle" font-family="sans-serif" font-size="30" fill="#FBF9E9">PORTRAIT FICTIF — DÉMONSTRATION</text>
            </svg>
            SVG;

        return self::enregistrer('portrait-'.str($nom)->slug().'.svg', 'image/svg+xml', $svg, $par);
    }

    public static function pdf(string $titre, ?Utilisateur $par = null): Fichier
    {
        return self::enregistrer(str($titre)->slug().'.pdf', 'application/pdf', self::pdfUnePage([$titre, self::MENTION]), $par);
    }

    private static function enregistrer(string $nom, string $type, string $contenu, ?Utilisateur $par): Fichier
    {
        $empreinte = hash('sha256', $contenu);
        $chemin = "demonstration/$empreinte-$nom";
        Storage::disk('local')->put($chemin, $contenu);

        return Fichier::firstOrCreate(['chemin_stockage' => $chemin], [
            'nom_original' => $nom, 'type_mime' => $type, 'taille_octets' => strlen($contenu),
            'empreinte_sha256' => $empreinte, 'prive' => false, 'analyse_ok' => true, 'televerse_par' => $par?->id,
        ]);
    }

    /** PDF minimal valide (PDF 1.4, police Helvetica), texte en WinAnsi. @param  list<string>  $lignes */
    private static function pdfUnePage(array $lignes): string
    {
        $flux = "BT /F1 18 Tf 72 760 Td 24 TL\n";
        foreach ($lignes as $ligne) {
            $flux .= '('.addcslashes(mb_convert_encoding($ligne, 'Windows-1252', 'UTF-8'), '()\\').") '\n";
        }
        $flux .= 'ET';
        $objets = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($flux)." >>\nstream\n$flux\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n";
        $positions = [];
        foreach ($objets as $i => $objet) {
            $positions[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n$objet\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objets) + 1)."\n0000000000 65535 f \n";
        foreach ($positions as $position) {
            $pdf .= sprintf("%010d 00000 n \n", $position);
        }

        return $pdf.'trailer << /Size '.(count($objets) + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
