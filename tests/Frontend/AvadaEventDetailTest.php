<?php

declare(strict_types=1);

namespace ChurchToolsPlugin\Tests\Frontend;

use ChurchToolsPlugin\Frontend\AvadaEventDetail;
use PHPUnit\Framework\TestCase;

final class AvadaEventDetailTest extends TestCase
{
    protected function setUp(): void
    {
        AvadaEventDetail::reset();
        ctp_test_reset_hooks();
    }

    protected function tearDown(): void
    {
        AvadaEventDetail::reset();
        ctp_test_reset_hooks();
    }

    public function testOrdinaryPagesAreUnchanged(): void
    {
        $contents = ['Gemeindebrief 1/2026', 'Untertitel', '<nav class="fusion-breadcrumbs">Gemeindebrief</nav>'];
        $this->assertSame($contents, AvadaEventDetail::filterTitleBar($contents));
    }

    public function testEventReplacesUnrelatedTitleSubtitleAndBreadcrumb(): void
    {
        AvadaEventDetail::registerForEvent(['title' => 'Gemeindebibelschule']);
        $callbacks = ctp_test_hook_callbacks('avada_page_title_bar_contents');
        $this->assertCount(1, $callbacks);
        $result = $callbacks[0]([
            'Gemeindebrief 1/2026',
            'Unrelated subtitle',
            '<nav class="fusion-breadcrumbs"><span class="breadcrumb-leaf">Gemeindebrief 1/2026</span></nav>',
        ]);
        $this->assertSame('Gemeindebibelschule', $result[0]);
        $this->assertSame('', $result[1]);
        $this->assertStringContainsString('Gemeindebibelschule', $result[2]);
        $this->assertStringContainsString('aria-current="page"', $result[2]);
        $this->assertStringContainsString(esc_url(home_url('/')), $result[2]);
        $this->assertStringNotContainsString('Gemeindebrief', implode('', $result));
    }

    public function testSearchBoxIsPreserved(): void
    {
        AvadaEventDetail::registerForEvent(['title' => 'Gemeindebibelschule']);
        $search = '<form role="search"><input type="search"></form>';
        $result = AvadaEventDetail::filterTitleBar(['Wrong title', '', $search]);
        $this->assertSame($search, $result[2]);
    }

    public function testNoBreadcrumbIsAddedWhenDisabled(): void
    {
        AvadaEventDetail::registerForEvent(['title' => 'Gemeindebibelschule']);
        $this->assertSame(['Gemeindebibelschule', '', ''], AvadaEventDetail::filterTitleBar(['Wrong', '', '']));
    }

    public function testHiddenTitleStaysHidden(): void
    {
        AvadaEventDetail::registerForEvent(['title' => 'Gemeindebibelschule']);
        $this->assertSame(['', '', ''], AvadaEventDetail::filterTitleBar(['', '', '']));
    }

    public function testEventTitleIsEscapedInTitleBarAndBreadcrumb(): void
    {
        AvadaEventDetail::registerForEvent(['title' => '<script>alert(1)</script> & Musik']);
        $result = AvadaEventDetail::filterTitleBar(['Wrong', '', '<nav class="fusion-breadcrumbs"></nav>']);
        $this->assertStringNotContainsString('<script>', implode('', $result));
        $this->assertStringContainsString('&lt;script&gt;', $result[0]);
        $this->assertStringContainsString('&amp; Musik', $result[2]);
    }
}
