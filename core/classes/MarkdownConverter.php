<?php declare(strict_types=1);

/**
 * MarkdownConverter - Simple Markdown to HTML converter
 */
class MarkdownConverter
{

    /**
     * Convert Markdown to HTML
     *
     * @param string $markdown Markdown content
     * @return string HTML content
     */
    public static function toHtml($markdown)
    {
        $codeBlocks = [];
        $codeIndex = 0;
        $html = $markdown;

        // Step 1: Extract fenced code blocks (protect from parsing)
        $html = preg_replace_callback('/```([a-z]*)\n(.*?)```/s', function ($matches) use (&$codeBlocks, &$codeIndex) {
            $placeholder = '<!--CODEBLOCK:' . $codeIndex . '-->';
            $lang = $matches[1];
            $code = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            if ($lang !== '') {
                $codeBlocks[$placeholder] = '<pre><code class="language-' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '">' . $code . '</code></pre>';
            } else {
                $codeBlocks[$placeholder] = '<pre><code>' . $code . '</code></pre>';
            }
            $codeIndex++;
            return "\n\n" . $placeholder . "\n\n";
        }, $html);

        // Step 2: Extract inline code (protect from parsing)
        $html = preg_replace_callback('/`([^`]+)`/', function ($matches) use (&$codeBlocks, &$codeIndex) {
            $placeholder = '<!--CODEINLINE:' . $codeIndex . '-->';
            $codeBlocks[$placeholder] = '<code>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</code>';
            $codeIndex++;
            return $placeholder;
        }, $html);

        // Step 3: Block-level elements

        // Headers
        $html = preg_replace('/^######\s+(.*)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^#####\s+(.*)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^####\s+(.*)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.*)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.*)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.*)$/m', '<h1>$1</h1>', $html);

        // Horizontal rules
        $html = preg_replace('/^(?:---+|\*\*\*+|___+)\s*$/m', '<hr>', $html);

        // Blockquotes (merge consecutive lines into single block)
        $html = preg_replace_callback('/(?:^>\s?(.*)$\n?)+/m', function ($matches) {
            preg_match_all('/^>\s?(.*)$/m', $matches[0], $lines);
            $content = implode("\n", $lines[1]);
            return '<blockquote><p>' . trim($content) . '</p></blockquote>';
        }, $html);

        // Ordered lists (merge consecutive lines)
        $html = preg_replace_callback('/(?:^\d+\.\s+(.*)$\n?)+/m', function ($matches) {
            preg_match_all('/^\d+\.\s+(.*)$/m', $matches[0], $items);
            $listHtml = '<ol>';
            foreach ($items[1] as $item) {
                $listHtml .= '<li>' . $item . '</li>';
            }
            $listHtml .= '</ol>';
            return $listHtml;
        }, $html);

        // Unordered lists (merge consecutive lines)
        $html = preg_replace_callback('/(?:^[ \t]*[-*+]\s+(.*)$\n?)+/m', function ($matches) {
            preg_match_all('/^[ \t]*[-*+]\s+(.*)$/m', $matches[0], $items);
            $listHtml = '<ul>';
            foreach ($items[1] as $item) {
                $listHtml .= '<li>' . $item . '</li>';
            }
            $listHtml .= '</ul>';
            return $listHtml;
        }, $html);

        // Step 4: Inline elements (no /s flag — single-line only)

        // Bold and italic
        $html = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/___(.+?)___/', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);
        $html = preg_replace('/_(.+?)_/', '<em>$1</em>', $html);

        // Strikethrough
        $html = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $html);

        // Images (must be before links)
        $html = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1">', $html);

        // Links (with optional title)
        $html = preg_replace_callback('/\[([^\]]+)\]\(([^\s\)]+)(?:\s+"([^"]*)")?\)/', function ($matches) {
            $text = $matches[1];
            $url = $matches[2];
            $title = $matches[3] ?? '';
            if ($title !== '') {
                return '<a href="' . $url . '" title="' . $title . '">' . $text . '</a>';
            }
            return '<a href="' . $url . '">' . $text . '</a>';
        }, $html);

        // Step 5: Paragraphs (blocks separated by blank lines)
        $blocks = explode("\n\n", $html);
        $paragraphs = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }
            // Don't wrap block-level elements or code placeholders
            if (preg_match('/^<(h[1-6]|ul|ol|pre|blockquote|div|hr|table)/', $block) ||
                str_contains($block, '<!--CODEBLOCK:')) {
                $paragraphs[] = $block;
            } else {
                $paragraphs[] = '<p>' . $block . '</p>';
            }
        }
        $html = implode("\n", $paragraphs);

        // Step 6: Line breaks (only inside <p> tags, not in block elements)
        $html = preg_replace_callback('/<p>(.*?)<\/p>/s', fn ($matches) => '<p>' . str_replace("\n", '<br>', $matches[1]) . '</p>', $html);

        // Step 7: Restore code placeholders
        foreach ($codeBlocks as $placeholder => $code) {
            $html = str_replace($placeholder, $code, $html);
        }

        return $html;
    }

    /**
     * Convert HTML to Markdown
     *
     * @param string $html HTML content
     * @return string Markdown content
     */
    public static function toMarkdown($html)
    {
        $markdown = $html;

        // Horizontal rules
        $markdown = preg_replace('/<hr\s*\/?>/i', "\n\n---\n\n", $markdown);

        // Headers
        $markdown = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', "# $1\n\n", $markdown);
        $markdown = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "## $1\n\n", $markdown);
        $markdown = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', "### $1\n\n", $markdown);
        $markdown = preg_replace('/<h4[^>]*>(.*?)<\/h4>/is', "#### $1\n\n", $markdown);
        $markdown = preg_replace('/<h5[^>]*>(.*?)<\/h5>/is', "##### $1\n\n", $markdown);
        $markdown = preg_replace('/<h6[^>]*>(.*?)<\/h6>/is', "###### $1\n\n", $markdown);

        // Code blocks (before inline processing to protect content)
        $markdown = preg_replace_callback('/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/is', function ($matches) {
            $code = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            return "\n\n```\n" . $code . "```\n\n";
        }, $markdown);
        $markdown = preg_replace_callback('/<code[^>]*>(.*?)<\/code>/is', fn ($matches) => '`' . html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') . '`', $markdown);

        // Bold and italic
        $markdown = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $markdown);
        $markdown = preg_replace('/<b[^>]*>(.*?)<\/b>/is', '**$1**', $markdown);
        $markdown = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '*$1*', $markdown);
        $markdown = preg_replace('/<i[^>]*>(.*?)<\/i>/is', '*$1*', $markdown);

        // Strikethrough
        $markdown = preg_replace('/<del[^>]*>(.*?)<\/del>/is', '~~$1~~', $markdown);
        $markdown = preg_replace('/<s[^>]*>(.*?)<\/s>/is', '~~$1~~', $markdown);

        // Links
        $markdown = preg_replace('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $markdown);

        // Images
        $markdown = preg_replace('/<img[^>]*src=["\']([^"\']*)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*\/?>/is', '![$2]($1)', $markdown);
        $markdown = preg_replace('/<img[^>]*alt=["\']([^"\']*)["\'][^>]*src=["\']([^"\']*)["\'][^>]*\/?>/is', '![$1]($2)', $markdown);
        $markdown = preg_replace('/<img[^>]*src=["\']([^"\']*)["\'][^>]*\/?>/is', '![]($1)', $markdown);

        // Ordered lists (before unordered to preserve numbering)
        $markdown = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function ($matches) {
            $content = $matches[1];
            $counter = 1;
            $content = preg_replace_callback('/<li[^>]*>(.*?)<\/li>/is', function ($m) use (&$counter) {
                return $counter++ . '. ' . trim($m[1]) . "\n";
            }, $content);
            return "\n" . $content . "\n";
        }, $markdown);

        // Unordered lists
        $markdown = preg_replace('/<ul[^>]*>(.*?)<\/ul>/is', "$1\n", $markdown);
        $markdown = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $markdown);

        // Blockquotes
        $markdown = preg_replace_callback('/<blockquote[^>]*>(.*?)<\/blockquote>/is', function ($matches) {
            $content = trim($matches[1]);
            $content = preg_replace('/<p[^>]*>(.*?)<\/p>/is', '$1', $content);
            $lines = explode("\n", $content);
            $quoted = array_map(fn($line) => '> ' . $line, $lines);
            return implode("\n", $quoted) . "\n\n";
        }, $markdown);

        // Paragraphs
        $markdown = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $markdown);

        // Line breaks
        $markdown = preg_replace('/<br\s*\/?>/i', "\n", $markdown);

        // Remove remaining HTML tags
        $markdown = strip_tags($markdown);

        // Clean up extra whitespace
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        $markdown = trim($markdown);

        return $markdown;
    }
}
