<?php

use Eonm\Seo\Betrieb;

/**
 * Strukturdaten (JSON-LD) fuer Suchmaschinen: der Betrieb, die Website und
 * der Pfad zur Seite (Brotkrumen).
 *   <?php snippet('seo/strukturdaten') ?>
 *
 * Betrieb: Daten aus den Einstellungen im Panel, alle Felder ausser firma
 * duerfen fehlen: firma, adresse, telefon, email, logo, oeffnungszeiten,
 * dazu die Profile in sozialen Netzwerken (Option profile) und das
 * Vorschaubild der Website. Aufbereitung in src/Betrieb.php. Ohne
 * Firmenname entfaellt der Betrieb, Website und Pfad bleiben.
 *
 * Pfad: Startseite > Elternseiten > Seite, aus der Seitenstruktur. Google
 * zeigt ihn im Treffer statt der Adresse. Nicht auf der Startseite, der
 * Fehlerseite und auf Seiten, die Suchmaschinen nicht sehen sollen.
 */

// Hochgeladenes Logo, sonst die Datei aus der Option, wenn es sie gibt
$logo = $site->logo()->toFile()?->url();
if ($logo === null && is_file(kirby()->root('index') . '/' . option('eonm.seo.logo'))) {
    $logo = url(option('eonm.seo.logo'));
}
$bild = $site->seo_bild()->toFile();

$firma = $site->firma()->isEmpty() ? null : array_filter([
    // LocalBusiness oder genauer (Bakery, Brewery ...), siehe Option betrieb
    '@type'     => option('eonm.seo.betrieb'),
    '@id'       => $site->url() . '#organisation',
    'name'      => $site->firma()->value(),
    'url'       => $site->url(),
    'logo'      => $logo,
    'image'     => $bild?->isResizable() ? $bild->thumb(['width' => 1200, 'format' => 'jpg', 'quality' => 82])->url() : null,
    'address'   => Betrieb::adresse($site),
    'telephone' => $site->telefon()->value(),
    'email'     => $site->email()->value(),
    'openingHoursSpecification' => Betrieb::oeffnungszeitenSchema($site),
    'sameAs'    => Betrieb::profile($site),
]);

$website = array_filter([
    '@type'      => 'WebSite',
    '@id'        => $site->url() . '#website',
    'url'        => $site->url(),
    'name'       => $site->title()->value(),
    'inLanguage' => 'de',
    'publisher'  => $firma ? ['@id' => $firma['@id']] : null,
]);

$pfad = null;
if ($page->isHomePage() === false && $page->seoIndex()) {
    $stufen = [$site->homePage(), ...$page->parents()->flip()->values(), $page];
    $pfad   = [
        '@type'           => 'BreadcrumbList',
        '@id'             => $page->url() . '#pfad',
        'itemListElement' => array_map(fn ($stufe, $nr) => [
            '@type'    => 'ListItem',
            'position' => $nr + 1,
            'name'     => $stufe->title()->value(),
            'item'     => $stufe->url(),
        ], $stufen, array_keys($stufen)),
    ];
}

$daten = [
    '@context' => 'https://schema.org',
    '@graph'   => array_values(array_filter([$firma, $website, $pfad])),
];
?>
<?php /* JSON_HEX_TAG: ein "</script>" in einem Panel-Feld darf das Skript nicht beenden */ ?>
<script type="application/ld+json"><?= json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
