<?php

namespace Eonm\Seo;

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;

/**
 * Vorschau im Reiter "SEO" (Panel-Feld seovorschau, index.js), zwei
 * getrennte Karten: der Treffer in der Google-Suche (Adresse, Titel,
 * Beschreibung) und die Karte beim Teilen (Bild, Domain, Titel,
 * Beschreibung). Beide zeigen, was die Seite ausgibt, mit allen
 * Rueckfallwerten: Die Redaktion sieht auch, was gilt, wenn ihre Felder
 * leer sind.
 *
 * Was die Redaktion gerade tippt, rechnet das Feld im Browser (Titel,
 * Beschreibung, Sichtbarkeit). Von hier kommt alles, was der Browser nicht
 * weiss: Seitentitel, Website, Vorgaben und das gespeicherte Vorschaubild.
 */
class Vorschau
{
    /**
     * Im Reiter einer Seite: Vorschau dieser Seite. In den Einstellungen
     * der Website: Vorschau der Startseite, dort wirken die Vorgaben.
     */
    public static function vorgaben(ModelWithContent $model): array
    {
        $website = $model instanceof Page === false;
        $seite   = $website ? $model->site()->homePage() : $model;
        if ($seite === null) {
            return [];
        }
        $site = $seite->site();
        $bild = $seite->seoBild();

        // Adresse wie im Treffer: https://example.de › ordner › seite
        $teile = array_filter(explode('/', (string) parse_url($seite->url(), PHP_URL_PATH)));
        $start = rtrim(preg_replace('#^(https?://[^/]+).*$#', '$1', $seite->url()), '/');

        return [
            'modus'       => $website ? 'website' : 'seite',
            'adresse'     => implode(' › ', [$start, ...$teile]),
            // Favicon neben dem Treffer: Der Browser nimmt die erste Adresse,
            // die es gibt (Option favicon), sonst zeigt die Karte eine Weltkugel
            'favicons'    => array_map(fn ($pfad) => url($pfad), option('eonm.seo.favicon')),
            // Teilen-Karte: Domain und Titel ohne den Namen der Website (og:title)
            'domain'      => parse_url($seite->url(), PHP_URL_HOST),
            'teilenTitel' => $seite->seoTitel(false),
            'seitentitel' => $seite->title()->value(),
            'website'     => $site->title()->value(),
            'trenner'     => option('eonm.seo.trenner'),
            'istStart'    => $seite->isHomePage(),
            // Website-Modus: Titel und eigene Werte der Startseite stehen fest
            'titel'        => $seite->seoTitel(),
            'eigeneBeschreibung' => trim((string) $seite->seo_beschreibung()->value()),
            'eigenesBild'  => $seite->seo_bild()->toFile() !== null,
            // Seiten-Modus: Vorgabe der Website, wenn das Feld leer ist
            'beschreibungWebsite' => trim((string) $site->seo_beschreibung()->value()),
            // Gespeichertes Vorschaubild samt Rueckfall, und was im Feld gespeichert ist
            'bild'     => $bild?->isResizable()
                ? $bild->thumb(['width' => 480, 'height' => 252, 'crop' => true, 'format' => 'jpg', 'quality' => 82])->url()
                : null,
            'bildFeld' => $model->seo_bild()->yaml(),
            'staging'  => option('staging') === true,
            'entwurf'  => $seite->isDraft(),
            'verborgen' => $seite->seo_noindex()->toBool(),
        ];
    }
}
