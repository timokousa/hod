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

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ?
                        $_SERVER['HTTP_ORIGIN'] : '*'));

$session = isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : null;

if (!ini_get('session.use_only_cookies') && isset($_GET[session_name()]))
        $session = $_GET[session_name()];

if (isset($_SERVER['HTTP_COOKIES']))
        foreach (explode('; ', $_SERVER['HTTP_COOKIES']) as $cookie) {
                $pair = explode('=', $cookie);
                if ($pair[0] == session_name())
                        $session = $pair[1];
        }

$session = explode(',', $session)[0];

if (isset($_SERVER['HTTPS']) || !ini_get('session.cookie_secure') && $session) {
        session_id($session);
        session_start();

        if (!isset($_SESSION['HTTPS']) && isset($_SERVER['HTTPS']))
                $_SESSION['HTTPS'] = true;
}

$key = isset($_GET['key']) ? $_GET['key'] : false;
$t = isset($_GET['t']) ? $_GET['t'] : false;
$file = isset($_SERVER['PATH_INFO']) ?
        ltrim($_SERVER['PATH_INFO'], '/') : false;
$src = false;

if (strpos(realpath($file), getcwd() . DIRECTORY_SEPARATOR) != 0)
        $file = false;
else
        $src = basename(dirname($file));

if ($src && !file_exists(dirname($file) . DIRECTORY_SEPARATOR . $src . '.m3u8') &&
                !file_exists($workdir . DIRECTORY_SEPARATOR . $src . '.lock')) {
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

                $dest = (isset($cache['sources'][$src]['live']) &&
                                $cache['sources'][$src]['live']) ?
                        'live' : 'vod';

                if (!is_dir($dest))
                        if (!mkdir($dest))
                                exit("Could not create $dest dir.");

                $tsfile = $workdir . DIRECTORY_SEPARATOR . $src . '.timestamp';
                touch($tsfile);

                $opts = ' -f' .
                        ' -p ' . escapeshellarg($dest .
                                        DIRECTORY_SEPARATOR . $src .
                                        DIRECTORY_SEPARATOR . $src) .
                        ' -u ' . escapeshellarg(($cookiehack ? 'PROTOCOL://HOST/' :
                                                'http://PROXY/') .
                                        basename($_SERVER['SCRIPT_NAME']) .
                                        '/' . $dest .
                                        '/' . urlencode($src) . '/') .
                        ' -U ' . escapeshellarg($cookiehack ?
                                                'http://PROXY/' .
                                                basename($_SERVER['SCRIPT_NAME']) .
                                                '/' . $dest .
                                                '/' . urlencode($src) . '/' : '') .
                        ' -w ' . escapeshellarg($workdir);

                if (isset($ffopts))
                        $opts .= ' -o ' . escapeshellarg($ffopts);

                if ($language)
                        $opts .= ' -l ' . escapeshellarg($language);

                if ($burn_subs)
                        $opts .= ' -S';

                if ($hlsv3)
                        $opts .= ' -3';

                if (isset($cache['sources'][$src]['live']) &&
                                $cache['sources'][$src]['live']) {
                        $opts .= ' -C ' . escapeshellarg($tsfile) .
                                ' -e' .
                                ' -n 6' .
                                ' -R 60';
                }
                else
                        $opts .= ' -N 10';

                if (isset($cache['sources'][$src]['encrypt']) &&
                                $cache['sources'][$src]['encrypt']) {
                        $opts .= ' -k ' . escapeshellarg('hod -a ' . $workdir .
                                        DIRECTORY_SEPARATOR . $src . '.key-%u') .
                                ' -K ' . escapeshellarg('PROTOCOL://HOST/' .
                                                basename($_SERVER['SCRIPT_NAME']) .
                                                '?' . ($cookiehack ? 'SESSION&' : '') .
                                                'key=' . urlencode($src) . '&' .
                                                't=%u');
                }

                exec('hod' . $opts .
                                ' -i ' . escapeshellarg(
                                        $cache['sources'][$src]['input']) .
                                ' > /dev/null' .
                                ' 2> ' . escapeshellarg(
                                        $workdir . DIRECTORY_SEPARATOR .
                                        $src . '.stderr') .
                                ' &');
        }
}

if ($file && $src) {
        $tsfile = $workdir . DIRECTORY_SEPARATOR . $src . '.timestamp';
        $lockfile = $workdir . DIRECTORY_SEPARATOR . $src . '.lock';

        if (preg_match('/\.m3u8$/i', $file)) {
                if (disk_free_space('vod') < $df_threshold) {
                        if (!isset($dirs))
                                $dirs = glob('vod' . DIRECTORY_SEPARATOR . '*',
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

                                        @unlink($prefix . '.stderr');
                                        @unlink($prefix . '.timestamp');

                                        $files = glob($prefix . '.key*');

                                        foreach ($files as $rmfile)
                                                @unlink($rmfile);

                                        break;
                                }

                                $mtime = filemtime($prefix . '.timestamp');

                                if ($mtime < $oldestmtime) {
                                        $oldest = $tmp;
                                        $oldestmtime = $mtime;
                                }
                        }

                        if (!isset($files) && $oldest)
                                @unlink($workdir . DIRECTORY_SEPARATOR .
                                                $oldest . '.timestamp');
                }

                for ($i = 0; $i < 30; $i++) {
                        clearstatcache();

                        if (file_exists($file) ||
                                        (filemtime($tsfile) + 3 < time() &&
                                         !file_exists($lockfile)))
                                break;

                        sleep(1);
                }
        }

        if (preg_match('/\.m3u8$/i', $file) && file_exists($file)) {
                ob_start();
                ob_start('ob_gzhandler');

                $protocol = (isset($_SERVER['HTTPS']) ||
                                        isset($_SESSION['HTTPS']) ||
                                        $force_https) ?
                        'https://' : 'http://';

                $host = '://' . $_SERVER['HTTP_HOST'] .
                        dirname($_SERVER['SCRIPT_NAME']) . '/';

                if (!isset($proxy))
                        $proxy = $_SERVER['HTTP_HOST'] .
                                dirname($_SERVER['SCRIPT_NAME']);

                $session = '';
                if (session_id() && !ini_get('session.use_only_cookies') &&
                                (isset($_SERVER['HTTPS']) ||
                                 !ini_get('session.cookie_secure')))
                        $session = session_name() . '=' . session_id();

                echo str_replace(array(
                                        'PROTOCOL://',
                                        '://HOST/',
                                        '://PROXY/',
                                        '?SESSION&',
                                        '.m3u8'
                                      ),
                                array(
                                        $protocol,
                                        $host,
                                        '://' . $proxy . '/',
                                        $session ? '?' . $session . '&' : '?',
                                        '.m3u8' . (($cookiehack && $session) ?
                                                '?' . $session : '')
                                     ),
                                file_get_contents($file));

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
        else if (preg_match('/\.ts$/i', $file) && file_exists($file)) {
                header('Content-Length: ' . filesize($file));
                header('Content-Type: video/MP2T');

                readfile($file);

                exit;
        }
}

if ($key) {
        include_once 'auth.php';

        if (strpos($key, DIRECTORY_SEPARATOR) !== false)
                exit;

        $keyfile = $workdir . DIRECTORY_SEPARATOR . $key . '.key';

        if (is_numeric($t)) {
                $keyfile .= '-' . $t;

                if ($t > 0) {
                        $files = glob($workdir . DIRECTORY_SEPARATOR .
                                        $key . '.key-*');

                        foreach ($files as $file) {
                                preg_match('/\.key-(\d+)$/', $file, $matches);

                                if (!isset($matches[1]) || !$matches[1])
                                        continue;

                                if ($matches[1] < time() - 1200)
                                        @unlink($file);
                        }
                }
        }

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

        header('Access-Control-Expose-Headers: Retry-After');
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
<pre>
<?php if ($src && file_exists($workdir . DIRECTORY_SEPARATOR . $src . '.stderr'))
        readfile($workdir . DIRECTORY_SEPARATOR . $src . '.stderr'); ?>
</pre>
 </body>
</html>
