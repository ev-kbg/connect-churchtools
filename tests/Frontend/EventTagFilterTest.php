<?php

declare(strict_types=1);

namespace ChurchToolsPlugin\Tests\Frontend;

use ChurchToolsPlugin\Db\EventRepository;
use ChurchToolsPlugin\Frontend\EventPager;
use ChurchToolsPlugin\Frontend\EventQueryCache;
use ChurchToolsPlugin\Frontend\EventTagFilter;
use ChurchToolsPlugin\Frontend\EventListRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class EventTagFilterTest extends TestCase
{
    protected function setUp(): void
    {
        ctp_test_reset_options();
        ctp_test_reset_transients();
        ctp_test_set_current_time('2026-08-18 12:00:00');
    }

    public function testParsesPerCalendarOrFiltersAndMergesDuplicateCalendars(): void
    {
        $this->assertSame(
            [7 => ['Familie', 'Kinder', '42'], 12 => ['Musik']],
            EventTagFilter::parse('7:Familie|Kinder;12:Musik;7:42|Familie')
        );
        $this->assertSame([], EventTagFilter::parse(''));
    }

    public function testCalendarNamesResolveAndMergeWithIds(): void
    {
        ctp_test_set_option('ctp_settings', ['calendars' => [
            7 => ['name' => 'Gottesdienste', 'enabled' => true],
            12 => ['name' => 'Jugend', 'enabled' => true],
        ]]);
        $value = ' gottesdienste :Familie|Kinder;12:Musik;7:42';
        $expected = [7 => ['Familie', 'Kinder', '42'], 12 => ['Musik']];
        $this->assertSame($expected, EventTagFilter::parse($value));
        $renderer = new EventListRenderer();
        $args = (new ReflectionMethod(EventListRenderer::class, 'prepareArgs'))->invoke($renderer, ['tags' => $value]);
        $this->assertSame($expected, $args['tag_filters']);
        $this->assertSame($value, $args['tags']);
    }

    public function testUnknownCalendarNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventTagFilter::parse('Unknown:Familie');
    }

    public function testAmbiguousCalendarNameIsRejected(): void
    {
        ctp_test_set_option('ctp_settings', ['calendars' => [
            7 => ['name' => 'Jugend', 'enabled' => true],
            12 => ['name' => 'JUGEND', 'enabled' => true],
        ]]);
        $this->expectException(InvalidArgumentException::class);
        EventTagFilter::parse('Jugend:Familie');
    }

    /** @dataProvider invalidFilters */
    public function testInvalidFiltersAreRejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventTagFilter::parse($value);
    }

    public function invalidFilters(): array
    {
        return [['Familie'], ['0:Musik'], ['7:'], ['7:A|'], ['7:A;'], [str_repeat('x', 4001)]];
    }

    public function testMatchesIdsOrNamesOnlyWithinTheirCalendar(): void
    {
        $events = [
            ['id' => 1, 'ct_calendar_id' => 7, 'raw_data' => ['tags' => [['id' => 42, 'name' => 'Familie']]]],
            ['id' => 2, 'ct_calendar_id' => 7, 'raw_data' => ['tags' => [['id' => 9, 'name' => 'Kinder']]]],
            ['id' => 3, 'ct_calendar_id' => 12, 'raw_data' => ['tags' => [['id' => 42, 'name' => 'Familie']]]],
            ['id' => 4, 'ct_calendar_id' => 12, 'raw_data' => '{"appointment":{"tags":[{"id":10,"name":"Musik"}]}}'],
            ['id' => 5, 'ct_calendar_id' => 30, 'raw_data' => ''],
            ['id' => 6, 'ct_calendar_id' => 7, 'raw_data' => '{}'],
        ];
        $this->assertSame(
            [1, 2, 4, 5],
            array_column(EventTagFilter::apply($events, EventTagFilter::parse('7:42|kinder;12:Musik')), 'id')
        );
    }

    public function testRepositoryFiltersBeforeLimitsAndKeepsCalendarsWithoutRules(): void
    {
        $this->seed();
        $repository = new EventRepository();
        $filters = EventTagFilter::parse('7:Familie');
        $first = $repository->findInWindow([7, 12], null, null, 1, 0, '', $filters);
        $second = $repository->findInWindow([7, 12], null, null, 1, 1, '', $filters);

        $this->assertSame([2], array_map('intval', array_column($first, 'ct_event_id')));
        $this->assertSame([3], array_map('intval', array_column($second, 'ct_event_id')));
        $this->assertFalse($repository->hasEventsFrom([7], '2026-11-01 00:00:00', $filters));
        $this->assertSame([12], $repository->calendarIdsWithUpcoming([7, 12], [7 => ['Missing']]));
        $this->assertSame([], $repository->findInWindow([7], null, null, 1, 0, 'Unmatched', $filters));
    }

    public function testPagingSearchAndCacheKeepTagRestrictions(): void
    {
        $this->seed();
        $filters = EventTagFilter::parse('7:Familie');
        $first = EventPager::load([7], 0, 2, 1, 0, $filters);
        $second = EventPager::load([7], $first['next_page'], 2, 1, $first['next_offset'], $filters);
        $this->assertSame([2], array_map('intval', array_column($first['events'], 'ct_event_id')));
        $this->assertSame([], $second['events']);
        $this->assertNull($second['next_page']);
        $this->assertCount(3, EventQueryCache::findUpcoming([7], 0));
        $this->assertCount(1, EventQueryCache::findUpcoming([7], 0, $filters));
        $this->assertCount(1, EventQueryCache::findMatching([7], 'Match', null, null, 1, $filters));
        $this->assertSame([], EventQueryCache::calendarIdsWithUpcoming([7], [7 => ['Missing']]));
    }

    public function testBothAjaxConfigurationsAndRequestsCarryTheTags(): void
    {
        $renderer = new EventListRenderer();
        $prepare = new ReflectionMethod(EventListRenderer::class, 'prepareArgs');
        $args = $prepare->invoke($renderer, ['tags' => '7:Familie|Kinder']);
        $args['next_page'] = 1;
        $args['next_offset'] = 0;
        foreach (['pagingConfig', 'resultsConfig'] as $name) {
            $config = (new ReflectionMethod(EventListRenderer::class, $name))->invoke($renderer, $args);
            $this->assertSame('7:Familie|Kinder', $config['tags']);
        }
        $script = (string) file_get_contents(CTP_PLUGIN_DIR . 'assets/js/frontend.js');
        $this->assertSame(2, substr_count($script, "tags: config.tags || '',"));
    }

    public function testPagerSkipsWindowsWithoutMatchingTags(): void
    {
        $this->seed();
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->query($wpdb->prepare(
            'UPDATE wp_ctp_events SET raw_data = %s WHERE ct_event_id = 4',
            '{"tags":[{"id":42,"name":"Familie"}]}'
        ));
        $filters = EventTagFilter::parse('7:Familie');
        $first = EventPager::load([7], 0, 1, 1, 0, $filters);
        $second = EventPager::load([7], $first['next_page'], 1, 1, $first['next_offset'], $filters);
        $last = EventPager::load([7], $second['next_page'], 1, 1, $second['next_offset'], $filters);
        $this->assertSame([2], array_map('intval', array_column($first['events'], 'ct_event_id')));
        $this->assertSame([4], array_map('intval', array_column($second['events'], 'ct_event_id')));
        $this->assertGreaterThan($first['page'], $second['page']);
        $this->assertSame([], $last['events']);
        $this->assertNull($last['next_page']);
    }

    private function seed(): void
    {
        $wpdb = ctp_test_install_wpdb();
        $wpdb->seedEvent(7, '2026-08-20 10:00:00', '2026-08-20 11:00:00', null, 1, ['title' => 'Unmatched']);
        $wpdb->seedEvent(7, '2026-08-21 10:00:00', '2026-08-21 11:00:00', null, 2, ['title' => 'Match']);
        $wpdb->seedEvent(12, '2026-08-22 10:00:00', '2026-08-22 11:00:00', null, 3);
        $wpdb->seedEvent(7, '2026-12-01 10:00:00', '2026-12-01 11:00:00', null, 4);
        $wpdb->query($wpdb->prepare(
            'UPDATE wp_ctp_events SET raw_data = %s WHERE ct_event_id = 2',
            '{"tags":[{"id":42,"name":"Familie"}]}'
        ));
    }
}
