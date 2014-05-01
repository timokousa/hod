<?php
/*
   Copyright (C) 2014 Timo Kousa

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

$src = isset($_GET['src']) ? $_GET['src'] : false;
$width = (isset($_GET['w']) && is_numeric($_GET['w'])) ? $_GET['w'] : false;
$height = (isset($_GET['h']) && is_numeric($_GET['h'])) ? $_GET['h'] : false;

if (!isset($cache['sources']))
        include_once 'sources.php';

if (!isset($cache['thumbs_purged']) ||
                $cache['thumbs_purged'] + 86400 < time()) {
        $cache['thumbs_purged'] = time();
        file_put_contents($workdir . DIRECTORY_SEPARATOR . 'hod-cache',
                        serialize($cache));

        $thumbs = glob($workdir . DIRECTORY_SEPARATOR . '*.jpg');

        foreach ($thumbs as $thumb)
                if (!in_array(basename($thumb, '.jpg'),
                                        array_keys($cache['sources'])))
                        @unlink($thumb);
}

$play = imagecreatefrompng('play.png');

$thumb = null;

if ($src && isset($cache['sources'][$src])) {
        $file = null;

        if (isset($cache['sources'][$src]['thumbnail']) &&
                        file_exists($cache['sources'][$src]['thumbnail']))
                $file = $cache['sources'][$src]['thumbnail'];
        elseif (!isset($cache['sources'][$src]['live']) ||
                        $cache['sources'][$src]['live'] === false) {
                $file = $workdir . DIRECTORY_SEPARATOR . $src . '.jpg';

                if (!file_exists($file)) {
                        touch($file);

                        exec('nice ffmpeg' .
                                        ' -i ' . escapeshellarg(
                                                $cache['sources'][$src]['input']) .
                                        ' -ss ' . $thumbnail_position .
                                        ' -vframes 1' .
                                        ' -vf thumbnail,scale=iw*sar/2:ih/2' .
                                        ' -y ' . escapeshellarg($file));
                }
        }

        $i = pathinfo($file);

        switch (isset($i['extension']) ? strtolower($i['extension']) : '') {
                case 'jpeg':
                case 'jpg':
                        $thumb = @imagecreatefromjpeg($file);
                        break;
                case 'png':
                        $thumb = @imagecreatefrompng($file);
                        break;
                case 'xpm':
                        $thumb = (imagetypes() & IMG_XPM) ?
                                        imagecreatefromxpm($file) : null;
                        break;
        }
}

$w = $width ? $width : max(imagesx($play), imagesx($thumb));
$h = $height ? $height : max(imagesy($play), imagesy($thumb));

$img = imagecreatetruecolor($w, $h);
imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
imagesavealpha($img, true);

if ($thumb) {
        $r = min(imagesx($img) / imagesx($thumb),
                        imagesy($img) / imagesy($thumb));

        $w = imagesx($thumb) * $r;
        $h = imagesy($thumb) * $r;

        $x = (imagesx($img) - $w) / 2;
        $y = (imagesy($img) - $h) / 2;

        imagecopyresampled($img, $thumb, $x, $y, 0, 0,
                        $w, $h, imagesx($thumb), imagesy($thumb));
}

$r = min(imagesx($img) / imagesx($play), imagesy($img) / imagesy($play));

$w = imagesx($play) * $r;
$h = imagesy($play) * $r;

$x = (imagesx($img) - $w) / 2;
$y = (imagesy($img) - $h) / 2;

imagecopyresampled($img, $play, $x, $y, 0, 0,
                $w, $h, imagesx($play), imagesy($play));

header('Content-Type: image/png');
imagepng($img);

?>
