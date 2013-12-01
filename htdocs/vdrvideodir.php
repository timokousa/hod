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

$videodir = '/var/vdr/video';

function find($dir, $q, $recursive = true) {
        $ret = array();

        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) .
                        DIRECTORY_SEPARATOR . '*');

        foreach ($files as $file) {
                if ($recursive && is_dir($file))
                        $ret = array_merge($ret, find($file, $q, true));
                elseif (preg_match($q, basename($file)))
                        $ret[] = $file;
        }

        return $ret;
}

$recordings = find($videodir, '/^info$/', true);

foreach ($recordings as $recording) {
        if (preg_match('/.del$/', dirname($recording)))
                continue;

        unset($channel, $start, $duration, $title, $subtitle, $description);

        foreach (file($recording) as $info) {
                if (preg_match('/^C ([^ ]*)/', $info, $matches))
                        $channel = $matches[1];
                elseif (preg_match('/^E \d+ (\d+) (\d+)/', $info, $matches)) {
                        $start = $matches[1];
                        $duration = $matches[2];
                }
                elseif (preg_match('/^T (.*)$/', $info, $matches))
                        $title = $matches[1];
                elseif (preg_match('/^S (.*)$/', $info, $matches))
                        $subtitle = $matches[1];
                elseif (preg_match('/^D (.*)$/', $info, $matches))
                        $description = $matches[1];
        }

        if (!isset($channel, $start, $duration, $title))
                continue;

        if ($start + $duration > time()) {
                if (!isset($cache['expires']) ||
                                        $cache['expires'] > $start + $duration)
                        $cache['expires'] = $start + $duration;

                continue;
        }

        $files = find(dirname($recording), '/^\d+\.ts$/', false);

        if (!$files)
                continue;

        $key = $channel . '-' . $start;

        $cache['sources'][$key]['live'] = false;
        $cache['sources'][$key]['encrypt'] = true;
        $cache['sources'][$key]['title'] = $title;
        $cache['sources'][$key]['uri'] = 'concat:' . implode('|', $files);

        if ($subtitle)
                $cache['sources'][$key]['description'] =
                        '<b>' . $subtitle . '</b>';
        if ($description)
                $cache['sources'][$key]['description'] .= '<br>' . $description;

        $cache['sources'][$key]['description'] .= '<br>';

        $h = floor($duration / 3600);
        $m = floor(($duration % 3600) / 60);

        if ($h)
                $cache['sources'][$key]['description'] .= $h . 'h';
        if ($m)
                $cache['sources'][$key]['description'] .= $m . 'm';
}

if (!isset($cache['expires']))
        $cache['expires'] = time() + 1800;

?>
