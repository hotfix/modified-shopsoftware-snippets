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
 * - Feed-URL z.B. https://www.deinshop.de/google_feed.php?token=geheim123
 * - Wird für Google Shopping als „geplanter Abruf“ genutzt
 * - Erfordert gültige Produktdaten in der Datenbank
 */

define('MERCHANT_TAX_RATE', 19.0); // Fester MwSt-Satz

// --- Sicherheit ---
if (!isset($_GET['token']) || $_GET['token'] !== 'geheim123') {
    die('Access denied');
}

require('includes/application_top.php');
header("Content-Type: application/xml; charset=utf-8");

// --- Hilfsfunktionen ---
function getBruttoPrice($netto, $taxRate = MERCHANT_TAX_RATE) {
    return $netto * (1 + $taxRate / 100);
}

function getSpecialInfo($productId) {
    $q = xtc_db_query("
        SELECT specials_old_products_price, specials_new_products_price,
               start_date, expires_date 
        FROM " . TABLE_SPECIALS . "
        WHERE products_id = " . (int)$productId . " AND status = 1
        LIMIT 1
    ");
    if ($s = xtc_db_fetch_array($q)) {
        return [
            'price_old'  => getBruttoPrice($s['specials_old_products_price']),
            'price_new'  => getBruttoPrice($s['specials_new_products_price']),
            'start'      => $s['start_date'],
            'end'        => $s['expires_date'],
        ];
    }
    return null;
}

function formatISODate($dateString) {
    return date('Y-m-d\TH:i:sO', strtotime($dateString));
}

function xmlTag($tag, $value, $ns = 'g') {
    if ($value === null || $value === '') return '';
    return "<{$ns}:{$tag}>" . htmlspecialchars($value) . "</{$ns}:{$tag}>";
}

// --- Start XML ---
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
echo '<channel>';
echo xmlTag('title', 'Mein Shop');
echo xmlTag('link', HTTP_SERVER);
echo xmlTag('description', 'Produktfeed für Google');

// --- Produktabfrage ---
$products_query = xtc_db_query("
  SELECT p.products_id, pd.products_name, pd.products_description,
         p.products_price, p.products_image, p.products_quantity,
         p.products_date_available, p.products_ean, p.products_manufacturers_model
  FROM " . TABLE_PRODUCTS . " p
  JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id = pd.products_id
  WHERE p.products_status = 1 AND pd.language_id = 2
");

while ($p = xtc_db_fetch_array($products_query)) {
    $availability = ($p['products_quantity'] > 0) ? 'in stock' : 'out of stock';
    $base_price = getBruttoPrice($p['products_price']);
    $image_url = HTTP_SERVER . DIR_WS_CATALOG . 'images/product_images/info_images/' . $p['products_image'];
    $condition = 'new';
    $special = getSpecialInfo($p['products_id']);

    echo '<item>';
    echo xmlTag('id', $p['products_id']);
    echo xmlTag('title', $p['products_name']);
    echo xmlTag('description', strip_tags($p['products_description']));
    echo xmlTag('link', xtc_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $p['products_id']));
    echo xmlTag('image_link', $image_url);
    echo xmlTag('price', number_format($special ? $special['price_old'] : $base_price, 2, '.', '') . ' EUR');

    if ($special) {
        echo xmlTag('sale_price', number_format($special['price_new'], 2, '.', '') . ' EUR');
        if ($special['start'] && $special['end']) {
            echo xmlTag('sale_price_effective_date', formatISODate($special['start']) . '/' . formatISODate($special['end']));
        }
    }

    echo xmlTag('condition', $condition);
    echo xmlTag('availability', $availability);

    if (!empty($p['products_date_available']) && strtotime($p['products_date_available']) > time()) {
        echo xmlTag('availability_date', formatISODate($p['products_date_available']));
    }

    if (!empty($p['products_ean'])) {
        echo xmlTag('gtin', $p['products_ean']);
    }

    if (!empty($p['products_manufacturers_model'])) {
        echo xmlTag('mpn', $p['products_manufacturers_model']);
    }

    echo '</item>';
}

echo '</channel>';
echo '</rss>';
