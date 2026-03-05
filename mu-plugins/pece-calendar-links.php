<?php
/**
 * Plugin Name: PECE Calendar Links Shortcode
 * Description: Provides [pece_calendar_links] shortcode for "Add to Calendar" buttons (Google, Outlook, Apple).
 * Version: 1.0.0
 * Author: PECE Platform
 *
 * USAGE:
 *   [pece_calendar_links title="Event Name" date="2026-04-15" start="09:00" end="10:30" location="Community Center" description="Optional description"]
 *
 * All parameters are required except description.
 * Date format: YYYY-MM-DD
 * Time format: HH:MM (24-hour)
 *
 * Place this file in wp-content/mu-plugins/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'pece_calendar_links', 'pece_render_calendar_links' );

function pece_render_calendar_links( $atts ) {
    $atts = shortcode_atts( array(
        'title'       => 'PECE Event',
        'date'        => '',
        'start'       => '09:00',
        'end'         => '10:00',
        'location'    => '',
        'description' => '',
    ), $atts, 'pece_calendar_links' );

    if ( empty( $atts['date'] ) ) {
        return '<p style="color: #c00;">Calendar links error: date is required.</p>';
    }

    $title       = esc_attr( $atts['title'] );
    $date        = sanitize_text_field( $atts['date'] );
    $start       = sanitize_text_field( $atts['start'] );
    $end         = sanitize_text_field( $atts['end'] );
    $location    = esc_attr( $atts['location'] );
    $description = esc_attr( $atts['description'] );

    // Build datetime strings
    $date_clean  = str_replace( '-', '', $date );
    $start_clean = str_replace( ':', '', $start );
    $end_clean   = str_replace( ':', '', $end );

    // For Google Calendar: dates in UTC (approximate — convert from Pacific)
    // A more precise approach would use PHP DateTime with timezone conversion,
    // but for simplicity we use the local times with a timezone hint.
    $gc_start = $date_clean . 'T' . $start_clean . '00';
    $gc_end   = $date_clean . 'T' . $end_clean . '00';

    // Google Calendar link
    $google_url = add_query_arg( array(
        'action'   => 'TEMPLATE',
        'text'     => $title,
        'dates'    => $gc_start . '/' . $gc_end,
        'details'  => $description,
        'location' => $location,
        'ctz'      => 'America/Los_Angeles',
    ), 'https://calendar.google.com/calendar/render' );

    // Outlook Web link (outlook.live.com)
    $outlook_start = $date . 'T' . $start . ':00';
    $outlook_end   = $date . 'T' . $end . ':00';

    $outlook_url = add_query_arg( array(
        'rru'      => 'addevent',
        'subject'  => $title,
        'startdt'  => $outlook_start,
        'enddt'    => $outlook_end,
        'body'     => $description,
        'location' => $location,
        'path'     => '/calendar/action/compose',
    ), 'https://outlook.live.com/calendar/0/action/compose' );

    // Apple Calendar / generic .ics download link
    // This generates a data URI with a minimal .ics file
    $uid = md5( $title . $date . $start ) . '@' . parse_url( home_url(), PHP_URL_HOST );
    $dtstamp = gmdate( 'Ymd\THis\Z' );

    $ics  = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//PECE//Calendar//EN\r\nCALSCALE:GREGORIAN\r\n";
    $ics .= "BEGIN:VTIMEZONE\r\nTZID:America/Los_Angeles\r\n";
    $ics .= "BEGIN:STANDARD\r\nDTSTART:19701101T020000\r\nRRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=11\r\nTZOFFSETFROM:-0700\r\nTZOFFSETTO:-0800\r\nTZNAME:PST\r\nEND:STANDARD\r\n";
    $ics .= "BEGIN:DAYLIGHT\r\nDTSTART:19700308T020000\r\nRRULE:FREQ=YEARLY;BYDAY=2SU;BYMONTH=3\r\nTZOFFSETFROM:-0800\r\nTZOFFSETTO:-0700\r\nTZNAME:PDT\r\nEND:DAYLIGHT\r\n";
    $ics .= "END:VTIMEZONE\r\n";
    $ics .= "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:{$dtstamp}\r\n";
    $ics .= "DTSTART;TZID=America/Los_Angeles:{$gc_start}\r\n";
    $ics .= "DTEND;TZID=America/Los_Angeles:{$gc_end}\r\n";
    $ics .= "SUMMARY:" . str_replace( ',', '\\,', $title ) . "\r\n";
    if ( $location ) {
        $ics .= "LOCATION:" . str_replace( ',', '\\,', $location ) . "\r\n";
    }
    if ( $description ) {
        $ics .= "DESCRIPTION:" . str_replace( ',', '\\,', $description ) . "\r\n";
    }
    $ics .= "STATUS:CONFIRMED\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    $ics_data_uri = 'data:text/calendar;charset=utf-8,' . rawurlencode( $ics );

    // Build the HTML output
    $html = '<div class="pece-calendar-links" style="margin: 20px 0; padding: 16px; background: #f7f9fc; border: 1px solid #dde3ea; border-radius: 8px;">';
    $html .= '<p style="margin: 0 0 12px 0; font-weight: 600; color: #1B4F72;">Add to your calendar:</p>';
    $html .= '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';

    // Google Calendar button
    $html .= '<a href="' . esc_url( $google_url ) . '" target="_blank" rel="noopener" '
           . 'style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; '
           . 'background: #4285F4; color: #fff; text-decoration: none; border-radius: 4px; '
           . 'font-size: 14px; font-weight: 500;">'
           . '&#128197; Google Calendar</a>';

    // Outlook button
    $html .= '<a href="' . esc_url( $outlook_url ) . '" target="_blank" rel="noopener" '
           . 'style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; '
           . 'background: #0078D4; color: #fff; text-decoration: none; border-radius: 4px; '
           . 'font-size: 14px; font-weight: 500;">'
           . '&#128197; Outlook</a>';

    // Apple Calendar / Download .ics button
    $html .= '<a href="' . $ics_data_uri . '" download="event.ics" '
           . 'style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; '
           . 'background: #333; color: #fff; text-decoration: none; border-radius: 4px; '
           . 'font-size: 14px; font-weight: 500;">'
           . '&#128197; Apple / Download .ics</a>';

    $html .= '</div></div>';

    return $html;
}
