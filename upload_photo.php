<?php

$config = json_decode(file_get_contents('data/config.json'), true);
$file = $config['app_name'] . '/speakers/' . basename($_FILES["file"]["name"]);

@mkdir($config['app_name'] . '/speakers');
move_uploaded_file($_FILES["file"]["tmp_name"], $file);

$source = imagecreatefromjpeg($file);

list($width, $height) = getimagesize($file);
$size = min($width, $height);
if (($width != 300) || ($height != 300)) {
    $img = imagecreatetruecolor(300, 300);
    imagecopyresampled($img, $source, 0, 0, 0, 0, 300, 300, $size, $size);
    imagejpeg($img, $file);
}

@mkdir($config['app_name'] . '/speakers/thumbs');
$source = imagecreatefromjpeg($file);
$thumb = imagecreatetruecolor(100, 100);
imagecopyresampled($thumb, $source, 0, 0, 0, 0, 100, 100, $size, $size);
imagejpeg($thumb, $config['app_name'] . '/speakers/thumbs/' . basename($file));
