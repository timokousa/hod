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
 </head>
 <body>
  <a align="right" href="?logout">Logout</a>
  <!--
  <form method="post">
   <input type="submit" name="refresh" value="refresh">
  </form>
  -->
  <h1>HLS On Demand</h1>
<?php
if (file_exists($workdir) && !is_writable($workdir))
        echo '<font color="red">Error: ' . $workdir . ' is not writable</font><br><br>';

if (!is_writable('data'))
        echo '<font color="red">Error: data dir is not writable</font><br><br>';
?>
  <a href="#static">static</a>
  <fieldset class="box" id="live">
   <legend>live</legend>
<?php
foreach (array_keys($cache['sources']) as $key) {
        if ($cache['sources'][$key]['live'] != true)
                continue;

        $key = urlencode($key);
?>
   <a href="player.php?src=<?=$key?>">
    <?=$cache['sources'][$key]['title']?>
   </a>
   <a href="<?=$urlbase?>/hod.php?<?=$session?>&src=<?=$key?>&file=<?=$key?>.m3u8">
    (direct&nbsp;link)
   </a>
   <br />
   <div class="tiny">
    <?=$cache['sources'][$key]['description']?>
   </div>
   <hr />
<?php
}
?>
  </fieldset>
  <a href="#live">live</a>
  <fieldset class="box" id="static">
   <legend>static</legend>
<?php
foreach (array_keys($cache['sources']) as $key) {
        if ($cache['sources'][$key]['live'] == true)
                continue;

        $key = urlencode($key);
?>
   <a href="player.php?src=<?=$key?>">
    <?=$cache['sources'][$key]['title']?>
   </a>
   <a href="<?=$urlbase?>/hod.php?<?=$session?>&src=<?=$key?>&file=<?=$key?>.m3u8">
    (direct&nbsp;link)
   </a>
   <br />
   <div class="tiny">
    <?=$cache['sources'][$key]['description']?>
   </div>
   <hr />
<?php
}
?>
  </fieldset>
 </body>
</html>
