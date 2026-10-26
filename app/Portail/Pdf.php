<?php

namespace App\Portail;

/**
 * Rédacteur PDF minimal, sans dépendance : texte sur une ou plusieurs pages A4, polices standard
 * Helvetica et Helvetica-Bold (codage WinAnsi), retours à la ligne et sauts de page automatiques.
 */
final class Pdf
{
    private const LARGEUR = 595;

    private const HAUTEUR = 842;

    private const MARGE = 56;

    /** @var list<string> flux de chaque page */
    private array $pages = [];

    /** @var list<array{donnees: string, largeur: int, hauteur: int}> images JPEG (en RGB) placées dans le document */
    private array $images = [];

    private string $flux = '';

    private float $y = 0;

    public function __construct(private readonly string $piedDePage = '')
    {
        $this->nouvellePage();
    }

    public function titre(string $texte): self
    {
        return $this->paragraphe($texte, 18, true, 8);
    }

    public function intertitre(string $texte): self
    {
        $this->y -= 6;

        return $this->paragraphe($texte, 12, true, 4);
    }

    public function texte(string $texte, int $taille = 10): self
    {
        return $this->paragraphe($texte, $taille, false, 4);
    }

    public function paragraphe(string $texte, int $taille, bool $gras, int $espaceApres): self
    {
        $interligne = $taille * 1.35;
        foreach ($this->couper($this->encoder($texte), $taille, $gras) as $ligne) {
            if ($this->y - $interligne < self::MARGE + 24) {
                $this->nouvellePage();
            }
            $this->y -= $interligne;
            $this->flux .= sprintf("BT /%s %d Tf %d %.1F Td (%s) Tj ET\n", $gras ? 'F2' : 'F1', $taille, self::MARGE, $this->y, $this->echapper($ligne));
        }
        $this->y -= $espaceApres;

        return $this;
    }

    /** Image JPEG (en RGB, non progressive) alignée à gauche à la position courante, de la hauteur donnée en points. */
    public function image(string $jpeg, float $hauteur): self
    {
        [$largeurPx, $hauteurPx] = getimagesizefromstring($jpeg) ?: [0, 0];
        if ($largeurPx === 0) {
            return $this;
        }
        $this->images[] = ['donnees' => $jpeg, 'largeur' => $largeurPx, 'hauteur' => $hauteurPx];
        if ($this->y - $hauteur < self::MARGE + 24) {
            $this->nouvellePage();
        }
        $this->y -= $hauteur;
        $this->flux .= sprintf("q %.2F 0 0 %.2F %d %.2F cm /Im%d Do Q\n", $hauteur * $largeurPx / $hauteurPx, $hauteur, self::MARGE, $this->y, count($this->images));
        $this->y -= 8;

        return $this;
    }

    public function rendu(): string
    {
        $this->terminerPage();
        $total = count($this->pages);
        // Objets : catalogue, arbre des pages, chaque page et son flux, deux polices, puis les images.
        $images = implode(' ', array_map(fn (int $i) => sprintf('/Im%d %d 0 R', $i + 1, 5 + 2 * $total + $i), array_keys($this->images)));
        $objets = ['<< /Type /Catalog /Pages 2 0 R >>', ''];
        $enfants = [];
        foreach ($this->pages as $i => $flux) {
            $pied = $this->encoder(trim($this->piedDePage.' · page '.($i + 1).' sur '.$total, ' ·'));
            $flux .= sprintf("BT /F1 8 Tf %d %d Td (%s) Tj ET\n", self::MARGE, 30, $this->echapper($pied));
            $numeroPage = count($objets) + 1;
            $enfants[] = "$numeroPage 0 R";
            $objets[] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Contents %d 0 R /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >>%s >> >>',
                self::LARGEUR, self::HAUTEUR, $numeroPage + 1, 3 + 2 * $total, 4 + 2 * $total, $images !== '' ? " /XObject << $images >>" : '');
            $objets[] = '<< /Length '.strlen($flux)." >>\nstream\n$flux\nendstream";
        }
        $objets[1] = '<< /Type /Pages /Kids ['.implode(' ', $enfants).'] /Count '.$total.' >>';
        $objets[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objets[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($this->images as $image) {
            $objets[] = sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>',
                $image['largeur'], $image['hauteur'], strlen($image['donnees']))."\nstream\n{$image['donnees']}\nendstream";
        }

        $pdf = "%PDF-1.4\n";
        $positions = [];
        foreach ($objets as $i => $objet) {
            $positions[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n$objet\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objets) + 1)."\n0000000000 65535 f \n";
        foreach ($positions as $position) {
            $pdf .= sprintf("%010d 00000 n \n", $position);
        }

        return $pdf.'trailer << /Size '.(count($objets) + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    private function nouvellePage(): void
    {
        if ($this->flux !== '') {
            $this->terminerPage();
        }
        $this->flux = "\n";
        $this->y = self::HAUTEUR - self::MARGE;
    }

    private function terminerPage(): void
    {
        $this->pages[] = $this->flux;
        $this->flux = '';
    }

    /**
     * Coupe un texte déjà codé en WinAnsi (un octet par caractère) en lignes tenant dans la largeur utile,
     * d'après la largeur moyenne des glyphes Helvetica.
     *
     * @return list<string>
     */
    private function couper(string $texte, int $taille, bool $gras): array
    {
        $parLigne = max(20, (int) floor((self::LARGEUR - 2 * self::MARGE) / ($taille * ($gras ? 0.56 : 0.5))));
        $lignes = [];
        foreach (preg_split('/\r\n|\r|\n/', $texte) as $paragraphe) {
            $lignes = [...$lignes, ...explode("\n", wordwrap($paragraphe, $parLigne, "\n", true))];
        }

        return $lignes;
    }

    /** UTF-8 vers WinAnsi (Windows-1252), le codage des polices standard du PDF. */
    private function encoder(string $texte): string
    {
        $texte = strtr($texte, ["\u{2019}" => "'", "\u{2018}" => "'", "\u{202F}" => ' ', "\u{00A0}" => ' ']);

        return (string) mb_convert_encoding($texte, 'Windows-1252', 'UTF-8');
    }

    private function echapper(string $texte): string
    {
        return addcslashes($texte, '()\\');
    }
}
