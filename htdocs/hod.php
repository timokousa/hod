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

$file = isset($_GET['file']) ? $_GET['file'] : false;
$key = isset($_GET['key']) ? $_GET['key'] : false;
$src = isset($_GET['src']) ? $_GET['src'] : false;

if ($src && !isset($cache['sources']))
        include_once 'sources.php';

if (is_dir('data') && disk_free_space('data') < $df_threshold) {
        $dirs = glob('data' . DIRECTORY_SEPARATOR . '*',
                        GLOB_ONLYDIR | GLOB_NOSORT);

        while (disk_free_space('data') < $df_threshold) {
                $oldest = false;
                $oldestmtime = time() - 600;

                foreach ($dirs as $i => $dir) {
                        $tmp = basename($dir);

                        if (!isset($cache['sources'][$tmp]) ||
                                        !file_exists($workdir .
                                                DIRECTORY_SEPARATOR . $tmp .
                                                '.timestamp')) {
                                $files = glob($dir . DIRECTORY_SEPARATOR . '*');

                                foreach ($files as $file)
                                        @unlink($file);

                                @rmdir($dir);

                                if (file_exists($workdir . DIRECTORY_SEPARATOR .
                                                        $tmp . '.timestamp'))
                                        @unlink($workdir . DIRECTORY_SEPARATOR .
                                                        $tmp . '.timestamp');

                                if (file_exists($workdir . DIRECTORY_SEPARATOR .
                                                        $tmp . '.streams'))
                                        @unlink($workdir . DIRECTORY_SEPARATOR .
                                                        $tmp . '.streams');

                                if (file_exists($workdir . DIRECTORY_SEPARATOR .
                                                        $tmp . '.key'))
                                        @unlink($workdir . DIRECTORY_SEPARATOR .
                                                        $tmp . '.key');

                                unset($dirs[$i]);
                                continue 2;
                        }

                        $mtime = filemtime($workdir . DIRECTORY_SEPARATOR .
                                        $tmp . '.timestamp');

                        if ($mtime < $oldestmtime) {
                                $oldest = $tmp;
                                $oldestmtime = $mtime;
                        }
                }

                if ($oldest)
                        @unlink($workdir . DIRECTORY_SEPARATOR .
                                        $oldest . '.timestamp');
                else
                        break;
        }
}

if ($src && isset($cache['sources'][$src])) {
        if (!is_dir($workdir))
                if (!mkdir($workdir))
                        exit("Could not create workdir.");

        $tsfile = $workdir . DIRECTORY_SEPARATOR . $src . '.timestamp';

        touch($tsfile);

        if (!file_exists($workdir . DIRECTORY_SEPARATOR . $src . '.streams')) {
                if (!is_dir('data'))
                        if (!mkdir('data'))
                                exit("Could not create data dir.");

                $opts = ' -f' .
                        ' -p ' . escapeshellarg('data' .
                                        DIRECTORY_SEPARATOR . $src .
                                        DIRECTORY_SEPARATOR . $src) .
                        ' -u ' . escapeshellarg('PROTOCOL://HOST/' .
                                        basename($_SERVER['SCRIPT_NAME']) .
                                        '?SESSION' .
                                        '&src=' . urlencode($src) .
                                        '&file=') .
                        ' -U ' . escapeshellarg('http://HOST/data/' .
                                        urlencode($src) . '/');

                if ($language)
                        $opts .= ' -l ' . escapeshellarg($language);

                if ($burn_subs)
                        $opts .= ' -S';

                if (isset($cache['sources'][$src]['live']) &&
                                $cache['sources'][$src]['live']) {
                        $opts .= ' -c ' . escapeshellarg($tsfile) .
                                ' -e' .
                                ' -n 3';
                }
                else
                        $opts .= ' -N 10';

                if (isset($cache['sources'][$src]['encrypt']) &&
                                $cache['sources'][$src]['encrypt']) {
                        $opts .= ' -k ' . escapeshellarg($workdir .
                                        DIRECTORY_SEPARATOR . $src . '.key') .
                                ' -K ' . escapeshellarg('PROTOCOL://HOST/' .
                                                basename($_SERVER['SCRIPT_NAME']) .
                                                '?SESSION' .
                                                '&key=' . urlencode($src));
                }

                exec('hod' . $opts .
                                ' ' . escapeshellarg(
                                        $cache['sources'][$src]['uri']) .
                                ' > /dev/null 2> /dev/null &');
        }
}

if ($file && $src) {
        $plfile = 'data' . DIRECTORY_SEPARATOR . $src .
                DIRECTORY_SEPARATOR . $file;
        $tsfile = $workdir . DIRECTORY_SEPARATOR . $src . '.timestamp';

        for ($i = 0; $i < 30; $i++) {
                if (file_exists($plfile) || !file_exists($tsfile))
                        break;

                sleep(1);
        }

        if (preg_match('/\.m3u8$/i', $file) && file_exists($plfile)) {
                ob_start();

                $protocol = (isset($_SERVER['HTTPS']) || $force_https) ?
                        'https://' : 'http://';

                $host = '://' . $_SERVER['HTTP_HOST'] .
                        dirname($_SERVER['SCRIPT_NAME']) . '/';

                $session = '?' . session_name() . '=' . session_id() . '&';

                echo str_replace(array(
                                        'PROTOCOL://',
                                        '://HOST/',
                                        '?SESSION&'
                                      ),
                                array(
                                        $protocol,
                                        $host,
                                        $session
                                     ),
                                file_get_contents($plfile));

                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
                header('Content-Type: application/x-mpegURL');
                header('Content-Length: ' . ob_get_length());

                ob_end_flush();

                exit;
        }
}

if ($key) {
        $keyfile = $workdir . DIRECTORY_SEPARATOR . $key . '.key';

        if (file_exists($keyfile)) {
                ob_start();

                readfile($keyfile);

                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
                header('Content-Type: application/octet-stream');
                header('Content-Length: ' . ob_get_length());

                ob_end_flush();

                exit;
        }
}

if ($src && isset($cache['sources'][$src])) {
        $err = '503 Service Temporarily Unavailable';
        header('Retry-After: 10');
}
else
        $err = '404 Not Found';

header('HTTP/1.0 ' . $err);
header('Status: ' . $err);

?>
<html>
 <head>
  <title>HOD</title>
 </head>
 <body>
  <h1><?=$err?></h1>
 </body>
</html>
