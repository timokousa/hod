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

$session = isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : null;

if (!ini_get('session.use_only_cookies') && isset($_GET[session_name()]))
        $session = $_GET[session_name()];

if (isset($_SERVER['HTTPS']) || !ini_get('session.cookie_secure') && $session) {
        session_id($session);
        session_start();

        if (!isset($_SESSION['HTTPS']) && isset($_SERVER['HTTPS']))
                $_SESSION['HTTPS'] = true;
}

$file = isset($_GET['file']) ? $_GET['file'] : false;
$key = isset($_GET['key']) ? $_GET['key'] : false;
$src = isset($_GET['src']) ? $_GET['src'] : false;

if ($src && !file_exists($workdir . DIRECTORY_SEPARATOR . $src . '.streams')) {
        if (!isset($cache['sources']))
                include_once 'sources.php';

        if (isset($cache['sources'][$src])) {
                if (isset($cache['sources'][$src]['encrypt']) &&
                                $cache['sources'][$src]['encrypt'])
                        include_once 'auth.php';
                else
                        $cookiehack = false;

                if (!is_dir($workdir))
                        if (!mkdir($workdir))
                                exit("Could not create workdir.");

                if (!is_dir('data'))
                        if (!mkdir('data'))
                                exit("Could not create data dir.");

                $tsfile = $workdir . DIRECTORY_SEPARATOR . $src . '.timestamp';
                touch($tsfile);

                $opts = ' -f' .
                        ' -p ' . escapeshellarg('data' .
                                        DIRECTORY_SEPARATOR . $src .
                                        DIRECTORY_SEPARATOR . $src) .
                        ' -u ' . escapeshellarg(($cookiehack ?
                                                'PROTOCOL' : 'http') .
                                        '://HOST/' .
                                        basename($_SERVER['SCRIPT_NAME']) .
                                        '?' . ($cookiehack ? 'SESSION&' : '') .
                                        'src=' . urlencode($src) .
                                        '&file=') .
                        ' -U ' . escapeshellarg(($cookiehack ?
                                                'http://HOST/' : '') . 'data/' .
                                        urlencode($src) . '/') .
                        ' -w ' . escapeshellarg($workdir);

                if (isset($ffopts))
                        $opts .= ' -o ' . escapeshellarg($ffopts);

                if ($language)
                        $opts .= ' -l ' . escapeshellarg($language);

                if ($burn_subs)
                        $opts .= ' -S';

                if (isset($cache['sources'][$src]['live']) &&
                                $cache['sources'][$src]['live']) {
                        $opts .= ' -c ' . escapeshellarg($tsfile) .
                                ' -e' .
                                ' -n 6';
                }
                else
                        $opts .= ' -N 10';

                if (isset($cache['sources'][$src]['encrypt']) &&
                                $cache['sources'][$src]['encrypt']) {
                        $opts .= ' -k ' . escapeshellarg($workdir .
                                        DIRECTORY_SEPARATOR . $src . '.key') .
                                ' -K ' . escapeshellarg('PROTOCOL://HOST/' .
                                                basename($_SERVER['SCRIPT_NAME']) .
                                                '?' . ($cookiehack ? 'SESSION&' : '') .
                                                'key=' . urlencode($src));
                }

                exec('hod' . $opts .
                                ' ' . escapeshellarg(
                                        $cache['sources'][$src]['input']) .
                                ' > /dev/null 2> /dev/null &');

                while (disk_free_space('data') < $df_threshold) {
                        if (!isset($dirs))
                                $dirs = glob('data' . DIRECTORY_SEPARATOR . '*',
                                                GLOB_ONLYDIR | GLOB_NOSORT);

                        $oldest = false;
                        $oldestmtime = time() - 600;

                        foreach ($dirs as $i => $dir) {
                                $tmp = basename($dir);
                                $prefix = $workdir . DIRECTORY_SEPARATOR . $tmp;

                                if (!isset($cache['sources'][$tmp]) ||
                                                !file_exists($prefix .
                                                        '.timestamp')) {
                                        $files = glob($dir .
                                                        DIRECTORY_SEPARATOR .
                                                        '*');

                                        foreach ($files as $rmfile)
                                                @unlink($rmfile);

                                        @rmdir($dir);

                                        @unlink($prefix . '.timestamp');
                                        @unlink($prefix . '.streams');
                                        @unlink($prefix . '.key');

                                        unset($dirs[$i]);
                                        continue 2;
                                }

                                $mtime = filemtime($prefix . '.timestamp');

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
}

if ($file && $src) {
        $plfile = 'data' . DIRECTORY_SEPARATOR . $src .
                DIRECTORY_SEPARATOR . $file;
        $tsfile = $workdir . DIRECTORY_SEPARATOR . $src . '.timestamp';

        for ($i = 0; $i < 30; $i++) {
                clearstatcache();

                if (file_exists($plfile) || !file_exists($tsfile))
                        break;

                sleep(1);
        }

        if (preg_match('/\.m3u8$/i', $file) && file_exists($plfile)) {
                ob_start();
                ob_start('ob_gzhandler');

                $protocol = (isset($_SERVER['HTTPS']) ||
                                        isset($_SESSION['HTTPS']) ||
                                        $force_https) ?
                        'https://' : 'http://';

                $host = '://' . $_SERVER['HTTP_HOST'] .
                        dirname($_SERVER['SCRIPT_NAME']) . '/';

                $session = '?';
                if (session_id() && !ini_get('session.use_only_cookies') &&
                                (isset($_SERVER['HTTPS']) ||
                                 !ini_get('session.cookie_secure')))
                        $session .= session_name() . '=' . session_id() . '&';

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

                if (!session_id() &&
                                strpos(ob_get_contents(),
                                        "#EXT-X-ENDLIST") === false) {
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
                }

                header('Content-Type: application/x-mpegURL');
                ob_end_flush();

                header('Content-Length: ' . ob_get_length());
                ob_end_flush();

                touch($tsfile);

                exit;
        }
}

if ($key) {
        include_once 'auth.php';

        $keyfile = $workdir . DIRECTORY_SEPARATOR . $key . '.key';

        if (file_exists($keyfile)) {
                ob_start();

                readfile($keyfile);

                if (!session_id()) {
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
                }
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
