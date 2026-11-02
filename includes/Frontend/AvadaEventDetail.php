<?php

declare(strict_types=1);

namespace ChurchToolsPlugin\Frontend;

final class AvadaEventDetail
{
    /** @var array<string, mixed>|null */
    private static ?array $event = null;

    /** @param array<string, mixed> $event */
    public static function registerForEvent(array $event): void
    {
        self::$event = $event;
        add_filter('avada_page_title_bar_contents', [self::class, 'filterTitleBar']);
    }

    public static function reset(): void
    {
        self::$event = null;
    }

    /**
     * The virtual route has no WP_Post; Avada otherwise uses an unrelated post.
     * Its title-bar filter runs after custom page titles and archive titles.
     *
     * @param array<int, string> $contents Title, subtitle and secondary content.
     * @return array<int, string>
     */
    public static function filterTitleBar(array $contents): array
    {
        if (self::$event === null) {
            return $contents;
        }

        $title = esc_html((string) self::$event['title']);
        if (($contents[0] ?? '') !== '') {
            $contents[0] = $title;
        }
        $contents[1] = '';

        // Keep a configured search box, but never a breadcrumb to another post.
        if (strpos($contents[2] ?? '', 'fusion-breadcrumbs') !== false) {
            $contents[2] = '<nav class="fusion-breadcrumbs" aria-label="'
                . esc_attr__('Brotkrumennavigation', 'churchtools-plugin')
                . '"><ol class="awb-breadcrumb-list">'
                . '<li class="fusion-breadcrumb-item awb-breadcrumb-sep awb-home"><a class="fusion-breadcrumb-link" href="'
                . esc_url(home_url('/')) . '"><span>'
                . esc_html__('Startseite', 'churchtools-plugin') . '</span></a></li>'
                . '<li class="fusion-breadcrumb-item awb-breadcrumb-sep" aria-current="page">'
                . '<span class="breadcrumb-leaf">' . $title . '</span></li></ol></nav>';
        }

        return $contents;
    }
}
