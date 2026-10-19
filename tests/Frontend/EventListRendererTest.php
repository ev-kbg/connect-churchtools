<?php

declare(strict_types=1);

namespace ChurchToolsPlugin\Tests\Frontend;

use ChurchToolsPlugin\Frontend\EventListRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class EventListRendererTest extends TestCase
{
    protected function setUp(): void
    {
        ctp_test_reset_options();
    }

    /**
     * primeAttachmentCache() holt die Bilder einer Seite in einem Zug in den
     * Objekt-Cache und spart damit zwei Abfragen je Bild (55 auf 5 bei 26
     * Bildern, gemessen gegen die Testumgebung). Dafuer ruft es
     * _prime_post_caches() auf - eine Kernfunktion, die WordPress zwar in jeder
     * WP_Query selbst benutzt, deren fuehrender Unterstrich aber sagt: nicht
     * Teil der oeffentlichen API.
     *
     * Faellt sie irgendwann weg, darf daraus kein Fatal Error auf jeder Seite
     * mit Terminen werden - ohne sie werden die Bilder eben wieder einzeln
     * nachgeschlagen. Dieser Bootstrap definiert sie nicht (er stubbt nur, was
     * die getesteten Klassen wirklich brauchen), der Test laeuft hier also
     * genau in dem Zustand, gegen den die Absicherung schuetzt.
     */
    public function testAttachmentPrimingSurvivesAMissingCoreHelper(): void
    {
        $this->assertFalse(
            function_exists('_prime_post_caches'),
            'Sobald der Bootstrap diese Funktion stubbt, prueft dieser Test nicht mehr, was er pruefen soll.'
        );

        $method = new ReflectionMethod(EventListRenderer::class, 'primeAttachmentCache');

        $method->invoke(null, [
            ['ct_calendar_id' => 7, 'attachment_id' => 42],
            ['ct_calendar_id' => 7, 'attachment_id' => 0],
        ], [
            7 => ['default_image_id' => 99],
        ]);

        // Kein Fatal Error bis hierher ist das Ergebnis.
        $this->addToAssertionCount(1);
    }

    /**
     * Ohne importierten Anhang darf die ChurchTools-Adresse nicht ersatzweise
     * ausgeliefert werden: Der Import laedt mit download_url() und damit ohne
     * Anmeldung, genau wie der Browser eines Besuchers. Was dort scheiterte,
     * scheitert auch dort. Der frueher hier stehende Rueckfall lieferte ein
     * garantiert kaputtes Bild und verhinderte zugleich das Standardbild des
     * Kalenders - und selbst eine ladende Adresse hiesse, dass Besucher von
     * der ChurchTools-Domain laden.
     */
    public function testDropsTheRemoteImageUrlWhenNothingWasImported(): void
    {
        $method = new ReflectionMethod(EventListRenderer::class, 'withCalendarMeta');
        $renderer = new EventListRenderer();

        $events = $method->invoke($renderer, [[
            'id' => 1,
            'ct_event_id' => 6781,
            'ct_calendar_id' => 32,
            'title' => 'Kindergottesdienst',
            'subtitle' => '',
            'description' => '',
            'start_date' => '2026-09-06 10:30:00',
            'end_date' => '2026-09-06 12:00:00',
            'all_day' => 0,
            'location' => '',
            'image_url' => 'https://musterkirche.church.tools/images/9244/abc?w=1600&h=1600&fit=max',
            'attachment_id' => 0,
        ]], 'none', []);

        $this->assertSame('', $events[0]['image_url']);
        $this->assertSame('', $events[0]['image_srcset']);
    }

    public function testResolvesTheChurchToolsLinkOnlyForAbsoluteHttpUrls(): void
    {
        $method = new ReflectionMethod(EventListRenderer::class, 'resolveExternalUrl');
        $valid = $method->invoke(null, [
            'raw_data' => wp_json_encode([
                'appointment' => [
                    'base' => ['link' => ' https://example.test/info?a=1&b=2 '],
                ],
            ]),
        ]);
        $unsafe = $method->invoke(null, [
            'raw_data' => wp_json_encode([
                'appointment' => [
                    'base' => ['link' => 'javascript:alert(1)'],
                ],
            ]),
        ]);
        $missing = $method->invoke(null, ['raw_data' => '{"appointment":{"base":{}}}']);

        $this->assertSame('https://example.test/info?a=1&b=2', $valid);
        $this->assertSame('', $unsafe);
        $this->assertSame('', $missing);
    }

    public function testCalendarNameVisibilityIsPreservedInBothAjaxConfigs(): void
    {
        $renderer = new EventListRenderer();
        $prepareArgs = new ReflectionMethod(EventListRenderer::class, 'prepareArgs');
        $shown = $prepareArgs->invoke($renderer, []);
        $hidden = $prepareArgs->invoke($renderer, ['show_calendar_name' => false]);

        $this->assertNotContains('calendar', $shown['hidden_elements']);
        $this->assertContains('calendar', $hidden['hidden_elements']);
        $this->assertSame(
            count($hidden['hidden_elements']),
            count(array_unique($hidden['hidden_elements'])),
            'The instance option must not duplicate a globally hidden calendar field.'
        );

        $configArgs = array_merge($hidden, [
            'calendar_ids' => [7],
            'layout' => 'list',
            'click_behavior' => 'none',
            'month_dividers' => false,
            'months' => 2,
            'limit' => 0,
            'next_page' => 1,
            'next_offset' => 0,
        ]);
        $pagingConfig = new ReflectionMethod(EventListRenderer::class, 'pagingConfig');
        $resultsConfig = new ReflectionMethod(EventListRenderer::class, 'resultsConfig');

        $this->assertSame(0, $pagingConfig->invoke($renderer, $configArgs)['show_calendar_name']);
        $this->assertSame(0, $resultsConfig->invoke($renderer, $configArgs)['show_calendar_name']);
    }
}