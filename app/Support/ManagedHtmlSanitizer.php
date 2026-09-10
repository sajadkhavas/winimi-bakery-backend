<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class ManagedHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 's', 'del',
        'mark', 'small', 'sup', 'sub', 'blockquote', 'br', 'hr', 'code', 'pre',
        'figure', 'figcaption', 'img', 'table', 'thead', 'tbody', 'tr', 'th',
        'td', 'details', 'summary', 'section', 'article',
    ];

    private const DROP_WITH_CONTENT_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'textarea', 'select', 'option', 'button', 'link', 'meta', 'base',
        'svg', 'math', 'video', 'audio', 'canvas', 'noscript',
    ];

    public static function sanitize(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        if (! class_exists(DOMDocument::class)) {
            return strip_tags($html, '<p><div><span><h1><h2><h3><h4><h5><h6><a><ul><ol><li><strong><b><em><i><u><s><del><mark><small><sup><sub><blockquote><br><hr><code><pre><figure><figcaption><img><table><thead><tbody><tr><th><td><details><summary><section><article>');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="__winimi_root__">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
            );

            $root = $document->getElementById('__winimi_root__');
            if (! $root instanceof DOMElement) {
                return null;
            }

            self::sanitizeChildren($root);

            $output = '';
            foreach (iterator_to_array($root->childNodes) as $child) {
                $output .= $document->saveHTML($child) ?: '';
            }

            $output = trim($output);

            return $output === '' ? null : $output;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);
                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT_TAGS, true)) {
                $parent->removeChild($node);
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::unwrap($node);
                continue;
            }

            self::sanitizeAttributes($node, $tag);
            self::sanitizeChildren($node);
        }
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = match ($tag) {
            'a' => ['href', 'target', 'rel', 'title', 'hreflang'],
            'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
            'th', 'td' => ['colspan', 'rowspan', 'style'],
            'p', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote' => ['style'],
            default => [],
        };

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            $value = trim((string) $attribute->value);

            if ($name === 'href' && ! self::safeHref($value)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if ($name === 'src' && ! self::safeImageSrc($value)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if (in_array($name, ['width', 'height', 'colspan', 'rowspan'], true) && preg_match('/^[1-9][0-9]{0,3}$/', $value) !== 1) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if ($name === 'loading' && ! in_array(strtolower($value), ['lazy', 'eager'], true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if ($name === 'target') {
                if ($value !== '_blank') {
                    $element->removeAttribute('target');
                } else {
                    $element->setAttribute('rel', self::safeRel($element->getAttribute('rel').' noopener noreferrer'));
                }
                continue;
            }

            if ($name === 'rel') {
                $element->setAttribute('rel', self::safeRel($value));
                continue;
            }

            if ($name === 'style') {
                $style = self::safeStyle($value);
                if ($style === '') {
                    $element->removeAttribute('style');
                } else {
                    $element->setAttribute('style', $style);
                }
            }
        }
    }

    private static function safeHref(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (($value[0] ?? '') === '/' && ! str_starts_with($value, '//')) {
            return true;
        }

        if (($value[0] ?? '') === '#') {
            return preg_match('/^#[A-Za-z][A-Za-z0-9_:\.-]*$/', $value) === 1;
        }

        return preg_match('~^(?:https?://|mailto:|tel:)~i', $value) === 1;
    }

    private static function safeImageSrc(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (($value[0] ?? '') === '/' && ! str_starts_with($value, '//')) {
            return true;
        }

        return preg_match('~^https?://~i', $value) === 1;
    }

    private static function safeRel(string $value): string
    {
        $allowed = ['noopener', 'noreferrer', 'nofollow', 'sponsored', 'ugc'];
        $tokens = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        $tokens = array_values(array_unique(array_intersect($tokens, $allowed)));

        return implode(' ', $tokens);
    }

    private static function safeStyle(string $value): string
    {
        $safe = [];

        foreach (explode(';', $value) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $rawValue] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);

            if ($property === 'text-align' && in_array(strtolower($rawValue), ['left', 'right', 'center', 'justify'], true)) {
                $safe[] = 'text-align: '.strtolower($rawValue);
                continue;
            }

            if (in_array($property, ['color', 'background-color'], true) && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $rawValue) === 1) {
                $safe[] = $property.': '.strtolower($rawValue);
            }
        }

        return implode('; ', $safe);
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
