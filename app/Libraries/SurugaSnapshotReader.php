<?php

namespace App\Libraries;

final class SurugaSnapshotReader
{
    private static function hasClass(array $node, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', trim((string) ($node['class'] ?? ''))), true);
    }

    private static function ancestor(array $node, callable $matches): bool
    {
        foreach ($node['ancestors'] ?? [] as $parent) {
            if (is_array($parent) && $matches($parent)) return true;
        }
        return false;
    }

    public function parse(array $snapshot, string $url, string $shopName): array
    {
        $unknown = static fn (string $reason): array => ['status' => 'unknown', 'reason' => $reason];
        $identity = ShopProductReader::identify($url);
        if (! $identity || ! str_starts_with($identity['key'], 'suruga:')) return ['status' => 'unsupported'];
        if (($snapshot['challenge'] ?? false) === true) return ['status' => 'verification'];
        if (($snapshot['schemaVersion'] ?? 0) < 2) return $unknown('old_snapshot');
        foreach (['url', 'canonical'] as $field) {
            if ((ShopProductReader::identify((string) ($snapshot[$field] ?? ''))['key'] ?? null) !== $identity['key']) {
                return ['status' => 'identity'];
            }
        }
        // Marketplace/store stock requires independent seller evidence, not the main-shop DOM.
        if ($shopName !== "\u{99ff}\u{6cb3}\u{5c4b}\u{672c}\u{9928}" || ! str_ends_with($identity['key'], ':')) {
            return ['status' => 'seller'];
        }
        $cart = $snapshot['suruga'] ?? [];
        $prices = [];
        foreach ($cart['prices'] ?? [] as $node) {
            if (($node['visible'] ?? false) !== true || ! self::hasClass($node, 'price-buy')) continue;
            if (! self::ancestor($node, static fn ($p) => self::hasClass($p, 'selected_price'))
                || ! self::ancestor($node, static fn ($p) => ($p['id'] ?? '') === 'cart')) continue;
            if (preg_match('/^([0-9]{1,8}|[0-9]{1,3}(?:,[0-9]{3}){1,2})\s*\x{5186}(?:\s*[\x{ff08}(]\x{7a0e}\x{8fbc}[\x{ff09})])?$/u', trim((string) ($node['text'] ?? '')), $match)) {
                $number = str_replace(',', '', $match[1]);
                if (strlen($number) <= 8) $prices[] = (int) $number;
            }
        }
        $prices = array_values(array_unique($prices));
        $active = false;
        foreach ($cart['buttons'] ?? [] as $node) {
            if (($node['tag'] ?? '') !== 'button' || ! self::hasClass($node, 'btn_buy') || ! self::hasClass($node, 'cart1')) continue;
            if (! self::ancestor($node, static fn ($p) => ($p['tag'] ?? '') === 'form' && self::hasClass($p, 'add-cart'))) continue;
            if (($node['visible'] ?? null) === true && ($node['disabled'] ?? null) === false
                && ($node['ariaDisabled'] ?? null) === false && ($node['classDisabled'] ?? null) === false
                && isset($node['pointerEvents']) && $node['pointerEvents'] !== 'none'
                && trim((string) ($node['text'] ?? '')) === "\u{30ab}\u{30fc}\u{30c8}\u{306b}\u{5165}\u{308c}\u{308b}") $active = true;
        }
        $soldOut = false;
        foreach ($cart['soldOut'] ?? [] as $node) {
            if (($node['visible'] ?? false) === true && self::hasClass($node, 'out-of-stock-text')
                && self::ancestor($node, static fn ($p) => ($p['id'] ?? '') === 'cart')
                && preg_match('/^(?:\x{7533}\x{3057}\x{8a33}\x{3054}\x{3056}\x{3044}\x{307e}\x{305b}\x{3093}\x{3002}\s*)?\x{54c1}\x{5207}\x{308c}\x{4e2d}\x{3067}\x{3059}\x{3002}?$/u', trim((string) ($node['text'] ?? '')))) $soldOut = true;
        }
        $states = [];
        $offerPrices = [];
        foreach ($snapshot['structured'] ?? [] as $product) {
            if ((ShopProductReader::identify((string) ($product['url'] ?? ''))['key'] ?? null) !== $identity['key']) continue;
            foreach ($product['offers'] ?? [] as $offer) {
                if (($offer['url'] ?? '') !== '' && (ShopProductReader::identify($offer['url'])['key'] ?? null) !== $identity['key']) continue;
                if (($offer['priceCurrency'] ?? '') !== 'JPY') continue;
                if (preg_match('~^https?://schema.org/(InStock|OutOfStock|SoldOut)$~D', $offer['availability'] ?? '', $match)) {
                    $states[] = $match[1] === 'InStock' ? 'in' : 'out';
                }
                if (preg_match('/^([0-9]{1,8})(?:\.00)?$/D', (string) ($offer['price'] ?? ''), $match)) $offerPrices[] = (int) $match[1];
            }
        }
        $states = array_values(array_unique($states));
        $offerPrices = array_values(array_unique($offerPrices));
        if (($active && $soldOut) || count($states) > 1 || ($active && in_array('out', $states, true)) || ($soldOut && in_array('in', $states, true))) {
            return $unknown('conflicting_stock');
        }
        // Missing/disabled buttons alone never establish unavailability.
        if ($soldOut && ! $active && $states === ['out']) return ['status' => 'unavailable', 'reason' => 'main_cart_sold_out'];
        if (! $active || $states !== ['in']) return $unknown('insufficient_stock');
        if (count($prices) !== 1 || $offerPrices !== $prices) return $unknown('ambiguous_price');
        return ['status' => 'available', 'price' => $prices[0], 'reason' => 'active_cart_and_offer'];
    }
}
