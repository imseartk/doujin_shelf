<?php

namespace App\Libraries;

use DOMDocument;
use DOMXPath;

final class ShopProductReader
{
    public static function identify(string $url): ?array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);
        foreach (['product_id', 'itemCode', 'tenpo_cd'] as $key) {
            if (isset($query[$key]) && ! is_string($query[$key])) return null;
        }
        if (in_array($host, ['www.melonbooks.co.jp', 'melonbooks.co.jp'], true)
            && $path === '/detail/detail.php' && ctype_digit((string) ($query['product_id'] ?? ''))) {
            return ['site' => 'Melonbooks', 'key' => 'melon:' . $query['product_id'], 'host' => $host];
        }
        if (in_array($host, ['www.suruga-ya.jp', 'suruga-ya.jp'], true)
            && preg_match('~^/product/detail/([A-Za-z0-9]+)$~D', $path, $match)) {
            return ['site' => '駿河屋', 'key' => 'suruga:' . $match[1] . ':' . ($query['tenpo_cd'] ?? ''), 'host' => $host];
        }
        if ($host === 'order.mandarake.co.jp' && $path === '/order/detailPage/item'
            && ctype_digit((string) ($query['itemCode'] ?? ''))) {
            return ['site' => 'Mandarake', 'key' => 'mandarake:' . $query['itemCode'], 'host' => $host];
        }
        return null;
    }

    public function fetch(string $url, string $shopName = ''): array
    {
        $identity = self::identify($url);
        if (! $identity) return ['status' => 'unsupported'];
        // Pin a public address; no arbitrary hosts, proxies, or unchecked redirects.
        $addresses = gethostbynamel($identity['host']) ?: [];
        if (! $addresses) return ['status' => 'network'];
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return ['status' => 'network'];
            }
        }
        $html = '';
        $curl = curl_init(preg_replace('~^http:~i', 'https:', $url));
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }
        curl_setopt_array($curl, [
            CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$identity['host'] . ':443:' . $addresses[0]],
            CURLOPT_USERAGENT => 'PersonalDoujinHelper/1.0 (manual product availability check)',
            CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: ja'],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$html): int {
                if (strlen($html) + strlen($chunk) > 4 * 1024 * 1024) return 0;
                $html .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($ok === false) return ['status' => 'network'];
        if ($code === 429) return ['status' => 'rate_limited'];
        if ($code >= 300 && $code < 400) return ['status' => 'redirect'];
        if (in_array($code, [401, 403], true)) {
            return ['status' => 'verification'];
        }
        if ($code !== 200) return ['status' => 'http_error'];
        return $this->parse($html, $url, $shopName);
    }

    public function parse(string $html, string $url, string $shopName = ''): array
    {
        $identity = self::identify($url);
        if (! $identity) return ['status' => 'unsupported'];
        if (strlen($html) > 4 * 1024 * 1024) return ['status' => 'unknown'];
        if (! mb_check_encoding($html, 'UTF-8')) {
            $encoding = mb_detect_encoding($html, ['SJIS-win', 'EUC-JP'], true);
            if (! $encoding) return ['status' => 'unknown'];
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        }
        if (preg_match('~cf-chl-|_cf_chl_opt|g-recaptcha|h-captcha|年齢認証|年齢確認~u', $html)) {
            return ['status' => 'verification'];
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $matches = false;
        foreach ($xpath->query('//link[@rel="canonical"]/@href | //meta[@property="og:url"]/@content') as $node) {
            if ((self::identify($node->nodeValue)['key'] ?? null) === $identity['key']) $matches = true;
        }
        // Saved pages must prove they describe this exact product, including the selected seller.
        if (! $matches) return ['status' => 'identity'];
        if ($identity['site'] === '駿河屋' && str_ends_with($identity['key'], ':')
            && $shopName !== '駿河屋本館') return ['status' => 'seller'];
        if ($identity['site'] === 'Melonbooks') {
            $priceNodes = $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," item-meta3 ")]//*[contains(concat(" ",normalize-space(@class)," ")," price--value ")]');
            $stockNodes = $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," product-info__inventory-status__text ")]');
            $prices = [];
            foreach ($priceNodes as $node) {
                $price = $this->price($node->textContent);
                if ($price !== null) $prices[] = $price;
            }
            $stocks = [];
            foreach ($stockNodes as $node) $stocks[] = trim($node->textContent);
            $stocks = array_values(array_unique($stocks));
            $prices = array_values(array_unique($prices));
            if (count($stocks) === 1) {
                if (in_array($stocks[0], ['在庫なし', '品切れ', '売り切れ', '売切れ'], true)) return ['status' => 'unavailable'];
                if (in_array($stocks[0], ['在庫あり', '在庫有り', '残りわずか'], true) && count($prices) === 1) {
                    return ['status' => 'available', 'price' => $prices[0]];
                }
            }
        }
        // Never use a page-wide price/stock regex: recommendations and marketplace offers differ.
        $offers = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $json = json_decode($node->textContent, true);
            if (is_array($json)) $this->productOffers($json, $identity['key'], $offers, $shopName);
        }
        $offers = array_values(array_unique($offers, SORT_REGULAR));
        return count($offers) === 1 ? $offers[0] : ['status' => 'unknown'];
    }

    private function productOffers(array $data, string $key, array &$results, string $shopName): void
    {
        if (($data['@type'] ?? '') === 'Product') {
            $productKey = self::identify((string) ($data['url'] ?? ''))['key'] ?? null;
            if ($productKey === $key) {
                $offers = $data['offers'] ?? [];
                if (isset($offers['@type'])) $offers = [$offers];
                foreach ($offers as $offer) {
                    if (! is_array($offer) || ($offer['@type'] ?? '') !== 'Offer') continue;
                    // Require the offer URL too: do not update a seller from another shop's offer.
                    if ((self::identify((string) ($offer['url'] ?? ''))['key'] ?? null) !== $key) continue;
                    if (str_starts_with($key, 'mandarake:')) {
                        $seller = $offer['seller']['name'] ?? '';
                        $normalize = static fn (string $name): string => preg_replace('/[\s　]+/u', '', str_ireplace(['まんだらけ', 'Mandarake'], '', $name));
                        if ($shopName === '' || ! is_string($seller) || $normalize($seller) !== $normalize($shopName)) continue;
                    }
                    if (($offer['priceCurrency'] ?? '') !== 'JPY') continue;
                    $availability = preg_replace('~^https?://schema.org/~', '', (string) ($offer['availability'] ?? ''));
                    if ($availability === 'OutOfStock' || $availability === 'SoldOut') $results[] = ['status' => 'unavailable'];
                    if ($availability === 'InStock') {
                        $price = $this->price((string) ($offer['price'] ?? ''));
                        if ($price !== null) $results[] = ['status' => 'available', 'price' => $price];
                    }
                }
            }
            return;
        }
        foreach ($data as $value) if (is_array($value)) $this->productOffers($value, $key, $results, $shopName);
    }

    private function price(string $text): ?int
    {
        $text = preg_replace('/[\s,\x{00a0}]/u', '', $text);
        if (! preg_match('/^([0-9]{1,8})(?:\.00)?$/D', $text, $match)) return null;
        return (int) $match[1];
    }
}
