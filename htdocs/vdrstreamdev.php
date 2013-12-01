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

$vdrhost = 'localhost';

foreach (file('http://' . $vdrhost . ':3000/channels.m3u') as $line) {
        if (!rtrim($line))
                continue;
        elseif (preg_match('/^#EXTINF:[^,]*,(.*)$/', $line, $matches))
                $name = rtrim($matches[1]);
        elseif (preg_match('/^[^#]/', $line)) {
                $key = basename(rtrim($line));
                $cache['sources'][$key]['encrypt'] = true;
                $cache['sources'][$key]['live'] = true;
                $cache['sources'][$key]['title'] = $name;
                $cache['sources'][$key]['uri'] = rtrim($line);
        }
}

exec('svdrpsend -d ' . $vdrhost . ' lste now', $epg);
foreach ($epg as $line) {
        if (preg_match('/^215-C ([^ ]*) /', $line, $matches))
                $key = $matches[1];
        elseif (preg_match('/^215-E \d+ (\d+)/', $line, $matches))
                $time = $matches[1];
        elseif (preg_match('/^215-T (.*)$/', $line, $matches))
                $now[$key] = date('H:i ', $time) . $matches[1];
}

unset($epg);

exec('svdrpsend -d ' . $vdrhost . ' lste next', $epg);
foreach ($epg as $line) {
        if (preg_match('/^215-C ([^ ]*) /', $line, $matches))
                $key = $matches[1];
        elseif (preg_match('/^215-E \d+ (\d+)/', $line, $matches)) {
                $time = $matches[1];
                if ($time > time() && (!isset($cache['expires']) ||
                                        $cache['expires'] > $time))
                        $cache['expires'] = $time;
        }
        elseif (preg_match('/^215-T (.*)$/', $line, $matches))
                $next[$key] = date('H:i ', $time) . $matches[1];
}

foreach (array_keys($cache['sources']) as $key) {
        if (isset($now[$key]) || isset($next[$key]))
                $cache['sources'][$key]['description'] = '<table>';

        if (isset($now[$key]))
                $cache['sources'][$key]['description'] .=
                        '<tr><td valign="top">Now:</td><td>' .
                        $now[$key] . '</td></tr>';

        if (isset($next[$key]))
                $cache['sources'][$key]['description'] .=
                        '<tr><td valign="top">Next:</td><td>' .
                        $next[$key] . '</td></tr>';

        if (isset($now[$key]) || isset($next[$key]))
                $cache['sources'][$key]['description'] .= '</table>';
}

if (!isset($cache['expires']))
        $cache['expires'] = time() + 1800;

?>
