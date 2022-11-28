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

function parse_sheets($client)
{
    $service = new Google_Service_Sheets($client);

    $config = json_decode(file_get_contents('data/config.json'), true);
    $spreadsheetId = $config['sheet_id'];

    # parse event
    $event = array(
        'app_name' => $config['app_name'], 'title' => 'Limmud FSU Israel', 'name' => 'Limmud FSU Israel', 'name_he' => 'Limmud FSU Israel', 'date' => '12-14 December 2019', 'date_he' => '12-14 December 2019', 'location_name' => '', 'location_name_he' => '',
        'copyright' => array('holder' => 'Limmud FSU Israel', 'holder_url' => 'http://limmudfsu.org.il', 'licence' => 'Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License', 'licence_url' => 'http://creativecommons.org/licenses/by-nc-sa/4.0/', 'logo' => 'https://i.creativecommons.org/l/by-nc-sa/4.0/80x15.png', 'year' => date("Y")),
        'organizer_name' => 'Limmud FSU Israel', 'email' => 'reg@limmudfsu.org.il',
        'social_links' => array(
            array('id' => 1, 'link' => 'http://limmudfsu.org.il', 'icon' => 'chrome', 'name' => 'Site'),
            array('id' => 2, 'link' => 'https://www.facebook.com/Limmud/', 'icon' => 'facebook', 'name' => 'Facebook'),
            array('id' => 3, 'link' => 'https://www.instagram.com/limmud.israel', 'icon' => 'instagram', 'name' => 'Instagram'),
            array('id' => 4, 'link' => 'https://t.me/limmudfsuil', 'icon' => 'telegram', 'name' => 'Telegram')
        )
    );
    $range = 'Event!A2:I';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    foreach ($response->getValues() as $row) {
        if (!empty($row[0])) {
            $event['title'] = $row[0];
        }
        if (!empty($row[1])) {
            $event['name'] = $row[1];
        }
        if (!empty($row[2])) {
            $event['name_he'] = $row[2];
        }
        if (!empty($row[3])) {
            $event['date'] = $row[3];
        }
        if (!empty($row[4])) {
            $event['date_he'] = $row[4];
        }
        if (!empty($row[5])) {
            $event['location_name'] = $row[5];
        }
        if (!empty($row[6])) {
            $event['location_name_he'] = $row[6];
        }
    }

    # parse translations
    $translate = array();
    $ranges = array("Translate!A1:B", "Translate!E1:F");
    foreach ($ranges as $range) {
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        foreach ($response->getValues() as $row) {
            if (!empty($row[0]) && !empty($row[1])) {
                $translate[$row[0]] = $row[1];
            }
        }
    }

    $language_short_translate = array();
    $response = $service->spreadsheets_values->get($spreadsheetId, "Translate!E1:G");
    foreach ($response->getValues() as $row) {
        if (!empty($row[0]) && !empty($row[2])) {
            $language_short_translate[$row[0]] = $row[2];
        }
    }

    # parse locations
    $locations = array();
    $locations_map = array();
    $range = 'Locations!A2:F';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    foreach ($response->getValues() as $row) {
        if (!empty($row[0])) {
            $hotel = $row[0];
        } else {
            $hotel = '-';
        }
        if (!empty($row[1])) {
            $room = $row[1];
        } else {
            continue;
        }
        if (!empty($row[2])) {
            $hotel_he = $row[2];
        } else {
            $hotel_he = $hotel;
        }
        if (!empty($row[3])) {
            $room_he = $row[3];
        } else {
            $room_he = $room;
        }
        if (!empty($row[4])) {
            $id = intval($row[4]);
        } else {
            $id = 100 + count($locations);
        }
        if (!empty($row[5])) {
            $color = $row[5];
        } else {
            $color = '';
        }
        if (!empty($hotel) && ($hotel != '-')) {
            $location = $hotel . ' - ' . $room;
            $location_he = $hotel_he . ' - ' . $room_he;
        } else {
            $location = $room;
            $location_he = $room_he;
        }
        $locations[$id] = array('id' => $id, 'name' => $location, 'name_he' => $location_he, 'color' => $color);
        $locations_map[$location] = $id;
    }
    ksort($locations);

    # parse tracks
    $tracks = array();
    $tracks_map = array();
    $range = 'Tracks!A2:C';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    foreach ($response->getValues() as $row) {
        if (!empty($row[0])) {
            # $track = mb_convert_case($row[0], MB_CASE_TITLE, 'UTF-8');
            $track = $row[0];
        } else {
            continue;
        }
        if (!empty($row[1])) {
            $color = $row[1];
        } else {
            $color = '#EEE';
        }
        if (!empty($row[2])) {
            $track_he = $row[2];
        } else {
            $track_he = $track;
        }
        $id = count($tracks);
        $tracks[$id] = array('id' => $id, 'name' => $track, 'name_he' => $track_he, 'color' => $color, 'in_use' => false);
        $tracks_map[$track] = $id;
    }

    # parse presentors
    $speakers = array();
    $speakers_map = array();
    $range = 'Presentors!B2:W';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    foreach ($response->getValues() as $row) {
        if (!empty($row[0])) {
            $name = $row[0];
        } else {
            continue;
        }
        if (!empty($row[1])) {
            $name_he = $row[1];
        } else {
            $name_he = $name;
        }
        if (!empty($row[2])) {
            $photo = $row[2];
        } else {
            $photo = 'avatar.png';
        }
        if (!empty($row[3])) {
            $bio = explode("\n\n", $row[3]);
        } else {
            $bio = [];
        }
        if (!empty($row[4])) {
            $bio_he = explode("\n\n", $row[4]);
        } else {
            $bio_he = [];
        }
        if (empty($bio)) {
            $bio = $bio_he;
        }
        if (empty($bio_he)) {
            $bio_he = $bio;
        }
        if (!empty($row[20])) {
            $name2 = $row[20];
        } else {
            $name_parts = explode(' ', $name);
            $name_parts[] = $name_parts[0];
            array_shift($name_parts);
            $name2 = implode(' ', $name_parts);
        }
        if (!empty($row[21])) {
            $name2_he = $row[21];
        } else {
            $name_he_parts = explode(' ', $name_he);
            $name_he_parts[] = $name_he_parts[0];
            array_shift($name_he_parts);
            $name2_he = implode(' ', $name_he_parts);
        }
        if (array_key_exists($name, $speakers_map)) {
            $id = $speakers_map[$name];
            if (!empty($bio)) {
                $speakers[$id]['long_biography'] = $bio;
            }
            if (!empty($bio_he)) {
                $speakers[$id]['long_biography_he'] = $bio_he;
            }
        } else {
            $id = count($speakers);
            $speakers[$id] = array('id' => $id, 'name' => $name, 'name_he' => $name_he, 'name2' => $name2, 'name2_he' => $name2_he, 'photo' => $photo, 'short_biography' => '', 'short_biography_he' => '', 'long_biography' => $bio, 'long_biography_he' => $bio_he, 'sessions' => array(), 'has_data' => true);
            $speakers_map[$name] = $id;
        }
    }

    foreach ($speakers as $key => $speaker) {
        if (($speaker['photo'] == 'avatar.png') && (empty($speaker['long_biography']))) {
            $speakers[$key]['has_data'] = false;
        }
    }

    # parse sessions data
    $session_data = array();
    $ranges = array('Presentors!G2:M', 'Presentors!N2:T');
    $offset = 0;
    foreach ($ranges as $range) {
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $idx = 1;
        foreach ($response->getValues() as $row) {
            $idx++;
            if (!empty($row[0])) {
                $name = $row[0];
            } else {
                continue;
            }
            if (!empty($row[1])) {
                $name_he = $row[1];
            } else {
                $name_he = $name;
            }
            if (!empty($row[2])) {
                $description = explode("\n\n", $row[2]);
            } else {
                $description = [];
            }
            if (!empty($row[3])) {
                $description_he = explode("\n\n", $row[3]);
            } else {
                $description_he = [];
            }
            if (!empty($row[4])) {
                $track = $row[4];
            } else {
                $track = 'лекция';
            }
            if (array_key_exists($track, $translate)) {
                $track_he = $translate[$track];
            } else {
                $track = 'лекция';
                $track_he = 'הרצאה';
            }
            if (!empty($row[5])) {
                $language = str_replace(' ', '', $row[5]);
            } else {
                $language = '';
            }
            if (array_key_exists($language, $translate)) {
                $language_he = $translate[$language];
            } else {
                $language = '';
                $language_he = '';
            }
            if (array_key_exists($language, $language_short_translate)) {
                $language_short = $language_short_translate[$language];
            } else {
                $language_short = '';
            }
            $session_data[$name] = array('name_he' => $name_he, 'description' => $description, 'description_he' => $description_he, 'language' => $language, 'language_he' => $language_he, 'language_short' => $language_short, 'id' => ($idx * count($ranges) + $offset));
        }
        $offset++;
    }

    # parse schedule
    $sessions = array();
    $sessions_map = array();
    $range = 'Schedule!A2:M';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    foreach ($response->getValues() as $row) {
        if (!empty($row[0])) {
            if (empty($row[1]) && empty($row[2])) {
                $current_date = $row[0];
                continue;
            } else {
                $date = $row[0];
            }
        } else {
            if (empty($current_date)) {
                continue;
            } else {
                $date = $current_date;
            }
        }
        if (!empty($row[1])) {
            $start = $row[1];
            $end = '';
            $next_end = '';
        }
        if (!empty($row[2])) {
            $end = $row[2];
            if (!empty($row[1])) {
                $next_end = $end;
            }
        } else {
            if (empty($end)) { continue; }
        }
        if (!empty($row[3])) {
            $hotel = $row[3];
            if (array_key_exists($hotel, $translate)) {
                $hotel_he = $translate[$hotel];
            } else {
                $hotel_he = $hotel;
            }
        }
        if (!empty($row[4])) {
            $room = $row[4];
            if (array_key_exists($room, $locations_map)) {
                $location_id = $locations_map[$room];
                $room_he = $locations[$location_id]['name_he'];
            } else {
                $room_he = $room;
            }
        } else {
            if (!empty($next_end)) {
                $end = $next_end;
            }
            continue;
        }
        $people = array();
        $people_he = array();
        $people2 = array();
        $people2_he = array();
        if (!empty($row[5])) {
            $speaker = $row[5];
            $people[] = $speaker;
            if (array_key_exists($row[5], $speakers_map)) {
                $speaker_id = $speakers_map[$speaker];
                $people_he[] = $speakers[$speaker_id]['name_he'];
                $people2[] = $speakers[$speaker_id]['name2'];
                $people2_he[] = $speakers[$speaker_id]['name2_he'];
            } else {
                $people_he[] = $speaker;
                $people2[] = $speaker;
                $people2_he[] = $speaker;
            }
        }
        if (!empty($row[6])) {
            $name = $row[6];
        } else {
            if (!empty($next_end)) {
                $end = $next_end;
            }
            continue;
        }
        if (!empty($row[7])) {
            $language = $row[7];
            if (array_key_exists($language, $translate)) {
                $language_he = $translate[$language];
            } else {
                $language = '';
                $language_he = '';
            }
        } else {
            $language = '';
            $language_he = '';
        }
        if (array_key_exists($language, $language_short_translate)) {
            $language_short = $language_short_translate[$language];
        } else {
            $language_short = '';
        }
        $language_translate = (strpos($language, 'РУССКИЙ') !== false);
        if ($language_translate) {
            $language = str_replace('РУССКИЙ', 'синхронный перевод на русский язык', $language);
            $language_he = str_replace('רוסית', 'תירגום סימולטני לרוסית', $language_he);
        }
        if (!empty($row[8])) {
            $track = $row[8];
            if (array_key_exists($track, $tracks_map)) {
                $track_id = $tracks_map[$track];
                $track_he = $tracks[$track_id]['name_he'];
                $tracks[$track_id]['in_use'] = true;
            } else {
                $track = 'лекция';
                $track_he = 'הרצאה';
            }
        } else {
            $track = 'лекция';
            $track_he = 'הרצאה';
        }
        if (!empty($row[9])) {
            $shabbat = true;
        } else {
            $shabbat = false;
        }
        if (!empty($row[10])) {
            $speaker = $row[10];
            $people[] = $speaker;
            if (array_key_exists($speaker, $speakers_map)) {
                $speaker_id = $speakers_map[$speaker];
                $people_he[] = $speakers[$speaker_id]['name_he'];
                $people2[] = $speakers[$speaker_id]['name2'];
                $people2_he[] = $speakers[$speaker_id]['name2_he'];
            } else {
                $people_he[] = $speaker;
                $people2[] = $speaker;
                $people2_he[] = $speaker;
            }
        }
        if (!empty($row[11])) {
            $speaker = $row[11];
            $people[] = $speaker;
            if (array_key_exists($speaker, $speakers_map)) {
                $speaker_id = $speakers_map[$speaker];
                $people_he[] = $speakers[$speaker_id]['name_he'];
                $people2[] = $speakers[$speaker_id]['name2'];
                $people2_he[] = $speakers[$speaker_id]['name2_he'];
            } else {
                $people_he[] = $speaker;
                $people2[] = $speaker;
                $people2_he[] = $speaker;
            }
        }
        if (!empty($row[12])) {
            $speaker = $row[12];
            $people[] = $speaker;
            if (array_key_exists($speaker, $speakers_map)) {
                $speaker_id = $speakers_map[$speaker];
                $people_he[] = $speakers[$speaker_id]['name_he'];
                $people2[] = $speakers[$speaker_id]['name2'];
                $people2_he[] = $speakers[$speaker_id]['name2_he'];
            } else {
                $people_he[] = $speaker;
                $people2[] = $speaker;
                $people2_he[] = $speaker;
            }
        }

        $recommend = false;

        if (array_key_exists($name, $session_data)) {
            $name_he = $session_data[$name]['name_he'];
            $description = $session_data[$name]['description'];
            $description_he = $session_data[$name]['description_he'];
            $id = $session_data[$name]['id'];
        } else {
            $name_he = '';
            $description = '';
            $description_he = '';
            $id = 0;
        }

        # update locations
        if (!empty($hotel) && ($hotel != '-')) {
            $location = $hotel . ' - ' . $room;
            $location_he = $hotel_he . ' - ' . $room_he;
        } else {
            $location = $room;
            $location_he = $room_he;
        }
        if (array_key_exists($location, $locations_map)) {
            $location_id = $locations_map[$location];
            $location_color = $locations[$location_id]['color'];
        } else {
            $location_id = count($locations);
            $location_color = '';
            $locations[$location_id] = array('id' => $location_id, 'name' => $location, 'name_he' => $location_he, 'color' => $location_color);
            $locations_map[$location] = $location_id;
        }

        # update speakers
        foreach($people as $key => $speaker) {
            if (!array_key_exists($speaker, $speakers_map)) {
                $speaker_id = count($speakers);
                $speakers[$speaker_id] = array('id' => $speaker_id, 'name' => $speaker, 'name_he' => $people_he[$key], 'name2' => $people2[$key], 'name2_he' => $people2_he[$key], 'photo' => 'avatar.png', 'short_biography' => '', 'short_biography_he' => '', 'long_biography' => '', 'long_biography_he' => '', 'sessions' => array());
                $speakers_map[$speaker] = $speaker_id;
            }
        }

        # update end_time if session overlaps to the next day
        $start_time = $date . 'T' . $start . ':00Z';
        if (intval(explode(':', $end)[0]) < 3) {
            $yymmdd = explode('-', $date);
            $end_time = $yymmdd[0] . '-' . $yymmdd[1] . '-' . strval(intval($yymmdd[2]) + 1) . 'T' . $end . ':00Z';
        } else {
            $end_time = $date . 'T' . $end . ':00Z';
        }

        # update tracks
        if (array_key_exists($track, $tracks_map)) {
            $track_id = $tracks_map[$track];
        } else {
            $track_id = count($tracks);
            $tracks[$track_id] = array('id' => $track_id, 'name' => $track, 'name_he' => $track_he, 'color' => '#EEE', 'in_use' => true);
            $tracks_map[$track] = $track_id;
        }

        # update sessions
        $session = array(
            'id' => $id, 'title' => $name, 'title_he' => $name_he,
            'start_time' => $start_time, 'end_time' => $end_time,
            'location' => array('id' => $location_id, 'name' => $location, 'name_he' => $location_he, 'color' => $location_color),
            'track' => array('id' => $track_id, 'name' => $track, 'name_he' => $track_he), 'speakers' => array(),
            'long_abstract' => $description, 'long_abstract_he' => $description_he,
            'language' => $language, 'language_he' => $language_he, 'language_short' => $language_short,
            'shabbat' => $shabbat, 'language_translate' => $language_translate, 'recommend' => $recommend
        );
        foreach($people as $key => $speaker) {
            $session['speakers'][] = array('id' => $speakers_map[$speaker], 'name' => $speaker, 'name_he' => $people_he[$key], 'name2' => $people2[$key], 'name2_he' => $people2_he[$key]);
        }

        $sessions[$id] = $session;

        if (!empty($next_end)) {
            $end = $next_end;
        }
    }

    if (!file_exists($event['app_name'])) {
        mkdir($event['app_name'], 0777, true);
    }
    if (!file_exists($event['app_name'] . '/json')) {
        mkdir($event['app_name'] . '/json', 0777, true);
    }

    $tracks_used = array();
    foreach ($tracks as $key => $track) {
        if ($track['in_use']) {
            $tracks_used[$key] = $track;
        }
    }

    file_put_contents($event['app_name'] . '/json/event.json', json_encode($event,  JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
    file_put_contents($event['app_name'] . '/json/sessions.json', json_encode($sessions,  JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
    #file_put_contents($event['app_name'] . '/json/sessions_map.json', json_encode($sessions_map, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
    file_put_contents($event['app_name'] . '/json/speakers.json', json_encode($speakers, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
    file_put_contents($event['app_name'] . '/json/locations.json', json_encode($locations, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
    file_put_contents($event['app_name'] . '/json/tracks.json', json_encode($tracks_used, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));

    return array($event, $sessions, $speakers, $locations, $tracks_used);
}

$days_ru = array(
    1 => 'понедельник',
    2 => 'вторник',
    3 => 'среда',
    4 => 'четверг',
    5 => 'пятница',
    6 => 'суббота',
    7 => 'воскресенье'
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
    1 => 'שני',
    2 => 'שלישי',
    3 => 'רביעי',
    4 => 'חמישי',
    5 => 'שישי',
    6 => 'שבת',
    7 => 'ראשון'
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

function foldByTime($sessions, $speakers, $tracks) {
    global $days_ru;
    global $months_ru;
    global $months_short_ru;
    global $days_he;
    global $months_he;
    global $months_short_he;

    $dates = array();
    foreach ($sessions as $session) {
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
                'date_short_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
                'date_short_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
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
            'title_he' => $session['title_he'],
            'location' => $session['location']['name'],
            'location_he' => $session['location']['name_he'],
            'location_color' => $session['location']['color'],
            'is_cancelled' => ($session['location']['name'] == 'Отмена'),
            'speakers_list' => $speakersList,
            'description' => $session['long_abstract'],
            'description_he' => $session['long_abstract_he'],
            'shabbat' => $session['shabbat'],
            'language_translate' => $session['language_translate'],
            'recommend' => $session['recommend'],
            'language' => $session['language'],
            'language_he' => $session['language_he'],
            'language_short' => $session['language_short'],
            'session_id' => $session['id'],
            'sessiondate' => $days_ru[date('N', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
            'sessiondate_he' => $days_he[date('N', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
            'tracktitle' => $session['track']['name'],
            'tracktitle_he' => $session['track']['name_he']
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
    return $top;
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


function foldByRooms($sessions, $speakers, $tracks) {
    global $days_ru;
    global $months_ru;
    global $months_short_ru;
    global $days_he;
    global $months_he;
    global $months_short_he;

    $dates = array();
    foreach ($sessions as $session) {
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
                'date_short_ru' => $days_ru[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
                'date_short_he' => $days_he[date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
                'start_time' => $start_time,
                'end_time' => $end_time,
                'timeLine' => array(),
                'sessions' => array(),
                'locations' => array()
            );
        }

        $venue = $session['location']['name'];
        $venue_he = $session['location']['name_he'];

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
            'title_he' => $session['title_he'],
            'location' => $session['location']['name'],
            'location_he' => $session['location']['name_he'],
            'location_color' => $session['location']['color'],
            'is_cancelled' => ($session['location']['name'] == 'Отмена'),
            'speakers_list' => $speakersList,
            'description' => $session['long_abstract'],
            'description_he' => $session['long_abstract_he'],
            'shabbat' => $session['shabbat'],
            'language_translate' => $session['language_translate'],
            'recommend' => $session['recommend'],
            'language' => $session['language'],
            'language_he' => $session['language_he'],
            'language_short' => $session['language_short'],
            'session_id' => $session['id'],
            'sessiondate' => $days_ru[date('N', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
            'sessiondate_he' => $days_he[date('N', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
            'tracktitle' => $session['track']['name'],
            'tracktitle_he' => $session['track']['name_he']
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
                $room['name_he'] = $dates[$date]['sessions'][$key]['location_he'];
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

    // extend session to cover 15 min break after them
    global $timeToPixel;
    foreach ($dates as $date => $data) {
        foreach ($dates[$date]['rooms'] as $room => $value) {
            $tops = array();
            foreach ($dates[$date]['rooms'][$room]['sessions'] as $key => $session) {
                $tops[] = $session['top'];
            }
            foreach ($dates[$date]['rooms'][$room]['sessions'] as $key => $session) {
                if ($session['location'] == 'Столовая')
                    continue;
                $adjust = $timeToPixel;
                foreach ($tops as $top) {
                    if (($session['bottom'] + $adjust > $top) && ($session['bottom'] + $adjust - $top <= $timeToPixel)) {
                        $adjust = $top - $session['bottom'];
                    }
                }
                $dates[$date]['rooms'][$room]['sessions'][$key]['height'] += $adjust - 6;
            }
        }
    }

    return $dates;
}

function getSpeakerSessions($speakerid, $sessions, $tracks)
{
    global $days_ru;
    global $months_ru;
    global $months_short_ru;
    global $days_he;
    global $months_he;
    global $months_short_he;

    $speakerSessions = array();
    foreach ($sessions as $session) {
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
            'title_he' => $session['title_he'],
            'location' => $session['location']['name'],
            'location_he' => $session['location']['name_he'],
            'location_color' => $session['location']['color'],
            'is_cancelled' => ($session['location']['name'] == 'Отмена'),
            'description' => $session['long_abstract'],
            'description_he' => $session['long_abstract_he'],
            'shabbat' => $session['shabbat'],
            'language_translate' => $session['language_translate'],
            'recommend' => $session['recommend'],
            'language' => $session['language'],
            'language_he' => $session['language_he'],
            'language_short' => $session['language_short'],
            'session_id' => $session['id'],
            'date' => $days_ru[date('N', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_ru[date('n', $timestamp)],
            'date_he' => $days_he[date('N', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months_short_he[date('n', $timestamp)],
            'tracktitle' => $session['track']['name'],
            'tracktitle_he' => $session['track']['name_he']
        );
    }
    ksort($speakerSessions);
    return $speakerSessions;
}

function foldBySpeakers($sessions, $speakers, $tracks)
{
    $speakersList = array();
    foreach ($speakers as $speaker) {
        $speakerSessions = getSpeakerSessions($speaker['id'], $sessions, $tracks);
        if (!empty($speakerSessions)) {
            $speaker['sessions'] = $speakerSessions;
            $speakersList[$speaker['name']] = $speaker;
        }
    }
    ksort($speakersList);

    $i = 0;
    foreach ($speakersList as $name => $speaker) {
        $speakersList[$name]['row_start'] = ($i % 3 == 0);
        $speakersList[$name]['row_end'] = ($i % 3 == 2);
        $i += 1;
    }
    return $speakersList;
}

function foldBySpeakers_he($sessions, $speakers, $tracks)
{
    $speakersList = array();
    foreach ($speakers as $speaker) {
        $speakerSessions = getSpeakerSessions($speaker['id'], $sessions, $tracks);
        if (!empty($speakerSessions)) {
            $speaker['sessions'] = getSpeakerSessions($speaker['id'], $sessions, $tracks);
            $speakersList[$speaker['name_he']] = $speaker;
        }
    }
    ksort($speakersList);

    $i = 0;
    foreach ($speakersList as $name => $speaker) {
        $speakersList[$name]['row_start'] = ($i % 3 == 0);
        $speakersList[$name]['row_end'] = ($i % 3 == 2);
        $i += 1;
    }
    return $speakersList;
}

function deleteDir($dirPath) {
    if (! is_dir($dirPath)) {
        return;
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

function generateThumbnails()
{
    deleteDir('speakers/thumbs');

    mkdir('speakers/thumbs');
    foreach ($files as $file) {
        list($width, $height) = getimagesize($file);
        $source = imagecreatefromjpeg($file);
        $thumb = imagecreatetruecolor(100, 100);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, 100, 100, $width, $height);
        imagejpeg($thumb, 'speakers/thumbs/' . basename($file));
    }
}

function rglob($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, rglob($dir . '/' . basename($pattern), $flags));
    }
    return $files;
}

function folderHash($app_name)
{
    $folderHash = '';

    $files = glob($app_name . '/*.{html,json}', GLOB_BRACE);
    foreach ($files as $file) {
        $folderHash .= hash_file('sha1', $file);
    }

    $files = rglob($app_name . '/assets/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $folderHash .= hash_file('sha1', $file);
        }
    }

    $files = rglob($app_name . '/speakers/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $folderHash .= hash_file('sha1', $file);
        }
    }

    return hash('sha1', $folderHash);
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

    list($event, $sessions, $speakers, $locations, $tracks) = parse_sheets($client);

    $model = array(
        'event' => $event,
        'tracks' => $tracks,
        'version' => date('mdHi')
    );

    $model['timeList'] = foldByTime($sessions, $speakers, $tracks);
    $model['roomsList'] = foldByRooms($sessions, $speakers, $tracks);
    $model['speakersList'] = foldBySpeakers($sessions, $speakers, $tracks);
    $model['speakersList_he'] = foldBySpeakers_he($sessions, $speakers, $tracks);

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

    $pages = array('index', 'schedule', /*'favorite',*/ 'calendar', 'speakers', 'map');
    foreach ($pages as $page) {
        $model['otherpage'] = $page . '_he';
        $model['otherpage_he'] = $page;
        file_put_contents(__DIR__ . '/' . $event['app_name'] . '/' . $page . '.html',  $handlebars->render($page, $model));
        file_put_contents(__DIR__ . '/' . $event['app_name'] . '/' . $page . '_he.html',  $handlebars->render($page . '_he', $model));
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
