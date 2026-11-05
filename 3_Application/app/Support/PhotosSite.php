<?php

namespace App\Support;

use App\Models\PhotoSite;
use App\Models\Site;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos des aires protégées : à l'envoi, l'original (souvent 5 à 10 Mo) est réduit en une grande image
 * (1600 px au plus, ~250 Ko) et une vignette (480 px, ~30 Ko), puis jeté. La qualité reste bonne à l'écran ;
 * la pleine qualité est dans la banque d'images externe (t_sites.lien_photos).
 */
class PhotosSite
{
    /** Nombre maximal de photos par aire protégée. */
    public const MAX = 12;

    /** Côté le plus long de chaque taille, en pixels. */
    public const TAILLES = ['grande' => 1600, 'vignette' => 480];

    /** Poids maximal d'un original envoyé, en Ko. */
    public const POIDS_MAX = 15360;

    private const QUALITE = 80;

    public static function enregistrer(Site $site, UploadedFile $fichier, ?string $legende = null, ?string $credit = null): PhotoSite
    {
        $source = self::charger($fichier);
        $nom = Str::uuid();
        $extension = function_exists('imagewebp') ? 'webp' : 'jpg';
        $chemins = [];
        $dimensions = [];

        foreach (self::TAILLES as $taille => $max) {
            $image = self::reduire($source, $max);
            $chemins[$taille] = "sites/{$site->id}/{$nom}-{$taille}.{$extension}";
            Storage::put($chemins[$taille], self::encoder($image, $extension));
            $dimensions[$taille] = [imagesx($image), imagesy($image)];
        }

        return $site->photos()->create([
            'chemin' => $chemins['grande'],
            'chemin_vignette' => $chemins['vignette'],
            'largeur' => $dimensions['grande'][0],
            'hauteur' => $dimensions['grande'][1],
            'legende' => $legende,
            'credit' => $credit,
            'ordre' => (int) $site->photos()->max('ordre') + 1,
        ]);
    }

    public static function supprimer(PhotoSite $photo): void
    {
        Storage::delete([$photo->chemin, $photo->chemin_vignette]);
        $photo->delete();
    }

    /**
     * Image décodée, remise à l'endroit si l'appareil l'a enregistrée tournée (orientation EXIF des téléphones).
     */
    private static function charger(UploadedFile $fichier): GdImage
    {
        $image = imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));
        abort_if($image === false, 422, 'Image illisible.');

        $orientation = function_exists('exif_read_data') && in_array($fichier->getMimeType(), ['image/jpeg', 'image/jpg'], true)
            ? (@exif_read_data($fichier->getRealPath())['Orientation'] ?? 1)
            : 1;

        return match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    /**
     * Copie réduite (proportions gardées) dont le plus grand côté fait au plus $max pixels, sur fond blanc
     * (une transparence PNG ne donne pas de fond noir).
     */
    private static function reduire(GdImage $source, int $max): GdImage
    {
        [$largeur, $hauteur] = [imagesx($source), imagesy($source)];
        $echelle = min(1, $max / max($largeur, $hauteur));
        [$l, $h] = [max(1, (int) round($largeur * $echelle)), max(1, (int) round($hauteur * $echelle))];

        $image = imagecreatetruecolor($l, $h);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $l, $h, $largeur, $hauteur);

        return $image;
    }

    private static function encoder(GdImage $image, string $extension): string
    {
        ob_start();
        $extension === 'webp' ? imagewebp($image, null, self::QUALITE) : imagejpeg($image, null, self::QUALITE);

        return (string) ob_get_clean();
    }
}
