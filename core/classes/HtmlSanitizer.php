<?php declare(strict_types=1);

/**
 * Allowlist sanitizer for editor-produced rich HTML.
 * Uses PHP's DOM extension and fails closed when DOM is unavailable.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'strong', 'b', 'em', 'i', 'u', 's', 'del',
        'a', 'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tfoot',
        'tr', 'th', 'td', 'pre', 'code', 'hr', 'div', 'span',
    ];

    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'svg', 'math', 'template', 'meta', 'link', 'base',
    ];

    private const ATTRIBUTES = [
        '*' => ['class'],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'td' => ['colspan', 'rowspan'],
        'code' => ['class'],
    ];

    public static function sanitize($html): string
    {
        $html = (string)$html;
        if ($html === '') {
            return '';
        }
        if (!class_exists(DOMDocument::class)) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapperId = 'mantra-sanitizer-root';
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="' . $wrapperId . '">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $wrapper = $dom->getElementById($wrapperId);
        if (!$wrapper instanceof DOMElement) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        self::cleanChildren($wrapper);

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
        return $result;
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $parent->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($child);
                continue;
            }

            self::cleanChildren($child);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(self::ATTRIBUTES['*'], self::ATTRIBUTES[$tag] ?? []);
        $names = [];
        foreach ($element->attributes as $attribute) {
            $names[] = $attribute->name;
        }

        foreach ($names as $name) {
            $normalized = strtolower($name);
            if (!in_array($normalized, $allowed, true)) {
                $element->removeAttribute($name);
                continue;
            }

            $value = trim($element->getAttribute($name));
            if (($normalized === 'href' || $normalized === 'src') && !self::isSafeUrl($value)) {
                $element->removeAttribute($name);
            } elseif ($normalized === 'class') {
                $element->setAttribute($name, preg_replace('/[^a-zA-Z0-9 _-]/', '', $value));
            } elseif (in_array($normalized, ['width', 'height', 'colspan', 'rowspan'], true)
                && preg_match('/^\d{1,4}$/', $value) !== 1) {
                $element->removeAttribute($name);
            } elseif ($normalized === 'target' && !in_array($value, ['_blank', '_self'], true)) {
                $element->removeAttribute($name);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/[\x00-\x20\x7f]+/', '', $decoded);
        if ($decoded === '' || str_starts_with($decoded, '#') || str_starts_with($decoded, '/')) {
            return true;
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $decoded, $matches) !== 1) {
            return true;
        }

        return in_array(strtolower($matches[1]), ['http', 'https', 'mailto', 'tel'], true);
    }
}
