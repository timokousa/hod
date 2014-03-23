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

if (isset($_POST['refresh'])) {
        unset($cache['sources']);

        header('Location: ' . (isset($_SERVER['HTTPS']) ?
                                'https://' : 'http://') .
                        $_SERVER['HTTP_HOST'] .
                        $_SERVER['REQUEST_URI']);

        exit;
}

if (!isset($cache['sources']))
        include_once 'sources.php';

if (isset($_GET['q'])) {
        foreach ($cache['sources'] as $key => $src)
                if (!preg_match('/' . $_GET['q'] . '/i',
                                        $src['title'] .
                                        ' ' . $src['description']))
                        unset($cache['sources'][$key]);
}

$urlbase = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
        $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$session = session_name() . '=' . session_id();

?>
<html>
 <head>
  <title>HOD</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, height=device-height,
  initial-scale=1" />
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="icon" type="image/png" href="play.png" />
  <link rel="apple-touch-icon" href="play.png" />
 </head>
 <body style="margin: 0; padding: 0">
  <div style="font-family: Arial; font-size: 1em; float: right;">
   <a align="right" href="?logout">Logout</a>
  </div>
  <!--
  <form method="post">
   <input type="submit" name="refresh" value="refresh">
  </form>
  -->
  <table width="100%" style="border-collapse: collapse;">
   <tr>
    <td style="padding: 0px; border-bottom: 1px solid black;">
     <div style="text-align: center; font-family: Arial;">
      <h1>HLS On Demand</h1>
      <form method="get">
       <input name="q" value="<?=isset($_GET['q']) ? htmlentities($_GET['q']) : ''?>">
       <input type="submit" value="Search">
      </form>
     </div>
    </td>
   </tr>
<?php
if (!is_writable($workdir))
        echo '<font color="red">Error: ' . $workdir . ' is not writable</font><br><br>';

if (!is_writable('data'))
        echo '<font color="red">Error: data dir is not writable</font><br><br>';
?>
<?php
foreach (array_keys($cache['sources']) as $key) {
?>
   <tr>
    <td style="padding: 0px; border-bottom: 1px solid black;">
     <a href="player.php?src=<?=urlencode($key)?>">
      <div style="min-width: 128px; height: 72px; float: left; text-align: center;">
       <img src="img.php?h=72" class="thumbnail"
       alt="img.php?h=72&src=<?=urlencode($key)?>">
      </div>
      <span style="font-family: Arial; font-size: 1em;">
       <?=$cache['sources'][$key]['title']?>
      </span>
     </a>
     <span style="font-family: Arial; font-size: 1em;">
      <a href="<?=$urlbase?>/hod.php?<?=$session?>&src=<?=urlencode($key)?>&file=<?=urlencode($key)?>.m3u8">
       (direct&nbsp;link)
      </a>
     </span>
     <br />
     <div class="tiny">
      <?=$cache['sources'][$key]['description']?>
     </div>
    </td>
   </tr>
<?php
}
?>
  </table>
 <script type="text/javascript">
function thumbs_up() {
        document.removeEventListener('scroll', thumbs_up);

        var imgs = document.getElementsByTagName("img");
        var thumb_down = false;

        for (var i = 0; i < imgs.length; i++) {
                var img = imgs[i];

                if (img.className != "thumbnail" || !img.alt)
                        continue;

                var height = window.innerHeight;
                var rects = img.getClientRects();
                var visible = false;

                for (var j = 0; j < rects.length; j++)
                        if ((rects[j].top >= 0 && rects[j].top <= height) ||
                                        (rects[j].bottom >= 0 &&
                                         rects[j].bottom <= height))
                                visible = true;

                if (!visible) {
                        thumb_down = true;
                        continue;
                }

                var alt = img.alt;
                img.alt = "";
                img.onload = thumbs_up;
                img.src = alt;

                break;
        }

        if (thumb_down && i >= imgs.length)
                document.addEventListener('scroll', thumbs_up);
}

window.onload = thumbs_up;
 </script>
 </body>
</html>
