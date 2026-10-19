<?php

declare(strict_types=1);

namespace ChurchToolsPlugin\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class EventFinderTemplateTest extends TestCase
{
    public function testFinderOnlyOffersCalendarSelectionAndOptionalSearch(): void
    {
        $args = [
            'toolbar_config' => ['show_calendar_name' => 1],
            'search' => true,
        ];
        $filterCalendars = [
            ['id' => 12, 'name' => 'EC-GRUPPEN', 'color' => '#123456'],
        ];

        ob_start();
        require CTP_PLUGIN_DIR . 'includes/Frontend/templates/partials/eventfinder.php';
        $html = (string) ob_get_clean();

        $this->assertStringNotContainsString('Welche Angebote sprechen dich an?', $html);
        $this->assertStringNotContainsString('Zeitraum', $html);
        $this->assertStringNotContainsString('Jederzeit', $html);
        $this->assertStringNotContainsString('data-ctp-finder-timeframe', $html);
        $this->assertStringContainsString('aria-label="Kalender auswählen"', $html);
        $this->assertStringContainsString('data-ctp-finder-calendar="12"', $html);
        $this->assertStringContainsString('EC-GRUPPEN', $html);
        $this->assertStringContainsString('ctp-events__search-input', $html);
    }
}
