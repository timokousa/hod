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

$src = isset($_GET['src']) ? $_GET['src'] : null;

if ($src && !isset($cache['sources']))
        include_once 'sources.php';

$videosrc = '';

if ($src && isset($cache['sources'][$src]))
        $videosrc = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
                $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) .
                '/hod.php?' . session_name() . '=' . session_id() .
                '&src=' . urlencode($src) .
                '&file=' . urlencode($src) . '.m3u8';

if (!isset($_SESSION['player']) ||
                !in_array($_SESSION['player'], array('html5', 'vlc'))) {
        if (preg_match('/(android|iphone|ipad)/i', $_SERVER['HTTP_USER_AGENT']))
                $_SESSION['player'] = 'html5';
        else
                $_SESSION['player'] = 'vlc';
}

$player = '';

if ($_SESSION['player'] == 'html5') {
        $player = '<video autoplay onClick="play_video();" id="video"' .
                ' width="640px" height="360px" src="' . $videosrc . '"' .
                ' poster="play.png" type="application/x-mpegURL">:(</video>';
}
elseif ($_SESSION['player'] == 'vlc') {
        $player = '<object classid="clsid:9BE31822-FDAD-461B-AD51-BE1D1C159921"' .
                ' codebase="http://download.videolan.org/pub/videolan/vlc/last/win32/axvlc.cab">' .
                '<embed type="application/x-vlc-plugin"' .
                ' pluginspage="http://www.videolan.org"' .
                ' controls="' . (isset($cache['sources'][$src]['live']) ?
                        "false" : "true") . '"' .
                ' width="640px" height="360px" src="' . $videosrc . '" />' .
                '</object>';
}

$title = isset($cache['sources'][$src]) ?
        $cache['sources'][$src]['title'] : '';

$description = isset($cache['sources'][$src]) ?
        $cache['sources'][$src]['description'] : '';

?>
<html>
 <head>
  <title><?=$title?> - HOD player</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <link rel="stylesheet" type="text/css" href="style.css" />
<?php if ($_SESSION['player'] == 'html5') { ?>
  <script type="text/javascript">
function play_video() {
        var video = document.getElementById('video');
        video.play();
        video.webkitEnterFullScreen();
}
  </script>
<?php } ?>
 </head>
 <body>
  <a href=".">back</a>
  <div width="640px" align="center">
   <fieldset class="box">
    <legend><?=$title?></legend>
    <?=$player?>
    <br />
    <?=$description?>
    <br />
    <a href="<?=$videosrc?>">link</a>
   </fieldset>
   <form method="post">
    <input type="submit" name="switch" value="switch player" />
   </form>
  </div>
 </body>
</html>
