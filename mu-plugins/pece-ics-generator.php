<?php
/**
 * Plugin Name: PECE ICS Calendar Invite Generator
 * Description: Hooks into Forminator RSVP form submissions to generate and email .ics calendar invites.
 * Version: 1.0.0
 * Author: PECE Platform
 *
 * SETUP INSTRUCTIONS:
 * 1. Place this file in wp-content/mu-plugins/
 * 2. Update PECE_RSVP_FORM_ID to match your Forminator RSVP form ID
 * 3. Update the field mapping constants to match your form field names
 * 4. Update PECE_DEFAULT_ORGANIZER_EMAIL to your admin/noreply email
 *
 * HOW IT WORKS:
 * - Listens for Forminator form submissions on the specified RSVP form
 * - Extracts attendee name, email, and event details from the form data
 * - Generates a standards-compliant iCalendar (.ics) file
 * - Emails the .ics file as an attachment to the attendee
 * - Includes proper VTIMEZONE for America/Los_Angeles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =============================================================
// CONFIGURATION — UPDATE THESE VALUES FOR YOUR SETUP
// =============================================================

// The Forminator form ID for your RSVP form (find in Forminator > Forms)
define( 'PECE_RSVP_FORM_ID', 0 ); // <-- CHANGE THIS to your form ID

// Forminator field name mappings (match your form's field names/IDs)
// These are the Forminator field slugs, e.g., 'name-1', 'email-1'
define( 'PECE_FIELD_ATTENDEE_NAME', 'name-1' );
define( 'PECE_FIELD_ATTENDEE_EMAIL', 'email-1' );
define( 'PECE_FIELD_EVENT_TITLE', 'text-1' );       // Or pull from event post
define( 'PECE_FIELD_EVENT_DATE', 'date-1' );         // Format: YYYY-MM-DD
define( 'PECE_FIELD_EVENT_START_TIME', 'time-1' );   // Format: HH:MM
define( 'PECE_FIELD_EVENT_END_TIME', 'time-2' );     // Format: HH:MM
define( 'PECE_FIELD_EVENT_LOCATION', 'text-2' );     // Physical or virtual location

// Default organizer email (appears as the invite sender in calendar apps)
define( 'PECE_DEFAULT_ORGANIZER_EMAIL', 'noreply@pomonaece.org' );

// Timezone for events (IANA timezone identifier)
define( 'PECE_EVENT_TIMEZONE', 'America/Los_Angeles' );

// =============================================================
// MAIN HOOK — Fires after Forminator form submission
// =============================================================

add_action( 'forminator_custom_form_submit_before_set_fields', 'pece_handle_rsvp_submission', 10, 3 );

function pece_handle_rsvp_submission( $entry, $form_id, $field_data_array ) {
    // Only process our RSVP form
    if ( (int) $form_id !== PECE_RSVP_FORM_ID ) {
        return;
    }

    // Build an associative array from field data for easier access
    $fields = array();
    foreach ( $field_data_array as $field ) {
        $fields[ $field['name'] ] = $field['value'];
    }

    // Extract values (with fallbacks)
    $attendee_name  = isset( $fields[ PECE_FIELD_ATTENDEE_NAME ] ) ? sanitize_text_field( $fields[ PECE_FIELD_ATTENDEE_NAME ] ) : '';
    $attendee_email = isset( $fields[ PECE_FIELD_ATTENDEE_EMAIL ] ) ? sanitize_email( $fields[ PECE_FIELD_ATTENDEE_EMAIL ] ) : '';
    $event_title    = isset( $fields[ PECE_FIELD_EVENT_TITLE ] ) ? sanitize_text_field( $fields[ PECE_FIELD_EVENT_TITLE ] ) : 'PECE Event';
    $event_date     = isset( $fields[ PECE_FIELD_EVENT_DATE ] ) ? sanitize_text_field( $fields[ PECE_FIELD_EVENT_DATE ] ) : '';
    $start_time     = isset( $fields[ PECE_FIELD_EVENT_START_TIME ] ) ? sanitize_text_field( $fields[ PECE_FIELD_EVENT_START_TIME ] ) : '09:00';
    $end_time       = isset( $fields[ PECE_FIELD_EVENT_END_TIME ] ) ? sanitize_text_field( $fields[ PECE_FIELD_EVENT_END_TIME ] ) : '10:00';
    $location       = isset( $fields[ PECE_FIELD_EVENT_LOCATION ] ) ? sanitize_text_field( $fields[ PECE_FIELD_EVENT_LOCATION ] ) : '';

    // Validate required fields
    if ( empty( $attendee_email ) || empty( $event_date ) ) {
        error_log( 'PECE ICS: Missing required fields (email or date). Skipping .ics generation.' );
        return;
    }

    // Generate the .ics content
    $ics_content = pece_generate_ics(
        $event_title,
        $event_date,
        $start_time,
        $end_time,
        $location,
        $attendee_name,
        $attendee_email
    );

    if ( empty( $ics_content ) ) {
        error_log( 'PECE ICS: Failed to generate .ics content.' );
        return;
    }

    // Write .ics to a temporary file
    $temp_dir  = get_temp_dir();
    $ics_file  = $temp_dir . 'pece-invite-' . wp_generate_password( 8, false ) . '.ics';
    $written   = file_put_contents( $ics_file, $ics_content );

    if ( false === $written ) {
        error_log( 'PECE ICS: Failed to write temp .ics file.' );
        return;
    }

    // Send the email with .ics attachment
    $subject = 'Calendar Invite: ' . $event_title;
    $body    = sprintf(
        "Hi %s,\n\nThank you for RSVPing to %s!\n\nPlease find the calendar invite attached. "
        . "You can also add this event to your calendar using the links on the confirmation page.\n\n"
        . "Event: %s\nDate: %s\nTime: %s - %s\nLocation: %s\n\n"
        . "Best regards,\nPomona Early Childhood Ecosystems Team",
        $attendee_name ?: 'there',
        $event_title,
        $event_title,
        $event_date,
        $start_time,
        $end_time,
        $location ?: 'TBD'
    );

    $headers     = array( 'Content-Type: text/plain; charset=UTF-8' );
    $attachments = array( $ics_file );

    $sent = wp_mail( $attendee_email, $subject, $body, $headers, $attachments );

    if ( ! $sent ) {
        error_log( 'PECE ICS: wp_mail() failed to send .ics to ' . $attendee_email );
    }

    // Clean up temp file
    @unlink( $ics_file );
}

// =============================================================
// ICS GENERATION — Standards-compliant iCalendar output
// =============================================================

function pece_generate_ics( $title, $date, $start_time, $end_time, $location, $attendee_name, $attendee_email ) {
    // Parse date and times
    // Expected formats: date = YYYY-MM-DD, times = HH:MM (24h)
    $date_clean = preg_replace( '/[^0-9\-]/', '', $date );
    $start_clean = preg_replace( '/[^0-9:]/', '', $start_time );
    $end_clean   = preg_replace( '/[^0-9:]/', '', $end_time );

    // Build datetime strings in iCalendar format: YYYYMMDDTHHMMSS
    $start_dt = str_replace( '-', '', $date_clean ) . 'T' . str_replace( ':', '', $start_clean ) . '00';
    $end_dt   = str_replace( '-', '', $date_clean ) . 'T' . str_replace( ':', '', $end_clean ) . '00';

    // Generate a unique ID for this event
    $uid = wp_generate_uuid4() . '@' . parse_url( home_url(), PHP_URL_HOST );

    // Current timestamp for DTSTAMP
    $dtstamp = gmdate( 'Ymd\THis\Z' );

    // Escape special characters for iCalendar text fields
    $title_escaped    = pece_ics_escape_text( $title );
    $location_escaped = pece_ics_escape_text( $location );
    $description      = pece_ics_escape_text( 'RSVP confirmed for ' . $title . '. Organized by Pomona Early Childhood Ecosystems Transformation Accelerator.' );

    // Build the .ics content with VTIMEZONE for America/Los_Angeles
    // This ensures correct time display across all calendar clients including Outlook
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//PECE Platform//Calendar Invite//EN\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:REQUEST\r\n";

    // VTIMEZONE component for America/Los_Angeles (Pacific Time)
    // Including both standard and daylight transitions for Outlook compatibility
    $ics .= "BEGIN:VTIMEZONE\r\n";
    $ics .= "TZID:America/Los_Angeles\r\n";
    $ics .= "BEGIN:STANDARD\r\n";
    $ics .= "DTSTART:19701101T020000\r\n";
    $ics .= "RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=11\r\n";
    $ics .= "TZOFFSETFROM:-0700\r\n";
    $ics .= "TZOFFSETTO:-0800\r\n";
    $ics .= "TZNAME:PST\r\n";
    $ics .= "END:STANDARD\r\n";
    $ics .= "BEGIN:DAYLIGHT\r\n";
    $ics .= "DTSTART:19700308T020000\r\n";
    $ics .= "RRULE:FREQ=YEARLY;BYDAY=2SU;BYMONTH=3\r\n";
    $ics .= "TZOFFSETFROM:-0800\r\n";
    $ics .= "TZOFFSETTO:-0700\r\n";
    $ics .= "TZNAME:PDT\r\n";
    $ics .= "END:DAYLIGHT\r\n";
    $ics .= "END:VTIMEZONE\r\n";

    // VEVENT component
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:" . $uid . "\r\n";
    $ics .= "DTSTAMP:" . $dtstamp . "\r\n";
    $ics .= "DTSTART;TZID=America/Los_Angeles:" . $start_dt . "\r\n";
    $ics .= "DTEND;TZID=America/Los_Angeles:" . $end_dt . "\r\n";
    $ics .= "SUMMARY:" . $title_escaped . "\r\n";

    if ( ! empty( $location ) ) {
        $ics .= "LOCATION:" . $location_escaped . "\r\n";
    }

    $ics .= "DESCRIPTION:" . $description . "\r\n";
    $ics .= "ORGANIZER;CN=PECE Platform:mailto:" . PECE_DEFAULT_ORGANIZER_EMAIL . "\r\n";

    if ( ! empty( $attendee_email ) ) {
        $cn = ! empty( $attendee_name ) ? $attendee_name : $attendee_email;
        $ics .= "ATTENDEE;CN=" . pece_ics_escape_text( $cn ) . ";RSVP=TRUE:mailto:" . $attendee_email . "\r\n";
    }

    $ics .= "STATUS:CONFIRMED\r\n";
    $ics .= "SEQUENCE:0\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";

    return $ics;
}

/**
 * Escape text for iCalendar format.
 * Per RFC 5545: backslash, semicolon, and comma must be escaped.
 * Newlines become literal \n.
 */
function pece_ics_escape_text( $text ) {
    $text = str_replace( '\\', '\\\\', $text );
    $text = str_replace( ';', '\\;', $text );
    $text = str_replace( ',', '\\,', $text );
    $text = str_replace( "\n", '\\n', $text );
    $text = str_replace( "\r", '', $text );
    return $text;
}
