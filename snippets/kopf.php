<?php
/**
 * Seitenkopf fuer Suchmaschinen und Teilen-Vorschau: <title>, Beschreibung,
 * Robots, Canonical, Open Graph. Einbinden im <head>:
 *   <?php snippet('seo/kopf') ?>
 *
 * Die Werte kommen aus den Seitenmethoden des Plugins (seoTitel() usw.),
 * damit Sitemap und Kopf dieselbe Logik nutzen. Auf Stage und lokal
 * (option staging) steht immer noindex.
 *
 * Vorschaubild: 1200 x 630 als JPG, zugeschnitten um den Fokuspunkt. Das ist
 * das Format, das Netzwerke und Messenger erwarten; WebP und AVIF zeigen
 * nicht alle an. Vektorgrafiken lassen sich nicht rechnen und fallen weg.
 */
$beschreibung = $page->seoBeschreibung();
$bild         = $page->seoBild();
$vorschau     = $bild?->isResizable()
    ? $bild->thumb(['width' => 1200, 'height' => 630, 'crop' => true, 'format' => 'jpg', 'quality' => 82])
    : null;
$index        = $page->seoIndex();
?>
<title><?= esc($page->seoTitel()) ?></title>
<?php if ($beschreibung): ?>
  <meta name="description" content="<?= esc($beschreibung) ?>">
<?php endif ?>
  <meta name="robots" content="<?= $index ? 'index,follow' : 'noindex,nofollow' ?>">
<?php if ($index): ?>
  <link rel="canonical" href="<?= esc($page->url()) ?>">
<?php endif ?>
  <meta property="og:type" content="website">
  <meta property="og:locale" content="<?= esc(option('eonm.seo.locale')) ?>">
  <meta property="og:site_name" content="<?= esc($site->title()->value()) ?>">
  <meta property="og:title" content="<?= esc($page->seoTitel(false)) ?>">
  <meta property="og:url" content="<?= esc($page->url()) ?>">
<?php if ($beschreibung): ?>
  <meta property="og:description" content="<?= esc($beschreibung) ?>">
<?php endif ?>
<?php if ($vorschau): ?>
  <meta property="og:image" content="<?= esc($vorschau->url()) ?>">
  <meta property="og:image:width" content="<?= (int) $vorschau->width() ?>">
  <meta property="og:image:height" content="<?= (int) $vorschau->height() ?>">
  <meta name="twitter:card" content="summary_large_image">
<?php endif ?>
