<?php

declare(strict_types=1);

namespace ChurchToolsPlugin\Frontend;

use ChurchToolsPlugin\Settings;
use InvalidArgumentException;

final class EventTagFilter
{
    /** @return array<int, string[]> */
    public static function parse(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        if (strlen($value) > 4000) {
            throw new InvalidArgumentException('Tag filter is too long.');
        }

        $filters = [];
        foreach (explode(';', $value) as $entry) {
            $parts = explode(':', trim($entry), 2);
            if (count($parts) !== 2 || trim($parts[0]) === '') {
                throw new InvalidArgumentException('Expected tags="Gottesdienste:Familie|Kinder;12:Musik".');
            }

            $tags = array_map('trim', explode('|', $parts[1]));
            if (in_array('', $tags, true)) {
                throw new InvalidArgumentException('Each calendar tag filter needs at least one tag.');
            }

            $id = self::resolveCalendar(trim($parts[0]));
            $filters[$id] = array_values(array_unique(array_merge($filters[$id] ?? [], $tags)));
        }

        ksort($filters);

        return $filters;
    }

    private static function resolveCalendar(string $reference): int
    {
        if (ctype_digit($reference)) {
            if ((int) $reference < 1) {
                throw new InvalidArgumentException('Calendar IDs must be positive.');
            }

            return (int) $reference;
        }

        $matches = [];
        foreach (Settings::get()['calendars'] as $id => $calendar) {
            if (mb_strtolower($calendar['name']) === mb_strtolower($reference)) {
                $matches[] = (int) $id;
            }
        }

        if (count($matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Calendar "%s" is unknown or ambiguous. Use its calendar ID in the tag filter.',
                $reference
            ));
        }

        return $matches[0];
    }

    /**
     * Tags requested via include=tags are retained in the stored API envelope.
     * Old rows without tags cannot match a configured tag restriction.
     *
     * @param array<int, array<string, mixed>> $events
     * @param array<int, string[]> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $events, array $filters): array
    {
        return array_values(array_filter($events, static function (array $event) use ($filters): bool {
            $wanted = $filters[(int) $event['ct_calendar_id']] ?? null;
            if ($wanted === null) {
                return true;
            }

            $raw = $event['raw_data'] ?? [];
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            if (!is_array($raw)) {
                return false;
            }

            $tags = $raw['tags'] ?? $raw['appointment']['tags'] ?? $raw['appointment']['base']['tags'] ?? [];
            if (!is_array($tags)) {
                return false;
            }

            foreach ($tags as $tag) {
                if (!is_array($tag)) {
                    continue;
                }
                foreach ($wanted as $reference) {
                    if (
                        (ctype_digit($reference) && (string) ($tag['id'] ?? '') === $reference)
                        || (is_string($tag['name'] ?? null) && mb_strtolower($tag['name']) === mb_strtolower($reference))
                    ) {
                        return true;
                    }
                }
            }

            return false;
        }));
    }
}
