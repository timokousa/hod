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

if (isset($_SERVER['HTTPS']) || !ini_get('session.cookie_secure'))
        session_start();

if (isset($_POST['refresh'])) {
        $cache['expires'] = 1;
        file_put_contents($workdir . DIRECTORY_SEPARATOR . 'hod-cache',
                        serialize($cache));

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

foreach ($cache['sources'] as $src) {
        if (isset($src['encrypt']) && $src['encrypt']) {
                include_once 'auth.php';
                break;
        }
}

$urlbase = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
        $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

$session = '';
if (session_id() && !ini_get('session.use_only_cookies') &&
                (isset($_SERVER['HTTPS']) || !ini_get('session.cookie_secure')))
        $session = session_name() . '=' . session_id();

$reload = max(3, $cache['expires'] - time());
header('X-update-divs: ' . $reload);

?>
<html>
 <head>
  <title><?=isset($_GET['q']) ? htmlentities($_GET['q']) . ' - ' : ''?>HOD</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, height=device-height,
  initial-scale=1" />
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="icon" type="image/png" href="play.png" />
  <link rel="apple-touch-icon" href="play.png" />
  <link rel="apple-touch-startup-image" href="play.png" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <script type="text/javascript" src="javascript.js"></script>
 </head>
 <body onload="thumbs_up(); setTimeout(function() { update_divs(); }, <?=$reload?> * 1000);">
  <div class="tiny" id="logout">
   <a href="?logout">Logout</a>
  </div>
  <!--
  <form method="post">
   <input type="submit" name="refresh" value="refresh">
  </form>
  -->
  <div class="header">
   <h1>HLS On Demand</h1>
   <form method="get">
    <input name="q" value="<?=isset($_GET['q']) ? htmlentities($_GET['q']) : ''?>">
    <input type="submit" value="Search">
   </form>
  </div>
<?php
if (!is_writable($workdir))
        echo '<font color="red">Error: ' . $workdir . ' is not writable</font><br><br>';

if (!is_writable('data'))
        echo '<font color="red">Error: data dir is not writable</font><br><br>';
?>
<?php
foreach ($cache['sources'] as $key => $src) {
?>
  <hr>
  <a onClick="window.location='player.php?src=<?=urlencode($key)?>';">
   <div class="thumb left">
    <img src="img.php?w=128&h=72" class="thumbnail" alt="img.php?w=128&h=72&src=<?=urlencode($key)?>">
   </div>
   <span>
    <?=$cache['sources'][$key]['title']?>
   </span>
  </a>
  <span>
   &nbsp;
   <a href="<?=$urlbase?>/hod.php/<?=urlencode($key)?>/<?=urlencode($key)?>.m3u8<?=(isset($src['encrypt']) && $src['encrypt']) ? '?' . $session : ''?>">
    <img src="img.php?h=15&w=15" alt="direct link">
   </a>
  </span>
  <br>
  <div class="tiny" id="divup-<?=md5($key)?>">
   <?=$cache['sources'][$key]['description']?>
  </div>
<?php
}
?>
 </body>
</html>
