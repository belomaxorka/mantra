<?php declare(strict_types=1);
/**
 * MarkdownConverter - Simple Markdown to HTML converter
 */

class MarkdownConverter {

    /**
     * Convert Markdown to HTML
     *
     * @param string $markdown Markdown content
     * @return string HTML content
     */
    public static function toHtml($markdown) {
        $html = $markdown;

        // Headers
        $html = preg_replace('/^######\s+(.*)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^#####\s+(.*)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^####\s+(.*)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.*)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.*)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.*)$/m', '<h1>$1</h1>', $html);

        // Bold and italic
        $html = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
        $html = preg_replace('/___(.+?)___/s', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/_(.+?)_/s', '<em>$1</em>', $html);

        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $html);

        // Images
        $html = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1">', $html);

        // Code blocks
        $html = preg_replace('/```([a-z]*)\n(.*?)```/s', '<pre><code>$2</code></pre>', $html);

        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Blockquotes
        $html = preg_replace('/^>\s+(.*)$/m', '<blockquote>$1</blockquote>', $html);

        // Unordered lists
        $html = preg_replace_callback('/^(\s*)[-*+]\s+(.*)$/m', fn($matches) => '<li>' . $matches[2] . '</li>', $html);

        // Wrap consecutive <li> in <ul>
        $html = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html);

        // Paragraphs (lines separated by blank lines)
        $lines = explode("\n\n", $html);
        $paragraphs = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            // Don't wrap if already has block-level tags
            if (preg_match('/^<(h[1-6]|ul|ol|pre|blockquote|div)/', $line)) {
                $paragraphs[] = $line;
            } else {
                $paragraphs[] = '<p>' . $line . '</p>';
            }
        }
        $html = implode("\n", $paragraphs);

        // Line breaks
        $html = str_replace("\n", '<br>', $html);

        return $html;
    }

    /**
     * Convert HTML to Markdown
     *
     * @param string $html HTML content
     * @return string Markdown content
     */
    public static function toMarkdown($html) {
        $markdown = $html;

        // Headers
        $markdown = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', "# $1\n\n", $markdown);
        $markdown = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "## $1\n\n", $markdown);
        $markdown = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', "### $1\n\n", $markdown);
        $markdown = preg_replace('/<h4[^>]*>(.*?)<\/h4>/is', "#### $1\n\n", $markdown);
        $markdown = preg_replace('/<h5[^>]*>(.*?)<\/h5>/is', "##### $1\n\n", $markdown);
        $markdown = preg_replace('/<h6[^>]*>(.*?)<\/h6>/is', "###### $1\n\n", $markdown);

        // Bold and italic
        $markdown = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $markdown);
        $markdown = preg_replace('/<b[^>]*>(.*?)<\/b>/is', '**$1**', $markdown);
        $markdown = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '*$1*', $markdown);
        $markdown = preg_replace('/<i[^>]*>(.*?)<\/i>/is', '*$1*', $markdown);

        // Links
        $markdown = preg_replace('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $markdown);

        // Images
        $markdown = preg_replace('/<img[^>]*src=["\']([^"\']*)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*\/?>/is', '![$2]($1)', $markdown);
        $markdown = preg_replace('/<img[^>]*alt=["\']([^"\']*)["\'][^>]*src=["\']([^"\']*)["\'][^>]*\/?>/is', '![$1]($2)', $markdown);
        $markdown = preg_replace('/<img[^>]*src=["\']([^"\']*)["\'][^>]*\/?>/is', '![]($1)', $markdown);

        // Lists
        $markdown = preg_replace('/<ul[^>]*>(.*?)<\/ul>/is', "$1\n", $markdown);
        $markdown = preg_replace('/<ol[^>]*>(.*?)<\/ol>/is', "$1\n", $markdown);
        $markdown = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $markdown);

        // Paragraphs
        $markdown = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $markdown);

        // Line breaks
        $markdown = preg_replace('/<br\s*\/?>/i', "\n", $markdown);

        // Code blocks
        $markdown = preg_replace('/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/is', "```\n$1\n```\n\n", $markdown);
        $markdown = preg_replace('/<code[^>]*>(.*?)<\/code>/is', '`$1`', $markdown);

        // Blockquotes
        $markdown = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/is', "> $1\n\n", $markdown);

        // Remove remaining HTML tags
        $markdown = strip_tags($markdown);

        // Clean up extra whitespace
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        $markdown = trim($markdown);

        return $markdown;
    }
}
