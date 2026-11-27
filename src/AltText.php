<?php

namespace Eonm\Seo;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use GuzzleHttp\Client as HttpClient;
use Kirby\Cms\File;
use Kirby\Cms\Files;
use Kirby\Cms\FileVersion;
use Kirby\Filesystem\F;
use Throwable;

/**
 * Alt-Text fuer hochgeladene Bilder ueber die Claude-API.
 *
 * Laeuft in den Hooks file.create:after und file.replace:after (siehe
 * index.php). Fuer Bilder, die schon vorher hochgeladen waren, gibt es das
 * Nachtragen: Feld seoalttexte im Panel und Befehl seo:alt-texte (nur Bilder
 * ohne Alt-Text, siehe nachtragen()). Der Upload darf daran
 * nie scheitern: Jeder Fehler (kein Netz, falscher Schluessel, Zeitlimit,
 * Ablehnung) wird nur protokolliert, das Feld "alt" bleibt dann leer und die
 * Redaktion traegt den Text von Hand ein.
 *
 * Aus ist die Funktion, solange kein Schluessel konfiguriert ist
 * (site/config/ki.php, Vorlage ki.php.example).
 */
class AltText
{
    /** Bildformate, die sich verkleinern und beschreiben lassen (kein SVG, kein GIF) */
    protected const FORMATE = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    /**
     * Obergrenze fuer den Alt-Text (Faustregel fuer Screenreader). Der Auftrag
     * verlangt weniger, weil das Modell die Laenge nur ungefaehr trifft; was
     * trotzdem darueber liegt, wird einmal gekuerzt (siehe kuerzen()).
     */
    public const MAX_ZEICHEN = 125;

    protected const AUFTRAG = <<<'TEXT'
        Du schreibst Alt-Texte für Bilder auf deutschsprachigen Websites. Ein Alt-Text
        wird von Screenreadern vorgelesen und von Suchmaschinen gelesen.

        Beschreibe sachlich, was auf dem Bild zu sehen ist: ein Satz auf Deutsch,
        höchstens 110 Zeichen. Beginne nicht mit „Bild von“ oder „Foto von“. Nenne
        keine Namen von Personen und erfinde nichts, was nicht zu sehen ist.

        Dateiname, Seitentitel und Website sind nur Kontext. Nutze sie, wenn sie
        zum Bild passen, etwa um ein Gewerk oder einen Ort richtig zu benennen.

        Antworte ausschließlich mit dem Alt-Text, ohne Anführungszeichen und ohne
        Erklärung.
        TEXT;

    protected const AUFTRAG_KUERZEN = <<<'TEXT'
        Du kürzt Alt-Texte für Bilder auf deutschsprachigen Websites. Kürze den
        folgenden Alt-Text auf höchstens 110 Zeichen: ein Satz auf Deutsch. Behalte
        das Wichtigste, was zu sehen ist, und füge nichts hinzu.

        Antworte ausschließlich mit dem gekürzten Alt-Text, ohne Anführungszeichen
        und ohne Erklärung.
        TEXT;

    /** Ohne API-Schluessel ist die Funktion aus */
    public static function aktiv(): bool
    {
        return empty(option('eonm.seo.ki.schluessel')) === false;
    }

    /**
     * Fuer die Hooks: traegt den Alt-Text ein und gibt die aktualisierte Datei
     * zurueck, sonst null (dann bleibt die Datei, wie sie ist).
     *
     * Beim Upload bleibt ein vorhandener Alt-Text unangetastet. Beim Ersetzen
     * eines Bildes ($ersetzt) wird neu beschrieben: Der alte Text gehoert
     * zum alten Bild, und die Redaktion bekommt einen neuen Vorschlag zum
     * Pruefen.
     */
    public static function fuerUpload(File $file, bool $ersetzt = false): ?File
    {
        if (static::aktiv() === false || static::geeignet($file) === false) {
            return null;
        }
        if ($ersetzt === false && $file->alt()->isNotEmpty()) {
            return null;
        }

        $ergebnis = static::eintragen($file);
        return $ergebnis instanceof File ? $ergebnis : null;
    }

    /**
     * Alle Bilder der Website, auch in Entwuerfen und in den Einstellungen
     * der Website, die einen Alt-Text haben koennten, aber keinen haben.
     * Fuer das Nachtragen (Feld seoalttexte im Panel, Befehl seo:alt-texte).
     */
    public static function fehlende(): Files
    {
        $site = kirby()->site();

        return $site->files()
            ->add($site->index(true)->files())
            ->filter(fn (File $file) => static::geeignet($file) && $file->alt()->isEmpty());
    }

    /**
     * Alt-Text fuer ein schon hochgeladenes Bild nachtragen. Ein vorhandener
     * Alt-Text wird nie ueberschrieben, auch kein ungespeicherter im Panel.
     *
     * Gibt ['text' => neuer Alt-Text] oder ['grund' => warum keiner].
     */
    public static function nachtragen(File $file): array
    {
        if (static::aktiv() === false) {
            return ['grund' => 'Kein API-Schlüssel eingerichtet (site/config/ki.php)'];
        }
        if (static::geeignet($file) === false) {
            return ['grund' => 'Kein Bild, das einen Alt-Text bekommt'];
        }
        if ($file->alt()->isNotEmpty()) {
            return ['grund' => 'Hat schon einen Alt-Text'];
        }
        // Jemand bearbeitet das Bild gerade im Panel. Speichert er spaeter,
        // ginge der Vorschlag ohnehin verloren; ausserdem koennte dort schon
        // ein Alt-Text stehen, der nur noch nicht gespeichert ist.
        if ($file->version('changes')->exists('current')) {
            return ['grund' => 'Hat ungespeicherte Änderungen im Panel'];
        }

        $ergebnis = static::eintragen($file);
        return $ergebnis instanceof File
            ? ['text' => $ergebnis->alt()->value()]
            : ['grund' => $ergebnis];
    }

    /**
     * Erzeugt den Alt-Text und speichert ihn. Gibt die aktualisierte Datei
     * zurueck oder den Grund, warum es keinen Text gibt (steht dann auch
     * im PHP-Fehlerprotokoll).
     */
    protected static function eintragen(File $file): File|string
    {
        try {
            $text = static::erzeugen($file, option('eonm.seo.ki.schluessel'));
            if ($text !== null) {
                // Der Text kommt vom System, nicht vom angemeldeten Benutzer
                return $file->kirby()->impersonate('kirby', fn () => $file->update(['alt' => $text]));
            }
            $grund = 'Keine brauchbare Antwort der Claude-API';
        } catch (AuthenticationException $e) {
            $grund = 'API-Schlüssel ungültig (site/config/ki.php)';
        } catch (RateLimitException $e) {
            $grund = 'Anfragelimit der Claude-API erreicht';
        } catch (APIStatusException $e) {
            $grund = 'Claude-API antwortet mit Fehler ' . $e->getMessage();
        } catch (APIConnectionException $e) {
            $grund = 'Claude-API nicht erreichbar oder Zeitlimit überschritten';
        } catch (Throwable $e) {
            $grund = $e->getMessage();
        }

        static::melden($file, $grund);
        return $grund;
    }

    /**
     * Nur Rasterbilder, deren Vorlage ein Feld "alt" hat. Ein Favicon
     * (files/favicon) braucht keine Beschreibung und geht nicht an die API.
     */
    public static function geeignet(File $file): bool
    {
        return $file->type() === 'image'
            && in_array(strtolower($file->extension()), static::FORMATE, true)
            && $file->blueprint()->field('alt') !== null;
    }

    /**
     * Fragt Claude nach einer Beschreibung. null, wenn keine brauchbare
     * Antwort kommt. Wirft bei Verbindungs- und API-Fehlern.
     */
    public static function erzeugen(File $file, string $schluessel): ?string
    {
        // Verkleinerte JPEG-Fassung: Originale aus dem Panel haben oft mehrere
        // Megabyte, fuer die Beschreibung reichen 1568 px an der langen Kante.
        $fassung = $file->thumb(['width' => 1568, 'height' => 1568, 'format' => 'jpg', 'quality' => 80]);
        if ($fassung instanceof FileVersion) {
            $fassung->save();
        }
        $daten = F::read($fassung->root());
        if ($daten === false) {
            return null;
        }

        // Das Zeitlimit setzt der HTTP-Client, das SDK selbst erzwingt keines.
        // Ein Upload soll hoechstens so lange auf die Beschreibung warten.
        $zeitlimit = (float) option('eonm.seo.ki.zeitlimit');
        $client    = new Client(
            apiKey: $schluessel,
            requestOptions: [
                'transporter' => new HttpClient(['timeout' => $zeitlimit, 'connect_timeout' => 5]),
                'maxRetries'  => 1,
            ],
        );

        $seite   = $file->parent();
        $kontext = 'Dateiname: ' . $file->filename() . "\n"
                 . 'Seite: ' . ($seite instanceof \Kirby\Cms\Page ? $seite->title()->value() : 'Einstellungen der Website') . "\n"
                 . 'Website: ' . $file->site()->title()->value();

        $text = static::anfragen($client, static::AUFTRAG, [
            ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => base64_encode($daten)]],
            ['type' => 'text', 'text' => $kontext],
        ]);

        if ($text !== null && mb_strlen($text) > static::MAX_ZEICHEN) {
            $text = static::kuerzen($client, $text);
        }
        return $text;
    }

    /**
     * Zu langer Alt-Text: einmal nachfragen, ohne Bild (der Text reicht dafuer
     * und die Anfrage ist billig). Hart abschneiden waere schlechter als zu
     * lang, deshalb gilt am Ende der kuerzere der beiden Texte, auch wenn er
     * noch ueber der Grenze liegt. Die Redaktion prueft ihn ohnehin.
     */
    protected static function kuerzen(Client $client, string $text): string
    {
        $kurz = static::anfragen($client, static::AUFTRAG_KUERZEN, [
            ['type' => 'text', 'text' => $text],
        ]);

        if ($kurz === null || mb_strlen($kurz) >= mb_strlen($text)) {
            return $text;
        }
        return $kurz;
    }

    /**
     * Eine Anfrage an Claude, Antwort als bereinigter Text. null, wenn keine
     * brauchbare Antwort kommt. Wirft bei Verbindungs- und API-Fehlern.
     */
    protected static function anfragen(Client $client, string $auftrag, array $inhalt): ?string
    {
        $antwort = $client->messages->create(
            model: option('eonm.seo.ki.modell'),
            // Das Modell denkt vor der Antwort kurz nach, das zaehlt mit
            maxTokens: 1024,
            outputConfig: ['effort' => 'low'],
            system: $auftrag,
            messages: [['role' => 'user', 'content' => $inhalt]],
        );

        // Abgelehnt oder abgeschnitten: lieber kein Alt-Text als ein halber
        if ($antwort->stopReason !== 'end_turn') {
            return null;
        }

        foreach ($antwort->content as $block) {
            if ($block->type === 'text') {
                $text = trim(preg_replace('/\s+/', ' ', $block->text), " \t\n\r\"„“”'");
                return $text !== '' ? $text : null;
            }
        }
        return null;
    }

    protected static function melden(File $file, string $grund): void
    {
        error_log('[eonm/seo] Kein Alt-Text fuer ' . $file->id() . ': ' . $grund);
    }
}
