<?php
if (!empty($_POST['code'])) {
    require_once(__DIR__ . '/lib/vendor/autoload.php');
    try {
        $client = new Google_Client();
        $client->setApplicationName('Limmud WebApp Generator');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
        $client->setAuthConfig('data/credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        $authCode = trim($_POST['code']);
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
        $client->setAccessToken($accessToken);
        if (array_key_exists('error', $accessToken)) {
            print 'ERROR: ' . join(', ', $accessToken);
        }

        $tokenPath = 'data/token.json';
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        if (file_exists($tokenPath)) {
            rename($tokenPath, $tokenPath . '1')
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        print 'Token successfully created';
    } catch (Exception $e) {
        print 'ERROR: ' .  $e->getMessage();
    }
} else {
    print 'ERROR: Invalid configuration';
}

?>