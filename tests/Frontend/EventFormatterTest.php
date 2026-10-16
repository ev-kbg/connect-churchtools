<?php

declare(strict_types=1);

namespace ChurchToolsPlugin\Tests\Frontend;

use ChurchToolsPlugin\Frontend\EventFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Deckt die Zeitangabe ab, die in jeder Ansicht hinter dem Uhr-Symbol steht.
 * mysql2date()/get_option() kommen aus tests/bootstrap.php.
 */
final class EventFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['ctp_test_options']['time_format'] = 'H:i';
    }

    private function event(array $overrides = []): array
    {
        return array_merge([
            'all_day' => 0,
            'start_date' => '2026-08-30 10:30:00',
            'end_date' => '2026-08-30 12:00:00',
        ], $overrides);
    }

    public function testTimeRangeCarriesTheUnit(): void
    {
        $this->assertSame('10:30–12:00 Uhr', EventFormatter::timeRange($this->event()));
    }

    /**
     * Endet der Termin an einem anderen Tag, steht der Gedankenstrich mit
     * Abstaenden - die Einheit haengt trotzdem genau einmal am Ende.
     */
    public function testTimeRangeAcrossTwoDaysKeepsOneUnit(): void
    {
        $range = EventFormatter::timeRange($this->event(['end_date' => '2026-08-31 12:00:00']));

        $this->assertSame('10:30 – 12:00 Uhr', $range);
    }

    /**
     * Im 12-Stunden-Format sagt am/pm dasselbe schon selbst; "10:30 am Uhr"
     * waere doppelt und falsch.
     */
    public function testTwelveHourFormatGetsNoUnit(): void
    {
        $GLOBALS['ctp_test_options']['time_format'] = 'g:i a';

        $this->assertSame('10:30 am–12:00 pm', EventFormatter::timeRange($this->event()));
    }

    /**
     * Ein maskiertes "a" im Formatstring ist ein Buchstabe, kein am/pm - der
     * Zusatz muss dort bleiben.
     */
    public function testEscapedLetterInFormatDoesNotCountAsMeridiem(): void
    {
        $GLOBALS['ctp_test_options']['time_format'] = 'H:i\\a';

        $this->assertStringEndsWith('Uhr', EventFormatter::timeRange($this->event()));
    }

    public function testAllDayEventHasNoTimeLine(): void
    {
        $this->assertSame('', EventFormatter::timeRange($this->event(['all_day' => 1])));
    }

    /** Absaetze, Zeilen und Aufzaehlungszeichen bleiben; gekuerzt wird an Wortgrenzen. */
    public function testTrimmingKeepsTheLinesOfTheText(): void
    {
        $text = "Wir treffen uns wöchentlich.\r\n\r\nDabei:\n•\tLobpreis\n•\tGebet und Austausch über die Bibel";

        $this->assertSame(
            "Wir treffen uns wöchentlich.\n\nDabei:\n•\tLobpreis\n•\tGebet…",
            EventFormatter::trimWordsKeepingLines($text, 9)
        );
    }

    public function testAShortTextStaysWhole(): void
    {
        $this->assertSame("Kurz.\nZweite Zeile", EventFormatter::trimWordsKeepingLines("  Kurz.\nZweite Zeile\n\n", 40));
        $this->assertSame('', EventFormatter::trimWordsKeepingLines("\n \n", 40));
    }

    /** Mehrere Leerzeilen am Stueck werden zu einer - Luft kostet in der Kachel Platz. */
    public function testRunsOfBlankLinesBecomeOne(): void
    {
        $this->assertSame("Eins\n\nZwei", EventFormatter::trimWordsKeepingLines("Eins\n\n \n\n\nZwei", 40));
    }

    /** Eine Adresse ist ein Wort: Sie steht ganz da oder gar nicht. */
    public function testALinkIsNeverCutInHalf(): void
    {
        $this->assertSame('Anmeldung unter…', EventFormatter::trimWordsKeepingLines('Anmeldung unter https://musterkirche.de/anmeldung bitte', 2));
    }

    public function testContainsHtml(): void
    {
        $this->assertTrue(EventFormatter::containsHtml('<p>Text</p>'));
        $this->assertTrue(EventFormatter::containsHtml('Text<br/>mehr'));
        $this->assertFalse(EventFormatter::containsHtml('Kinder < 12 Jahre, Eltern > 30'));
        $this->assertFalse(EventFormatter::containsHtml("Zeile\nZeile"));
    }

    public function testDescriptionRendersMarkdownLinksAndEscapedLineBreaks(): void
    {
        $description = "Weitere Infos findest Du hier:\\\n[Gemeindefreizeit 2027](https://ev-kirche-kirchberg.de/gemeindefreizeit-2027/)";
        $html = EventFormatter::descriptionHtml($description);

        $this->assertStringContainsString('Weitere Infos findest Du hier:<br />', $html);
        $this->assertStringContainsString(
            '<a href="https://ev-kirche-kirchberg.de/gemeindefreizeit-2027/">Gemeindefreizeit 2027</a>',
            $html
        );
        $this->assertStringNotContainsString('[Gemeindefreizeit 2027](', $html);
        $this->assertStringNotContainsString("here:\\", $html);
    }

    public function testDescriptionEscapesMarkdownLinkLabelsAndIgnoresNonHttpSchemes(): void
    {
        $html = EventFormatter::descriptionHtml('[Information & Anmeldung](https://example.org/) [unsafe](javascript:alert(1))');

        $this->assertStringContainsString('<a href="https://example.org/">Information &amp; Anmeldung</a>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }
}
