<?php

use Kirby\Cms\File;
use Kirby\Http\Response;
use Eonm\Seo\AltText;
use Eonm\Seo\Betrieb;
use Eonm\Seo\Vorschau;

load([
    'Eonm\\Seo\\AltText' => __DIR__ . '/src/AltText.php',
    'Eonm\\Seo\\Betrieb' => __DIR__ . '/src/Betrieb.php',
    'Eonm\\Seo\\Vorschau' => __DIR__ . '/src/Vorschau.php',
]);

/**
 * SEO-Grundausstattung
 *
 * Was die Projekte wirklich brauchen: Meta-Tags, Reiter "SEO" im Panel,
 * sitemap.xml, robots.txt, Strukturdaten und Alt-Texte beim Bild-Upload.
 *
 * Dazu ein Brotkrumen-Pfad in den Strukturdaten und im Reiter "SEO" eine
 * Vorschau, die beim Tippen mitlaeuft (Feld seovorschau: index.js,
 * index.css, src/Vorschau.php).
 *
 * Einbinden:
 *   <?php snippet('seo/kopf') ?>             im <head> (site/snippets/header.php)
 *   <?php snippet('seo/strukturdaten') ?>    vor </body> (site/snippets/footer.php)
 *   tabs: { seo: tabs/seo }                  in Seiten-Blueprints
 *   tabs: { seo: tabs/seo-site }             in site/blueprints/site.yml
 *
 * Rueckfall der Werte: Seite -> Einstellungen der Website.
 *   $page->seoTitel()          seo_titel oder Seitentitel, dahinter " - Website"
 *                              (seoTitel(false) ohne den Zusatz, fuer og:title)
 *   $page->seoBeschreibung()   seo_beschreibung der Seite, sonst der Website
 *   $page->seoBild()           seo_bild -> Bildfelder der Seite (Option bildfelder)
 *                              -> Bild der Website
 *   $page->seoIndex()          false auf Stage/lokal, bei seo_noindex, Fehlerseite, Entwurf
 *
 * Stage und lokal (option staging === true): ueberall noindex, robots.txt
 * sperrt alles, die Sitemap gibt es nicht.
 *
 * Alt-Texte: Beim Hochladen eines Bildes schlaegt Claude einen Alt-Text vor
 * (src/AltText.php) und traegt ihn ins Feld "alt" ein. Nur aktiv, wenn ein
 * API-Schluessel in site/config/ki.php steht (Vorlage ki.php.example).
 * Fuer Bilder, die schon da sind: Feld seoalttexte (steht in tabs/seo-site)
 * oder auf der Kommandozeile kirby seo:alt-texte. Beides fuellt nur leere
 * Alt-Texte, vorhandene bleiben, wie sie sind.
 *
 * Das Plugin haengt an keinem anderen Plugin: Das Vorschaubild rechnet es
 * selbst (1200 x 630, JPG), die Firmendaten liest es aus den Feldern firma,
 * adresse, telefon, email, logo und oeffnungszeiten der Website (alle
 * optional, Aufbereitung in src/Betrieb.php).
 *
 * Felder fuer site/blueprints/site.yml, die das Plugin mitbringt:
 *   oeffnungszeiten: fields/seo-oeffnungszeiten   Liste aus Tagen und Uhrzeiten
 *   profile: fields/seo-profile                   Instagram, Facebook, YouTube ...
 * Anzeige auf der Website: $site->oeffnungszeitenZeilen() gibt je Zeile
 * ['tage' => 'Mo–Fr', 'zeit' => '08:00–17:00 Uhr'].
 *
 * Optionen (site/config/config.php bzw. ki.php):
 *   eonm.seo.locale          og:locale, Standard de_DE
 *   eonm.seo.trenner         zwischen Seitentitel und Website, Standard ' - '
 *   eonm.seo.bildfelder      Bildfelder der Seite fuer das Vorschaubild, in dieser
 *                           Reihenfolge; Standard ['hero_image', 'card_bild']
 *   eonm.seo.sitemap.ohne    Vorlagen, deren Seiten nicht in die Sitemap sollen,
 *                           z.B. ['projekte']; Standard []
 *   eonm.seo.logo            Logo fuer die Strukturdaten, wenn in der Website keins
 *                           hochgeladen ist; Standard assets/img/logo.svg
 *   eonm.seo.betrieb         Art des Betriebs bei schema.org, Standard LocalBusiness;
 *                           genauer z.B. Bakery, Brewery, Restaurant, Store,
 *                           HomeAndConstructionBusiness. Ohne festen Standort:
 *                           Organization
 *   eonm.seo.land            Land der Anschrift, Standard DE
 *   eonm.seo.profile         Felder der Website mit Profil-Adressen (sameAs); Standard
 *                           instagram, facebook, youtube, linkedin, tiktok
 *   eonm.seo.favicon         Wo das Favicon liegt, fuer die Vorschau im Panel; die erste
 *                           Adresse, die es gibt. Standard favicon.ico, dann
 *                           assets/img/favicon.svg
 *   eonm.seo.ki.schluessel   API-Schluessel der Claude-API, ohne ihn keine Alt-Texte
 *   eonm.seo.ki.modell       Standard claude-haiku-5-5 (guenstigstes Modell mit Bildverstaendnis)
 *   eonm.seo.ki.zeitlimit    Sekunden, die ein Upload hoechstens auf die Antwort wartet
 */
Kirby::plugin('eonm/seo', [
    'options' => [
        'locale'        => 'de_DE',
        'trenner'       => ' - ',
        'bildfelder'    => ['hero_image', 'card_bild'],
        'sitemap.ohne'  => [],
        'logo'          => 'assets/img/logo.svg',
        'betrieb'       => 'LocalBusiness',
        'land'          => 'DE',
        'profile'       => ['instagram', 'facebook', 'youtube', 'linkedin', 'tiktok'],
        'favicon'       => ['favicon.ico', 'assets/img/favicon.svg'],
        'ki.schluessel' => null,
        'ki.modell'     => 'claude-haiku-5-5',
        'ki.zeitlimit'  => 20,
    ],

    'hooks' => [
        // Gibt die Datei mit Alt-Text zurueck; null laesst sie unveraendert
        'file.create:after'  => fn (File $file) => AltText::fuerUpload($file),
        // Ersetztes Bild: Der alte Alt-Text beschreibt das alte Bild, also
        // neu beschreiben, auch wenn das Feld gefuellt ist.
        // Kirby uebergibt Hook-Argumente nach Namen: newFile und oldFile.
        'file.replace:after' => fn (File $newFile, File $oldFile) => AltText::fuerUpload($newFile, ersetzt: true),
    ],

    'snippets' => [
        'seo/kopf'          => __DIR__ . '/snippets/kopf.php',
        'seo/strukturdaten' => __DIR__ . '/snippets/strukturdaten.php',
    ],

    'blueprints' => [
        'tabs/seo'      => __DIR__ . '/blueprints/tabs/seo.yml',
        'tabs/seo-site' => __DIR__ . '/blueprints/tabs/seo-site.yml',
        'fields/seo-oeffnungszeiten' => __DIR__ . '/blueprints/fields/seo-oeffnungszeiten.yml',
        'fields/seo-profile'         => __DIR__ . '/blueprints/fields/seo-profile.yml',
    ],

    'fields' => [
        // Vorschau im Reiter "SEO": reines Anzeige-Feld, Darstellung in index.js
        'seovorschau' => [
            'save'     => false,
            'computed' => [
                'vorgaben' => function (): array {
                    return Vorschau::vorgaben($this->model());
                },
            ],
        ],
        // Alt-Texte nachtragen: Knopf im Reiter "SEO" der Website, arbeitet
        // die Bilder ohne Alt-Text ueber die API-Routen unten nacheinander ab
        'seoalttexte' => [
            'save' => false,
        ],
    ],

    // Fuer das Feld seoalttexte. Ein Bild je Anfrage, damit keine Anfrage an
    // das Zeitlimit von PHP stoesst und das Panel den Fortschritt zeigen kann.
    'api' => [
        'routes' => [
            [
                'pattern' => 'eonm-seo/alt-texte',
                'method'  => 'GET',
                'action'  => function () {
                    $bilder = AltText::fehlende()->filter(fn (File $file) => $file->permissions()->can('update'));

                    return [
                        'aktiv'  => AltText::aktiv(),
                        'bilder' => array_values($bilder->values(fn (File $file) => [
                            'id'   => $file->id(),
                            'name' => $file->filename(),
                            'ort'  => $file->parent() instanceof Kirby\Cms\Page ? $file->parent()->title()->value() : 'Website',
                            'link' => $file->panel()->url(true),
                        ])),
                    ];
                },
            ],
            [
                'pattern' => 'eonm-seo/alt-texte',
                'method'  => 'POST',
                'action'  => function () {
                    $file = $this->kirby()->file((string) $this->requestBody('id'));

                    if ($file === null) {
                        return ['grund' => 'Bild nicht gefunden'];
                    }
                    if ($file->permissions()->can('update') === false) {
                        return ['grund' => 'Keine Berechtigung, das Bild zu bearbeiten'];
                    }
                    // Zeitlimit der API und eine moegliche Kuerzung passen
                    // nicht immer in die 30 Sekunden, die PHP oft erlaubt
                    @set_time_limit(120);

                    return AltText::nachtragen($file);
                },
            ],
        ],
    ],

    // Dasselbe auf der Kommandozeile (getkirby/cli): kirby seo:alt-texte
    'commands' => [
        'seo:alt-texte' => [
            'description' => 'Alt-Texte fuer Bilder ohne Alt-Text nachtragen (vorhandene bleiben unangetastet)',
            'args'        => [
                'liste' => [
                    'longPrefix'  => 'liste',
                    'description' => 'Nur auflisten, nichts an die Claude-API schicken',
                    'noValue'     => true,
                ],
            ],
            'command' => static function ($cli): void {
                $bilder = AltText::fehlende();
                $cli->info($bilder->count() . ' Bilder ohne Alt-Text');

                if ($cli->arg('liste')) {
                    foreach ($bilder as $file) {
                        $cli->out($file->id());
                    }
                    return;
                }
                if (AltText::aktiv() === false) {
                    $cli->error('Kein API-Schluessel eingerichtet (site/config/ki.php)');
                    return;
                }

                $ohne = 0;
                foreach ($bilder as $file) {
                    $ergebnis = AltText::nachtragen($file);
                    if (isset($ergebnis['text'])) {
                        $cli->success($file->id() . ': ' . $ergebnis['text']);
                    } else {
                        $ohne++;
                        $cli->error($file->id() . ': ' . $ergebnis['grund']);
                    }
                }
                $cli->info('Fertig, ' . ($bilder->count() - $ohne) . ' Alt-Texte eingetragen, ' . $ohne . ' ohne');
            },
        ],
    ],

    'siteMethods' => [
        // Oeffnungszeiten zum Anzeigen, z.B. im Footer oder Standort-Abschnitt
        'oeffnungszeitenZeilen' => function (): array {
            return Betrieb::oeffnungszeitenZeilen($this);
        },
    ],

    'pageMethods' => [
        'seoTitel' => function (bool $mitWebsite = true): string {
            $website = $this->site()->title()->value();

            // Die Startseite heisst wie die Website, solange nichts eingetragen ist
            if ($this->isHomePage() && $this->seo_titel()->isEmpty()) {
                return $website;
            }
            $titel = $this->seo_titel()->or($this->title())->value();
            return $mitWebsite ? $titel . option('eonm.seo.trenner') . $website : $titel;
        },

        'seoBeschreibung' => function (): ?string {
            $text = $this->seo_beschreibung()->or($this->site()->seo_beschreibung())->value();
            $text = trim(preg_replace('/\s+/', ' ', (string) $text));
            return $text !== '' ? $text : null;
        },

        'seoBild' => function (): ?File {
            foreach (['seo_bild', ...option('eonm.seo.bildfelder')] as $feld) {
                if ($bild = $this->content()->get($feld)->toFile()) {
                    return $bild;
                }
            }
            return $this->site()->seo_bild()->toFile();
        },

        'seoIndex' => function (): bool {
            if (option('staging') === true || $this->isErrorPage() || $this->isDraft()) {
                return false;
            }
            return $this->seo_noindex()->toBool() === false;
        },
    ],

    'routes' => [
        [
            'pattern' => 'robots.txt',
            'action'  => function () {
                $zeilen = option('staging') === true
                    ? ['User-agent: *', 'Disallow: /']
                    : ['User-agent: *', 'Allow: /', 'Disallow: /panel', '', 'Sitemap: ' . url('sitemap.xml')];

                return new Response(implode("\n", $zeilen) . "\n", 'text/plain');
            },
        ],
        [
            'pattern' => 'sitemap.xml',
            'action'  => function () {
                // Auf Stage und lokal gibt es keine Sitemap (Fehlerseite)
                if (option('staging') === true) {
                    return false;
                }

                $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                     . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
                $ohne   = option('eonm.seo.sitemap.ohne');
                $seiten = site()->index()->filter(
                    fn ($seite) => $seite->seoIndex() && !in_array($seite->intendedTemplate()->name(), $ohne, true)
                );
                foreach ($seiten as $seite) {
                    $xml .= '  <url><loc>' . esc($seite->url(), 'xml') . '</loc>'
                          . '<lastmod>' . $seite->modified('Y-m-d') . '</lastmod></url>' . "\n";
                }
                $xml .= '</urlset>' . "\n";

                return new Response($xml, 'application/xml');
            },
        ],
    ],
]);
