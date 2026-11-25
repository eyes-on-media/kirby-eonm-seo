/**
 * Panel-Feld "seovorschau": Vorschau im Reiter "SEO", zwei getrennte Karten.
 *   1. Treffer in der Google-Suche, aufgebaut wie dort: Favicon, Name der
 *      Website und Adresse, darunter Titel und Beschreibung
 *   2. Karte beim Teilen (Messenger, soziale Netzwerke): Bild, Domain,
 *      Titel ohne den Namen der Website, Beschreibung
 * Beide laufen beim Tippen mit. Die festen Werte (Seitentitel, Vorgaben der
 * Website, gespeichertes Bild) kommen vom Server, siehe src/Vorschau.php.
 */
window.panel.plugin("eonm/seo", {
  fields: {
    seovorschau: {
      props: {
        label: String,
        help: String,
        vorgaben: { type: Object, default: () => ({}) },
        // aktuelle Werte aller Felder des Formulars, auch ungespeicherte
        formData: { type: Object, default: () => ({}) }
      },
      data() {
        // Nummer der Favicon-Adresse, die gerade probiert wird
        return { faviconNr: 0 };
      },
      computed: {
        favicon() {
          return (this.vorgaben.favicons || [])[this.faviconNr] || null;
        },
        website() {
          return this.vorgaben.modus === "website";
        },
        eigenerTitel() {
          return this.text(this.formData.seo_titel);
        },
        titel() {
          if (this.website) {
            return this.vorgaben.titel;
          }
          if (this.eigenerTitel === "" && this.vorgaben.istStart) {
            return this.vorgaben.website;
          }
          return (this.eigenerTitel || this.vorgaben.seitentitel) + this.vorgaben.trenner + this.vorgaben.website;
        },
        // Beim Teilen steht der Name der Website nicht hinter dem Titel
        teilenTitel() {
          if (this.website) {
            return this.vorgaben.teilenTitel;
          }
          return this.eigenerTitel || (this.vorgaben.istStart ? this.vorgaben.website : this.vorgaben.seitentitel);
        },
        beschreibung() {
          const feld = this.text(this.formData.seo_beschreibung);
          return this.website
            ? this.vorgaben.eigeneBeschreibung || feld
            : feld || this.vorgaben.beschreibungWebsite;
        },
        // Das Bild rechnet der Server. Ein neu gewaehltes sieht man erst
        // nach dem Speichern und Neuladen, darauf weist die Karte hin.
        bildGeaendert() {
          if (this.website && this.vorgaben.eigenesBild) {
            return false;
          }
          return JSON.stringify(this.ids(this.formData.seo_bild)) !== JSON.stringify(this.ids(this.vorgaben.bildFeld));
        },
        laengen() {
          const h = [];
          if (this.titel.length > 60) {
            h.push(`Der Titel hat ${this.titel.length} Zeichen. Ab etwa 60 kürzt Google ihn ab.`);
          }
          if (this.beschreibung.length > 160) {
            h.push(`Die Beschreibung hat ${this.beschreibung.length} Zeichen. Ab etwa 160 wird sie abgeschnitten.`);
          }
          return h;
        },
        sichtbarkeit() {
          if (this.vorgaben.staging) {
            return "Das hier ist eine Testumgebung: Suchmaschinen sind für alle Seiten gesperrt.";
          }
          if (this.vorgaben.entwurf) {
            return "Die Seite ist ein Entwurf und für Suchmaschinen noch nicht sichtbar.";
          }
          if (this.website ? this.vorgaben.verborgen : this.formData.seo_noindex === true) {
            return "Die Seite ist vor Suchmaschinen verborgen und fehlt in der Sitemap.";
          }
          return null;
        }
      },
      methods: {
        text(wert) {
          return String(wert ?? "").replace(/\s+/g, " ").trim();
        },
        // Dateifeld: je nach Kirby-Version Kennungen oder Objekte
        ids(wert) {
          return (Array.isArray(wert) ? wert : []).map((d) => (typeof d === "string" ? d : d.uuid || d.id || ""));
        }
      },
      template: `
        <k-field :label="label" :help="help" class="eonm-seo-vorschau">
          <section class="eonm-seo-karte">
            <h3 class="eonm-seo-karte-kopf"><k-icon type="search" /> <span>In der Google-Suche</span></h3>
            <div class="eonm-seo-karte-kasten">
              <div class="eonm-seo-google-kopf">
                <span class="eonm-seo-google-icon">
                  <img v-if="favicon" :src="favicon" alt="" @error="faviconNr++">
                  <k-icon v-else type="globe" />
                </span>
                <div class="eonm-seo-google-seite">
                  <p class="eonm-seo-google-name">{{ vorgaben.website }}</p>
                  <p class="eonm-seo-leise">{{ vorgaben.adresse }}</p>
                </div>
              </div>
              <p class="eonm-seo-google-titel">{{ titel }}</p>
              <p v-if="beschreibung" class="eonm-seo-text">{{ beschreibung }}</p>
              <p v-else class="eonm-seo-leise"><em>Keine Beschreibung eingetragen. Google wählt dann selbst einen Textausschnitt von der Seite.</em></p>
            </div>
            <p v-for="hinweis in laengen" :key="hinweis" class="eonm-seo-hinweis">
              <k-icon type="alert" /> <span>{{ hinweis }}</span>
            </p>
            <p v-if="sichtbarkeit" class="eonm-seo-hinweis">
              <k-icon type="alert" /> <span>{{ sichtbarkeit }}</span>
            </p>
          </section>

          <section class="eonm-seo-karte">
            <h3 class="eonm-seo-karte-kopf"><k-icon type="share" /> <span>Beim Teilen, z. B. in WhatsApp oder auf Facebook</span></h3>
            <div class="eonm-seo-karte-kasten eonm-seo-teilen">
              <img v-if="vorgaben.bild && !bildGeaendert" :src="vorgaben.bild" alt="" class="eonm-seo-teilen-bild">
              <p v-else class="eonm-seo-teilen-ohne">
                <template v-if="bildGeaendert">Vorschaubild geändert. Das neue Bild erscheint hier nach dem Speichern und Neuladen der Seite.</template>
                <template v-else>Kein Vorschaubild. Die Seite erscheint beim Teilen ohne Bild.</template>
              </p>
              <div class="eonm-seo-teilen-text">
                <p class="eonm-seo-leise">{{ vorgaben.domain }}</p>
                <p class="eonm-seo-teilen-titel">{{ teilenTitel }}</p>
                <p v-if="beschreibung" class="eonm-seo-text">{{ beschreibung }}</p>
              </div>
            </div>
          </section>
        </k-field>
      `
    }
  }
});
