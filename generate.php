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
        'event_map_column' => 'map',
        'event_map2_column' => 'map-he',
        'event_organizer_name_column' => 'organizer-name',
        'event_site_url_column' => 'site-url',
        'event_facebook_url_column' => 'facebook-url',
        'event_instagram_url_column' => 'instagram-url',
        'event_telegram_url_column' => 'telegram-url',
        'event_email_column' => 'email',
        'event_logo_column' => 'logo',
        'event_icon_column' => 'icon',
        'event_analytics_column' => 'google-analytics-tag'
    );
    return $defaults;
}

function get_cell($header, $row, $column, $default)
{
    $value = $default;
    $key = array_search($column, $header);
    if ($key !== null && array_key_exists($key, $row)) {
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
        $event['map'] = get_cell($header, $row, $config['event_map_column'], '');
        $event['map2'] = get_cell($header, $row, $config['event_map2_column'], '');
        $event['organizer_name'] = get_cell($header, $row, $config['event_organizer_name_column'], $event['organizer_name']);
        $event['email'] = get_cell($header, $row, $config['event_email_column'], $event['email']);
        $event['analytics'] = get_cell($header, $row, $config['event_analytics_column'], '');

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
        $locations[$id] = array('id' => $id, 'name' => $location, 'name2' => $location2);
        $locations_map[$location] = $id;
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
        if (empty($photo)) {
            $photo = 'avatar.png';
        } else {
            if (strpos($photo, '.') === false) {
                $photo .= '.jpg';
            }
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
            $speakers[$id] = array('id' => $id, 'name' => $name, 'name2' => $name2, 'name_first_last' => $name_first_last, 'name2_first_last' => $name2_first_last, 'photo' => $photo, 'bio' => $bio, 'bio2' => $bio2, 'sessions' => array(), 'has_data' => true);
            $speakers_map[$name] = $id;
        }
    }

    foreach ($speakers as $key => $speaker) {
        if (($speaker['photo'] == 'avatar.png') && (empty($speaker['bio']))) {
            $speakers[$key]['has_data'] = false;
        }
    }

    # parse sessions data
    $idx = 1;
    foreach ($rows as $row) {
        $idx++;
        $name = get_cell($header, $row, $config['presenter_session_name_column'], '');
        if (!empty($name)) {
            $name2 = get_cell($header, $row, $config['presenter_session_name2_column'], $name);

            $description = get_cell($header, $row, $config['presenter_session_desc_column'], '');
            $description = empty($description) ? [] : explode("\n\n", $description);

            $description2 = get_cell($header, $row, $config['presenter_session_desc2_column'], '');
            $description2 = empty($description_en) ? [] : explode("\n\n", $description2);

            $track = get_cell($header, $row, $config['presenter_session_type_column'], '');
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
            $session_data[$name] = array('name2' => $name2, 'description' => $description, 'description2' => $description2, 'language' => $language, 'language2' => $language2, 'language_short' => $language_short, 'id' => $idx);
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
    $sessions_map = array();
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
            if (array_key_exists($room, $locations_map)) {
                $location_id = $locations_map[$room];
                $room2 = $locations[$location_id]['name2'];
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
        $tmp_speaker2 = get_cell($header, $row, $config['schedule_presenter2_column'], '');
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
        $tmp_speaker3 = get_cell($header, $row, $config['schedule_presenter3_column'], '');
        if (!empty($tmp_speaker3)) {
            $speaker = $tmp_speaker3;
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
        $tmp_speaker4 = get_cell($header, $row, $config['schedule_presenter4_column'], '');
        if (!empty($tmp_speaker4)) {
            $speaker = $tmp_speaker4;
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

        if (array_key_exists($name, $session_data)) {
            $name2 = $session_data[$name]['name2'];
            $description = $session_data[$name]['description'];
            $description2 = $session_data[$name]['description2'];
            $id = $session_data[$name]['id'];
        } else {
            $name2 = '';
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
            $yymmdd = explode('-', $date);
            $end_time = $yymmdd[0] . '-' . $yymmdd[1] . '-' . strval(intval($yymmdd[2]) + 1) . 'T' . $end . ':00' . $config['server_timezone'];
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
            'id' => $id, 'title' => $name, 'title2' => $name2,
            'start_time' => $start_time, 'end_time' => $end_time,
            'location' => array('id' => $location_id, 'name' => $location, 'name2' => $location2),
            'track' => array('id' => $track_id, 'name' => $track, 'name2' => $track2), 'speakers' => array(),
            'long_abstract' => $description, 'long_abstract2' => $description2,
            'language' => $language, 'language2' => $language2, 'language_short' => $language_short,
            'shabbat' => $shabbat, 'language_translate' => $language_translate
        );
        foreach($people as $key => $speaker) {
            $session['speakers'][] = array('id' => $speakers_map[$speaker], 'name' => $speaker, 'name2' => $people2[$key], 'name_first_last' => $people_first_last[$key], 'name2_first_last' => $people2_first_last[$key]);
        }

        $sessions[$id] = $session;

        if (!empty($next_end)) {
            $end = $next_end;
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
                'date_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_he[date('n', $timestamp)],
                'date_en' => $days_en[date('w', $timestamp)] . ', ' . $months_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
                'date_short_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
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

        $dates[$date]['times'][$sortKey]['sessions'][$session['location']['id']] = array(
            'start' => date('H:i', strtotime($session['start_time'])),
            'end' => date('H:i', strtotime($session['end_time'])),
            'color' => $tracks[$session['track']['id']]['color'],
            'title' => $session['title'],
            'title2' => $session['title2'],
            'location' => $session['location']['name'],
            'location2' => $session['location']['name2'],
            'location_color' => $session['location']['color'],
            'is_cancelled' => ($session['location']['name'] == 'Отмена'),
            'speakers_list' => $speakersList,
            'description' => $session['long_abstract'],
            'description2' => $session['long_abstract2'],
            'shabbat' => $session['shabbat'],
            'language_translate' => $session['language_translate'],
            'recommend' => $session['recommend'],
            'language' => $session['language'],
            'language2' => $session['language2'],
            'language_short' => $session['language_short'],
            'session_id' => $session['id'],
            'sessiondate_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
            'sessiondate_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
            'sessiondate_en' => $days_en[date('w', $timestamp)] . ', ' . $months_short_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
            'tracktitle' => $session['track']['name'],
            'tracktitle2' => $session['track']['name2']
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

function createTimeLine($startTime, $endTime)
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

        $time = str_replace('24:', '00:', $time);
        $time = str_replace('25:', '01:', $time);
        $timeLine[] = array('time' => $time);

        $i = ($i + 15) % 60;
        $height += $timeToPixel;
        if ($i == 0) {
            $startHour++;
        }
    }

    return array('timeline' => $timeLine, 'height' => $height, 'timeToPixel' => $timeToPixel);
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


function foldByRooms($sessions, $speakers, $tracks, $config, $lang) {
    global $days_ru;
    global $months_ru;
    global $months_short_ru;
    global $days_he;
    global $months_he;
    global $months_short_he;
    global $days_en;
    global $months_en;
    global $months_short_en;

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
                'date_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_he[date('n', $timestamp)],
                'date_en' => $days_en[date('w', $timestamp)] . ', ' . $months_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
                'date_short_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
                'date_short_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
                'date_short_en' => $days_en[date('w', $timestamp)] . ', ' . $months_short_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
                'start_time' => $start_time,
                'end_time' => $end_time,
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

        $speakersList = array();
        foreach($session['speakers'] as $speaker) {
            $spkr = $speakers[$speaker['id']];
            $spkr['last'] = false;
            $speakersList[] = $spkr;
        }
        if (!empty($speakersList)) {
            $speakersList[count($speakersList)-1]['last'] = true;
        }

        $dates[$date]['sessions'][$sortKey] = array(
            'start' => date('H:i', strtotime($session['start_time'])),
            'end' => date('H:i', strtotime($session['end_time'])),
            'color' => $tracks[$session['track']['id']]['color'],
            'title' => $session['title'],
            'title2' => $session['title2'],
            'location' => $session['location']['name'],
            'location2' => $session['location']['name2'],
            'is_cancelled' => ($session['location']['name'] == 'Отмена'),
            'speakers_list' => $speakersList,
            'description' => $session['long_abstract'],
            'description2' => $session['long_abstract2'],
            'shabbat' => $session['shabbat'],
            'language_translate' => $session['language_translate'],
            'language' => $session['language'],
            'language2' => $session['language2'],
            'language_short' => $session['language_short'],
            'session_id' => $session['id'],
            'sessiondate_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
            'sessiondate_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
            'sessiondate_en' => $days_en[date('w', $timestamp)] . ', ' . $months_short_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
            'tracktitle' => $session['track']['name'],
            'tracktitle2' => $session['track']['name2']
        );
    }

    ksort($dates);

    foreach($dates as $date => $value) {
        ksort($dates[$date]['sessions']);

        $dates[$date]['rooms'] = array();

        $timeline = createTimeLine($dates[$date]['start_time'], $dates[$date]['end_time']);
        $dates[$date]['timeline'] = $timeline['timeline'];
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
                $room['name'] = $dates[$date]['sessions'][$key]['location'];
                $room['name2'] = $dates[$date]['sessions'][$key]['location2'];
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
            foreach ($dates[$date]['rooms'] as $room => $value) {
                $tops = array();
                foreach ($dates[$date]['rooms'][$room]['sessions'] as $key => $session) {
                    $tops[] = $session['top'];
                }
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
            'session_id' => $session['id'],
            'date_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
            'date_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
            'date_en' => $days_en[date('w', $timestamp)] . ', ' . $months_short_en[date('n', $timestamp)] . ' ' . date('j', $timestamp),
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

    if (!empty($config['event_timezone'])) {
        date_default_timezone_set($config['event_timezone']);
    }

    list($event, $sessions, $speakers, $locations, $tracks) = parse_sheets($client, $config);

    $model = array(
        'event' => $event,
        'tracks' => $tracks,
        'version' => date('mdHi')
    );

    $model['timeList'] = foldByTime($sessions, $speakers, $tracks, $config, 'primary');
    $model['timeList2'] = foldByTime($sessions, $speakers, $tracks, $config, 'secondary');
    $model['roomsList'] = foldByRooms($sessions, $speakers, $tracks, $config, 'primary');
    $model['roomsList2'] = foldByRooms($sessions, $speakers, $tracks, $config, 'secondary');
    $model['speakersList'] = foldBySpeakers($sessions, $speakers, $tracks, $config, 'primary');
    $model['speakersList2'] = foldBySpeakers($sessions, $speakers, $tracks, $config, 'secondary');

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
    
    foreach ($pages as $page) {
        if (!empty($event['second_language'])) {        
            $model['otherpage'] = $page . '_' . $event['second_language'];
            $model['otherpage2'] = $page;
            file_put_contents(__DIR__ . '/' . $event['app_name'] . '/' . $page . '.html',  $handlebars->render($page, $model));
            file_put_contents(__DIR__ . '/' . $event['app_name'] . '/' . $page . '_'  . $event['second_language'] . '.html',  $handlebars->render($page . '_' . $event['second_language'], $model));
        } else {
            $model['otherpage'] = '';
            file_put_contents(__DIR__ . '/' . $event['app_name'] . '/' . $page . '.html',  $handlebars->render($page, $model));
        }
    }

    file_put_contents(__DIR__ . '/' . $event['app_name'] . '/manifest.json',  $handlebars->render('manifest_json', $model));

    // $model['folderHash'] = folderHash($event['app_name']);
    $model['folderHash'] = date("YmdHis");
    file_put_contents(__DIR__ . '/' . $event['app_name'] . '/sw.js',  $handlebars->render('sw_js', $model));

    file_put_contents(__DIR__ . '/' . $event['app_name'] . '/.htaccess',  $handlebars->render('htaccess', $model));
}

try {
    generate();
    print 'WebApp successfully generated';
} catch (Exception $e) {
    print 'ERROR: ' .  $e->getMessage();
}
