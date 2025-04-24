# Google Merchant XML-Feed für Modified Shop

Dieses PHP-Script erzeugt einen XML-Produktfeed für den **Google Merchant Center**, basierend auf den Produktdaten eines **Modified eCommerce Shopsystems**.

---

## ✅ Funktionsumfang

- Exportiert alle aktiven Produkte als Google-konformes XML-Feed
- Unterstützt:
  - Brutto-Preisberechnung mit fixem Steuersatz (19 %)
  - Sonderpreise (`<g:sale_price>`) und deren Gültigkeit (`<g:sale_price_effective_date>`)
  - Verfügbarkeit (`<g:availability>`) und Lieferdatum (`<g:availability_date>`)
  - Bildpfadkorrektur für `info_images`
- Zugriffsschutz über **Token-URL** (`?token=...`)

---

## 📁 Installation

1. Lege die Datei `google_feed.php` im **Root-Verzeichnis deines Modified Shops** ab:
`/dein-shop-root/google_feed.php`


2. Rufe das Feed im Browser oder im Google Merchant Center so auf:
`https://www.deinshop.de/google_feed.php?token=geheim123`



3. Trage diese URL im **Google Merchant Center** unter:
- **Produkte > Feeds > Neuer Feed > Geplanter Abruf**
- Format: **XML**

---

## ⚠️ Hinweise & Einschränkungen

> **Hinweis:**  
> Dieses Script erzeugt den Feed **dynamisch bei jedem Aufruf**.  
> **Für größere Shops** oder bei häufigem Abruf durch Google wird empfohlen, den Feed **als Datei zu erzeugen und zwischenzuspeichern**, z.B.:

```php
file_put_contents('/pfad/zum/feed/google.xml', $feedContent);

```

Vorteile:

- Besser skalierbar

- Kontrollierter Abruf (z.B. per Cronjob)

- Schnellere Antwortzeiten

## 🔐 Sicherheit
Der Zugriff auf das Feed ist geschützt durch ein einfaches Token:

``` php
if ($_GET['token'] !== 'geheim123') die('Access denied');

```

Du kannst den Token regelmäßig ändern, um den Zugriff einzuschränken.


## 📦 Empfohlene Erweiterungen
- brand, mpn, gtin (für Produkterkennung)
- product_type, google_product_category
- shipping-Informationen
- Unterstützung für Varianten (Farben, Größen etc.)