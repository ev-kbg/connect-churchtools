<?php

/**
 * Gefuehrte Werkzeugleiste fuer die Layouts list/grid — Kalenderauswahl,
 * plus (wenn "search" eingeschaltet
 * ist) ein Suchfeld, in place of the plain filter dropdown/search input from
 * partials/toolbar.php (opt-in via the shortcode/block/WPBakery "eventfinder"
 * attribute, see EventListRenderer::render()). Das Suchfeld hing frueher am
 * Eventfinder statt am eigenen Schalter: Wer die Suche abwaehlte und den
 * Eventfinder anliess, bekam sie trotzdem. Not part of the theme-override contract
 * (only event-{layout}.php files are looked up via locate_template()); a
 * theme wanting a different toolbar overrides the whole layout template
 * instead.
 *
 * Buttons toggle a "ctp-events__finder-btn--active" state, which
 * assets/js/frontend.js reads twice over: once to filter the items already in
 * the DOM (applyToolbarState(), same data-ctp-calendar/data-ctp-search
 * attributes as the plain toolbar) and once to ask the server the same question
 * against the whole synced horizon (refreshFromServer(), via data-ctp-toolbar-config
 * an dieser Werkzeugleiste - nicht am Suchfeld, das es hier auch ohne geben
 * darf).
 *
 * @var array $args
 * @var array $filterCalendars
 */

use ChurchToolsPlugin\Frontend\Icons;

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="ctp-events__toolbar ctp-events__eventfinder" data-ctp-toolbar-config="<?php echo esc_attr((string) wp_json_encode($args['toolbar_config'])); ?>">
    <?php if ($filterCalendars !== []) : ?>
        <div class="ctp-events__finder-row">
            <div class="ctp-events__finder-strip">
            <div
                class="ctp-events__finder-group"
                role="group"
                aria-label="<?php esc_attr_e('Kalender auswählen', 'churchtools-plugin'); ?>"
            >
                <button
                    type="button"
                    class="ctp-events__finder-btn ctp-events__finder-btn--active"
                    data-ctp-finder-calendar=""
                    aria-pressed="true"
                ><?php esc_html_e('Alle', 'churchtools-plugin'); ?></button>
                <?php foreach ($filterCalendars as $filterCalendar) : ?>
                    <button
                        type="button"
                        class="ctp-events__finder-btn ctp-events__finder-btn--calendar"
                        data-ctp-finder-calendar="<?php echo esc_attr($filterCalendar['id']); ?>"
                        <?php if (($filterCalendar['color'] ?? '') !== '') : ?>
                            style="--ctp-accent:<?php echo esc_attr($filterCalendar['color']); ?>;"
                        <?php endif; ?>
                        aria-pressed="false"
                    ><?php echo esc_html($filterCalendar['name']); ?></button>
                <?php endforeach; ?>
            </div>
            <?php
            /*
             * Zwei Blaetterknoepfe, je einer pro Richtung. Sie sind erst da, wenn
             * das Skript sie einschaltet - ohne JavaScript scrollt die Leiste
             * weiterhin mit dem Finger, aber ein Knopf, der dann nichts tut,
             * waere schlimmer als keiner. Der nach links erscheint erst, sobald
             * tatsaechlich etwas hinter einem liegt.
             */
            ?>
            <button
                type="button"
                class="ctp-events__finder-scroll ctp-events__finder-scroll--prev"
                aria-label="<?php esc_attr_e('Vorherige Themen anzeigen', 'churchtools-plugin'); ?>"
                hidden
            >
                <span aria-hidden="true">&lsaquo;</span>
            </button>
            <button
                type="button"
                class="ctp-events__finder-scroll ctp-events__finder-scroll--next"
                aria-label="<?php esc_attr_e('Weitere Themen anzeigen', 'churchtools-plugin'); ?>"
                hidden
            >
                <span aria-hidden="true">&rsaquo;</span>
            </button>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($args['search']) : ?>
        <div class="ctp-events__search">
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons:: returns fixed, hard-coded SVG markup with no request input (see Icons.php docblock). ?>
            <?php echo Icons::search(); ?>
            <input
                type="search"
                class="ctp-events__search-input"
                placeholder="<?php esc_attr_e('Termine durchsuchen …', 'churchtools-plugin'); ?>"
                aria-label="<?php esc_attr_e('Termine durchsuchen', 'churchtools-plugin'); ?>"
            />
        </div>
    <?php endif; ?>
    <p class="ctp-events__toolbar-empty" hidden><?php esc_html_e('Keine Termine gefunden.', 'churchtools-plugin'); ?></p>
</div>
