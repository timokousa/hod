<?php
/*
   Copyright (C) 2013 Timo Kousa

   This file is part of HLS On Demand.

   HLS On Demand is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   HLS On Demand is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with HLS On Demand.  If not, see <http://www.gnu.org/licenses/>.
*/

include_once 'rc.php';

$src = isset($_GET['src']) ? $_GET['src'] : null;

if ($src && !isset($cache['sources']))
        include_once 'sources.php';

if (isset($_SERVER['HTTPS']) || !ini_get('session.cookie_secure')) {
        session_start();

        if (isset($cache['sources'][$src]) &&
                        isset($cache['sources'][$src]['encrypt']) &&
                        $cache['sources'][$src]['encrypt'])
                include_once 'auth.php';
}

if (isset($_SERVER['HTTPS'])) {
        header('Location: http://' .
                        $_SERVER['HTTP_HOST'] .
                        $_SERVER['REQUEST_URI']);

        exit;
}

cache_refresh();

$videosrc = '';

if ($src && isset($cache['sources'][$src]))
        $videosrc = ((isset($cache['sources'][$src]['encrypt']) &&
                        $cache['sources'][$src]['encrypt'] &&
                        ini_get('session.cookie_secure')) ?
                        'https://' : 'http://') .
                $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) .
                '/hod.php/' . ((isset($cache['sources'][$src]['live']) &&
                                        $cache['sources'][$src]['live']) ?
                                'live' : 'vod') . '/' . urlencode($src) . '/' .
                urlencode($src) . '.m3u8';

$title = isset($cache['sources'][$src]) ?
        $cache['sources'][$src]['title'] : '';

$description = isset($cache['sources'][$src]) ?
        $cache['sources'][$src]['description'] : '';

$reload = max(3, (isset($cache['expires']) ? $cache['expires'] : 0) - time());
header('X-update-divs: ' . $reload);

?>
<html>
 <head>
  <title><?=$title?> - HOD player</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=640" />
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="icon" type="image/png" href="play.png" />
  <link rel="apple-touch-icon" href="img.php?src=<?=urlencode($src)?>" />
  <link rel="apple-touch-startup-image" href="play.png" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <script type="text/javascript" src="javascript.js"></script>
 </head>
 <body onload="check_stream_status('<?=$videosrc?>', '<?=urlencode($src)?>');
 setTimeout(function() { update_divs(); }, <?=$reload?> * 1000);">
  <div class="player">
   <video id="video" class="video-js vjs-default-skin" controls
     width="640px" height="360px" poster="wait.png"
     data-setup='{"html5":{"hls":{"withCredentials":true}}}'>
    <source src="<?=$videosrc?>" type="application/x-mpegURL">
   </video>
   <div class="right tooltip">
    <img id="status" src="img.php?h=30&w=30&icon=wait" class="spinccw">
   </div>
   <div>
    <h2><?=$title?></h2>
    <div id="divup-<?=md5($src)?>">
     <?=$description?>
    </div>
    <div class="right">
     <a><img class="flip" src="img.php?h=30&w=30" alt="back" onClick="window.history.back();"></a>
     &nbsp;
    </div>
   </div>
  </div>
 </body>
</html>
