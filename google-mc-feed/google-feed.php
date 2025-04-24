<?php
/**
 * Google Merchant Produktfeed
 *
 * Erstellt ein XML-Feed im Google Shopping-kompatiblen Format mit Preis, Sonderpreis,
 * Verfügbarkeit, optionalem Verfügbarkeitsdatum und Sicherheits-Token-Schutz.
 *
 * Features:
 * - Unterstützung für reguläre und Sonderpreise
 * - Ausgabe von sale_price_effective_date
 * - Dynamische Verfügbarkeit und optional availability_date
 * - Bildpfad-Anpassung an Shopstruktur
 * - Token-basierte Zugriffsbeschränkung
 *
 * @author     hotfix
 * @copyright  hotfix
 * @version    1.0
 * @date       2025-04-23
 * @license    MIT 
 *
 * Hinweise:
 * - Feed-URL z. B. https://www.deinshop.de/google_feed.php?token=geheim123
 * - Wird für Google Shopping als „geplanter Abruf“ genutzt
 * - Erfordert gültige Produktdaten in der Datenbank
 */
if (!isset($_GET['token']) || $_GET['token'] !== 'geheim123') {
    die('Access denied');
}

require('includes/application_top.php');
header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
echo '<channel>';
echo '<title>Mein Shop</title>';
echo '<link>' . HTTP_SERVER . '</link>';
echo '<description>Produktfeed für Google</description>';

$products_query = xtc_db_query("
  SELECT p.products_id, pd.products_name, pd.products_description,
         p.products_price, p.products_image, p.products_quantity,
         p.products_date_available
  FROM " . TABLE_PRODUCTS . " p
  JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id = pd.products_id
  WHERE p.products_status = 1 AND pd.language_id = 2
");

while ($p = xtc_db_fetch_array($products_query)) {
    $availability = ($p['products_quantity'] > 0) ? 'in stock' : 'out of stock';

    // Fester Steuersatz (19 %)
    $tax_rate = 19.0;

    $price_regular_net = $p['products_price'];
    $price_regular_gross = $price_regular_net * (1 + $tax_rate / 100);

    $image_url = HTTP_SERVER . DIR_WS_CATALOG . 'images/product_images/info_images/' . $p['products_image'];
    $condition = 'new';

    // Sonderpreis abfragen
    $special = xtc_db_fetch_array(xtc_db_query("
      SELECT 
        specials_old_products_price, specials_new_products_price,
        start_date, expires_date 
      FROM " . TABLE_SPECIALS . "
      WHERE products_id = " . (int)$p['products_id'] . " 
        AND status = 1
      LIMIT 1
    "));

    echo '<item>';
    echo '<g:id>' . $p['products_id'] . '</g:id>';
    echo '<title>' . htmlspecialchars($p['products_name']) . '</title>';
    echo '<description>' . htmlspecialchars(strip_tags($p['products_description'])) . '</description>';
    echo '<link>' . xtc_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $p['products_id']) . '</link>';
    echo '<g:image_link>' . htmlspecialchars($image_url) . '</g:image_link>';

    if ($special) {
        // Sonderpreise verwenden
        $old_brutto = $special['specials_old_products_price'] * (1 + $tax_rate / 100);
        $new_brutto = $special['specials_new_products_price'] * (1 + $tax_rate / 100);

        echo '<g:price>' . number_format($old_brutto, 2, '.', '') . ' EUR</g:price>';
        echo '<g:sale_price>' . number_format($new_brutto, 2, '.', '') . ' EUR</g:sale_price>';

        // Gültigkeitszeitraum
        if (!empty($special['start_date']) && !empty($special['expires_date'])) {
            $start_iso = date('Y-m-d\TH:i:sO', strtotime($special['start_date']));
            $end_iso = date('Y-m-d\TH:i:sO', strtotime($special['expires_date']));
            echo '<g:sale_price_effective_date>' . $start_iso . '/' . $end_iso . '</g:sale_price_effective_date>';
        }
    } else {
        // Kein Sonderpreis → normaler Brutto-Preis
        echo '<g:price>' . number_format($price_regular_gross, 2, '.', '') . ' EUR</g:price>';
    }

    echo '<g:condition>' . $condition . '</g:condition>';
    echo '<g:availability>' . $availability . '</g:availability>';

    if (!empty($p['products_date_available']) && strtotime($p['products_date_available']) > time()) {
        $date_iso = date('Y-m-d\TH:i:sO', strtotime($p['products_date_available']));
        echo '<g:availability_date>' . $date_iso . '</g:availability_date>';
    }

    echo '</item>';
}

echo '</channel>';
echo '</rss>';
