<?php
require_once(__DIR__ . '/lib/vendor/autoload.php');

$client = new Google_Client();
$client->setApplicationName('Limmud WebApp Generator');
$client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
$client->setAuthConfig('data/client_secret.json');
$client->setAccessType('offline');
$client->setRedirectUri('https://' . $_SERVER['HTTP_HOST'] . '/webapp/redirect.php');

if (! isset($_GET['code'])) {
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
} else {
    $authCode = trim($_GET['code']);
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
    $client->setAccessToken($accessToken);
    if (array_key_exists('error', $accessToken)) {
        print 'ERROR: ' . join(', ', $accessToken);
    }

    $tokenPath = 'data/token.json';
    if (!file_exists(dirname($tokenPath))) {
        mkdir(dirname($tokenPath), 0700, true);
    }
    file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    print 'Token successfully created';
}
