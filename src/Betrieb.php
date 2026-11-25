<?php

namespace Eonm\Seo;

use Kirby\Cms\Site;
use Kirby\Toolkit\V;

/**
 * Firmendaten aus den Einstellungen der Website, aufbereitet fuer die
 * Strukturdaten (snippets/strukturdaten.php) und fuer die Anzeige
 * ($site->oeffnungszeitenZeilen()).
 *
 * Die Redaktion pflegt jede Angabe genau einmal: Die Anschrift bleibt ein
 * Textfeld (wie sie im Footer steht) und wird hier in Strasse, PLZ und Ort
 * zerlegt; die Oeffnungszeiten sind eine Liste aus Tagen und Uhrzeiten.
 */
class Betrieb
{
    /** Kuerzel aus dem Panel => Wochentag bei schema.org, in Wochenreihenfolge */
    protected const TAGE = [
        'mo' => 'Monday',
        'di' => 'Tuesday',
        'mi' => 'Wednesday',
        'do' => 'Thursday',
        'fr' => 'Friday',
        'sa' => 'Saturday',
        'so' => 'Sunday',
    ];

    /**
     * Anschrift als PostalAddress. Eigene Felder strasse, plz und ort
     * gewinnen, wenn ein Projekt sie hat. Sonst wird das Feld adresse
     * zerlegt: letzte Zeile "PLZ Ort", alles davor ist die Strasse. Passt die
     * letzte Zeile nicht, bleibt die Anschrift eine Zeile Text (das
     * versteht eine Suchmaschine auch, nur ungenauer).
     */
    public static function adresse(Site $site): array|string|null
    {
        $strasse = trim((string) $site->strasse()->value());
        $plz     = trim((string) $site->plz()->value());
        $ort     = trim((string) $site->ort()->value());

        if ($plz === '' && $ort === '') {
            $zeilen = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $site->adresse()->value()))));
            if ($zeilen === []) {
                return null;
            }
            // 4 oder 5 Ziffern (AT/CH/DE), davor erlaubt: "D-", "A-", "CH-"
            if (preg_match('/^(?:[A-Z]{1,2}-)?(\d{4,5})\s+(.+)$/u', end($zeilen), $treffer) !== 1) {
                return implode(', ', $zeilen);
            }
            [, $plz, $ort] = $treffer;
            $strasse = implode(', ', array_slice($zeilen, 0, -1));
        }

        return array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => $strasse,
            'postalCode'      => $plz,
            'addressLocality' => $ort,
            'addressCountry'  => option('eonm.seo.land'),
        ]);
    }

    /**
     * Oeffnungszeiten aus dem Feld oeffnungszeiten (blueprints/fields/
     * seo-oeffnungszeiten.yml): je Zeile ['tage' => ['mo', 'di'], 'von' =>
     * '08:00', 'bis' => '17:00']. Zeilen ohne Tag oder Uhrzeit fallen weg.
     */
    public static function oeffnungszeiten(Site $site): array
    {
        $zeiten = [];
        foreach ($site->oeffnungszeiten()->toStructure() as $zeile) {
            $gewaehlt = $zeile->tage()->split(',');
            $tage     = array_values(array_filter(array_keys(static::TAGE), fn ($tag) => in_array($tag, $gewaehlt, true)));
            // Das Zeitfeld speichert HH:MM:SS
            $von = substr((string) $zeile->von()->value(), 0, 5);
            $bis = substr((string) $zeile->bis()->value(), 0, 5);

            if ($tage !== [] && strlen($von) === 5 && strlen($bis) === 5) {
                $zeiten[] = ['tage' => $tage, 'von' => $von, 'bis' => $bis];
            }
        }
        return $zeiten;
    }

    /** Oeffnungszeiten als openingHoursSpecification fuer die Strukturdaten */
    public static function oeffnungszeitenSchema(Site $site): array
    {
        return array_map(fn ($zeit) => [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => array_map(fn ($tag) => static::TAGE[$tag], $zeit['tage']),
            'opens'     => $zeit['von'],
            'closes'    => $zeit['bis'],
        ], static::oeffnungszeiten($site));
    }

    /**
     * Oeffnungszeiten zum Anzeigen: je Zeile ['tage' => 'Mo–Fr', 'zeit' =>
     * '08:00–17:00 Uhr']. Aufeinanderfolgende Tage werden zusammengezogen.
     */
    public static function oeffnungszeitenZeilen(Site $site): array
    {
        $alle = array_keys(static::TAGE);

        return array_map(function ($zeit) use ($alle) {
            $gruppen = [];
            foreach ($zeit['tage'] as $tag) {
                $nr     = array_search($tag, $alle, true);
                $letzte = array_key_last($gruppen);
                if ($letzte !== null && end($gruppen[$letzte]) === $nr - 1) {
                    $gruppen[$letzte][] = $nr;
                } else {
                    $gruppen[] = [$nr];
                }
            }
            $teile = array_map(function ($gruppe) use ($alle) {
                $erster  = ucfirst($alle[$gruppe[0]]);
                $letzter = ucfirst($alle[end($gruppe)]);
                // Zwei Tage nebeneinander lesen sich als "Sa, So" besser
                return match (count($gruppe)) {
                    1       => $erster,
                    2       => $erster . ', ' . $letzter,
                    default => $erster . '–' . $letzter,
                };
            }, $gruppen);

            return ['tage' => implode(', ', $teile), 'zeit' => $zeit['von'] . '–' . $zeit['bis'] . ' Uhr'];
        }, static::oeffnungszeiten($site));
    }

    /**
     * Adressen der Profile in sozialen Netzwerken (Option profile: Namen der
     * Felder in den Einstellungen), fuer sameAs. Nur gueltige Adressen mit
     * einem Pfad: "https://www.instagram.com/" ohne Profil ist ein
     * Platzhalter und wuerde das Netzwerk selbst als Profil der Firma melden.
     */
    public static function profile(Site $site): array
    {
        $adressen = [];
        foreach (option('eonm.seo.profile') as $feld) {
            $adresse = trim((string) $site->content()->get($feld)->value());
            $pfad = trim((string) parse_url($adresse, PHP_URL_PATH), '/');
            if ($adresse !== '' && V::url($adresse) && $pfad !== '') {
                $adressen[] = $adresse;
            }
        }
        return $adressen;
    }
}
