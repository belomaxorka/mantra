<?php declare(strict_types=1);

class MarkdownConverterTest extends MantraTestCase
{

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function html(string $md): string
    {
        return MarkdownConverter::toHtml($md);
    }

    private function md(string $html): string
    {
        return MarkdownConverter::toMarkdown($html);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Headers
    // ═══════════════════════════════════════════════

    public function testToHtmlHeaders(): void
    {
        $this->assertSame('<h1>Title</h1>', $this->html('# Title'), 'H1 header');
        $this->assertSame('<h2>Title</h2>', $this->html('## Title'), 'H2 header');
        $this->assertSame('<h3>Title</h3>', $this->html('### Title'), 'H3 header');
        $this->assertSame('<h4>Title</h4>', $this->html('#### Title'), 'H4 header');
        $this->assertSame('<h5>Title</h5>', $this->html('##### Title'), 'H5 header');
        $this->assertSame('<h6>Title</h6>', $this->html('###### Title'), 'H6 header');
    }

    public function testToHtmlHeaderNotWrappedInParagraph(): void
    {
        $result = $this->html("# Header\n\nParagraph");
        $this->assertStringContainsString('<h1>Header</h1>', $result);
        $this->assertStringContainsString('<p>Paragraph</p>', $result);
        $this->assertStringNotContainsString('<p><h1>', $result, 'Header not nested in paragraph');
    }

    public function testToHtmlHeaderWithInlineFormatting(): void
    {
        $result = $this->html('## **Bold** header');
        $this->assertSame('<h2><strong>Bold</strong> header</h2>', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Bold
    // ═══════════════════════════════════════════════

    public function testToHtmlBoldAsterisks(): void
    {
        $this->assertSame('<p><strong>bold</strong></p>', $this->html('**bold**'));
    }

    public function testToHtmlBoldUnderscores(): void
    {
        $this->assertSame('<p><strong>bold</strong></p>', $this->html('__bold__'));
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Italic
    // ═══════════════════════════════════════════════

    public function testToHtmlItalicAsterisks(): void
    {
        $this->assertSame('<p><em>italic</em></p>', $this->html('*italic*'));
    }

    public function testToHtmlItalicUnderscores(): void
    {
        $this->assertSame('<p><em>italic</em></p>', $this->html('_italic_'));
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Bold + Italic
    // ═══════════════════════════════════════════════

    public function testToHtmlBoldItalicAsterisks(): void
    {
        $this->assertSame(
            '<p><strong><em>text</em></strong></p>',
            $this->html('***text***'),
        );
    }

    public function testToHtmlBoldItalicUnderscores(): void
    {
        $this->assertSame(
            '<p><strong><em>text</em></strong></p>',
            $this->html('___text___'),
        );
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Strikethrough
    // ═══════════════════════════════════════════════

    public function testToHtmlStrikethrough(): void
    {
        $this->assertSame('<p><del>deleted</del></p>', $this->html('~~deleted~~'));
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Bold does not cross paragraph boundary
    // ═══════════════════════════════════════════════

    public function testToHtmlBoldDoesNotCrossParagraphs(): void
    {
        $result = $this->html("**start\n\nend**");
        $this->assertStringNotContainsString('<strong>', $result, 'Bold must not span across paragraphs');
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Links
    // ═══════════════════════════════════════════════

    public function testToHtmlLink(): void
    {
        $this->assertSame(
            '<p><a href="https://example.com">text</a></p>',
            $this->html('[text](https://example.com)'),
        );
    }

    public function testToHtmlLinkWithTitle(): void
    {
        $this->assertSame(
            '<p><a href="https://example.com" title="My Title">text</a></p>',
            $this->html('[text](https://example.com "My Title")'),
        );
    }

    public function testToHtmlLinkInsideText(): void
    {
        $this->assertSame(
            '<p>Visit <a href="https://example.com">us</a> today.</p>',
            $this->html('Visit [us](https://example.com) today.'),
        );
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Images
    // ═══════════════════════════════════════════════

    public function testToHtmlImage(): void
    {
        $this->assertSame(
            '<p><img src="photo.jpg" alt="alt text"></p>',
            $this->html('![alt text](photo.jpg)'),
        );
    }

    public function testToHtmlImageEmptyAlt(): void
    {
        $this->assertSame(
            '<p><img src="photo.jpg" alt=""></p>',
            $this->html('![](photo.jpg)'),
        );
    }

    public function testToHtmlImageNotConvertedToLink(): void
    {
        $result = $this->html('![photo](img.jpg)');
        $this->assertStringContainsString('<img ', $result, 'Produces <img>');
        $this->assertStringNotContainsString('<a ', $result, 'Does not produce <a>');
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Inline code
    // ═══════════════════════════════════════════════

    public function testToHtmlInlineCode(): void
    {
        $this->assertSame('<p><code>code</code></p>', $this->html('`code`'));
    }

    public function testToHtmlInlineCodeEscapesHtml(): void
    {
        $result = $this->html('`<script>alert(1)</script>`');
        $this->assertStringContainsString('&lt;script&gt;', $result, 'HTML is escaped inside inline code');
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testToHtmlInlineCodeProtectsFromParsing(): void
    {
        $result = $this->html('`**not bold**`');
        $this->assertStringNotContainsString('<strong>', $result, 'Bold syntax is not parsed inside inline code');
        $this->assertStringContainsString('**not bold**', $result, 'Markdown preserved as literal text');
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Fenced code blocks
    // ═══════════════════════════════════════════════

    public function testToHtmlFencedCodeBlock(): void
    {
        $result = $this->html("```\nsome code\n```");
        $this->assertStringContainsString('<pre><code>', $result);
        $this->assertStringContainsString('some code', $result);
    }

    public function testToHtmlFencedCodeBlockWithLanguage(): void
    {
        $result = $this->html("```php\necho 1;\n```");
        $this->assertStringContainsString('class="language-php"', $result, 'Language class added');
    }

    public function testToHtmlFencedCodeBlockEscapesHtml(): void
    {
        $result = $this->html("```\n<div>test</div>\n```");
        $this->assertStringContainsString('&lt;div&gt;', $result, 'HTML escaped in code block');
    }

    public function testToHtmlFencedCodeBlockProtectsFromParsing(): void
    {
        $result = $this->html("```\n# Not a header\n**not bold**\n```");
        $this->assertStringNotContainsString('<h1>', $result, 'Header syntax not parsed in code block');
        $this->assertStringNotContainsString('<strong>', $result, 'Bold syntax not parsed in code block');
        $this->assertStringContainsString('# Not a header', $result);
        $this->assertStringContainsString('**not bold**', $result);
    }

    public function testToHtmlFencedCodeBlockNotWrappedInParagraph(): void
    {
        $result = $this->html("```\ncode\n```");
        $this->assertStringNotContainsString('<p><pre>', $result, 'Code block not nested in paragraph');
    }

    public function testToHtmlFencedCodeBlockNoLineBreakTags(): void
    {
        $result = $this->html("```\nline 1\nline 2\n```");
        $this->assertStringNotContainsString('<br>', $result, 'No <br> inside code blocks');
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Horizontal rules
    // ═══════════════════════════════════════════════

    public function testToHtmlHorizontalRuleHyphens(): void
    {
        $this->assertSame('<hr>', $this->html('---'));
    }

    public function testToHtmlHorizontalRuleAsterisks(): void
    {
        $this->assertSame('<hr>', $this->html('***'));
    }

    public function testToHtmlHorizontalRuleUnderscores(): void
    {
        $this->assertSame('<hr>', $this->html('___'));
    }

    public function testToHtmlHorizontalRuleLong(): void
    {
        $this->assertSame('<hr>', $this->html('-----'));
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Blockquotes
    // ═══════════════════════════════════════════════

    public function testToHtmlBlockquoteSingleLine(): void
    {
        $result = $this->html('> quoted text');
        $this->assertStringContainsString('<blockquote>', $result);
        $this->assertStringContainsString('quoted text', $result);
    }

    public function testToHtmlBlockquoteMultipleLinesAreMerged(): void
    {
        $result = $this->html("> line 1\n> line 2");
        $this->assertSame(
            1,
            substr_count($result, '<blockquote>'),
            'Consecutive quote lines produce single blockquote',
        );
        $this->assertStringContainsString('line 1', $result);
        $this->assertStringContainsString('line 2', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Unordered lists
    // ═══════════════════════════════════════════════

    public function testToHtmlUnorderedListDash(): void
    {
        $result = $this->html("- item 1\n- item 2");
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>item 1</li>', $result);
        $this->assertStringContainsString('<li>item 2</li>', $result);
    }

    public function testToHtmlUnorderedListAsterisk(): void
    {
        $result = $this->html("* item 1\n* item 2");
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>item 1</li>', $result);
    }

    public function testToHtmlUnorderedListPlus(): void
    {
        $result = $this->html("+ item 1\n+ item 2");
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>item 1</li>', $result);
    }

    public function testToHtmlUnorderedListNotWrappedInParagraph(): void
    {
        $result = $this->html("- a\n- b");
        $this->assertStringNotContainsString('<p><ul>', $result, 'List not nested in paragraph');
    }

    public function testToHtmlUnorderedListNoLineBreakTags(): void
    {
        $result = $this->html("- a\n- b");
        $this->assertStringNotContainsString('<br>', $result, 'No <br> between list items');
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Ordered lists
    // ═══════════════════════════════════════════════

    public function testToHtmlOrderedList(): void
    {
        $result = $this->html("1. first\n2. second\n3. third");
        $this->assertStringContainsString('<ol>', $result);
        $this->assertStringContainsString('<li>first</li>', $result);
        $this->assertStringContainsString('<li>second</li>', $result);
        $this->assertStringContainsString('<li>third</li>', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Paragraphs and line breaks
    // ═══════════════════════════════════════════════

    public function testToHtmlPlainTextWrappedInParagraph(): void
    {
        $this->assertSame('<p>Hello world</p>', $this->html('Hello world'));
    }

    public function testToHtmlBlankLineSeparatesParagraphs(): void
    {
        $result = $this->html("First\n\nSecond");
        $this->assertStringContainsString('<p>First</p>', $result);
        $this->assertStringContainsString('<p>Second</p>', $result);
    }

    public function testToHtmlSingleNewlineBecomesLineBreak(): void
    {
        $result = $this->html("line 1\nline 2");
        $this->assertSame('<p>line 1<br>line 2</p>', $result);
    }

    public function testToHtmlEmptyInput(): void
    {
        $this->assertSame('', $this->html(''));
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Mixed content
    // ═══════════════════════════════════════════════

    public function testToHtmlMixedContent(): void
    {
        $md = "# Title\n\nSome **bold** and *italic* text.\n\n- item 1\n- item 2";
        $result = $this->html($md);

        $this->assertStringContainsString('<h1>Title</h1>', $result);
        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>item 1</li>', $result);
    }

    public function testToHtmlBoldInsideListItem(): void
    {
        $result = $this->html("- **bold item**\n- normal");
        $this->assertStringContainsString('<li><strong>bold item</strong></li>', $result);
    }

    public function testToHtmlInlineCodeInsideParagraph(): void
    {
        $result = $this->html('Use `printf()` for output.');
        $this->assertSame('<p>Use <code>printf()</code> for output.</p>', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Nested formatting
    // ═══════════════════════════════════════════════

    public function testToHtmlItalicInsideBold(): void
    {
        $result = $this->html('**text *italic* more**');
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
    }

    public function testToHtmlBoldInsideItalic(): void
    {
        $result = $this->html('*text **bold** more*');
        $this->assertStringContainsString('<em>', $result);
        $this->assertStringContainsString('<strong>bold</strong>', $result);
    }

    public function testToHtmlBoldInsideLink(): void
    {
        $result = $this->html('[**bold link**](https://example.com)');
        $this->assertStringContainsString('<a href="https://example.com">', $result);
        $this->assertStringContainsString('<strong>bold link</strong>', $result);
    }

    public function testToHtmlLinkInsideBold(): void
    {
        $result = $this->html('**[link](https://example.com) text**');
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<a href="https://example.com">link</a>', $result);
    }

    public function testToHtmlStrikethroughWithBold(): void
    {
        $result = $this->html('~~**bold deleted**~~');
        $this->assertStringContainsString('<del>', $result);
        $this->assertStringContainsString('<strong>bold deleted</strong>', $result);
    }

    public function testToHtmlBoldInsideBlockquote(): void
    {
        $result = $this->html('> **bold** in quote');
        $this->assertStringContainsString('<blockquote>', $result);
        $this->assertStringContainsString('<strong>bold</strong>', $result);
    }

    public function testToHtmlMultipleInlineFormats(): void
    {
        $result = $this->html('**bold** and *italic* and ~~deleted~~');
        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
        $this->assertStringContainsString('<del>deleted</del>', $result);
    }

    public function testToHtmlDeepNestingBoldItalicStrikethrough(): void
    {
        $result = $this->html('***~~nested~~***');
        $this->assertStringContainsString('<strong><em><del>nested</del></em></strong>', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: XSS prevention — dangerous URL schemes
    // ═══════════════════════════════════════════════

    public function testToHtmlLinkJavascriptUrlBlocked(): void
    {
        $result = $this->html('[click](javascript:alert(1))');
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('<a ', $result, 'Dangerous link must not produce <a> tag');
        $this->assertStringContainsString('click', $result, 'Link text preserved');
    }

    public function testToHtmlLinkJavascriptUrlCaseInsensitive(): void
    {
        $result = $this->html('[click](JavaScript:alert(1))');
        $this->assertStringNotContainsString('<a ', $result, 'Case-insensitive javascript: check');

        $result = $this->html('[click](JAVASCRIPT:alert(1))');
        $this->assertStringNotContainsString('<a ', $result, 'Uppercase JAVASCRIPT: check');
    }

    public function testToHtmlLinkVbscriptUrlBlocked(): void
    {
        $result = $this->html('[click](vbscript:MsgBox(1))');
        $this->assertStringNotContainsString('vbscript:', $result);
        $this->assertStringNotContainsString('<a ', $result);
    }

    public function testToHtmlLinkDataUrlBlocked(): void
    {
        $result = $this->html('[click](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)');
        $this->assertStringNotContainsString('data:', $result);
        $this->assertStringNotContainsString('<a ', $result);
    }

    public function testToHtmlImageJavascriptSrcBlocked(): void
    {
        $result = $this->html('![x](javascript:alert(1))');
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('<img ', $result, 'Dangerous src must not produce <img> tag');
    }

    public function testToHtmlImageDataSrcBlocked(): void
    {
        $result = $this->html('![x](data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+)');
        $this->assertStringNotContainsString('data:', $result);
        $this->assertStringNotContainsString('<img ', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: XSS prevention — attribute injection
    // ═══════════════════════════════════════════════

    public function testToHtmlLinkUrlQuotesEscaped(): void
    {
        $result = $this->html('[text](http://x"onclick="alert(1))');
        $this->assertStringContainsString('&quot;', $result, 'Quotes in URL must be HTML-escaped');
    }

    public function testToHtmlImageAltQuotesEscaped(): void
    {
        $result = $this->html('![" onload="alert(1)](img.jpg)');
        $this->assertStringContainsString('&quot;', $result, 'Quotes in alt must be HTML-escaped');
    }

    public function testToHtmlImageSrcQuotesEscaped(): void
    {
        $result = $this->html('![alt](img.jpg"onerror="alert(1))');
        $this->assertStringContainsString('&quot;', $result, 'Quotes in src must be HTML-escaped');
    }

    public function testToHtmlLinkTitleHtmlEscaped(): void
    {
        $result = $this->html('[text](http://ok "<script>alert(1)</script>")');
        $this->assertStringNotContainsString('<script>', $result, 'HTML in title must be escaped');
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: XSS prevention — safe URLs still work
    // ═══════════════════════════════════════════════

    public function testToHtmlLinkSafeProtocolsAllowed(): void
    {
        $result = $this->html('[a](https://example.com)');
        $this->assertStringContainsString('href="https://example.com"', $result, 'HTTPS allowed');

        $result = $this->html('[a](http://example.com)');
        $this->assertStringContainsString('href="http://example.com"', $result, 'HTTP allowed');

        $result = $this->html('[a](/path)');
        $this->assertStringContainsString('href="/path"', $result, 'Relative URL allowed');

        $result = $this->html('[a](mailto:user@example.com)');
        $this->assertStringContainsString('href="mailto:user@example.com"', $result, 'Mailto allowed');

        $result = $this->html('[a](#anchor)');
        $this->assertStringContainsString('href="#anchor"', $result, 'Anchor allowed');
    }

    public function testToHtmlImageSafeUrlsAllowed(): void
    {
        $result = $this->html('![a](https://example.com/img.jpg)');
        $this->assertStringContainsString('src="https://example.com/img.jpg"', $result, 'HTTPS image allowed');

        $result = $this->html('![a](/local/img.jpg)');
        $this->assertStringContainsString('src="/local/img.jpg"', $result, 'Relative image allowed');
    }

    // ═══════════════════════════════════════════════
    //  toMarkdown: Nested HTML
    // ═══════════════════════════════════════════════

    public function testToMarkdownNestedBoldItalic(): void
    {
        $this->assertSame(
            '***bold italic***',
            $this->md('<strong><em>bold italic</em></strong>'),
        );
    }

    public function testToMarkdownBoldInsideListItem(): void
    {
        $result = $this->md('<ul><li><strong>bold</strong> item</li></ul>');
        $this->assertStringContainsString('- **bold** item', $result);
    }

    public function testToMarkdownBoldInsideBlockquote(): void
    {
        $result = $this->md('<blockquote><p><strong>bold</strong> text</p></blockquote>');
        $this->assertStringContainsString('> **bold** text', $result);
    }

    // ═══════════════════════════════════════════════
    //  toMarkdown: Malicious HTML
    // ═══════════════════════════════════════════════

    public function testToMarkdownStripsScriptTags(): void
    {
        $result = $this->md('<p>before</p><script>alert(1)</script><p>after</p>');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('</script>', $result);
        $this->assertStringContainsString('before', $result);
        $this->assertStringContainsString('after', $result);
    }

    public function testToMarkdownStripsEventHandlerAttributes(): void
    {
        $result = $this->md('<p onclick="alert(1)">text</p>');
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('text', $result);
    }

    public function testToMarkdownStripsIframeTags(): void
    {
        $result = $this->md('<iframe src="http://evil.com"></iframe>');
        $this->assertStringNotContainsString('<iframe', $result);
    }

    // ═══════════════════════════════════════════════
    //  toMarkdown: Headers
    // ═══════════════════════════════════════════════

    public function testToMarkdownHeaders(): void
    {
        $this->assertSame('# Title', $this->md('<h1>Title</h1>'));
        $this->assertSame('## Title', $this->md('<h2>Title</h2>'));
        $this->assertSame('### Title', $this->md('<h3>Title</h3>'));
        $this->assertSame('#### Title', $this->md('<h4>Title</h4>'));
        $this->assertSame('##### Title', $this->md('<h5>Title</h5>'));
        $this->assertSame('###### Title', $this->md('<h6>Title</h6>'));
    }

    public function testToMarkdownHeaderWithAttributes(): void
    {
        $this->assertSame('## Title', $this->md('<h2 class="big">Title</h2>'));
    }

    // ═══════════════════════════════════════════════
    //  toMarkdown: Bold and italic
    // ═══════════════════════════════════════════════

    public function testToMarkdownBold(): void
    {
        $this->assertSame('**bold**', $this->md('<strong>bold</strong>'));
        $this->assertSame('**bold**', $this->md('<b>bold</b>'));
    }

    public function testToMarkdownItalic(): void
    {
        $this->assertSame('*italic*', $this->md('<em>italic</em>'));
        $this->assertSame('*italic*', $this->md('<i>italic</i>'));
    }

    public function testToMarkdownStrikethrough(): void
    {
        $this->assertSame('~~deleted~~', $this->md('<del>deleted</del>'));
        $this->assertSame('~~deleted~~', $this->md('<s>deleted</s>'));
    }

    // ═══════════════════════════════════════════════
    //  toMarkdown: Links and images
    // ═══════════════════════════════════════════════

    public function testToMarkdownLink(): void
    {
        $this->assertSame(
            '[text](https://example.com)',
            $this->md('<a href="https://example.com">text</a>'),
        );
    }

    public function testToMarkdownImageSrcBeforeAlt(): void
    {
        $this->assertSame(
            '![alt](photo.jpg)',
            $this->md('<img src="photo.jpg" alt="alt">'),
        );
    }

    public function testToMarkdownImageAltBeforeSrc(): void
    {
        $this->assertSame(
            '![alt](photo.jpg)',
            $this->md('<img alt="alt" src="photo.jpg">'),
        );
    }

    public function testToMarkdownImageNoAlt(): void
    {
        $this->assertSame(
            '![](photo.jpg)',
            $this->md('<img src="photo.jpg">'),
        );
    }

    // ═══════════════════════════════════════════════
    //  toMarkdown: Code
    // ═══════════════════════════════════════════════

    public function testToMarkdownCodeBlock(): void
    {
        $result = $this->md('<pre><code>echo 1;</code></pre>');
        $this->assertStringContainsString('```', $result);
        $this->assertStringContainsString('echo 1;', $result);
    }

    public function testToMarkdownCodeBlockDecodesEntities(): void
    {
        $result = $this->md('<pre><code>echo &quot;hello&quot;;</code></pre>');
        $this->assertStringContainsString('echo "hello";', $result, 'Entities decoded in code blocks');
    }

    public function testToMarkdownInlineCode(): void
    {
        $this->assertStringContainsString('`code`', $this->md('<code>code</code>'));
    }

    public function testToMarkdownInlineCodeDecodesEntities(): void
    {
        $result = $this->md('<code>a &amp; b</code>');
        $this->assertStringContainsString('`a & b`', $result, 'Entities decoded in inline code');
    }

    // ═══════════════════════════════════════════════
    //  toMarkdown: Block elements
    // ═══════════════════════════════════════════════

    public function testToMarkdownHorizontalRule(): void
    {
        $this->assertSame('---', $this->md('<hr>'));
        $this->assertSame('---', $this->md('<hr/>'));
        $this->assertSame('---', $this->md('<hr />'));
    }

    public function testToMarkdownOrderedList(): void
    {
        $result = $this->md('<ol><li>first</li><li>second</li><li>third</li></ol>');
        $this->assertStringContainsString('1. first', $result);
        $this->assertStringContainsString('2. second', $result);
        $this->assertStringContainsString('3. third', $result);
    }

    public function testToMarkdownUnorderedList(): void
    {
        $result = $this->md('<ul><li>one</li><li>two</li></ul>');
        $this->assertStringContainsString('- one', $result);
        $this->assertStringContainsString('- two', $result);
    }

    public function testToMarkdownBlockquote(): void
    {
        $result = $this->md('<blockquote>quoted text</blockquote>');
        $this->assertStringContainsString('> quoted text', $result);
    }

    public function testToMarkdownBlockquoteStripsInnerParagraphs(): void
    {
        $result = $this->md('<blockquote><p>quoted text</p></blockquote>');
        $this->assertStringContainsString('> quoted text', $result);
        $this->assertStringNotContainsString('<p>', $result, 'Inner <p> removed');
    }

    public function testToMarkdownParagraph(): void
    {
        $this->assertSame('Some text', $this->md('<p>Some text</p>'));
    }

    public function testToMarkdownLineBreak(): void
    {
        $result = $this->md('line 1<br>line 2');
        $this->assertStringContainsString("line 1\nline 2", $result);
    }

    // ═══════════════════════════════════════════════
    //  toMarkdown: Cleanup
    // ═══════════════════════════════════════════════

    public function testToMarkdownStripsUnknownHtml(): void
    {
        $result = $this->md('<div class="x">text</div>');
        $this->assertStringNotContainsString('<div', $result);
        $this->assertStringContainsString('text', $result, 'Text content preserved');
    }

    public function testToMarkdownCollapsesExtraNewlines(): void
    {
        $result = $this->md("<p>first</p>\n\n\n\n<p>second</p>");
        $this->assertStringNotContainsString("\n\n\n", $result, 'No triple newlines');
    }

    public function testToMarkdownEmptyInput(): void
    {
        $this->assertSame('', $this->md(''));
    }

    // ═══════════════════════════════════════════════
    //  Round-trip: md → html → md
    // ═══════════════════════════════════════════════

    public function testRoundTripPlainText(): void
    {
        $original = 'Hello world';
        $this->assertSame($original, $this->md($this->html($original)), 'Plain text round-trip');
    }

    public function testRoundTripBold(): void
    {
        $original = '**bold text**';
        $this->assertSame($original, $this->md($this->html($original)), 'Bold round-trip');
    }

    public function testRoundTripItalic(): void
    {
        $original = '*italic text*';
        $this->assertSame($original, $this->md($this->html($original)), 'Italic round-trip');
    }

    public function testRoundTripStrikethrough(): void
    {
        $original = '~~deleted text~~';
        $this->assertSame($original, $this->md($this->html($original)), 'Strikethrough round-trip');
    }

    public function testRoundTripLink(): void
    {
        $original = '[example](https://example.com)';
        $this->assertSame($original, $this->md($this->html($original)), 'Link round-trip');
    }

    public function testRoundTripHeader(): void
    {
        $original = '# Title';
        $this->assertSame($original, $this->md($this->html($original)), 'Header round-trip');
    }

    public function testRoundTripHorizontalRule(): void
    {
        $original = '---';
        $this->assertSame($original, $this->md($this->html($original)), 'Horizontal rule round-trip');
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Code block language identifiers
    // ═══════════════════════════════════════════════

    public function testToHtmlFencedCodeBlockUppercaseLanguage(): void
    {
        $result = $this->html("```PHP\necho 1;\n```");
        $this->assertStringContainsString('<pre><code', $result, 'Recognized as code block');
        $this->assertStringContainsString('class="language-PHP"', $result, 'Uppercase language preserved');
    }

    public function testToHtmlFencedCodeBlockLanguageWithDigits(): void
    {
        $result = $this->html("```python3\nprint(1)\n```");
        $this->assertStringContainsString('class="language-python3"', $result);
    }

    public function testToHtmlFencedCodeBlockLanguageWithHyphen(): void
    {
        $result = $this->html("```objective-c\nNSLog();\n```");
        $this->assertStringContainsString('class="language-objective-c"', $result);
    }

    public function testToHtmlFencedCodeBlockLanguageWithPlus(): void
    {
        $result = $this->html("```c++\ncout;\n```");
        $this->assertStringContainsString('class="language-c++"', $result);
    }

    public function testToHtmlFencedCodeBlockLanguageCSharp(): void
    {
        $result = $this->html("```c#\nConsole.Write();\n```");
        $this->assertStringContainsString('class="language-c#"', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Underscore emphasis — word boundary
    // ═══════════════════════════════════════════════

    public function testToHtmlUnderscoreInVariableName(): void
    {
        $this->assertSame('<p>some_variable_name</p>', $this->html('some_variable_name'));
    }

    public function testToHtmlDoubleUnderscoreMidWord(): void
    {
        $this->assertSame('<p>my__var__name</p>', $this->html('my__var__name'));
    }

    public function testToHtmlUnderscoreMultipleInIdentifier(): void
    {
        $this->assertSame('<p>a_b_c_d</p>', $this->html('a_b_c_d'));
    }

    public function testToHtmlUnderscoreStandaloneStillWorks(): void
    {
        $this->assertSame('<p><em>italic</em></p>', $this->html('_italic_'));
        $this->assertSame('<p><strong>bold</strong></p>', $this->html('__bold__'));
    }

    // ═══════════════════════════════════════════════
    //  toHtml: URLs with parentheses
    // ═══════════════════════════════════════════════

    public function testToHtmlLinkUrlWithParentheses(): void
    {
        $result = $this->html('[Wiki](https://en.wikipedia.org/wiki/Foo_(bar))');
        $this->assertStringContainsString('href="https://en.wikipedia.org/wiki/Foo_(bar)"', $result);
    }

    public function testToHtmlImageUrlWithParentheses(): void
    {
        $result = $this->html('![alt](https://example.com/img_(1).jpg)');
        $this->assertStringContainsString('src="https://example.com/img_(1).jpg"', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Trailing # in headers
    // ═══════════════════════════════════════════════

    public function testToHtmlHeaderTrailingHashStripped(): void
    {
        $this->assertSame('<h2>Title</h2>', $this->html('## Title ##'));
        $this->assertSame('<h1>Title</h1>', $this->html('# Title #'));
        $this->assertSame('<h3>Title</h3>', $this->html('### Title ###'));
    }

    public function testToHtmlHeaderTrailingHashMidTitlePreserved(): void
    {
        $this->assertSame('<h2>Title # more</h2>', $this->html('## Title # more'));
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Spaced horizontal rules
    // ═══════════════════════════════════════════════

    public function testToHtmlHorizontalRuleSpacedHyphens(): void
    {
        $this->assertSame('<hr>', $this->html('- - -'));
    }

    public function testToHtmlHorizontalRuleSpacedAsterisks(): void
    {
        $this->assertSame('<hr>', $this->html('* * *'));
    }

    public function testToHtmlHorizontalRuleSpacedUnderscores(): void
    {
        $this->assertSame('<hr>', $this->html('_ _ _'));
    }

    public function testToHtmlHorizontalRuleWithLeadingSpaces(): void
    {
        $this->assertSame('<hr>', $this->html('  ---'));
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Blockquote multi-paragraph
    // ═══════════════════════════════════════════════

    public function testToHtmlBlockquoteWithEmptyQuoteLine(): void
    {
        $result = $this->html("> para 1\n>\n> para 2");
        $this->assertSame(
            1,
            substr_count($result, '<blockquote>'),
            'Single blockquote for continuous quote block',
        );
        $this->assertStringContainsString('para 1', $result);
        $this->assertStringContainsString('para 2', $result);
        $this->assertStringNotContainsString('> para', $result, 'No literal > prefix leaked into text');
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Asterisk false-positive emphasis
    // ═══════════════════════════════════════════════

    public function testToHtmlAsteriskInMathExpression(): void
    {
        $result = $this->html('5 * 3 * 15');
        $this->assertStringNotContainsString('<em>', $result, 'Spaced asterisks are not emphasis');
        $this->assertSame('<p>5 * 3 * 15</p>', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: CRLF line endings
    // ═══════════════════════════════════════════════

    public function testToHtmlCrlfLineEndings(): void
    {
        $result = $this->html("line 1\r\nline 2");
        $this->assertSame('<p>line 1<br>line 2</p>', $result);
        $this->assertStringNotContainsString("\r", $result, 'No CR in output');
    }

    public function testToHtmlCrlfParagraphSeparation(): void
    {
        $result = $this->html("first\r\n\r\nsecond");
        $this->assertStringContainsString('<p>first</p>', $result);
        $this->assertStringContainsString('<p>second</p>', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Unclosed / malformed elements
    // ═══════════════════════════════════════════════

    public function testToHtmlUnclosedFencedCodeBlock(): void
    {
        $result = $this->html("```\nsome code without closing");
        $this->assertStringNotContainsString('<pre>', $result, 'Unclosed code block not rendered as code');
    }

    public function testToHtmlUnclosedInlineCode(): void
    {
        $result = $this->html('start `unclosed code');
        $this->assertStringNotContainsString('<code>', $result, 'Unclosed backtick not rendered as code');
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Image inside link
    // ═══════════════════════════════════════════════

    public function testToHtmlImageInsideLink(): void
    {
        $result = $this->html('[![alt](img.jpg)](https://example.com)');
        $this->assertStringContainsString('<a href="https://example.com">', $result);
        $this->assertStringContainsString('<img src="img.jpg" alt="alt">', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Mixed list markers
    // ═══════════════════════════════════════════════

    public function testToHtmlMixedListMarkersAreMerged(): void
    {
        $result = $this->html("- item 1\n* item 2\n+ item 3");
        $this->assertSame(1, substr_count($result, '<ul>'), 'Mixed markers produce single list');
        $this->assertStringContainsString('<li>item 1</li>', $result);
        $this->assertStringContainsString('<li>item 2</li>', $result);
        $this->assertStringContainsString('<li>item 3</li>', $result);
    }

    // ═══════════════════════════════════════════════
    //  toHtml: Back-to-back code blocks
    // ═══════════════════════════════════════════════

    public function testToHtmlBackToBackCodeBlocks(): void
    {
        $result = $this->html("```\nblock1\n```\n\n```\nblock2\n```");
        $this->assertSame(2, substr_count($result, '<pre><code>'), 'Two separate code blocks');
    }

    // ═══════════════════════════════════════════════
    //  Round-trip: additional coverage
    // ═══════════════════════════════════════════════

    public function testRoundTripUnorderedList(): void
    {
        $original = "- item 1\n- item 2";
        $back = $this->md($this->html($original));
        $this->assertStringContainsString('- item 1', $back);
        $this->assertStringContainsString('- item 2', $back);
    }

    public function testRoundTripOrderedList(): void
    {
        $original = "1. first\n2. second";
        $back = $this->md($this->html($original));
        $this->assertStringContainsString('1. first', $back);
        $this->assertStringContainsString('2. second', $back);
    }

    public function testRoundTripBlockquote(): void
    {
        $original = '> quoted text';
        $back = $this->md($this->html($original));
        $this->assertStringContainsString('> quoted text', $back);
    }

    public function testRoundTripImage(): void
    {
        $original = '![alt text](photo.jpg)';
        $this->assertSame($original, $this->md($this->html($original)), 'Image round-trip');
    }

    public function testRoundTripInlineCode(): void
    {
        $original = 'Use `printf()` for output.';
        $this->assertSame($original, $this->md($this->html($original)), 'Inline code round-trip');
    }

    public function testRoundTripFencedCodeBlock(): void
    {
        $original = "```\nsome code\n```";
        $back = $this->md($this->html($original));
        $this->assertStringContainsString('```', $back);
        $this->assertStringContainsString('some code', $back);
    }
}
