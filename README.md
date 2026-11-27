# eonm/seo

SEO-Grundausstattung fuer Kirby 5 (PHP 8.2+): Meta-Tags, Reiter "SEO" im
Panel, `sitemap.xml`, `robots.txt`, Strukturdaten (JSON-LD) und Alt-Texte
beim Bild-Upload ueber die Claude-API. Haengt an keinem anderen Plugin.

## Einbau in ein Projekt

1. Installieren. Das Paket liegt nicht auf Packagist, deshalb zuerst das
   Repo in der `composer.json` des Projekts bekannt machen:

   ```bash
   composer config repositories.eonm-seo vcs https://github.com/eyes-on-media/kirby-eonm-seo
   composer config allow-plugins.php-http/discovery false
   composer require eyes-on-media/kirby-eonm-seo
   ```

   Das Plugin landet in `site/plugins/seo`; der Ordner gehoert in
   `.gitignore` (`/site/plugins/seo/`). Aktualisieren:
   `composer update eyes-on-media/kirby-eonm-seo`.

2. Snippets einbinden:

   ```php
   <?php snippet('seo/kopf') ?>            // im <head>, statt eigenem <title>
   <?php snippet('seo/strukturdaten') ?>   // vor </body>
   ```

3. Reiter in die Blueprints:

   ```yaml
   # jede Seiten-Vorlage
   tabs:
     seo: tabs/seo
   # site/blueprints/site.yml
   tabs:
     seo: tabs/seo-site
   ```

4. Stage und lokal kennzeichnen: `'staging' => true` in der Konfiguration
   des jeweiligen Hosts (`config.localhost.php`, `config.<stage-host>.php`).
   Dort steht ueberall noindex, `robots.txt` sperrt alles, die Sitemap fehlt.

## Was das Projekt mitbringen muss

| Wofuer | Was | Wenn es fehlt |
|---|---|---|
| Alt-Texte | Datei-Blueprint mit Feld `alt` | kein Vorschlag fuer diese Dateien |
| Vorschaubild | Bildfelder der Seite, siehe Option `bildfelder` | Bild aus den Einstellungen der Website |
| Strukturdaten | Felder `firma`, `adresse`, `telefon`, `email`, `logo` in `site.yml` | ohne `firma` keine Ausgabe, der Rest ist freiwillig |

## Betrieb in den Strukturdaten

Die Firma geht als `LocalBusiness` an die Suchmaschinen (Option `betrieb`,
genauer z.B. `Bakery`, `Brewery`, `HomeAndConstructionBusiness`; ohne festen
Standort `Organization`).

- **Anschrift:** Feld `adresse` als Textfeld, Strasse in der ersten Zeile,
  "PLZ Ort" in der letzten. Das Plugin zerlegt sie selbst. Hat ein Projekt
  eigene Felder `strasse`, `plz`, `ort`, gewinnen die.
- **Oeffnungszeiten:** Feld aus dem Plugin, in `site.yml`:

  ```yaml
  oeffnungszeiten: fields/seo-oeffnungszeiten
  ```

  Anzeige auf der Website: `$site->oeffnungszeitenZeilen()` gibt je Zeile
  `['tage' => 'Mo–Fr', 'zeit' => '08:00–17:00 Uhr']`.
- **Profile in sozialen Netzwerken** (`sameAs`): Felder aus dem Plugin,

  ```yaml
  profile: fields/seo-profile    # instagram, facebook, youtube, linkedin, tiktok
  ```

  oder eigene Felder mit anderen Namen ueber die Option `profile`.

## Optionen

```php
// site/config/config.php
'eonm.seo.locale'       => 'de_DE',                       // og:locale
'eonm.seo.trenner'      => ' - ',                         // Seitentitel - Website
'eonm.seo.bildfelder'   => ['hero_image', 'card_bild'],   // Vorschaubild, in dieser Reihenfolge
'eonm.seo.sitemap.ohne' => [],                            // Vorlagen ohne Sitemap-Eintrag
'eonm.seo.logo'         => 'assets/img/logo.svg',         // Logo, wenn keins hochgeladen ist
'eonm.seo.betrieb'      => 'LocalBusiness',               // Art des Betriebs bei schema.org
'eonm.seo.land'         => 'DE',                          // Land der Anschrift
'eonm.seo.profile'      => ['instagram', 'facebook', 'youtube', 'linkedin', 'tiktok'],
'eonm.seo.favicon'      => ['favicon.ico', 'assets/img/favicon.svg'],  // fuer die Vorschau im Panel
```

Werte in Templates nur ueber die Seitenmethoden: `$page->seoTitel()`,
`seoBeschreibung()`, `seoBild()`, `seoIndex()`.

## Was sonst dazugehoert

- **Brotkrumen-Pfad:** `seo/strukturdaten` gibt zu jeder Seite ausser der
  Startseite den Pfad aus (Startseite > Elternseiten > Seite), aus der
  Seitenstruktur. Suchmaschinen zeigen ihn im Treffer statt der Adresse.
- **Vorschau im Panel:** Im Reiter "SEO" stehen links die Felder, rechts
  zwei getrennte Karten: der Treffer in der Google-Suche (Adresse, Titel,
  Beschreibung) und die Karte beim Teilen (Bild, Domain, Titel,
  Beschreibung). Beide laufen beim Tippen mit. Hinweise bei zu langem
  Titel, zu langer Beschreibung und verborgenen Seiten. Das Feld
  `seovorschau` steht schon in `tabs/seo` und `tabs/seo-site`.

## Alt-Texte

Laedt die Redaktion ein Bild hoch oder ersetzt eins, schlaegt Claude einen
Alt-Text vor und traegt ihn ins Feld `alt` ein. Beim Upload bleibt ein
vorhandener Text stehen, beim Ersetzen kommt ein neuer Vorschlag. Vorschlaege
ueber 125 Zeichen werden einmal gekuerzt. Ein Fehler laesst den Upload
trotzdem durchlaufen (Meldung im PHP-Fehlerprotokoll).

Einrichten:

1. `ki.php.example` aus diesem Ordner nach `site/config/ki.php` kopieren
   und den Schluessel eintragen.
2. Kirby liest `ki.php` nicht von selbst. `site/config/config.php` muss die
   Datei einbinden, zum Beispiel so:

   ```php
   $ki = file_exists(__DIR__ . '/ki.php') ? require __DIR__ . '/ki.php' : [];

   return array_merge([
       // ... die Optionen des Projekts
   ], $ki);
   ```

3. `/site/config/ki.php` in `.gitignore` eintragen und auf dem Server von
   Hand anlegen.
4. Deploys, die auf dem Server aufraeumen (rsync oder lftp mit `--delete`),
   muessen `site/config/ki.php` ausnehmen. Sonst loescht der naechste Deploy
   die von Hand angelegte Datei, und die Alt-Texte hoeren still auf.

Ohne die Datei ist die Funktion aus.

```php
'eonm.seo.ki.schluessel' => 'sk-ant-...',
'eonm.seo.ki.modell'     => 'claude-haiku-5-5',   // Standard
'eonm.seo.ki.zeitlimit'  => 20,                   // Sekunden
```

### Alt-Texte nachtragen

Fuer Bilder, die schon vor dem Einrichten hochgeladen waren. Beides fuellt
nur leere Alt-Texte; ein vorhandener Text wird nie ueberschrieben, und
Bilder mit ungespeicherten Aenderungen im Panel werden uebersprungen.

- **Im Panel:** Reiter "SEO" der Website, Feld "Alt-Texte nachtragen"
  (steht schon in `tabs/seo-site`). Zeigt, wie viele Bilder keinen
  Alt-Text haben, beschreibt sie auf Knopfdruck nacheinander und listet
  die Vorschlaege mit Link zum Bild. Laesst sich anhalten; die Seite muss
  waehrend des Laufs offen bleiben. Nur Bilder, die der Benutzer
  bearbeiten darf.
- **Auf der Kommandozeile** (mit [Kirby CLI](https://github.com/getkirby/cli)):

  ```bash
  kirby seo:alt-texte --liste   # nur zeigen, welche Bilder keinen haben
  kirby seo:alt-texte           # Alt-Texte erzeugen
  ```

Bewusst leer gelassene Alt-Texte (dekorative Bilder) bekommen dabei
ebenfalls einen Vorschlag.

Jedes hochgeladene Bild geht verkleinert an Anthropic. Das gehoert in die
Datenschutzerklaerung und ist mit dem Kunden abzusprechen.
