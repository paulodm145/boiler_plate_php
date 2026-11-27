<?php

namespace App\Helpers;

/**
 * Sanitizador allowlist para HTML vindo de um editor rico: aplicado sempre
 * no momento de salvar (nunca confie no sanitizador do editor no navegador,
 * ja que um POST direto nao passa por ele). Qualquer tag fora da allowlist e
 * removida inteira (nao desembrulhada) -- mais simples e mais seguro, e tags
 * fora da lista so deveriam aparecer por injecao direta, nunca pelo uso
 * normal do editor.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3', 'h4', 'blockquote',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    /** Esquemas aceitos num link; sem esquema (caminho relativo, ancora) tambem vale. */
    private const SAFE_LINK_SCHEMES = ['http', 'https', 'mailto'];

    public static function clean(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return '';
        }

        self::sanitizeChildren($dom, $root);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    private static function sanitizeChildren(\DOMDocument $dom, \DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (!($child instanceof \DOMElement)) {
                $parent->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $parent->removeChild($child);
                continue;
            }

            self::sanitizeAttributes($child, $tag);
            self::sanitizeChildren($dom, $child);
        }
    }

    private static function sanitizeAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            // Defesa em profundidade: atributos on* nunca sao permitidos,
            // independente do que a allowlist acima vier a declarar no futuro.
            if (strpos($name, 'on') === 0 || !in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if ($name === 'href' && !self::isSafeLink($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * Allowlist, nunca denylist: o navegador ignora controles e espacos no
     * esquema ("java\tscript:" executa), entao eles saem antes de olhar o
     * esquema, e so http, https, mailto ou link sem esquema passam; vazio cai.
     * Antes do primeiro / ? # (onde mora o esquema) nao pode haver '&':
     * assim nenhuma entidade -- com ou sem ';', que o navegador aceita e o
     * PHP nao -- chega a formar um esquema, sem imitar o decodificador dele.
     */
    public static function isSafeLink(string $href): bool
    {
        $compact = (string) preg_replace('/[\x00-\x20\x7f]+/', '', $href);
        $schemePart = preg_split('#[/?\#]#', $compact, 2)[0];

        if ($compact === '' || strpos($schemePart, '&') !== false) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $compact, $match) !== 1) {
            return true;
        }

        return in_array(strtolower($match[1]), self::SAFE_LINK_SCHEMES, true);
    }
}
