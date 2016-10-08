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

cache_refresh();

$src = isset($_GET['src']) ? $_GET['src'] : null;

if ($src && !isset($cache['sources']))
        include_once 'sources.php';

if (isset($_SERVER['HTTPS']) || !ini_get('session.cookie_secure'))
        session_start();

if (isset($cache['sources'][$src]) &&
                        isset($cache['sources'][$src]['encrypt']) &&
                        $cache['sources'][$src]['encrypt'])
        include_once 'auth.php';

if (isset($_POST['switch'])) {
        if (isset($_SESSION['player']) && $_SESSION['player'] == 'vlc')
                $_SESSION['player'] = 'html5';
        else
                $_SESSION['player'] = 'vlc';

        header('Location: ' . (isset($_SERVER['HTTPS']) ?
                                'https://' : 'http://') .
                        $_SERVER['HTTP_HOST'] .
                        $_SERVER['REQUEST_URI']);

        exit;
}

$videosrc = '';

$session = '';
if (isset($cache['sources'][$src]) &&
                isset($cache['sources'][$src]['encrypt']) &&
                $cache['sources'][$src]['encrypt'] && session_id() &&
                !ini_get('session.use_only_cookies') &&
                (isset($_SERVER['HTTPS']) || !ini_get('session.cookie_secure')))
        $session = '?' . session_name() . '=' . session_id();

if ($src && isset($cache['sources'][$src]))
        $videosrc = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
                $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) .
                '/hod.php/' . urlencode($src) . '/' .
                urlencode($src) . '.m3u8' . $session;

if (!isset($_SESSION['player']) ||
                !in_array($_SESSION['player'], array('html5', 'vlc'))) {
        if (preg_match('/(android|iphone|ipad)/i', $_SERVER['HTTP_USER_AGENT']))
                $_SESSION['player'] = 'html5';
        else
                $_SESSION['player'] = 'vlc';
}

$player = '';

if ($_SESSION['player'] == 'html5') {
        $player = '<video' . ((isset($cache['sources'][$src]['live']) &&
                                $cache['sources'][$src]['live']) ?
                        '' : ' controls' ) .
                ' onClick="play_video();" id="video"' .
                ' width="640px" height="360px" src="' . $videosrc . '"' .
                ' poster="wait.png"' .
                ' type="application/x-mpegURL">' .
                '<a href="' . $videosrc . '">direct link</a>' .
                '</video>';
}
elseif ($_SESSION['player'] == 'vlc') {
        $player = '<object classid="clsid:9BE31822-FDAD-461B-AD51-BE1D1C159921"' .
                ' codebase="http://download.videolan.org/pub/videolan/vlc/last/win32/axvlc.cab">' .
                '<embed type="application/x-vlc-plugin"' .
                ' pluginspage="http://www.videolan.org"' .
                ' toolbar="' . ((isset($cache['sources'][$src]['live']) &&
                                $cache['sources'][$src]['live']) ?
                        "false" : "true") . '"' .
                ' width="640px" height="360px" src="' . $videosrc . '" />' .
                '</object>';
}

$title = isset($cache['sources'][$src]) ?
        $cache['sources'][$src]['title'] : '';

$description = isset($cache['sources'][$src]) ?
        $cache['sources'][$src]['description'] : '';

$reload = max(3, $cache['expires'] - time());
header('X-update-divs: ' . $reload);

?>
<html>
 <head>
  <title><?=$title?> - HOD player</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=640px" />
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="icon" type="image/png" href="play.png" />
  <link rel="apple-touch-icon" href="img.php?src=<?=urlencode($src)?>" />
  <link rel="apple-touch-startup-image" href="play.png" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <script type="text/javascript" src="javascript.js"></script>
 </head>
 <body onload="<?php if ($_SESSION['player'] == 'html5')
        echo 'check_stream_status(document.getElementById(\'video\').src, ' .
        "'" . urlencode($src) . "'" . ');' .
        ' window.addEventListener(\'orientationchange\', orientation_check); ';
        ?>setTimeout(function() { update_divs(); }, <?=$reload?> * 1000);">
  <div class="player">
   <?=$player?>
   <form method="post">
    <div class="right">
     <input type="submit" name="switch" value="switch player" />
    </div>
   </form>
   <div>
    <h2><?=$title?></h2>
    <div id="divup-<?=md5($src)?>">
     <?=$description?>
    </div>
    <br>
    <br>
    &nbsp;
    <a href="<?=$videosrc?>"><img src="img.php?h=30&w=30" alt="direct link"></a>
    <div class="right">
     <a><img class="flip" src="img.php?h=30&w=30" alt="back" onClick="window.history.back();"></a>
     &nbsp;
    </div>
   </div>
  </div>
 </body>
</html>
