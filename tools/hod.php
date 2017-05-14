#!/usr/bin/php
<?php
/*
   Copyright (C) 2016 Timo Kousa

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

$opts = getopt('3c:C:efhi:k:K:l:n:N:o:p:rsSt:u:U:w:');

if (!$opts) {
        echo "Usage: $argv[0] [options] -i input\n";
        exit;
}

if (isset($opts['h'])) {
        echo "A script to make ABR HLS (C) Timo Kousa\n\n";
        echo "Usage: $argv[0] [options] input\n\n";
        echo "Options:\n";
        echo " -3            restrict to HLSv3\n";
        echo " -c <file>     ABR config file (default /etc/hod.conf)\n";
        echo " -C <file>     \"keepalive\" file\n";
        echo " -e            use time() as index in filenames\n";
        echo " -f            force overwrite of output files\n";
        echo " -i <input>    input stream\n";
        echo " -k <keyfile>  keyfile for aes encryption\n";
        echo " -K <url>      url for aes key in the playlist\n";
        echo " -l <lang>     preferred language for audio / subtitles\n";
        echo " -n <count>    keep <count> segments (0 keeps all)\n";
        echo " -N <int>      value to pass for nice -n\n";
        echo " -o <opts>     options for ffmpeg\n";
        echo " -p <prefix>   prefix for all files to be created\n";
        echo " -r            randomize every IV\n";
        echo " -s            use the system time instead of PTS\n";
        echo " -S            burn subtitles\n";
        echo " -t <sec>      target duration of a segment (default: 10)\n";
        echo " -u <url>      url prefix for playlists in the abr playlist\n";
        echo " -U <url>      url prefix for ts-files in the playlist\n";
        echo " -w <dir>      directory to store temporary files (default: .)\n\n";
        exit;
}

if (!isset($opts['i']) || !$opts['i']) {
        error_log("Input is required (-i)");
        exit;
}

$conf_file = (isset($opts['c']) && $opts['c']) ? $opts['c'] : '/etc/hod.conf';
$prefix = (isset($opts['p']) && $opts['p']) ? basename($opts['p']) : basename($opts['i']);
$workdir = (isset($opts['w']) && $opts['w']) ?
        rtrim($opts['w'], DIRECTORY_SEPARATOR) : '.';
$datadir = (isset($opts['p']) && $opts['p']) ? dirname($opts['p']) : '.';
$files = array();
$nice = '';

if (!is_dir($datadir) && !mkdir($datadir)) {
        error_log("can not create directory $datadir");
        exit;
}

if (file_exists($datadir . DIRECTORY_SEPARATOR . $prefix . '.m3u8') &&
                !isset($opts['f'])) {
        error_log("file exists " . $datadir . DIRECTORY_SEPARATOR .
                $prefix .  '.m3u8');
        exit;
}

$seg_opts = '';
if (isset($opts['C']))
        $seg_opts .= ' -c ' . escapeshellarg($opts['C']);
if (isset($opts['e']))
        $seg_opts .= ' -e';
if (isset($opts['f']))
        $seg_opts .= ' -f';
if (isset($opts['K']))
        $seg_opts .= ' -K ' . escapeshellarg($opts['K']);
if (isset($opts['n']))
        $seg_opts .= ' -n ' . escapeshellarg($opts['n']);
if (isset($opts['N']))
        $nice = 'nice -n ' . escapeshellarg($opts['N']) . ' ';
if (isset($opts['r']))
        $seg_opts .= ' -r';
if (isset($opts['s']))
        $seg_opts .= ' -s';
if (isset($opts['t']))
        $seg_opts .= ' -t ' . escapeshellarg($opts['t']);
if (isset($opts['U']))
        $seg_opts .= ' -U ' . escapeshellarg($opts['U']);

if (isset($opts['k'])) {
        if (!file_exists($opts['k'])) {
                file_put_contents($opts['k'], openssl_random_pseudo_bytes(16));
                $files[] = $opts['k'];
        }

        $seg_opts .= ' -k ' . escapeshellarg($opts['k']);
}

if (file_exists($conf_file))
        include_once $conf_file;
else {
        $filter = "yadif";

        $audio['bw'] = "64000";
        $audio['codec'] = "mp4a.40.2";
        $audio['ffopt'] = "-c:a aac -ac 2 -b:a " . $audio['bw'];

        $profiles['cell']['bw'] = "400000";
        $profiles['cell']['width'] = "480";
        $profiles['cell']['height'] = "270";
        $profiles['cell']['codec'] = "avc1.42001e";
        $profiles['cell']['ffopt'] = "-c:v libx264 -preset ultrafast -profile:v baseline -level 3.0 -s " . $profiles['cell']['width'] . "x" . $profiles['cell']['height'] . " -b:v " . $profiles['cell']['bw'] . " -minrate " . $profiles['cell']['bw'] . " -maxrate " . $profiles['cell']['bw'] . " -bufsize " . round($profiles['cell']['bw'] / 2) . " -r 12.5 -x264opts keyint=40";

        $profiles['wifi']['bw'] = "1200000";
        $profiles['wifi']['width'] = "640";
        $profiles['wifi']['height'] = "360";
        $profiles['wifi']['codec'] = "avc1.42001f";
        $profiles['wifi']['ffopt'] = "-c:v libx264 -preset ultrafast -profile:v baseline -level 3.1 -s " . $profiles['wifi']['width'] . "x" . $profiles['wifi']['height'] . " -b:v " . $profiles['wifi']['bw'] . " -minrate " . $profiles['wifi']['bw'] . " -maxrate " . $profiles['wifi']['bw'] . " -bufsize " . round($profiles['wifi']['bw'] / 2) . " -r 25 -x264opts keyint=80";
}

if (!file_exists($workdir)) {
        if (!mkdir($workdir)) {
                error_log("Failed to create directory $workdir");
                exit;
        }
}

$lock = fopen($workdir . DIRECTORY_SEPARATOR . $prefix . '.lock', 'c');

if (!flock($lock, LOCK_EX | LOCK_NB)) {
        error_log("Couldn't get the lock, script already running?");
        exit;
}

$output = array();
exec('timeout -s 9 60 ffprobe -loglevel fatal -print_format json -show_data -show_streams ' .
                escapeshellarg($opts['i']), $output);

$probe = json_decode(implode($output));

$fifos = array();
$video_indexes = array();
$audio_indexes = array();
$subtitle_indexes = array();
$default_audio = false;
$default_subtitle = false;

if ($probe && isset($probe->{'streams'}))
        foreach ($probe->{'streams'} as $stream) {
                if (!isset($stream->{'codec_type'}))
                        continue;

                switch ($stream->{'codec_type'}) {
                        case "video":
                                $video_indexes[] = $stream->{'index'};
                                break;
                        case "audio":
                                $audio_indexes[] = $stream->{'index'};

                                if (isset($opts['l'], $stream->{'tags'},
                                                        $stream->{'tags'}->{'language'}) &&
                                                stripos($stream->{'tags'}->{'language'},
                                                        $opts['l']) !== false &&
                                                $default_audio === false)
                                        $default_audio = $stream->{'index'};
                                break;
                        case "subtitle":
                                $subtitle_indexes[] = $stream->{'index'};

                                if (isset($opts['l'], $stream->{'tags'},
                                                        $stream->{'tags'}->{'language'}) &&
                                                stripos($stream->{'tags'}->{'language'},
                                                        $opts['l']) !== false &&
                                                ($default_subtitle === false ||
                                                 stripos($probe->{'streams'}[$default_subtitle]->{'codec_name'},
                                                         'teletext') !== false))
                                        $default_subtitle = $stream->{'index'};
                                break;
                }
        }

if (count($audio_indexes)) {
        if ($default_audio === false)
                $default_audio = $audio_indexes[0];

        while ($audio_indexes[0] != $default_audio) {
                $tmp = array_shift($audio_indexes);
                $audio_indexes[] = $tmp;
        }
}

$ffopts = '';

if (isset($opts['S']) && $default_subtitle !== false &&
                stripos($probe->{'streams'}[$default_subtitle]->{'codec_name'},
                        'teletext') !== false) {
        $tmp = trim($probe->{'streams'}[$default_subtitle]->{'extradata'});
        $tmp = preg_replace('/^.*:\s*/', '', $tmp);
        $tmp = preg_replace('/[^0-9]*$/', '', $tmp);

        $txtpages = array_combine(explode(' ', $tmp),
                        explode(',', $probe->{'streams'}[$default_subtitle]->{'tags'}->{'language'}));

        foreach ($txtpages as $page => $lang)
                if (stripos($lang, $opts['l']) !== false)
                        $ffopts = ' -txt_page ' . $page;
}

$ffopts .= (isset($opts['o']) ? ' ' . $opts['o'] : '') . ' -i ' . escapeshellarg($opts['i']);

if (count($video_indexes)) {
        $ffopts .= ' -filter_complex ';

        $tmp = '';

        if ($filter)
                $tmp = '[0:' . $video_indexes[0] . ']' . $filter;

        if ($default_subtitle !== false && isset($opts['S'])) {
                if ($filter)
                        $tmp .= '[vidoverlay];';

                $tmp .= '[0:' . $default_subtitle . ']scale=' .
                        $probe->{'streams'}[$video_indexes[0]]->{'width'} . ':'
                        . $probe->{'streams'}[$video_indexes[0]]->{'height'} .
                        '[suboverlay];';
                if ($filter)
                        $tmp .= '[vidoverlay]';
                else
                        $tmp .= '[0:' . $video_indexes[0] . ']';
                $tmp .= '[suboverlay]overlay';
        }

        if ($tmp)
                $tmp .= ',';

        $tmp .= 'split=' . count($profiles) . '[' . join('][',
                                        array_keys($profiles)) . ']';

        $ffopts .= escapeshellarg($tmp);
}

$manifest = '';

if (!isset($opts['3'])) {
        foreach ($audio_indexes as $index) {
                $lang = isset($probe->{'streams'}[$index]->{'tags'},
                                $probe->{'streams'}[$index]->{'tags'}->{'language'}) ?
                        $probe->{'streams'}[$index]->{'tags'}->{'language'} : $index;

                $manifest .= '#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID="audio"';
                $manifest .= ',NAME="' . $lang . ' (' . $index . ')"';
                $manifest .= ',LANGUAGE="' . $lang . '"';
                $manifest .= ',AUTOSELECT=YES';
                if ($index == $default_audio)
                        $manifest .= ',DEFAULT=YES';
                $manifest .= ',URI="' . (isset($opts['u']) ? $opts['u'] : '' ) .
                        $prefix . '.audio.' . $lang . '-' . $index . '.m3u8"' . "\n";

                $fifo = $workdir . DIRECTORY_SEPARATOR .
                        $prefix . '.audio.' . $lang . '-' . $index . '.fifo';

                if (@filetype($fifo) != 'fifo' && !posix_mkfifo($fifo, 0644)) {
                        error_log("could not create fifo");
                        exit;
                }

                $fifos[] = $fifo;

                $ffopts .= ' -vn -map 0:' . $index . ' ' . $audio['ffopt'] .
                        ' -f mpegts -y ' . escapeshellarg($fifo);

                $m3u8 = $datadir . DIRECTORY_SEPARATOR . $prefix . '.audio.' .
                        $lang . '-' . $index . '.m3u8';

                exec('segmenter' .
                                $seg_opts .
                                ' -p ' . escapeshellarg($m3u8) .
                                ' -i ' . escapeshellarg($fifo) .
                                ' ' . escapeshellarg($datadir . DIRECTORY_SEPARATOR .
                                        $prefix . '.audio.' . $lang . '-' .
                                        $index . '.%u.ts') .
                                ' > /dev/null 2> /dev/null &');

                $files[] = $m3u8;
        }
}

if (count($video_indexes)) {
        foreach ($profiles as $key => $profile) {
                $manifest .= '#EXT-X-STREAM-INF:PROGRAM-ID=1';
                $manifest .= ',BANDWIDTH=' . (($profile['bw'] + (count($audio_indexes) ?
                                                $audio['bw'] : 0)) * 1.1);
                $manifest .= ',CODECS="' . $profile['codec'];
                if (isset($opts['3']) && count($audio_indexes))
                        $manifest .= ',' . $audio['codec'];
                $manifest .= '"';
                $manifest .= ',RESOLUTION=' . $profile['width'] . 'x' . $profile['height'];
                if (count($audio_indexes) && !isset($opts['3']))
                        $manifest .= ',AUDIO="audio"';
                $manifest .= "\n";

                $manifest .= (isset($opts['u']) ? $opts['u'] : '' ) .
                        $prefix . '.video.' . $key . ".m3u8\n";

                $fifo = $workdir . DIRECTORY_SEPARATOR .
                        $prefix . '.video.' . $key . '.fifo';

                if (@filetype($fifo) != 'fifo' && !posix_mkfifo($fifo, 0644)) {
                        error_log("could not create fifo");
                        exit;
                }

                $fifos[] = $fifo;

                $ffopts .= ' -map ' . escapeshellarg('[' . $key . ']');

                if (isset($opts['3']) && count($audio_indexes))
                        $ffopts .= ' -map 0:' .$default_audio . ' ' . $audio['ffopt'];
                else
                        $ffopts .= ' -an';

                if (count($subtitle_indexes) && !isset($opts['S']))
                        $ffopts .= ' -map 0:s -c:s copy';

                $ffopts .= ' -s ' . $profile['width'] . 'x' . $profile['height'] .
                        ' ' . $profile['ffopt'] . ' -f mpegts -y ' . escapeshellarg($fifo);

                $m3u8 = $datadir . DIRECTORY_SEPARATOR . $prefix . '.video.' .
                        $key . '.m3u8';

                exec('segmenter' .
                                $seg_opts .
                                ((isset($opts['3']) && count($audio_indexes)) ?
                                 '' : ' -a') .
                                ' -p ' . escapeshellarg($m3u8) .
                                ' -i ' . escapeshellarg($fifo) .
                                ' ' . escapeshellarg($datadir . DIRECTORY_SEPARATOR .
                                        $prefix . '.video.' . $key . '.%u.ts') .
                                ' > /dev/null 2> /dev/null &');

                $files[] = $m3u8;
        }
}

if (count($audio_indexes)) {
        $lang = isset($probe->{'streams'}[$default_audio]->{'tags'},
                        $probe->{'streams'}[$default_audio]->{'tags'}->{'language'}) ?
                $probe->{'streams'}[$default_audio]->{'tags'}->{'language'} : $default_audio;

        $manifest .= '#EXT-X-STREAM-INF:PROGRAM-ID=1';
        $manifest .= ',BANDWIDTH=' . ($audio['bw'] * 1.1);
        $manifest .= ',CODECS="' . $audio['codec'] . '"';
        if (!isset($opts['3']))
                $manifest .= ',AUDIO="audio"';
        $manifest .= "\n";

        $manifest .= (isset($opts['u']) ? $opts['u'] : '' ) .
                $prefix . '.audio.' . $lang . ".m3u8\n";

        if (isset($opts['3'])) {
                $fifo = $workdir . DIRECTORY_SEPARATOR .
                        $prefix . '.audio.' . $lang . '.fifo';

                if (@filetype($fifo) != 'fifo' && !posix_mkfifo($fifo, 0644)) {
                        error_log("could not create fifo");
                        exit;
                }

                $fifos[] = $fifo;

                $ffopts .= ' -vn -map 0:' . $default_audio . ' ' . $audio['ffopt'] .
                        ' -f mpegts -y ' . escapeshellarg($fifo);

                $m3u8 = $datadir . DIRECTORY_SEPARATOR . $prefix . '.audio.' .
                        $lang . '.m3u8';

                exec('segmenter' .
                                $seg_opts .
                                ' -p ' . escapeshellarg($m3u8) .
                                ' -i ' . escapeshellarg($fifo) .
                                ' ' . escapeshellarg($datadir . DIRECTORY_SEPARATOR .
                                        $prefix . '.audio.' . $lang . '.%u.ts') .
                                ' > /dev/null 2> /dev/null &');

                $files[] = $m3u8;
        }
}

if ($manifest) {
        $manifest = "#EXTM3U\n" . $manifest;
        $m3u8 = $datadir . DIRECTORY_SEPARATOR . $prefix . '.m3u8';

        file_put_contents($m3u8 . '.part', $manifest);
        rename($m3u8 . '.part', $m3u8);

        $files[] = $m3u8;
}

if (count($fifos)) {
        exec($nice . 'ffmpeg' . $ffopts);
        exec('touch ' . join(' ', $fifos));

        foreach ($fifos as $fifo)
                unlink($fifo);
}

if (isset($opts['n'])) {
        $files = array_merge($files, glob($datadir . DIRECTORY_SEPARATOR .
                                $prefix . '.*.ts'));
        $files = array_merge($files, glob($datadir . DIRECTORY_SEPARATOR .
                                $prefix . '.*.part'));

        foreach ($files as $file)
                @unlink($file);

        if ($datadir != '.')
                @rmdir($datadir);
}

@unlink($workdir . DIRECTORY_SEPARATOR . $prefix . '.lock');
fclose($lock);

?>
