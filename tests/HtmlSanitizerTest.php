<?php declare(strict_types=1);

class HtmlSanitizerTest extends MantraTestCase
{
    public function testDangerousMarkupIsRemovedAndFormattingIsPreserved(): void
    {
        $html = '<script>alert(1)</script>'
            . '<p onclick="alert(2)" style="background:url(javascript:alert(3))">Hello <strong>world</strong></p>'
            . '<a href="javascript:alert(4)" target="_blank">bad link</a>';

        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('script', strtolower($result));
        $this->assertStringNotContainsString('onclick', strtolower($result));
        $this->assertStringNotContainsString('style=', strtolower($result));
        $this->assertStringNotContainsString('javascript:', strtolower($result));
        $this->assertStringContainsString('<p>Hello <strong>world</strong></p>', $result);
        $this->assertStringContainsString('rel="noopener noreferrer"', $result);
    }

    public function testSafeEditorMarkupRemainsUsable(): void
    {
        $html = '<h2>Heading</h2><p><a href="https://example.com">Link</a></p>'
            . '<img src="/uploads/image.png" alt="Image" onerror="alert(1)">';

        $result = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('<h2>Heading</h2>', $result);
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('src="/uploads/image.png"', $result);
        $this->assertStringNotContainsString('onerror', $result);
    }
}
