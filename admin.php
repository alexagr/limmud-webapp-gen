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
      <h1>Limmud FSU Schedule Generator</h1>
    </div>
      <h2>Configuration</h2>
      <?php
        $configPath = 'data/config.json';
        if (file_exists($configPath)) {
          $config = json_decode(file_get_contents($configPath), true);
        } else {
          $config = array('sheet_id' => '', 'app_name' => 'limmud-test');
        }
      ?>
      <div id="config-form">
      <div id="config-status"></div>
      <div class="row"> 
        <div class="col-md-3">
          <label for="sheet-id">Google Sheet ID:</label>
        </div>
        <div class="col-md-9">
          <input type="text" id="sheet-id" size="50" value="<?php echo $config['sheet_id'] ?>"><br>
        </div>
      </div>
      <div class="row">
        <div class="col-md-3">
          <label for="app-name">App Name:</label>
        </div> 
        <div class="col-md-9">
          <input type="text" id="app-name" value="<?php echo $config['app_name'] ?>"><br>
        </div> 
      </div>
      <!--
      <div class="row">
        <div class="col-md-3">
          <label for="sheet-id">Google API Status:</label>
        </div>
        <div class="col-md-9">
          <input type="text" id="api-status" size="20" value="<?php
            $tokenPath = 'data/token.json';
            if (file_exists($tokenPath)) {
              echo 'Connected';
            } else {
              echo 'Not connected';
            }
            ?>" readonly disabled><br>
        </div>
      </div>
      -->
      <br>
      <button name="config" class="btn btn-primary btn-action" onClick="updateConfig();">Update</button>&nbsp;&nbsp;&nbsp;
      <!--
      <button name="show-webapp" class="btn btn-primary btn-action" onClick="openInNewTab('get_token.php');">Connect to Google API</button>
      -->
      <br>
      </div>

      <h2>Generate</h2>
      <div id="config-form">
      <div id="generate-status"></div>
      <div class="row">
      <button name="generate" class="btn btn-primary btn-action" onClick="generate();">Generate</button>&nbsp;&nbsp;&nbsp;
      <button name="copy-assets" class="btn btn-primary btn-action" onClick="copyAssets();">Copy Assets</button>&nbsp;&nbsp;&nbsp;
      <button name="show-webapp" class="btn btn-primary btn-action" onClick="openInNewTab('<?php echo $config['app_name'] ?>');">Open Web App</button>
      </div>
      </div>
      
      <h2>Photos</h2>
      <div id="config-form">
      <div id="photos-status"></div>
      <div class="row">
      <input type="file" id="upload-files" name="uploadFiles[]" multiple >
      </div>
      <br>
      <button name="upload-photos" class="btn btn-primary btn-action" onClick="uploadPhotos();">Upload</button>&nbsp;&nbsp;&nbsp;
      <button name="show-photos" class="btn btn-primary btn-action" onClick="openInNewTab('show_photos.php')">Show</button>
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
    
    <script>
        function updateConfig() {
          $("#config-status").html('');
          jQuery.ajax({
            url: 'config.php',
            data: 'sheet_id='+$("#sheet-id").val()+'&app_name='+$("#app-name").val(),
            type: 'POST',
		    success: function(data){
              if (data.includes('ERROR')) {
                $("#config-status").html('<p class="error">' + data + '</p>');
              } else {
                $("#config-status").html('<p class="success">' + data + '</p>');
              }
		    },
		    error:function(){
              $("#config-status").html('<p class="error">ERROR: Cannot execute config.php</p>');
            }
		  });
	    }         

        function copyAssets() {
          $("#generate-status").html('<p class="info">Copying assets</p>');
          jQuery.ajax({
            url: 'copy_assets.php',
            data: '',
            type: 'POST',
		    success: function(data){
              if (data.includes('ERROR')) {
                $("#generate-status").html('<p class="error">' + data + '</p>');
              } else {
                $("#generate-status").html('<p class="success">' + data + '</p>');
              }
		    },
		    error:function(){
              $("#generate-status").html('<p class="error">ERROR: Cannot execute copy_assets.php</p>');
            }
		  });
	    }         

        function generate() {
          $("#generate-status").html('<p class="info">Generating WebApp</p>');
          jQuery.ajax({
            url: 'generate.php',
            data: '',
            type: 'POST',
            success: function(data){
              if (data.includes('ERROR')) {
                $("#generate-status").html('<p class="error">' + data + '</p>');
              } else {
                $("#generate-status").html('<p class="success">' + data + '</p>');
              }
		    },
		    error:function(){
              $("#generate-status").html('<p class="error">ERROR: Cannot execute generate.php</p>');
            }
		  });
	    }
        
        function uploadPhoto(id) {
          let fileList = $('#upload-files').prop("files");
          if (id >= fileList.length) {
            $("#photos-status").html('<p class="success">All photos were uploaded</p>');
            return;
          }
                
          let file_id = id + 1;
          $("#photos-status").html('<p class="info">Uploading photo #' + file_id.toString() + ' ' + fileList[id].name + '</p>');
          
          form_data = new FormData();
          form_data.append("file", fileList[id]);          
          jQuery.ajax({
            url: 'upload_photo.php',
            data: form_data,
            type: 'POST',
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            timeout: 60000,
            xhr: function() {  
              var xhr = $.ajaxSettings.xhr();
              return xhr;
            },
            success: function(data){
              uploadPhoto(id+1);
		    },
		    error:function(){
              $("#photos-status").html('<p class="error">ERROR: Cannot execute upload_photo.php</p>');
            }
		  });
        }

        function uploadPhotos() {
          let fileList = $('#upload-files').prop("files");
          if (fileList.length == 0) {
            $("#photos-status").html('<p class="error">Choose at least one file</p>');
          } else {
            uploadPhoto(0);
          }
        }

        function openInNewTab(url) {
          window.open(url, '_blank').focus();
        }

    </script>
  </footer>
</body>

</html>
