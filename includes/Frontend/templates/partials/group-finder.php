<?php

/**
 * Werkzeugleiste ueber dem Raster (group-grid.php): mit `finder` die
 * Knopfreihen fuer Veranstalter, Kategorie und Zielgruppe, mit `search` das
 * Suchfeld - zwei Schalter wie beim Eventfinder (partials/eventfinder.php),
 * dessen Suchfeld ebenfalls am eigenen Schalter haengt.
 *
 * Dieselben Klassen wie der Eventfinder (partials/eventfinder.php), damit
 * Knoepfe, Blaetterpfeile und Umbruch gleich aussehen und dasselbe Skript die
 * Leisten einrichtet. Gefiltert wird aber anders: Alle Gruppen stehen schon in
 * der Seite, frontend.js blendet Zellen nur aus (applyGroupFinder()) und fragt
 * keinen Server. Welche Reihen und Knoepfe es gibt, rechnet
 * GroupListRenderer::finderRows() vor.
 *
 * Nicht einzeln ueberschreibbar, wie alle Partials.
 *
 * @var array $args
 */

use ChurchToolsPlugin\Frontend\Icons;

if (!defined('ABSPATH')) {
    exit;
}

$rowTexts = [
    'category' => [
        'label' => __('Kategorie', 'churchtools-plugin'),
        'prev' => __('Vorherige Kategorien anzeigen', 'churchtools-plugin'),
        'next' => __('Weitere Kategorien anzeigen', 'churchtools-plugin'),
    ],
    'target' => [
        'label' => __('Für wen', 'churchtools-plugin'),
        'prev' => __('Vorherige Zielgruppen anzeigen', 'churchtools-plugin'),
        'next' => __('Weitere Zielgruppen anzeigen', 'churchtools-plugin'),
    ],
    'homepage' => [
        'label' => __('Veranstalter', 'churchtools-plugin'),
        'prev' => __('Vorherige Veranstalter anzeigen', 'churchtools-plugin'),
        'next' => __('Weitere Veranstalter anzeigen', 'churchtools-plugin'),
    ],
];
?>
<div class="ctp-events__toolbar<?php echo $args['finder'] ? ' ctp-events__eventfinder ctp-groups__finder' : ' ctp-groups__toolbar'; ?>">
    <?php foreach ($args['finder_rows'] as $row) : ?>
        <?php
        $texts = $rowTexts[$row['key']];
        $labelId = wp_unique_id('ctp-group-finder-' . $row['key'] . '-');
        ?>
        <div class="ctp-events__finder-row ctp-groups__finder-row--<?php echo esc_attr($row['key']); ?>">
            <span class="ctp-events__finder-row-label" id="<?php echo esc_attr($labelId); ?>"><?php echo esc_html($texts['label']); ?></span>
            <div class="ctp-events__finder-strip">
                <div class="ctp-events__finder-group" role="group" aria-labelledby="<?php echo esc_attr($labelId); ?>">
                    <button
                        type="button"
                        class="ctp-events__finder-btn ctp-events__finder-btn--active"
                        data-ctp-group-filter="<?php echo esc_attr($row['key']); ?>"
                        data-ctp-group-value=""
                        aria-pressed="true"
                    ><?php esc_html_e('Alle', 'churchtools-plugin'); ?></button>
                    <?php foreach ($row['options'] as $option) : ?>
                        <button
                            type="button"
                            class="ctp-events__finder-btn"
                            data-ctp-group-filter="<?php echo esc_attr($row['key']); ?>"
                            data-ctp-group-value="<?php echo esc_attr($option); ?>"
                            aria-pressed="false"
                        ><?php echo esc_html($row['labels'][$option] ?? $option); ?></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="ctp-events__finder-scroll ctp-events__finder-scroll--prev" aria-label="<?php echo esc_attr($texts['prev']); ?>" hidden>
                    <span aria-hidden="true">&lsaquo;</span>
                </button>
                <button type="button" class="ctp-events__finder-scroll ctp-events__finder-scroll--next" aria-label="<?php echo esc_attr($texts['next']); ?>" hidden>
                    <span aria-hidden="true">&rsaquo;</span>
                </button>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($args['search']) : ?>
    <div class="ctp-events__search">
        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons:: returns fixed, hard-coded SVG markup with no request input (see Icons.php docblock). ?>
        <?php echo Icons::search(); ?>
        <input
            type="search"
            class="ctp-events__search-input"
            placeholder="<?php esc_attr_e('Gruppen durchsuchen …', 'churchtools-plugin'); ?>"
            aria-label="<?php esc_attr_e('Gruppen durchsuchen', 'churchtools-plugin'); ?>"
        />
    </div>
    <?php endif; ?>
    <p class="ctp-events__toolbar-empty" role="status" hidden><?php esc_html_e('Keine Gruppen gefunden.', 'churchtools-plugin'); ?></p>
</div>
