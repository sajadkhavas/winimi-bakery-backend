<?php

namespace Tests\Unit;

use App\Support\SafeContentHtml;
use App\Support\WinimiContentEditor;
use PHPUnit\Framework\TestCase;

class SafeContentHtmlTest extends TestCase
{
    public function test_allowed_persian_editor_markup_round_trips_deterministically(): void
    {
        $html = '<h2 style="text-align: center; color: #27390c">راهنمای نگهداری</h2>'
            .'<p dir="rtl"><strong>تازه</strong> نگه دارید.</p>'
            .'<ul data-type="taskList"><li data-type="taskItem" data-checked="true">بسته‌بندی</li></ul>'
            .'<table><tbody><tr><td colspan="2">جزئیات</td></tr></tbody></table>'
            .'<details open><summary>بیشتر</summary><p>متن</p></details>'
            .'<a href="/products" target="_blank">محصولات</a>';

        $sanitized = SafeContentHtml::sanitize($html);

        $this->assertNotNull($sanitized);
        $this->assertStringContainsString('راهنمای نگهداری', $sanitized);
        $this->assertStringContainsString('text-align: center', $sanitized);
        $this->assertStringContainsString('color: #27390c', $sanitized);
        $this->assertStringContainsString('data-type="taskList"', $sanitized);
        $this->assertStringContainsString('data-checked="true"', $sanitized);
        $this->assertStringContainsString('colspan="2"', $sanitized);
        $this->assertStringContainsString('rel="noopener noreferrer"', $sanitized);
        $this->assertSame($sanitized, SafeContentHtml::sanitize($sanitized));
    }

    public function test_executable_html_and_unsafe_attributes_are_removed(): void
    {
        $html = '<p onclick="alert(1)" style="background-image:url(javascript:alert(1)); color:#27390c">متن</p>'
            .'<script>alert(1)</script>'
            .'<iframe src="https://example.test"></iframe>'
            .'<a href="javascript:alert(1)" onmouseover="alert(1)">لینک</a>'
            .'<img src="data:text/html;base64,PHNjcmlwdD4=" onload="alert(1)" alt="x">';

        $sanitized = SafeContentHtml::sanitize($html);

        $this->assertNotNull($sanitized);
        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('<iframe', $sanitized);
        $this->assertStringNotContainsString('onclick=', $sanitized);
        $this->assertStringNotContainsString('onmouseover=', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);
        $this->assertStringNotContainsString('data:text/html', $sanitized);
        $this->assertStringNotContainsString('background-image', $sanitized);
        $this->assertStringContainsString('color: #27390c', $sanitized);
    }

    public function test_unified_editor_exposes_source_but_not_executable_embed_tools(): void
    {
        $tools = WinimiContentEditor::tools();

        $this->assertContains('source', $tools);
        $this->assertContains('media', $tools);
        $this->assertContains('table', $tools);
        $this->assertContains('color', $tools);
        $this->assertContains('highlight', $tools);
        $this->assertNotContains('code-block', $tools);
        $this->assertNotContains('oembed', $tools);
    }
}
