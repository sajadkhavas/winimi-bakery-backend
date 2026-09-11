<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class SafeContentHtml
{
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'div', 'span', 'section', 'article', 'main', 'header', 'footer',
        'a', 'ul', 'ol', 'li',
        'strong', 'b', 'em', 'i', 'u', 's', 'del', 'mark', 'small', 'sup', 'sub',
        'blockquote', 'br', 'hr',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'figure', 'figcaption', 'img',
        'details', 'summary',
        'code', 'pre',
    ];

    private const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed',
        'form', 'input', 'textarea', 'select', 'option', 'button',
        'link', 'meta', 'base', 'svg', 'math', 'video', 'audio', 'canvas', 'noscript',
    ];

    private const HURDLE_COLORS = [
        'gray_light', 'gray', 'gray_dark', 'primary', 'secondary', 'tertiary', 'accent',
    ];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><html><body>'.$html.'</body></html>',
            LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if ($loaded === false) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return strip_tags($html);
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if (! $body instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return strip_tags($html);
        }

        foreach (self::children($body) as $child) {
            self::sanitizeNode($child);
        }

        $result = '';
        foreach (self::children($body) as $child) {
            $result .= $document->saveHTML($child) ?: '';
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return trim($result);
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        if ($node->nodeType === XML_COMMENT_NODE) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::DROP_TAGS, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        foreach (self::children($node) as $child) {
            self::sanitizeNode($child);
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            self::unwrap($node);

            return;
        }

        self::sanitizeAttributes($node, $tag);
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }

        foreach ($attributes as $name => $value) {
            $normalized = strtolower($name);

            if (str_starts_with($normalized, 'on')) {
                $element->removeAttribute($name);

                continue;
            }

            $keep = match ($normalized) {
                'dir' => in_array(strtolower(trim($value)), ['rtl', 'ltr', 'auto'], true),
                'style' => self::sanitizeStyleAttribute($element, $value),
                'href' => $tag === 'a' && self::sanitizeHrefAttribute($element, $value),
                'target' => $tag === 'a' && in_array($value, ['_blank', '_self'], true),
                'rel' => $tag === 'a',
                'title' => in_array($tag, ['a', 'img'], true),
                'src' => $tag === 'img' && self::isSafeResourceUrl($value),
                'alt' => $tag === 'img',
                'width', 'height' => $tag === 'img' && self::isSafeDimension($value),
                'loading' => $tag === 'img' && in_array($value, ['lazy', 'eager'], true),
                'decoding' => $tag === 'img' && in_array($value, ['async', 'auto', 'sync'], true),
                'colspan', 'rowspan' => in_array($tag, ['th', 'td'], true) && self::isSafeSpan($value),
                'start' => $tag === 'ol' && preg_match('/^-?\d{1,6}$/', $value) === 1,
                'open' => $tag === 'details',
                'class' => self::sanitizeClassAttribute($element, $tag, $value),
                'data-color' => $tag === 'div' && in_array($value, self::HURDLE_COLORS, true),
                'data-type' => in_array($tag, ['ul', 'li'], true) && in_array($value, ['taskList', 'taskItem'], true),
                'data-checked' => $tag === 'li' && in_array($value, ['true', 'false'], true),
                default => false,
            };

            if (! $keep) {
                $element->removeAttribute($name);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        } elseif ($tag === 'a' && $element->hasAttribute('rel')) {
            $element->removeAttribute('rel');
        }

        if ($tag === 'img' && ! $element->hasAttribute('src')) {
            $element->parentNode?->removeChild($element);
        }
    }

    private static function sanitizeStyleAttribute(DOMElement $element, string $style): bool
    {
        $safe = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $value = trim($value);

            if ($property === 'text-align' && in_array(strtolower($value), ['left', 'right', 'center', 'justify', 'start', 'end'], true)) {
                $safe[] = 'text-align: '.strtolower($value);

                continue;
            }

            if (in_array($property, ['color', 'background-color'], true) && self::isSafeColor($value)) {
                $safe[] = $property.': '.$value;
            }
        }

        if ($safe === []) {
            $element->removeAttribute('style');

            return false;
        }

        $element->setAttribute('style', implode('; ', $safe));

        return true;
    }

    private static function sanitizeHrefAttribute(DOMElement $element, string $value): bool
    {
        $href = trim($value);
        if ($href === '' || ! self::isSafeLinkUrl($href)) {
            $element->removeAttribute('href');

            return false;
        }

        $element->setAttribute('href', $href);

        return true;
    }

    private static function sanitizeClassAttribute(DOMElement $element, string $tag, string $value): bool
    {
        $tokens = preg_split('/\s+/', trim($value)) ?: [];
        $allowed = [];

        foreach ($tokens as $token) {
            if ($tag === 'div' && $token === 'filament-tiptap-hurdle') {
                $allowed[] = $token;
            }

            if ($tag === 'p' && $token === 'lead') {
                $allowed[] = $token;
            }
        }

        if ($allowed === []) {
            $element->removeAttribute('class');

            return false;
        }

        $element->setAttribute('class', implode(' ', array_values(array_unique($allowed))));

        return true;
    }

    private static function isSafeLinkUrl(string $value): bool
    {
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return true;
        }

        if (str_starts_with($value, '#')) {
            return preg_match('/^#[A-Za-z0-9\-_.:%]+$/u', $value) === 1;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    private static function isSafeResourceUrl(string $value): bool
    {
        $value = trim($value);
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return $scheme === 'https';
    }

    private static function isSafeColor(string $value): bool
    {
        $value = trim($value);

        if (preg_match('/^#[0-9a-f]{3,8}$/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^rgba?\([0-9.,%\s\/]+\)$/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^hsla?\([0-9.,%\s\/deg]+\)$/i', $value) === 1) {
            return true;
        }

        return in_array(strtolower($value), ['transparent', 'currentcolor'], true);
    }

    private static function isSafeDimension(string $value): bool
    {
        return preg_match('/^[1-9]\d{0,3}$/', $value) === 1 && (int) $value <= 6000;
    }

    private static function isSafeSpan(string $value): bool
    {
        return preg_match('/^[1-9]\d?$/', $value) === 1 && (int) $value <= 50;
    }

    /** @return list<DOMNode> */
    private static function children(DOMNode $node): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        return $children;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        foreach (self::children($element) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }
}
