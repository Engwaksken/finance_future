<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    #[DataProvider('unsafeUrls')]
    public function test_encoded_and_unsafe_urls_are_removed(string $url): void
    {
        $clean = HtmlSanitizer::clean('<a href="'.$url.'">Link</a><img src="'.$url.'">');

        $this->assertStringNotContainsString('href=', $clean);
        $this->assertStringNotContainsString('src=', $clean);
        $this->assertStringContainsString('Link', $clean);
    }

    public static function unsafeUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'encoded scheme' => ['jav&#x61;script:alert(1)'],
            'encoded whitespace' => ['java&#x09;script:alert(1)'],
            'data' => ['data:text/html,hello'],
            'file' => ['file:///etc/passwd'],
        ];
    }

    public function test_safe_formatting_urls_and_long_form_content_are_preserved(): void
    {
        $text = str_repeat('Financial news. ', 2000);
        $clean = HtmlSanitizer::clean('<h2>News</h2><p>'.$text.'</p><a href="https://example.com" target="_blank">Source</a><img src="/storage/chart.png" alt="Chart">');

        $this->assertStringContainsString($text, $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
        $this->assertStringContainsString('src="/storage/chart.png"', $clean);
    }
}
