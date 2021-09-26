<!DOCTYPE html>
<html lang="ru">
<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">

</head>

<body>
  <div class="container">
    <div class="row"> 
      <h1>Images</h1>
    </div>

    <div class="row">
    <?php
    $config = json_decode(file_get_contents('data/config.json'), true);
    $files = glob($config['app_name'] . "/speakers/*.jpg");

    for ($i=0; $i<count($files); $i++) {
      $image = $files[$i];
      ?>
        <div class="col-lg-3 col-sm-4 col-xs-6">
            <img src="<?php echo $image ?>" class="img-thumbnail" width="300" height="300">
            <br><?php echo basename($image) ?>
        </div>
      <?php
   }
   ?>
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
  </footer>
</body>

</html>
