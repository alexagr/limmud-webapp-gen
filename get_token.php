<!DOCTYPE html>
<html lang="ru">
<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

  <meta name="description" content="Limmud FSU Schedule Generator">
  <meta name="author" content="Limmud FSU Israel">
  
  <title>Limmud FSU Schedule Generator</title>

</head>

<body>
  <style>
  body {width:610px;}
  #config-form {border-top:#F0F0F0 2px solid;background:#FAF8F8;padding:10px;}
  #config-form div {margin-bottom: 15px}
  #config-form div label {margin-left: 5px}
  .input {padding:10px; border:#F0F0F0 1px solid; border-radius:4px;}
  .error {background-color: #FF6600;border:#AA4502 1px solid;padding: 5px 10px;color: #FFFFFF;border-radius:4px;}
  .success {background-color: #12CC1A;border:#0FA015 1px solid;padding: 5px 10px;color: #FFFFFF;border-radius:4px;}
  .info {background-color: #127ACC;border:#0F75A0 1px solid;padding: 5px 10px;color: #FFFFFF;border-radius:4px;}
  .btn-action {background-color:#2FC332;border:0;padding:10px 40px;color:#FFF;border:#F0F0F0 1px solid; border-radius:4px;}
  </style>

  <div class ="container">
    <div class="row"> 
      <h1>Connect to Google Sheets API</h1>
    </div>
      <?php
        require_once(__DIR__ . '/lib/vendor/autoload.php');
        $client = new Google_Client();
        $client->setApplicationName('Limmud WebApp Generator');
        $client->setAuthConfig('data/client_secret.json');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
        $client->setRedirectUri('https://' . $_SERVER['HTTP_HOST'] . '/webapp/redirect.php');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
      ?>
      <div id="config-form">
      <div id="config-status"></div>
      <div class="row">
      <p>Click <a href="<?php echo $client->createAuthUrl(); ?>" target="_blank">here</a> to get a new access token for Google Sheets API.</p>
      <p>A new tab will be opened and you will be asked to choose an account. Choose Google account where the sheet is available.</p>
      <p>Then you will get a warning screen saying that the app is not verified. This is fine because we're using private application ID. So click &quot;Advanced&quot; and then &quot;Go to Limmud Webapp Generator&quot;.</p>
      <p>At the next screen make sure to check &quot;See all your Google Sheets spreadsheets&quot; checkbox and click &quot;Continue&quot;.</p>
      </div>
  </div>

  <footer class="classic">
    <br>
    <div class="row">
    <p>
       <a href="http://creativecommons.org/licenses/by/4.0/"> <img src="https://i.creativecommons.org/l/by/4.0/80x15.png"> </a>
       &copy; 2020
       <a href="http://limmudfsu.org.il">Limmud FSU Israel</a>
       <br>
       The website and it's contents are licensed under
       <a href="http://creativecommons.org/licenses/by/4.0/"> Creative Commons Attribution License </a>
    </p>
    </div>
    
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js" type="text/javascript"></script>

  </footer>
</body>

</html>
