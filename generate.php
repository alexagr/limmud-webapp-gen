<?php

require_once(__DIR__ . '/lib/vendor/autoload.php');

use Handlebars\Handlebars;
use Handlebars\Loader\FilesystemLoader;

function get_client()
{
    $client = new Google_Client();
    $client->setApplicationName('Limmud WebApp Generator');
    $client->setAuthConfig('data/client_secret.json');
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
    $client->setRedirectUri('https://' . $_SERVER['HTTP_HOST'] . '/webapp/redirect.php');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    $tokenPath = 'data/token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    } else {
        throw new Exception("Can't find token.json");
    }

    if ($client->isAccessTokenExpired()) {
        $refresh_token = $client->getRefreshToken();
        if ($refresh_token) {
            $accessToken = $client->fetchAccessTokenWithRefreshToken($refresh_token);
            if (array_key_exists('error', $accessToken)) {
                $desc = isset($accessToken['error_description']) ? ' - ' . $accessToken['error_description'] : '';
                throw new Exception('Google refused to refresh the token (' . $accessToken['error'] . $desc . '). Re-authorize via get_token.php');
            }
            if (!array_key_exists('refresh_token', $accessToken)) {
                $accessToken['refresh_token'] = $refresh_token;
            }
            $client->setAccessToken($accessToken);
        } else {
            throw new Exception("Can't get refresh token");
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }

    return $client;
}

function get_defaults()
{
    $defaults = array(
        'webapp_folder' => 'webapp',
        'event_timezone' => 'Asia/Jerusalem',
        'close_calendar_gaps' => '15',
        'close_calendar_gaps_ignore_meals' => 'yes',
        'language2_hide_content_no_data' => 'no',
        'schedule_sheet' => 'Schedule',
        'schedule_date_column' => 'date',
        'schedule_start_column' => 'start',
        'schedule_end_column' => 'end',
        'schedule_hotel_column' => 'hotel',
        'schedule_room_column' => 'room',
        'schedule_presenter_column' => 'presenter',
        'schedule_session_name_column' => 'name',
        'schedule_session_language_column' => 'language',
        'schedule_session_type_column' => 'session-type',
        'schedule_session_sabbath_column' => 'sabbath',
        'schedule_presenter2_column' => 'presenter2',
        'schedule_presenter3_column' => 'presenter3',
        'schedule_presenter4_column' => 'presenter4',
        'schedule_presenter5_column' => 'presenter5',
        'schedule_presenter6_column' => 'presenter6',
        'schedule_presenter7_column' => 'presenter7',
        'schedule_presenter8_column' => 'presenter8',
        'schedule_alt_presenter_summary_column' => 'alt-presenter-summary',
        'schedule_reg_seats_column' => 'reg-seats',
        'schedule_reg_start_column' => 'reg-start',
        'schedule_reg_category_column' => 'reg-category',
        'schedule_reg_limit_column' => 'reg-limit',
        'schedule_alt_presenter_summary2_column' => 'alt-presenter-summary-he',
        'presenter_sheet' => 'Presenters',
        'presenter_name_column' => 'name-russian',
        'presenter_name2_column' => 'name-hebrew',
        'presenter_photo_column' => 'photo',
        'presenter_bio_column' => 'cv-russian',
        'presenter_bio2_column' => 'cv-hebrew',
        'presenter_session_name_column' => 'session-name-russian',
        'presenter_session_name2_column' => 'session-name-hebrew',
        'presenter_session_desc_column' => 'session-descr-russian',
        'presenter_session_desc2_column' => 'session-descr-hebrew',
        'presenter_session_comment_column' => 'session-comment-russian',
        'presenter_session_comment2_column' => 'session-comment-hebrew',
        'presenter_session_type_column' => 'session-type',
        'presenter_session_language_column' => 'session-language',
        'presenter_session2_name_column' => 'session2-name-russian',
        'presenter_session2_name2_column' => 'session2-name-hebrew',
        'presenter_session2_desc_column' => 'session2-descr-russian',
        'presenter_session2_desc2_column' => 'session2-descr-hebrew',
        'presenter_session2_type_column' => 'session2-type',
        'presenter_session2_language_column' => 'session2-language',
        'presenter_name_first_last_column' => 'name-first-last',
        'presenter_name2_first_last_column' => 'name-he-first-last',
        'presenter_title_column' => 'title',
        'presenter_title2_column' => 'title-he',
        'session_type_sheet' => 'Tracks',
        'session_type_name_column' => 'name',
        'session_type_name2_column' => 'name-he',
        'session_type_color_column' => 'color',
        'language_sheet' => 'Languages',
        'language_name_column' => 'name',
        'language_name2_column' => 'name-he',
        'language_short_column' => 'name-short',
        'location_sheet' => 'Locations',
        'location_hotel_column' => 'hotel',
        'location_room_column' => 'room',
        'location_hotel2_column' => 'hotel-he',
        'location_room2_column' => 'room-he',
        'location_order_column' => 'order',
        'event_sheet' => 'Event',
        'event_app_title_column' => 'app-title',
        'event_name_column' => 'event-name',
        'event_second_language_column' => 'second-language',
        'event_name2_column' => 'event-name-he',
        'event_date_column' => 'event-date',
        'event_date2_column' => 'event-date-he',
        'event_poster_column' => 'poster',
        'event_poster_mobile_column' => 'poster-mobile',
        'event_poster2_column' => 'poster-he',
        'event_poster_mobile2_column' => 'poster-mobile-he',
        'event_map_column' => 'map',
        'event_map2_column' => 'map-he',
        'event_organizer_name_column' => 'organizer-name',
        'event_site_url_column' => 'site-url',
        'event_facebook_url_column' => 'facebook-url',
        'event_instagram_url_column' => 'instagram-url',
        'event_telegram_url_column' => 'telegram-url',
        'event_registration_url_column' => 'registration-url',
        'event_email_column' => 'email',
        'event_logo_column' => 'logo',
        'event_icon_column' => 'icon',
        'event_analytics_column' => 'google-analytics-tag',
        'speaker_photos_column' => 'speaker-photos',
        'second_language_ribbon_column' => 'second-language-ribbon',
        'export_bookmarks_column' => 'export-bookmarks',
        'event_theme_column' => 'theme',
        'event_short_name_column' => 'app-short-name',
        'event_icon_large_column' => 'icon-large',
        'event_theme_color_column' => 'theme-color',
        'event_background_color_column' => 'background-color'
    );
    return $defaults;
}

function get_cell($header, $row, $column, $default)
{
    $value = $default;
    $key = array_search($column, $header);
    if ((is_string($key) || is_int($key)) && array_key_exists($key, $row)) {
        $value = $row[$key];
    }
    return $value;
}

function parse_sheets($client, $config)
{
    $service = new Google_Service_Sheets($client);

    $sheet_id = $config['sheet_id'];

    # parse event
    $event = array(
        'app_name' => $config['app_name'], 'title' => 'Limmud FSU Israel', 'name' => 'Limmud FSU Israel', 'name_he' => 'Limmud FSU Israel', 'date' => '12-14 December 2019', 'date_he' => '12-14 December 2019', 'location_name' => '', 'location_name_he' => '',
        'copyright' => array('holder' => 'Limmud FSU Israel', 'holder_url' => 'http://limmudfsu.org.il', 'licence' => 'Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License', 'licence_url' => 'http://creativecommons.org/licenses/by-nc-sa/4.0/', 'logo' => 'https://i.creativecommons.org/l/by-nc-sa/4.0/80x15.png', 'year' => date("Y")),
        'organizer_name' => 'Limmud FSU Israel', 'email' => 'reg@limmudfsu.org.il',
        'social_links' => array(), 'second_language' => 'he', 'logo' => 'assets/img/logo.png', 'icon' => 'assets/img/icon.png', 'analytics' => ''
    );

    $header = $service->spreadsheets_values->get($sheet_id, $config['event_sheet'] . '!A1:1')->getValues()[0];
    $rows = $service->spreadsheets_values->get($sheet_id, $config['event_sheet'] . '!A2:AZ2')->getValues();
    foreach ($rows as $row) {
        $event['title'] = get_cell($header, $row, $config['event_app_title_column'], $event['title']);
        $event['name'] = get_cell($header, $row, $config['event_name_column'], $event['name']);
        $event['name2'] = get_cell($header, $row, $config['event_name2_column'], $event['name2']);
        $event['date'] = get_cell($header, $row, $config['event_date_column'], $event['date']);
        $event['date2'] = get_cell($header, $row, $config['event_date2_column'], $event['date2']);
        $event['poster'] = get_cell($header, $row, $config['event_poster_column'], '');
        $event['poster_mobile'] = get_cell($header, $row, $config['event_poster_mobile_column'], '');
        // optional second-language posters (fall back to the main ones)
        $event['poster2'] = get_cell($header, $row, $config['event_poster2_column'], '') ?: $event['poster'];
        $event['poster_mobile2'] = get_cell($header, $row, $config['event_poster_mobile2_column'], '') ?: $event['poster_mobile'];
        $event['map'] = get_cell($header, $row, $config['event_map_column'], '');
        $event['map2'] = get_cell($header, $row, $config['event_map2_column'], '');
        $event['organizer_name'] = get_cell($header, $row, $config['event_organizer_name_column'], $event['organizer_name']);
        $event['email'] = get_cell($header, $row, $config['event_email_column'], $event['email']);
        $event['analytics'] = get_cell($header, $row, $config['event_analytics_column'], '');

        $event['speaker_photos'] = get_cell($header, $row, $config['speaker_photos_column'], '');
        if ($event['speaker_photos'] != 'yes') {
            $event['speaker_photos'] = '';
        }

        $event['second_language_ribbon'] = get_cell($header, $row, $config['second_language_ribbon_column'], '');
        if ($event['second_language_ribbon'] != 'yes') {
            $event['second_language_ribbon'] = '';
        }

        $event['export_bookmarks'] = get_cell($header, $row, $config['export_bookmarks_column'], '');
        if ($event['export_bookmarks'] != 'yes') {
            $event['export_bookmarks'] = '';
        }

        // optional per-year theme: loads assets/css/themes/<theme>.css and Tracks column 'color-<theme>'
        $event['theme'] = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim(get_cell($header, $row, $config['event_theme_column'], ''))));

        // PWA manifest (defaults keep the previous behaviour)
        $event['short_name'] = get_cell($header, $row, $config['event_short_name_column'], '') ?: $event['title'];
        $event['icon_large'] = get_cell($header, $row, $config['event_icon_large_column'], '');
        $event['theme_color'] = get_cell($header, $row, $config['event_theme_color_column'], '') ?: '#fe9b00';
        $event['background_color'] = get_cell($header, $row, $config['event_background_color_column'], '') ?: '#003568';

        $event['copyright']['holder'] = $event['organizer_name'];

        $value = get_cell($header, $row, $config['event_site_url_column'], '');
        if (!empty($value)) {
            $event['copyright']['holder_url'] = $value;
            $event['social_links'][] = array('link' => $value, 'icon' => 'chrome', 'name' => 'Site');
        }

        $value = get_cell($header, $row, $config['event_facebook_url_column'], '');
        if (!empty($value)) {
            $event['social_links'][] = array('link' => $value, 'icon' => 'facebook', 'name' => 'Facebook');
        }

        $value = get_cell($header, $row, $config['event_instagram_url_column'], '');
        if (!empty($value)) {
            $event['social_links'][] = array('link' => $value, 'icon' => 'instagram', 'name' => 'Instagram');
        }

        $value = get_cell($header, $row, $config['event_telegram_url_column'], '');
        if (!empty($value)) {
            $event['social_links'][] = array('link' => $value, 'icon' => 'telegram', 'name' => 'Telegram');
        }

        $event['registration_url'] = get_cell($header, $row, $config['event_registration_url_column'], '');

        $event['second_language'] = get_cell($header, $row, $config['event_second_language_column'], $event['second_language']);
        if ($event['second_language'] == 'en') {
            $event['second_language_text'] = 'ENG';
        } elseif ($event['second_language'] == 'he') {
            $event['second_language_text'] = 'עבר';
        } else {
            $event['second_language'] = '';
            $event['second_language_text'] = '';
        }

        $value = get_cell($header, $row, $config['event_logo_column'], '');
        if (!empty($value)) {
            $event['logo'] = $value;
        }
        $value = get_cell($header, $row, $config['event_icon_column'], '');
        if (!empty($value)) {
            $event['icon'] = $value;
        }
    }

    # parse locations
    $locations = array();
    $locations_map = array();
    $locations_name2 = array();
    $header = $service->spreadsheets_values->get($sheet_id, $config['location_sheet'] . '!A1:1')->getValues()[0];
    $rows = $service->spreadsheets_values->get($sheet_id, $config['location_sheet'] . '!A2:AZ')->getValues();
    foreach ($rows as $row) {
        $hotel = get_cell($header, $row, $config['location_hotel_column'], '-');
        $room = get_cell($header, $row, $config['location_room_column'], '');
        if (empty($room)) {
            continue;
        }
        $hotel2 = get_cell($header, $row, $config['location_hotel2_column'], $hotel);
        $room2 = get_cell($header, $row, $config['location_room2_column'], $room);

        $id = 100 + count($locations);
        $order = get_cell($header, $row, $config['location_order_column'], '');
        if (!empty($order)) {
            $id = intval($order);
        }

        if (!empty($hotel) && ($hotel != '-')) {
            $location = $hotel . ' - ' . $room;
            $location2 = $hotel2 . ' - ' . $room2;
        } else {
            $location = $room;
            $location2 = $room2;
        }

        if (!array_key_exists($id, $locations)) {
            $locations[$id] = array('id' => $id, 'name' => $location, 'name2' => $location2);
        }
        $locations_map[$location] = $id;
        $locations_name2[$location] = $location2;
    }
    ksort($locations);

    # parse tracks
    $tracks = array();
    $tracks_map = array();
    $header = $service->spreadsheets_values->get($sheet_id, $config['session_type_sheet'] . '!A1:1')->getValues()[0];
    $rows = $service->spreadsheets_values->get($sheet_id, $config['session_type_sheet'] . '!A2:AZ')->getValues();
    foreach ($rows as $row) {
        $track = get_cell($header, $row, $config['session_type_name_column'], '');
        if (empty($track)) {
            continue;
        }
        $track2 = get_cell($header, $row, $config['session_type_name2_column'], $track);
        $color = get_cell($header, $row, $config['session_type_color_column'], '#EEE');
        if (!empty($event['theme'])) {
            $theme_color = get_cell($header, $row, 'color-' . $event['theme'], '');
            if (!empty($theme_color)) {
                $color = $theme_color;
            }
        }

        $id = count($tracks);
        $tracks[$id] = array('id' => $id, 'name' => $track, 'name2' => $track2, 'color' => $color, 'in_use' => false);
        $tracks_map[$track] = $id;
    }

    # parse languages
    $languages = array();
    $languages_map = array();
    $header = $service->spreadsheets_values->get($sheet_id, $config['language_sheet'] . '!A1:1')->getValues()[0];
    $rows = $service->spreadsheets_values->get($sheet_id, $config['language_sheet'] . '!A2:AZ')->getValues();
    foreach ($rows as $row) {
        $name = get_cell($header, $row, $config['language_name_column'], '');
        $name2 = get_cell($header, $row, $config['language_name2_column'], '');
        $short = get_cell($header, $row, $config['language_short_column'], '');

        $id = count($languages);
        $languages[$id] = array('id' => $id, 'name' => $name, 'name2' => $name2, 'short' => $short);
        $languages_map[$name] = $id;
    }

    # parse presenters
    $speakers = array();
    $speakers_map = array();
    $header = $service->spreadsheets_values->get($sheet_id, $config['presenter_sheet'] . '!A1:1')->getValues()[0];
    $rows = $service->spreadsheets_values->get($sheet_id, $config['presenter_sheet'] . '!A2:AZ')->getValues();
    foreach ($rows as $row) {
        $name = get_cell($header, $row, $config['presenter_name_column'], '');
        if (empty($name)) {
            continue;
        }

        $name2 = get_cell($header, $row, $config['presenter_name2_column'], $name);
        $photo = get_cell($header, $row, $config['presenter_photo_column'], '');
        // several photos ("rina.jpg, ilia.jpg" or "rina.jpg ilia.jpg", e.g. a couple): each is shown as its
        // own round picture next to the others; 'photo' stays the first one
        $photo_parts = preg_split('/[\s,]+/', trim($photo), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($photo_parts as $k => $part) {
            if (strpos($part, '.') === false) {
                $photo_parts[$k] = $part . '.jpg';
            }
        }
        $photo = empty($photo_parts) ? 'avatar.png' : $photo_parts[0];
        if (empty($photo_parts)) {
            $photo_parts = array('avatar.png');
        }

        $bio = get_cell($header, $row, $config['presenter_bio_column'], '');
        $bio = empty($bio) ? [] : explode("\n\n", $bio);

        $bio2 = get_cell($header, $row, $config['presenter_bio2_column'], '');
        $bio2 = empty($bio2) ? [] : explode("\n\n", $bio2);

        $name_first_last = get_cell($header, $row, $config['presenter_name_first_last_column'], '');
        if (empty($name_first_last)) {
            $name_parts = explode(' ', $name);
            $name_parts[] = $name_parts[0];
            array_shift($name_parts);
            $name_first_last = implode(' ', $name_parts);
        }
        $name2_first_last = get_cell($header, $row, $config['presenter_name2_first_last_column'], '');
        if (empty($name2_first_last)) {
            $name2_parts = explode(' ', $name2);
            $name2_parts[] = $name2_parts[0];
            array_shift($name2_parts);
            $name2_first_last = implode(' ', $name2_parts);
        }

        $title = get_cell($header, $row, $config['presenter_title_column'], '');
        if (!empty($title)) {
            $title .= ' ';
        }
        $title2 = get_cell($header, $row, $config['presenter_title2_column'], '');
        if (!empty($title2)) {
            $title2 .= ' ';
        }

        $has_data = ($photo != 'avatar.png') && !empty($bio);

        if (array_key_exists($name, $speakers_map)) {
            $id = $speakers_map[$name];
            if (!empty($bio)) {
                $speakers[$id]['bio'] = $bio;
            }
            if (!empty($bio2)) {
                $speakers[$id]['bio2'] = $bio2;
            }
        } else {
            $id = count($speakers);
            $speakers[$id] = array('id' => $id, 'name' => $name, 'name2' => $name2, 'name_first_last' => $name_first_last, 'name2_first_last' => $name2_first_last, 'photo' => $photo, 'photo_parts' => $photo_parts, 'bio' => $bio, 'bio2' => $bio2, 'title' => $title, 'title2' => $title2, 'sessions' => array(), 'has_data' => $has_data, 'has_photo' => ($photo != 'avatar.png'));   // has_photo: card circles also for presenters without a bio
            $speakers_map[$name] = $id;
        }
    }

    # parse sessions data
    $idx = 1;
    foreach ($rows as $row) {
        $idx++;
        $name = get_cell($header, $row, $config['presenter_session_name_column'], '');
        if (!empty($name)) {
            $name2 = get_cell($header, $row, $config['presenter_session_name2_column'], $name);

            $comment = get_cell($header, $row, $config['presenter_session_comment_column'], '');
            $comment2 = get_cell($header, $row, $config['presenter_session_comment2_column'], '');

            $description = get_cell($header, $row, $config['presenter_session_desc_column'], '');
            $description = empty($description) ? [] : explode("\n\n", $description);

            $description2 = get_cell($header, $row, $config['presenter_session_desc2_column'], '');
            $description2 = empty($description2) ? [] : explode("\n\n", $description2);

            $track = get_cell($header, $row, $config['presenter_session_type_column'], '');
            if (array_key_exists($track, $tracks_map)) {
                $track_id = $tracks_map[$track];
                $track2 = $tracks[$track_id]['name2'];
            } else {
                $track = 'лекция';
                if ($event['main_language'] == 'ukr') {
                    $track = 'лекція';
                }
                if ($event['second_language'] == 'en') {
                    $track2 = 'lecture';
                }
                if ($event['second_language'] == 'he') {
                    $track2 = 'הרצאה';
                }
            }

            $language = get_cell($header, $row, $config['presenter_session_language_column'], '');
            if (array_key_exists($language, $languages_map)) {
                $language_id = $languages_map[$language];
                $language2 = $languages[$language_id]['name2'];
                $language_short = $languages[$language_id]['short'];
            } else {
                $language = '';
                $language2 = '';
                $language_short = '';
            }
            $session_data[$name] = array('name2' => $name2, 'comment' => $comment, 'comment2' => $comment2, 'description' => $description, 'description2' => $description2, 'language' => $language, 'language2' => $language2, 'language_short' => $language_short, 'id' => $idx);
        }

        $idx++;
        $name = get_cell($header, $row, $config['presenter_session2_name_column'], '');
        if (!empty($name)) {
            $name2 = get_cell($header, $row, $config['presenter_session2_name2_column'], $name);

            $description = get_cell($header, $row, $config['presenter_session2_desc_column'], '');
            $description = empty($description) ? [] : explode("\n\n", $description);

            $description2 = get_cell($header, $row, $config['presenter_session2_desc2_column'], '');
            $description2 = empty($description_en) ? [] : explode("\n\n", $description2);

            $track = get_cell($header, $row, $config['presenter_session2_type_column'], '');
            if (array_key_exists($track, $tracks_map)) {
                $track_id = $tracks_map[$track];
                $track2 = $tracks[$track_id]['name2'];
            } else {
                $track = 'лекция';
                if ($event['second_language'] == 'en') {
                    $track2 = 'lecture';
                }
                if ($event['second_language'] == 'he') {
                    $track2 = 'הרצאה';
                }
            }

            $language = get_cell($header, $row, $config['presenter_session2_language_column'], '');
            if (array_key_exists($language, $languages_map)) {
                $language_id = $languages_map[$language];
                $language2 = $languages[$language_id]['name2'];
                $language_short = $languages[$language_id]['short'];
            } else {
                $language = '';
                $language2 = '';
                $language_short = '';
            }
            $session_data[$name] = array('name2' => $name2, 'description' => $description, 'description2' => $description2, 'language' => $language, 'language2' => $language2, 'language_short' => $language_short, 'id' => $idx);
        }
    }

    # parse schedule
    $sessions = array();
    $reg_category_limits = array();
    $current_date = '';
    $next_end = '';
    $header = $service->spreadsheets_values->get($sheet_id, $config['schedule_sheet'] . '!A1:1')->getValues()[0];
    $rows = $service->spreadsheets_values->get($sheet_id, $config['schedule_sheet'] . '!A2:AZ')->getValues();
    foreach ($rows as $row) {
        $tmp_date = get_cell($header, $row, $config['schedule_date_column'], '');
        $tmp_start = get_cell($header, $row, $config['schedule_start_column'], '');
        $tmp_end = get_cell($header, $row, $config['schedule_end_column'], '');

        if (substr($tmp_start, 0, 3) == '00:') {
            $tmp_start = '24:' . substr($tmp_start, 3);
        }
        if (substr($tmp_start, 0, 3) == '01:') {
            $tmp_start = '25:' . substr($tmp_start, 3);
        }
        if (substr($tmp_end, 0, 3) == '00:') {
            $tmp_end = '24:' . substr($tmp_end, 3);
        }
        if (substr($tmp_end, 0, 3) == '01:') {
            $tmp_end = '25:' . substr($tmp_end, 3);
        }

        if (!empty($tmp_date)) {
            if (empty($tmp_start) && empty($tmp_end)) {
                $current_date = $tmp_date;
                continue;
            } else {
                $date = $tmp_date;
            }
        } else {
            if (empty($current_date)) {
                continue;
            } else {
                $date = $current_date;
            }
        }
        if (!empty($tmp_start)) {
            $start = $tmp_start;
            $end = '';
            $next_end = '';
        }
        if (!empty($tmp_end)) {
            $end = $tmp_end;
            if (!empty($tmp_start)) {
                $next_end = $end;
            }
        } else {
            if (empty($end)) { continue; }
        }

        $tmp_hotel = get_cell($header, $row, $config['schedule_hotel_column'], '');
        if (!empty($tmp_hotel)) {
            $hotel = $tmp_hotel;
            /*
            if (array_key_exists($hotel, $translate)) {
                $hotel_he = $translate[$hotel];
            } else {
                $hotel_he = $hotel;
            }
            */
            $hotel2 = $hotel;
        }
        $tmp_room = get_cell($header, $row, $config['schedule_room_column'], '');
        if (!empty($tmp_room)) {
            $room = $tmp_room;
            if (array_key_exists($room, $locations_name2)) {
                $room2 = $locations_name2[$room];
            } else {
                $room2 = $room;
            }
        } else {
            if (!empty($next_end)) {
                $end = $next_end;
            }
            continue;
        }

        $people = array();
        $people2 = array();
        $people_first_last = array();
        $people2_first_last = array();
        $tmp_speaker = get_cell($header, $row, $config['schedule_presenter_column'], '');
        if (!empty($tmp_speaker)) {
            $speaker = $tmp_speaker;
            $people[] = $speaker;
            if (array_key_exists($speaker, $speakers_map)) {
                $speaker_id = $speakers_map[$speaker];
                $people2[] = $speakers[$speaker_id]['name2'];
                $people_first_last[] = $speakers[$speaker_id]['name_first_last'];
                $people2_first_last[] = $speakers[$speaker_id]['name2_first_last'];
            } else {
                $people2[] = $speaker;
                $people_first_last[] = $speaker;
                $people2_first_last[] = $speaker;
            }
        }

        $tmp_name = get_cell($header, $row, $config['schedule_session_name_column'], '');
        if (!empty($tmp_name)) {
            $name = $tmp_name;
        } else {
            if (!empty($next_end)) {
                $end = $next_end;
            }
            continue;
        }

        $language = '';
        $language2 = '';
        $language_short = '';
        $tmp_language = get_cell($header, $row, $config['schedule_session_language_column'], '');
        if (!empty($tmp_language)) {
            $language = $tmp_language;
            if (array_key_exists($language, $languages_map)) {
                $language_id = $languages_map[$language];
                $language2 = $languages[$language_id]['name2'];
                $language_short = $languages[$language_id]['short'];
            }
        }

        $language_ribbon = '';
        if (($event['second_language'] == 'he') && ($language_short == 'עבר')) {
            $language_ribbon = 'עבר';
        }

        $language_translate = (strpos($language, 'РУССКИЙ') !== false);
        if ($language_translate) {
            $language = str_replace('РУССКИЙ', 'синхронный перевод на русский язык', $language);
            $language2 = str_replace('RUSSIAN', 'simultaneous translation into Russian', $language2);
            $language2 = str_replace('רוסית', 'תירגום סימולטני לרוסית', $language2);
        }

        $track = 'лекция';
        if ($event['second_language'] == 'en') {
            $track2 = 'lecture';
        }
        if ($event['second_language'] == 'he') {
            $track2 = 'הרצאה';
        }
        $tmp_track = get_cell($header, $row, $config['schedule_session_type_column'], '');
        if (!empty($tmp_track)) {
            $track = $tmp_track;
            if (array_key_exists($track, $tracks_map)) {
                $track_id = $tracks_map[$track];
                $track2 = $tracks[$track_id]['name2'];
                $tracks[$track_id]['in_use'] = true;
            }
        }

        $tmp_sabbath = get_cell($header, $row, $config['schedule_session_sabbath_column'], '');
        if (!empty($tmp_sabbath)) {
            $shabbat = true;
        } else {
            $shabbat = false;
        }

        for ($i = 0; $i < 7; $i++) {
            $tmp_speaker2 = get_cell($header, $row, $config['schedule_presenter' . strval($i + 2) . '_column'], '');
            if (!empty($tmp_speaker2)) {
                $speaker = $tmp_speaker2;
                $people[] = $speaker;
                if (array_key_exists($speaker, $speakers_map)) {
                    $speaker_id = $speakers_map[$speaker];
                    $people2[] = $speakers[$speaker_id]['name2'];
                    $people_first_last[] = $speakers[$speaker_id]['name_first_last'];
                    $people2_first_last[] = $speakers[$speaker_id]['name2_first_last'];
                } else {
                    $people2[] = $speaker;
                    $people_first_last[] = $speaker;
                    $people2_first_last[] = $speaker;
                }
            }
        }

        $alt_presenter_summary = get_cell($header, $row, $config['schedule_alt_presenter_summary_column'], '');

        // registration in advance (limited seats): reg-start is local event time, e.g. "2026-12-17 9:00"
        $reg = null;
        $reg_live = 0;   // limited seats without registration (no reg-start): "first come, first served"
        $reg_seats = (int)trim(get_cell($header, $row, $config['schedule_reg_seats_column'], ''));
        if ($reg_seats > 0) {
            $reg_start_raw = trim(get_cell($header, $row, $config['schedule_reg_start_column'], ''));
            $reg_start = '';
            if ($reg_start_raw !== '') {
                try {
                    $dt = new DateTime($reg_start_raw, new DateTimeZone(date_default_timezone_get()));
                    $reg_start = $dt->format('c');
                } catch (Exception $e) {
                    echo "WARNING: cannot read reg-start '" . $reg_start_raw . "' of session '" . $name . "'\n";
                }
            }
            $reg_category = trim(get_cell($header, $row, $config['schedule_reg_category_column'], ''));
            $reg_limit = (int)trim(get_cell($header, $row, $config['schedule_reg_limit_column'], ''));
            if ($reg_category !== '') {
                if (isset($reg_category_limits[$reg_category]) && $reg_category_limits[$reg_category] != $reg_limit) {
                    echo "WARNING: reg-limit of category '" . $reg_category . "' differs between sessions ("
                        . $reg_category_limits[$reg_category] . " / " . $reg_limit . "), the smaller one is used\n";
                    $reg_limit = min($reg_limit, $reg_category_limits[$reg_category]);
                }
                $reg_category_limits[$reg_category] = $reg_limit;
            }
            if ($reg_start === '') {
                $reg_live = $reg_seats;
            } else {
                $reg = array('seats' => $reg_seats, 'waitlist' => (int)ceil($reg_seats * 0.3), 'start' => $reg_start,
                             'category' => $reg_category, 'limit' => $reg_limit);
            }
        }
        $alt_presenter_summary2 = get_cell($header, $row, $config['schedule_alt_presenter_summary2_column'], '');

        if (array_key_exists($name, $session_data)) {
            $name2 = $session_data[$name]['name2'];
            $comment = $session_data[$name]['comment'];
            $comment2 = $session_data[$name]['comment2'];
            $description = $session_data[$name]['description'];
            $description2 = $session_data[$name]['description2'];
            $id = $session_data[$name]['id'];
        } else {
            $name2 = '';
            $comment = '';
            $comment2 = '';
            $description = '';
            $description2 = '';
            $id = 0;
        }

        # update locations
        if (!empty($hotel) && ($hotel != '-')) {
            $location = $hotel . ' - ' . $room;
            $location2 = $hotel2 . ' - ' . $room2;
        } else {
            $location = $room;
            $location2 = $room2;
        }
        if (array_key_exists($location, $locations_map)) {
            $location_id = $locations_map[$location];
        } else {
            $location_id = count($locations);
            $locations[$location_id] = array('id' => $location_id, 'name' => $location, 'name2' => $location2);
            $locations_map[$location] = $location_id;
            $locations_name2[$location] = $location2;
        }

        # update speakers
        foreach($people as $key => $speaker) {
            if (!array_key_exists($speaker, $speakers_map)) {
                $speaker_id = count($speakers);
                $speakers[$speaker_id] = array('id' => $speaker_id, 'name' => $speaker, 'name2' => $people2[$key], 'name_first_last' => $people_first_last[$key], 'name2_first_last' => $people2_first_last[$key], 'photo' => 'avatar.png', 'bio' => '', 'bio2' => '', 'sessions' => array());
                $speakers_map[$speaker] = $speaker_id;
            }
        }

        # update end_time if session overlaps to the next day
        $start_time = $date . 'T' . $start . ':00' . $config['server_timezone'];
        if (intval(explode(':', $end)[0]) < 3) {
            // the next calendar day (also across a month / year end: 31.12 -> 01.01)
            $end_time = date('Y-m-d', strtotime($date . ' +1 day')) . 'T' . $end . ':00' . $config['server_timezone'];
        } else {
            $end_time = $date . 'T' . $end . ':00' . $config['server_timezone'];
        }

        # update tracks
        if (array_key_exists($track, $tracks_map)) {
            $track_id = $tracks_map[$track];
        } else {
            $track_id = count($tracks);
            $tracks[$track_id] = array('id' => $track_id, 'name' => $track, 'name2' => $track2, 'color' => '#EEE', 'in_use' => true);
            $tracks_map[$track] = $track_id;
        }

        # update sessions
        $session = array(
            'id' => $id, 'title' => $name, 'title2' => $name2, 'comment' => $comment, 'comment2' => $comment2,
            'start_time' => $start_time, 'end_time' => $end_time,
            'location' => array('id' => $location_id, 'name' => $location, 'name2' => $location2),
            'track' => array('id' => $track_id, 'name' => $track, 'name2' => $track2), 'speakers' => array(),
            'long_abstract' => $description, 'long_abstract2' => $description2,
            'language' => $language, 'language2' => $language2, 'language_short' => $language_short, 'language_ribbon' => $language_ribbon,
            'shabbat' => $shabbat, 'language_translate' => $language_translate, 'reg' => $reg, 'reg_live' => $reg_live,
            'alt_presenter_summary' => $alt_presenter_summary, 'alt_presenter_summary2' => $alt_presenter_summary2
        );
        foreach($people as $key => $speaker) {
            $session['speakers'][] = array('id' => $speakers_map[$speaker], 'name' => $speaker, 'name2' => $people2[$key], 'name_first_last' => $people_first_last[$key], 'name2_first_last' => $people2_first_last[$key]);
        }

        $sessions[$id] = $session;

        if (!empty($next_end)) {
            $end = $next_end;
        }
    }

    // the same limit for every session of a category (the smallest one given)
    foreach ($sessions as $sid => $sess) {
        if (!empty($sess['reg']['category'])) {
            $sessions[$sid]['reg']['limit'] = $reg_category_limits[$sess['reg']['category']];
        }
    }

    $tracks_used = array();
    foreach ($tracks as $key => $track) {
        if ($track['in_use']) {
            $tracks_used[$key] = $track;
        }
    }

    @mkdir($event['app_name']);
    @mkdir($event['app_name'] . '/json');
    file_put_contents($event['app_name'] . '/json/event.json', json_encode($event,  JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
    file_put_contents($event['app_name'] . '/json/sessions.json', json_encode($sessions,  JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
    file_put_contents($event['app_name'] . '/json/speakers.json', json_encode($speakers, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
    file_put_contents($event['app_name'] . '/json/locations.json', json_encode($locations, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
    file_put_contents($event['app_name'] . '/json/tracks.json', json_encode($tracks_used, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));

    return array($event, $sessions, $speakers, $locations, $tracks_used);
}

$days_ru = array(
    0 => 'воскресенье',
    1 => 'понедельник',
    2 => 'вторник',
    3 => 'среда',
    4 => 'четверг',
    5 => 'пятница',
    6 => 'суббота'
);
$months_ru = array(
    1 => 'января',
    2 => 'февраля',
    3 => 'марта',
    4 => 'апреля',
    5 => 'мая',
    6 => 'июня',
    7 => 'июля',
    8 => 'августа',
    9 => 'сентября',
    10 => 'октября',
    11 => 'ноября',
    12 => 'декабря'
);
$months_short_ru = array(
    1 => 'янв.',
    2 => 'фев.',
    3 => 'мар.',
    4 => 'апр.',
    5 => 'мая',
    6 => 'июня',
    7 => 'июля',
    8 => 'авг.',
    9 => 'сент.',
    10 => 'окт.',
    11 => 'ноя.',
    12 => 'дек.'
);

$days_he = array(
    0 => 'ראשון',
    1 => 'שני',
    2 => 'שלישי',
    3 => 'רביעי',
    4 => 'חמישי',
    5 => 'שישי',
    6 => 'שבת'
);
$months_he = array(
    1 => 'ינואר',
    2 => 'פברואר',
    3 => 'מרץ',
    4 => 'אפריל',
    5 => 'מאי',
    6 => 'יוני',
    7 => 'יולי',
    8 => 'אוגוסט',
    9 => 'ספטמבר',
    10 => 'אוקטובר',
    11 => 'נובמבר',
    12 => 'דצמבר'
);
$months_short_he = array(
    1 => "ינו׳",
    2 => "פבר׳",
    3 => "מרץ",
    4 => "אפר׳",
    5 => "מאי",
    6 => "יונ׳",
    7 => "יול׳",
    8 => "אוג׳",
    9 => "ספט׳",
    10 => "אוק׳",
    11 => "נוב׳",
    12 => "דצמ׳"
);

$days_en = array(
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday'
);
$months_en = array(
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'Juny',
    7 => 'July',
    8 => 'August',
    9 => 'Septembe',
    10 => 'October',
    11 => 'November',
    12 => 'December'
);
$months_short_en = array(
    1 => 'Jan',
    2 => 'Feb',
    3 => 'Mar',
    4 => 'Apr',
    5 => 'May',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Aug',
    9 => 'Sep',
    10 => 'Oct',
    11 => 'Nov',
    12 => 'Dec'
);

$days_ukr = array(
    0 => 'неділя',
    1 => 'понеділок',
    2 => 'вівторок',
    3 => 'середа',
    4 => 'четвер',
    5 => 'п’ятниця',
    6 => 'субота'
);
$months_ukr = array(
    1 => 'січня',
    2 => 'лютого',
    3 => 'березня',
    4 => 'квітня',
    5 => 'травня',
    6 => 'червня',
    7 => 'липня',
    8 => 'серпня',
    9 => 'вересня',
    10 => 'жовтня',
    11 => 'листопада',
    12 => 'грудня'
);
$months_short_ukr = array(
    1 => 'січ.',
    2 => 'лют.',
    3 => 'бер.',
    4 => 'квіт.',
    5 => 'трав.',
    6 => 'черв.',
    7 => 'лип.',
    8 => 'серп.',
    9 => 'вер.',
    10 => 'жовт.',
    11 => 'лист.',
    12 => 'груд.'
);

function foldByTime($sessions, $speakers, $tracks, $config, $lang) {
    global $days_ru;
    global $months_ru;
    global $months_short_ru;
    global $days_he;
    global $months_he;
    global $months_short_he;
    global $days_en;
    global $months_en;
    global $months_short_en;
    global $days_ukr;
    global $months_ukr;
    global $months_short_ukr;

    $dates = array();
    foreach ($sessions as $session) {
        if (empty($session['title2']) && ($lang == 'secondary') && $config['language2_hide_content_no_data'] == 'yes') {
            continue;
        }

        $timestamp = strtotime($session['start_time']);
        $date = date('Y-m-d', $timestamp);
        $time = date('H:i', $timestamp);
        if (intval(date('H', $timestamp)) < 3) {
            $sortKey = '1:' . $time;
        } else {
            $sortKey = '0:' . $time;
        }

        if (!isset($dates[$date])) {
            $dates[$date] = array(
                'slug' => $date,
                'date_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_ru[date('n', $timestamp)],
                'date_ukr' => $days_ukr[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_ukr[date('n', $timestamp)],
                'date_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_he[date('n', $timestamp)],
                'date_en' => $days_en[date('w', $timestamp)] . ', ' . $months_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
                'date_short_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
                'date_short_ukr' => $days_ukr[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ukr[date('n', $timestamp)],
                'date_short_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
                'date_short_en' => $days_en[date('w', $timestamp)] . ', ' . $months_short_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
                'times' => array()
            );
        }

        if (!isset($dates[$date]['times'][$sortKey])) {
            $dates[$date]['times'][$sortKey] = array(
                'caption' => $time,
                'sessions' => array()
            );
        }

        $speakersList = array();
        foreach($session['speakers'] as $speaker) {
            $spkr = $speakers[$speaker['id']];
            $spkr['last'] = false;
            $speakersList[] = $spkr;
        }
        if (!empty($speakersList)) {
            $speakersList[count($speakersList)-1]['last'] = true;
        }

        $manySpeakersList = '';
        if (count($speakersList) > 2) {
            $manySpeakersList = 'yes';
        }

        // long sessions (1:30 or more, with presenters - not meals etc.): duration badge on the programme card,
        // in half hours: 1:30-1:49 -> 1,5 ч, 1:50-2:19 -> 2 ч, 2:20-2:49 -> 2,5 ч, 2:50-3:19 -> 3 ч ...
        // limited seats, registration in advance: "(20 мест, по предварительной записи)"
        $reg_label = array('ru' => '', 'ukr' => '', 'en' => '', 'he' => '');
        if (!empty($session['reg'])) {
            $n = $session['reg']['seats'];
            $m10 = $n % 10; $m100 = $n % 100;
            $few = ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14));
            $one = ($m10 == 1 && $m100 != 11);
            $reg_label = array(
                'ru' => $n . ' ' . ($one ? 'место' : ($few ? 'места' : 'мест')) . ', по предварительной записи',
                'ukr' => $n . ' ' . ($one ? 'місце' : ($few ? 'місця' : 'місць')) . ', за попереднім записом',
                'en' => $n . ' ' . ($n == 1 ? 'seat' : 'seats') . ', registration required',
                'he' => $n . ' מקומות, בהרשמה מראש');
        }
        // limited seats, no registration: "(20 человек, в порядке живой очереди)"
        $live_label = array('ru' => '', 'ukr' => '', 'en' => '', 'he' => '');
        if (!empty($session['reg_live'])) {
            $n = $session['reg_live'];
            $m10 = $n % 10; $m100 = $n % 100;
            $few = ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14));
            $one = ($m10 == 1 && $m100 != 11);
            $live_label = array(
                'ru' => $n . ' ' . ($one ? 'человек' : ($few ? 'человека' : 'человек')) . ', в порядке живой очереди',
                'ukr' => $n . ' ' . ($one ? 'особа' : ($few ? 'особи' : 'осіб')) . ', у порядку живої черги',
                'en' => $n . ' ' . ($n == 1 ? 'person' : 'people') . ', first come, first served',
                'he' => $n . ' משתתפים, לפי סדר ההגעה');
        }

        $minutes = (int)round((strtotime($session['end_time']) - strtotime($session['start_time'])) / 60);
        $duration = array('ru' => '', 'ukr' => '', 'en' => '', 'he' => '');
        if ($minutes >= 90 && (!empty($speakersList) || !empty($session['alt_presenter_summary']))) {
            $halves = intdiv($minutes + 10, 30);   // half hours, 10 minutes early: 1:50 already counts as 2 ч
            $hh = intdiv($halves, 2);
            $half = $halves % 2;
            $duration = array('ru' => $hh . ($half ? ',5' : '') . ' ч', 'ukr' => $hh . ($half ? ',5' : '') . ' год',
                              'en' => $hh . ($half ? '.5' : '') . ' h', 'he' => $hh . ($half ? '.5' : '') . ' ש׳');
        }

        $dates[$date]['times'][$sortKey]['sessions'][$session['location']['id']] = array(
            'start' => date('H:i', strtotime($session['start_time'])),
            'end' => date('H:i', strtotime($session['end_time'])),
            'color' => $tracks[$session['track']['id']]['color'],
            'title' => $session['title'],
            'title2' => $session['title2'],
            'comment' => $session['comment'],
            'comment2' => $session['comment2'],
            'location' => $session['location']['name'],
            'location2' => $session['location']['name2'],
            'location_color' => $session['location']['color'],
            'is_cancelled' => ($session['location']['name'] == 'Отмена'),
            'speakers_list' => $speakersList,
            'many_speakers_list' => $manySpeakersList,
            'duration_ru' => $duration['ru'],
            'is_meal' => ($session['track']['name'] == 'еда'),   // meals: start - end time on the card ("9:00 - 11:30")
            'reg_seats' => $reg_label['ru'] !== '',
            'reg_live_ru' => $live_label['ru'], 'reg_live_ukr' => $live_label['ukr'], 'reg_live_en' => $live_label['en'], 'reg_live_he' => $live_label['he'],
            'reg_ru' => $reg_label['ru'], 'reg_ukr' => $reg_label['ukr'], 'reg_en' => $reg_label['en'], 'reg_he' => $reg_label['he'],
            'duration_ukr' => $duration['ukr'],
            'duration_en' => $duration['en'],
            'duration_he' => $duration['he'],
            'description' => $session['long_abstract'],
            'description2' => $session['long_abstract2'],
            'shabbat' => $session['shabbat'],
            'language_translate' => $session['language_translate'],
            'recommend' => $session['recommend'],
            'language' => $session['language'],
            'language2' => $session['language2'],
            'language_short' => $session['language_short'],
            'language_ribbon' => $session['language_ribbon'],
            'session_id' => $session['id'],
            'sessiondate_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
            'sessiondate_ukr' => $days_ukr[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ukr[date('n', $timestamp)],
            'sessiondate_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
            'sessiondate_en' => $days_en[date('w', $timestamp)] . ', ' . $months_short_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
            'tracktitle' => $session['track']['name'],
            'tracktitle2' => $session['track']['name2'],
            'alt_presenter_summary' => $session['alt_presenter_summary'],
            'alt_presenter_summary2' => $session['alt_presenter_summary2']
        );
    }

    ksort($dates);

    foreach($dates as $date => $value) {
        ksort($dates[$date]['times']);
        foreach($dates[$date]['times'] as $time => $data) {
            ksort($dates[$date]['times'][$time]['sessions']);
        }
    }

    # echo "--------\n";

    # foreach($dates as $date) {
    #     echo $date['slug'] . "\n";
    #     foreach($date['times'] as $time) {
    #         echo "  " . $time['caption'] . "\n";
    #         foreach($time['sessions'] as $session) {
    #         echo "    " . $session['title'] . "\n";
    #         }
    #     }
    # }

    return $dates;
}

$timeToPixel = 35; // 15 mins = 35 pixels
$columnWidth = 185;
$calendarWidth = 1060;

function timeDiff($startTime, $endTime)
{
    $start = intval(explode(':', $startTime)[0]) * 60 + explode(':', $startTime)[1];
    $end = intval(explode(':', $endTime)[0]) * 60 + explode(':', $endTime)[1];
    return $end - $start;
}

function convertTimeToPixel($startTime, $sessionTime)
{
    global $timeToPixel;
    $timeDiff = timeDiff($startTime, $sessionTime);

    if ($timeDiff < 0) {
        $timeDiff += 24 * 60;
    }
    $top = $timeDiff * $timeToPixel / 15 + $timeToPixel; // distance of session from top of the table
    return round($top);
}

function createTimeLine($startTime, $endTime, $sessionTimes)
{
    global $timeToPixel;
    global $columnWidth;
    global $calendarWidth;

    $timeLine = array();
    $startHour = intval(explode(':', $startTime)[0]);
    $startMinute = intval(explode(':', $startTime)[1]);
    $endHour = intval(explode(':', $endTime)[0]);

    $i = $startMinute;
    if ($i % 15 != 0) {
        $i = 0;
    }

    $height = $timeToPixel;

    while ($startHour <= $endHour) {
        if ($i % 30 == 0) {
            $time = ($startHour < 10 ? '0' : '') . $startHour . ':' . ($i == 0 ? '00' : strval($i));
        } else {
            $time = '';
        }
        $time = ($startHour < 10 ? '0' : '') . $startHour . ':' . ($i == 0 ? '00' : strval($i));
        $time = '';   // the times themselves are drawn as 'labels' below

        $time = str_replace('24:', '00:', $time);
        $time = str_replace('25:', '01:', $time);
        $timeLine[] = array('time' => $time);

        $i = ($i + 15) % 60;
        $height += $timeToPixel;
        if ($i == 0) {
            $startHour++;
        }
    }

    // a label at the exact start of every session (also 18:10, 19:20 - not only on the 15-minute rows)
    $labels = array();
    $sorted = $sessionTimes;
    sort($sorted);
    foreach ($sorted as $t) {
        $labels[] = array(
            'time' => str_replace(array('24:', '25:'), array('00:', '01:'), $t),
            'top' => convertTimeToPixel($startTime, $t),
        );
    }

    return array('timeline' => $timeLine, 'labels' => $labels, 'height' => $height, 'timeToPixel' => $timeToPixel);
}

function checkWidth($columns)
{
    global $columnWidth;
    global $calendarWidth;

    if ($columns * $columnWidth > $calendarWidth) {
        return $columnWidth . 'px';
    }
    $percentageWidth = 100 / $columns;
    return $percentageWidth . '%';
}


function foldByRooms($sessions, $speakers, $locations, $tracks, $config, $lang) {
    global $days_ru;
    global $months_ru;
    global $months_short_ru;
    global $days_he;
    global $months_he;
    global $months_short_he;
    global $days_en;
    global $months_en;
    global $months_short_en;
    global $days_ukr;
    global $months_ukr;
    global $months_short_ukr;

    $dates = array();
    foreach ($sessions as $session) {
       if (empty($session['title2']) && ($lang == 'secondary') && $config['language2_hide_content_no_data'] == 'yes') {
            continue;
        }

        $timestamp = strtotime($session['start_time']);
        $date = date('Y-m-d', $timestamp);
        $time = date('H:i', $timestamp);

        $start_time = date('H:i', strtotime($session['start_time']));
        $start_time = str_replace('00:', '24:', $start_time);
        $start_time = str_replace('01:', '25:', $start_time);
        $end_time = date('H:i', strtotime($session['end_time']));
        $end_time = str_replace('00:', '24:', $end_time);
        $end_time = str_replace('01:', '25:', $end_time);

        $roomName = $session['location']['name'];
        if (($roomName == 'Отмена') /*|| ($roomName == 'Столовая') || ($roomName == 'Экскурсия')*/) {
            continue;
        }

        if (!isset($dates[$date])) {
            $dates[$date] = array(
                'slug' => $date,
                'date_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_ru[date('n', $timestamp)],
                'date_ukr' => $days_ukr[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_ukr[date('n', $timestamp)],
                'date_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_he[date('n', $timestamp)],
                'date_en' => $days_en[date('w', $timestamp)] . ', ' . $months_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
                'date_short_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
                'date_short_ukr' => $days_ukr[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ukr[date('n', $timestamp)],
                'date_short_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
                'date_short_en' => $days_en[date('w', $timestamp)] . ', ' . $months_short_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
                'start_time' => $start_time,
                'end_time' => $end_time,
                'session_times' => array(),
                'timeLine' => array(),
                'sessions' => array(),
                'locations' => array()
            );
        }

        $venue = $session['location']['name'];
        $venue2 = $session['location']['name2'];

        $venue_id = $session['location']['id'];
        $venue_sort = strval($venue_id);
        if ($venue_id < 10) {
            $venue_sort = '0' . $venue_sort;
        }
        if (intval(date('H', $timestamp)) < 3) {
            $sortKey = $venue_sort . ':1:' . $time;
        } else {
            $sortKey = $venue_sort . ':0:' . $time;
        }

        if ($dates[$date]['start_time'] > $start_time) {
            $dates[$date]['start_time'] = $start_time;
        }
        if ($dates[$date]['end_time'] < $end_time) {
            $dates[$date]['end_time'] = $end_time;
        }
        if (!in_array($start_time, $dates[$date]['session_times'])) {
            $dates[$date]['session_times'][] = $start_time;
        }

        $speakersList = array();
        foreach($session['speakers'] as $speaker) {
            $spkr = $speakers[$speaker['id']];
            $spkr['last'] = false;
            $speakersList[] = $spkr;
        }
        if (!empty($speakersList)) {
            $speakersList[count($speakersList)-1]['last'] = true;
        }

        $manySpeakersList = '';
        if (count($speakersList) > 2) {
            $manySpeakersList = 'yes';
        }

        $dates[$date]['sessions'][$sortKey] = array(
            'start' => date('H:i', strtotime($session['start_time'])),
            'end' => date('H:i', strtotime($session['end_time'])),
            'color' => $tracks[$session['track']['id']]['color'],
            'title' => $session['title'],
            'title2' => $session['title2'],
            'location' => $session['location']['name'],
            'location2' => $session['location']['name2'],
            'location_id' => $session['location']['id'],
            'is_cancelled' => ($session['location']['name'] == 'Отмена'),
            'speakers_list' => $speakersList,
            'many_speakers_list' => $manySpeakersList,
            'description' => $session['long_abstract'],
            'description2' => $session['long_abstract2'],
            'shabbat' => $session['shabbat'],
            'language_translate' => $session['language_translate'],
            'language' => $session['language'],
            'language2' => $session['language2'],
            'language_short' => $session['language_short'],
            'language_ribbon' => $session['language_ribbon'],
            'session_id' => $session['id'],
            'sessiondate_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
            'sessiondate_ukr' => $days_ukr[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ukr[date('n', $timestamp)],
            'sessiondate_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
            'sessiondate_en' => $days_en[date('w', $timestamp)] . ', ' . $months_short_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
            'tracktitle' => $session['track']['name'],
            'tracktitle2' => $session['track']['name2'],
            'alt_presenter_summary' => $session['alt_presenter_summary'],
            'alt_presenter_summary2' => $session['alt_presenter_summary2']
        );
    }

    ksort($dates);

    foreach($dates as $date => $value) {
        ksort($dates[$date]['sessions']);

        $dates[$date]['rooms'] = array();

        $timeline = createTimeLine($dates[$date]['start_time'], $dates[$date]['end_time'], $dates[$date]['session_times']);
        $dates[$date]['timeline'] = $timeline['timeline'];
        $dates[$date]['time_labels'] = $timeline['labels'];
        $dates[$date]['height'] = $timeline['height'];
        $dates[$date]['timeToPixel'] = $timeline['timeToPixel'];
        $dates[$date]['timeToPixelOffset'] = $timeline['timeToPixel'] / 2 + 1;

        $prevLocation = '';
        $room = array('sessions' => array(), 'valid' => false);
        foreach($dates[$date]['sessions'] as $key => $session) {
            $dates[$date]['sessions'][$key]['top'] = convertTimeToPixel($dates[$date]['start_time'], $dates[$date]['sessions'][$key]['start']);
            $dates[$date]['sessions'][$key]['bottom'] = convertTimeToPixel($dates[$date]['start_time'], $dates[$date]['sessions'][$key]['end']);
            $dates[$date]['sessions'][$key]['height'] = $dates[$date]['sessions'][$key]['bottom'] - $dates[$date]['sessions'][$key]['top'];

            if ($dates[$date]['sessions'][$key]['location'] == $prevLocation) {
                $room['sessions'][] = $dates[$date]['sessions'][$key];
            } else {
                if ($room['valid']) {
                    $dates[$date]['rooms'][] = $room;
                    $room = array('sessions' => array(), 'valid' => false);
                }
                $location_id = $dates[$date]['sessions'][$key]['location_id'];
                if (array_key_exists($location_id, $locations)) {
                    $room['name'] = $locations[$location_id]['name'];
                    $room['name2'] = $locations[$location_id]['name2'];
                } else {
                    $room['name'] = $dates[$date]['sessions'][$key]['location'];
                    $room['name2'] = $dates[$date]['sessions'][$key]['location2'];
                }
                $room['sessions'][] = $dates[$date]['sessions'][$key];
                $room['valid'] = true;
                $prevLocation = $room['name'];
            }
        }
        if ($room['valid']) {
            $dates[$date]['rooms'][] = $room;
        }
        $dates[$date]['width'] = checkWidth(count($dates[$date]['rooms']));
    }

    if ($config['close_calendar_gaps'] != 'no') {
        // extend session to cover 15 min break after them
        global $timeToPixel;
        foreach ($dates as $date => $data) {
            $tops = array();
            foreach ($dates[$date]['rooms'] as $room => $value) {
                foreach ($dates[$date]['rooms'][$room]['sessions'] as $key => $session) {
                    if (!in_array($session['top'], $tops)) {
                        $tops[] = $session['top'];
                    }
                }
            }

            foreach ($dates[$date]['rooms'] as $room => $value) {
                #$tops = array();
                #foreach ($dates[$date]['rooms'][$room]['sessions'] as $key => $session) {
                #    $tops[] = $session['top'];
                #}
                foreach ($dates[$date]['rooms'][$room]['sessions'] as $key => $session) {
                    if (($config['close_calendar_gaps_ignore_meals'] == 'yes') && ($session['location'] == 'Столовая'))
                        continue;
                    $adjust = intval($config['close_calendar_gaps']) / 15 * $timeToPixel;
                    foreach ($tops as $top) {
                        if (($session['bottom'] + $adjust > $top) && ($session['bottom'] + $adjust - $top <= $timeToPixel)) {
                            $adjust = $top - $session['bottom'];
                        }
                    }
                    $dates[$date]['rooms'][$room]['sessions'][$key]['height'] += $adjust - 6;
                }
            }
        }
    }

    return $dates;
}

function getSpeakerSessions($speakerid, $sessions, $tracks, $config, $lang)
{
    global $days_ru;
    global $months_ru;
    global $months_short_ru;
    global $days_he;
    global $months_he;
    global $months_short_he;
    global $days_en;
    global $months_en;
    global $months_short_en;
    global $days_ukr;
    global $months_ukr;
    global $months_short_ukr;

    $speakerSessions = array();
    foreach ($sessions as $session) {
        if (empty($session['title2']) && ($lang == 'secondary') && $config['language2_hide_content_no_data'] == 'yes') {
            continue;
        }

        $roomName = $session['location']['name'];
        if (($roomName == 'Отмена') /*|| ($roomName == 'Столовая') || ($roomName == 'Экскурсия')*/) {
            continue;
        }

        $speakerFound = false;
        foreach ($session['speakers'] as $spkr) {
            if ($spkr['id'] == $speakerid) {
                $speakerFound = true;
                break;
            }
        }
        if (!$speakerFound) {
            continue;
        }

        $timestamp = strtotime($session['start_time']);

        $speakerSessions[$session['start_time']] = array(
            'start' => date('H:i', strtotime($session['start_time'])),
            'end' => date('H:i', strtotime($session['end_time'])),
            'color' => $tracks[$session['track']['id']]['color'],
            'title' => $session['title'],
            'title2' => $session['title2'],
            'location' => $session['location']['name'],
            'location2' => $session['location']['name2'],
            'location_color' => $session['location']['color'],
            'is_cancelled' => ($session['location']['name'] == 'Отмена'),
            'description' => $session['long_abstract'],
            'description2' => $session['long_abstract2'],
            'shabbat' => $session['shabbat'],
            'language_translate' => $session['language_translate'],
            'language' => $session['language'],
            'language2' => $session['language2'],
            'language_short' => $session['language_short'],
            'language_ribbon' => $session['language_ribbon'],
            'session_id' => $session['id'],
            'date_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
            'date_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
            'date_en' => $days_en[date('w', $timestamp)] . ', ' . $months_short_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
            'date_ukr' => $days_ukr[date('w', $timestamp)] . ', ' . $months_short_ukr[date('n', $timestamp)] . ' ' . date('j', $timestamp),
            'tracktitle' => $session['track']['name'],
            'tracktitle2' => $session['track']['name2']
        );
    }
    ksort($speakerSessions);
    return $speakerSessions;
}

function foldBySpeakers($sessions, $speakers, $tracks, $config, $lang)
{
    $speakersList = array();
    foreach ($speakers as $speaker) {
        $speakerSessions = getSpeakerSessions($speaker['id'], $sessions, $tracks, $config, $lang);
        if (!empty($speakerSessions)) {
            $speaker['sessions'] = $speakerSessions;
            if ($lang == 'primary') {
                $speakersList[$speaker['name']] = $speaker;
            } else {
                $speakersList[$speaker['name2']] = $speaker;
            }
        }
    }
    ksort($speakersList);

    $i = 0;
    foreach ($speakersList as $name => $speaker) {
        if ($speakersList[$name]['has_data']) {
            $speakersList[$name]['row_start'] = ($i % 3 == 0);
            $speakersList[$name]['row_end'] = ($i % 3 == 2);
            $i += 1;
        }
    }
    return $speakersList;
}

function recursive_copy($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);
    while ($file = readdir($dir)) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if (is_dir($src . '/' . $file)) {
                recursive_copy($src . '/' . $file, $dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function generate()
{
    $client = get_client();
    if (! isset($client)) {
        return;
    }

    $config = json_decode(file_get_contents('data/config.json'), true);
    $config = array_merge($config, get_defaults());
    // global configuration (admin.php): event time zone - session times and registration start times are local
    if (!empty($config['timezone'])) {
        $config['event_timezone'] = $config['timezone'];
    }

    if (!empty($config['event_timezone'])) {
        date_default_timezone_set($config['event_timezone']);
    }

    list($event, $sessions, $speakers, $locations, $tracks) = parse_sheets($client, $config);

    // cache-busting: add the photo file's modification time, so a replaced photo with the same name
    // is downloaded again by browsers and the service worker (e.g. speakers/thumbs/x.jpg?v=1764023456)
    foreach ($speakers as $key => $speaker) {
        $dir = __DIR__ . '/' . $event['app_name'] . '/speakers/';
        $parts = !empty($speaker['photo_parts']) ? $speaker['photo_parts'] : array($speaker['photo']);
        $photos = array();
        foreach ($parts as $part) {
            $versioned = $part;
            foreach (array($dir . 'thumbs/' . $part, $dir . $part) as $file) {
                if ($part != 'avatar.png' && file_exists($file)) {
                    $versioned = $part . '?v=' . filemtime($file);
                    break;
                }
            }
            $photos[] = array('photo' => $versioned);
        }
        $speakers[$key]['photos'] = $photos;           // templates: {{#each photos}} one round picture each
        $speakers[$key]['photo'] = $photos[0]['photo'];
    }

    $event['main_language'] = 'ru';
    $event['main_language_text'] = 'РУС';
    if (file_exists('data/custom.json')) {
        $custom = json_decode(file_get_contents('data/custom.json'), true);
        if (!empty($custom['main_language'])) {
            $event['main_language'] = $custom['main_language'];
            $event['main_language_text'] = $custom['main_language_text'];
        }
    }

    $model = array(
        'event' => $event,
        'tracks' => $tracks,
        'version' => date('mdHi')
    );

    $model['timeList'] = foldByTime($sessions, $speakers, $tracks, $config, 'primary');
    $model['timeList2'] = foldByTime($sessions, $speakers, $tracks, $config, 'secondary');
    $model['roomsList'] = foldByRooms($sessions, $speakers, $locations, $tracks, $config, 'primary');
    $model['roomsList2'] = foldByRooms($sessions, $speakers, $locations, $tracks, $config, 'secondary');
    $model['speakersList'] = foldBySpeakers($sessions, $speakers, $tracks, $config, 'primary');
    $model['speakersList2'] = foldBySpeakers($sessions, $speakers, $tracks, $config, 'secondary');

    if (count($model['timeList']) < 2) {
        $model['hideTimeList'] = strval(count($model['timeList']));
    } else {
        $model['hideTimeList'] = '';
    }
    if (count($model['roomsList2']) < 2) {
        $model['hideRoomsList2'] = strval(count($model['roomsList2']));
    } else {
        $model['hideRoomsList2'] = '';
    }

    $partialsDir = __DIR__ . "/templates/partials";
    $partialsLoader = new FilesystemLoader($partialsDir,
        [
            "extension" => "hbs"
        ]
    );

    $templatesDir = __DIR__ . "/templates";
    $templatesLoader = new FilesystemLoader($templatesDir,
        [
            "extension" => "hbs"
        ]
    );

    $handlebars = new Handlebars([
        "loader" => $templatesLoader,
        "partials_loader" => $partialsLoader
    ]);

    $pages = array('index', 'schedule', 'calendar', 'speakers');
    if (!empty($event['map'])) {
        $pages[] = 'map';
    }

    if ($event['main_language'] != 'ru') {
        $page_suffix = '_' . $event['main_language'];
    } else {
        $page_suffix = '';
    }

    foreach ($pages as $page) {
        if (!empty($event['second_language'])) {
            $model['otherpage'] = $page . '_' . $event['second_language'];
            $model['otherpage2'] = $page;
            file_put_contents(__DIR__ . '/' . $event['app_name'] . '/' . $page . '.html',  $handlebars->render($page . $page_suffix, $model));
            file_put_contents(__DIR__ . '/' . $event['app_name'] . '/' . $page . '_'  . $event['second_language'] . '.html',  $handlebars->render($page . '_' . $event['second_language'], $model));
        } else {
            $model['otherpage'] = '';
            file_put_contents(__DIR__ . '/' . $event['app_name'] . '/' . $page . '.html',  $handlebars->render($page . $page_suffix, $model));
        }
    }

    file_put_contents(__DIR__ . '/' . $event['app_name'] . '/manifest.json',  $handlebars->render('manifest_json', $model));

    // $model['folderHash'] = folderHash($event['app_name']);
    $model['folderHash'] = date("YmdHis");
    $model['precacheJson'] = json_encode(build_precache_list($event, $pages, $speakers), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents(__DIR__ . '/' . $event['app_name'] . '/sw.js',  $handlebars->render('sw_js', $model));

    file_put_contents(__DIR__ . '/' . $event['app_name'] . '/.htaccess',  $handlebars->render('htaccess', $model));
}

// Files the service worker saves on install, so the whole app works offline
// and the previous version keeps working until the new one is fully downloaded.
function build_precache_list($event, $pages, $speakers)
{
    $appDir = __DIR__ . '/' . $event['app_name'];
    $list = array('./', 'manifest.json');
    foreach ($pages as $page) {
        $list[] = $page . '.html';
        if (!empty($event['second_language'])) {
            $list[] = $page . '_' . $event['second_language'] . '.html';
        }
    }

    // everything under <app>/assets except legacy font formats modern browsers never request
    $skip = array('eot', 'svg', 'ttf', 'otf', 'map', 'scss', 'less', 'md', 'txt');
    if (is_dir($appDir . '/assets')) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir . '/assets', FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, $skip)) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($appDir) + 1));
            $list[] = implode('/', array_map('rawurlencode', explode('/', $rel)));
        }
    }

    // presenter thumbnails actually used in the programme
    foreach ($speakers as $speaker) {
      $photos = !empty($speaker['photos']) ? $speaker['photos'] : array(array('photo' => $speaker['photo']));
      foreach ($photos as $p) {
        $parts = explode('?', $p['photo'], 2);
        if (empty($parts[0]) || $parts[0] == 'avatar.png') {
            continue;
        }
        $query = isset($parts[1]) ? '?' . $parts[1] : '';
        // small photo in programme cards + full photo on the presenters page
        foreach (array('speakers/thumbs/', 'speakers/') as $folder) {
            if (file_exists($appDir . '/' . $folder . $parts[0])) {
                $list[] = $folder . rawurlencode($parts[0]) . $query;
            }
        }
      }
    }

    return array_values(array_unique($list));
}

try {
    generate();
    print 'WebApp successfully generated';
} catch (Exception $e) {
    print 'ERROR: ' .  $e->getMessage();
}
