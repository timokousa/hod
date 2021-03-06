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

#include <errno.h>
#include <fcntl.h>
#include <inttypes.h>
#include <libgen.h>
#include <openssl/aes.h>
#include <openssl/err.h>
#include <openssl/rand.h>
#include <signal.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <time.h>
#include <unistd.h>

#define DELTA_THRESHOLD 10
#define MAX_PIDS 8192
#define PTS_HZ 90000
#define TS_SIZE 188
#define WBUF_SIZE TS_SIZE * AES_BLOCK_SIZE
#define KEY_SIZE 16

static int random_iv = 0;

typedef struct segment {
        unsigned int index, nameindex, discont, keyindex;
        unsigned char iv[AES_BLOCK_SIZE];
        long long time;
        struct segment *next;
} segment;

void bailout_plan(int sig)
{
        fprintf(stderr, "watchdog timer expired, exiting..\n");

        exit(EXIT_FAILURE);
}

int seg_push(segment **seg, unsigned int index, long long time,
                unsigned int nameindex, unsigned int keyindex,
                unsigned char *iv, unsigned int discont)
{
        segment *new, *tmp;

        new = malloc(sizeof(segment));

        if (!new) {
                fprintf(stderr, "malloc fail.\n");
                return 0;
        }

        new->index = index;
        new->time = time;
        new->nameindex = nameindex;
        memcpy(new->iv, iv, AES_BLOCK_SIZE);
        new->discont = discont;
        new->keyindex = keyindex;
        new->next = NULL;

        if (*seg) {
                tmp = *seg;
                while (tmp->next)
                        tmp = tmp->next;

                tmp->next = new;
        }
        else
                *seg = new;

        return 1;
}

ssize_t safe_read(int fd, uint8_t *buf, size_t count)
{
        ssize_t tmp, n = 0;

        while (n < count) {
                tmp = read(fd, &buf[n], count - n);

                if (tmp <= 0)
                        break;

                n += tmp;
        }

        return n;
}

int playlist(segment *seg, char* plist, char *fmt, char *url_prefix,
                char *key_url, int live, int the_end)
{
        char *tmpfile, *tmpurl, *basec = NULL, *basen, ivstr[AES_BLOCK_SIZE * 2 + 1];
        int i, tdur = 0, keyindex = -1;
        FILE *fp;
        segment *tmp;

        if (!(tmpfile = malloc(sizeof (char) * (strlen(plist) + 6)))) {
                fprintf(stderr, "malloc error.\n");
                return 0;
        }

        if (key_url)
                if (!(tmpurl = malloc(sizeof (char) * (strlen(key_url) + 20)))) {
                        fprintf(stderr, "malloc error.\n");
                        return EXIT_FAILURE;
                }

        sprintf(tmpfile, "%s.part", plist);

        if ((fp = fopen(tmpfile, "w")) == NULL) {
                fprintf(stderr, "could not open file %s\n", tmpfile);
                free(tmpfile);

                return 0;
        }

        tmp = seg;

        if (tmp && tmp->index && tmp->next)
                tmp = tmp->next;

        while (tmp) {
                if (tmp->time / PTS_HZ + 1 > tdur)
                        tdur = tmp->time / PTS_HZ + 1;
                tmp = tmp->next;
        }

        tmp = seg;

        if (tmp && tmp->index && tmp->next)
                tmp = tmp->next;

        fprintf(fp, "#EXTM3U\n");
        fprintf(fp, "#EXT-X-VERSION:3\n");
        fprintf(fp, "#EXT-X-TARGETDURATION:%u\n", tdur);
        fprintf(fp, "#EXT-X-MEDIA-SEQUENCE:%u\n", tmp ? tmp->index : 0);

        if (!live)
                fprintf(fp, "#EXT-X-PLAYLIST-TYPE:%s\n",
                                the_end ? "VOD" : "EVENT");

        if (url_prefix) {
                basec = strdup(fmt);
                basen = basename(basec);
        }

        while (tmp) {
                if (key_url && (random_iv || keyindex != tmp->keyindex)) {
                        sprintf(tmpurl, key_url, tmp->keyindex);

                        fprintf(fp, "#EXT-X-KEY:METHOD=AES-128,URI=\"%s\"",
                                        tmpurl);

                        if (random_iv) {
                                for (i = 0; i < AES_BLOCK_SIZE; i++)
                                        sprintf(&ivstr[i * 2], "%02x",
                                                        tmp->iv[i]);

                                fprintf(fp, ",IV=0x%s", ivstr);
                        }

                        fprintf(fp, "\n");

                        keyindex = tmp->keyindex;
                }

                fprintf(fp, "#EXTINF:%f,\n", ((double) tmp->time / PTS_HZ));

                if (url_prefix) {
                        fprintf(fp, "%s", url_prefix);
                        fprintf(fp, basen, tmp->nameindex);
                }
                else
                        fprintf(fp, fmt, tmp->nameindex);

                fprintf(fp, "\n");

                if (tmp->discont)
                        fprintf(fp, "#EXT-X-DISCONTINUITY\n");

                tmp = tmp->next;
        }

        if (basec)
                free(basec);

        if (the_end)
                fprintf(fp, "#EXT-X-ENDLIST\n");

        fclose(fp);

        if (rename(tmpfile, plist) == -1) {
                fprintf(stderr, "could not rename file %s %s\n",
                                tmpfile, plist);
                free(tmpfile);

                return 0;
        }

        free(tmpfile);

        if (key_url)
                free(tmpurl);

        return 1;
}

int main(int argc, char *argv[])
{
        uint8_t ts[TS_SIZE], patpmt[TS_SIZE * 2], *buf;
        uint16_t pid, pmtpid = 0, tpid = 0;
        uint64_t pts = 0, last_pts = 0;
        long long pts_delta, cur_seg = 0, last_seg = 0;
        int fd, afe, afl, pl_st, count = 0, opt, force = 0, epoch = 0,
            seg_len = 10, verbose = 0, audio_pts = 1, systime = 0, i,
            pes_remaining[MAX_PIDS] = { 0 }, limbo = 0, buf_size = TS_SIZE * 2,
            buf_n = 0, wbuf_n = 0, pesses = 0, input = STDIN_FILENO;
        unsigned int index = 0, nameindex, keyindex, kr_count = 0, rotate = 0,
                     disco = 0, bits = 0, bandwidth = 0;
        char *fname, *fmt, *plist = NULL, *tmpfile, *keepalive = NULL,
             *keycmd = NULL, *tmpcmd, *url_prefix = NULL, *key_url = NULL;
        struct stat sb;
        segment *seg = NULL, *tmpseg;
        unsigned char wbuf[WBUF_SIZE], key[KEY_SIZE], iv[AES_BLOCK_SIZE],
                      iv_init[AES_BLOCK_SIZE], ebuf[WBUF_SIZE];
        AES_KEY aes_key;
        FILE *fp;

        while ((opt = getopt(argc, argv, "ac:efhi:k:K:n:p:rR:st:T:U:v")) != -1) {
                switch (opt) {
                        case 'a':
                                audio_pts = 0;
                                break;
                        case 'c':
                                keepalive = optarg;
                                break;
                        case 'e':
                                epoch = 1;
                                break;
                        case 'f':
                                force = 1;
                                break;
                        case 'h':
                                printf("TS Segmenter 0.1 (C) Timo Kousa\n");
                                printf("Reads mpeg-TS from a file and writes it to smaller files\n\n");
                                printf("Usage: %s [options] -i input output_format\n\n",
                                                argv[0]);
                                printf("Options:\n");
                                printf(" -a             use the first detected video stream for PTS instead of audio\n");
                                printf(" -c <file>      \"keepalive\" file\n");
                                printf(" -e             use time() as index in filenames\n");
                                printf(" -f             force overwrite of output files\n");
                                printf(" -i <file>      use <file> for input (default: stdin)\n");
                                printf(" -k <cmd>       command to get encryption key, use %%u as the key index\n");
                                printf("                example: \"openssl rand 16 | tee key-%%u\"\n");
                                printf(" -K <url>       url for aes key in the playlist, use %%u as the key index\n");
                                printf(" -n <count>     keep <count> segments in the playlist (0 keeps all)\n");
                                printf(" -p <filename>  write playlist file\n");
                                printf(" -r             randomize every IV\n");
                                printf(" -R <count>     rotate key every <count> segments\n");
                                printf(" -s             use the system time instead of PTS\n");
                                printf(" -t <sec>       target duration of a segment (default: 10)\n");
                                printf(" -T <pid>       use <pid> for PTS\n");
                                printf(" -U <url>       url prefix for files in the playlist\n");
                                printf(" -v             increase verbosity\n");
                                printf(" output_format  output filename format, use %%u as the index e.g. foo-%%u.ts\n\n");
                                return EXIT_SUCCESS;
                        case 'i':
                                input = open(optarg, O_RDONLY);
                                if (input == -1) {
                                        fprintf(stderr, "could not open input\n");
                                        return EXIT_FAILURE;
                                }
                                break;
                        case 'k':
                                keycmd = optarg;
                                break;
                        case 'K':
                                key_url = optarg;
                                break;
                        case 'n':
                                count = atoi(optarg);
                                break;
                        case 'p':
                                plist = optarg;
                                break;
                        case 'r':
                                random_iv = 1;
                                break;
                        case 'R':
                                rotate = atoi(optarg);
                                break;
                        case 's':
                                systime = 1;
                                break;
                        case 't':
                                seg_len = atoi(optarg);
                                break;
                        case 'T':
                                tpid = atoi(optarg);
                                break;
                        case 'U':
                                url_prefix = optarg;
                                break;
                        case 'v':
                                verbose++;
                                break;
                        default:
                                fprintf(stderr, "Usage: %s [options] output_format\n",
                                                argv[0]);
                                return EXIT_FAILURE;
                }
        }

        if (optind >= argc) {
                fprintf(stderr, "output filename format required.\n");
                return EXIT_FAILURE;
        }
        else
                fmt = argv[optind];

        nameindex = epoch ? time(NULL) : index;
        keyindex = nameindex;

        if (keycmd) {
                if (!(tmpcmd = malloc(sizeof (char) * (strlen(keycmd) + 20)))) {
                        fprintf(stderr, "malloc error.\n");
                        return EXIT_FAILURE;
                }

                sprintf(tmpcmd, keycmd, keyindex);

                if (!(fp = popen(tmpcmd, "r"))) {
                        fprintf(stderr, "could not run key command\n");
                        return EXIT_FAILURE;
                }

                for (i = 0; i < KEY_SIZE; i++) {
                        key[i] = fgetc(fp);

                        if (feof(fp)) {
                                fprintf(stderr, "error reading encryption key.\n");
                                return EXIT_FAILURE;
                        }
                }

                if (pclose(fp) != EXIT_SUCCESS) {
                        fprintf(stderr, "error running key command.\n");
                        return EXIT_FAILURE;
                }

                AES_set_encrypt_key(key, 128, &aes_key);

                if (random_iv) {
                        if (RAND_bytes(iv_init, AES_BLOCK_SIZE) <= 0) {
                                fprintf(stderr, "%s\n",
                                                ERR_error_string(
                                                        ERR_get_error(),
                                                        NULL));
                                return EXIT_FAILURE;
                        }
                }
                else
                        memset(iv_init, 0, AES_BLOCK_SIZE);

                memcpy(iv, iv_init, AES_BLOCK_SIZE);
        }

        if (!(fname = malloc(sizeof (char) * (strlen(fmt) + 20)))) {
                fprintf(stderr, "malloc error.\n");
                return EXIT_FAILURE;
        }

        if (!(tmpfile = malloc(sizeof (char) * (strlen(fmt) + 25)))) {
                fprintf(stderr, "malloc error.\n");
                return EXIT_FAILURE;
        }

        sprintf(fname, fmt, nameindex);
        sprintf(tmpfile, "%s.part", fname);

        if (!force && !(stat(tmpfile, &sb) == -1 && errno == ENOENT)) {
                fprintf(stderr, "file exists %s\n", tmpfile);
                return EXIT_FAILURE;
        }

        fd = open(tmpfile, O_CREAT | O_WRONLY,
                        S_IRUSR | S_IWUSR | S_IRGRP | S_IROTH);

        if (fd == -1) {
                fprintf(stderr, "could not open file %s\n", tmpfile);
                return EXIT_FAILURE;
        }

        if (!(buf = malloc(sizeof (uint8_t) * buf_size))) {
                fprintf(stderr, "malloc error.\n");
                return EXIT_FAILURE;
        }

        if (count) {
                signal(SIGALRM, bailout_plan);
                alarm(seg_len * 3);
        }

        if (verbose)
                printf("writing %s ..\n", fname);

        while (safe_read(input, ts, TS_SIZE) == TS_SIZE) {
                if (ts[0] != 0x47) {
                        fprintf(stderr, "incoming data is not TS\n");
                        return EXIT_FAILURE;
                }

                pid = ((ts[1] & 0x1f) << 8) | ts[2];

                if (pid == 0x1fff)
                        continue;

                afl = 0;
                afe = (ts[3] & 0x30) >> 4;

                if (afe == 2)
                        afl = 184;
                else if (afe == 3)
                        afl = ts[4] + 1;

                pl_st = 4 + afl;

                if (pid == 0) {
                        if (!ts[pl_st + 6] & 0x01)
                                continue;

                        memcpy(patpmt, ts, TS_SIZE);
                        pmtpid = ((ts[pl_st + 11] & 0x1f) << 8) | ts[pl_st + 12];

                        if (buf_n)
                                continue;
                }

                if (pmtpid && pid == pmtpid) {
                        if (!ts[pl_st + 6] & 0x01)
                                continue;

                        memcpy(patpmt + TS_SIZE, ts, TS_SIZE);

                        if (!buf_n) {
                                patpmt[3] = (patpmt[3] & 0xf0) |
                                        ((index + 1) & 0x0f);
                                patpmt[TS_SIZE + 3] = (patpmt[TS_SIZE + 3] & 0xf0) |
                                        ((index + 1) & 0x0f);

                                memcpy(buf, patpmt, TS_SIZE * 2);
                                buf_n = TS_SIZE * 2;
                        }
                        else
                                continue;
                }

                if (!systime && !tpid) {
                        if ((ts[1] & 0x40) &&
                                        (ts[pl_st] == 0x00) &&
                                        (ts[pl_st + 1] == 0x00) &&
                                        (ts[pl_st + 2] == 0x01) &&
                                        ((!audio_pts &&
                                          ts[pl_st + 3] >= 0xe0 &&
                                          ts[pl_st + 3] <= 0xef) ||
                                         (audio_pts &&
                                          ts[pl_st + 3] >= 0xc0 &&
                                          ts[pl_st + 3] <= 0xdf)))
                                tpid = pid;

                        if (verbose && tpid)
                                printf("using PID %u for PTS\n", tpid);
                }

                if (pid == tpid || systime) {
                        if (!systime && (ts[1] & 0x40) &&
                                        (ts[pl_st] == 0x00) &&
                                        (ts[pl_st + 1] == 0x00) &&
                                        (ts[pl_st + 2] == 0x01) &&
                                        (ts[pl_st + 7] & 0x80)) {
                                uint64_t p0, p1, p2, p3, p4;

                                p0 = (ts[pl_st + 13] & 0xfe) >> 1 |
                                        ((ts[pl_st + 12] & 1) << 7);
                                p1 = (ts[pl_st + 12] & 0xfe) >> 1 |
                                        ((ts[pl_st + 11] & 2) << 6);
                                p2 = (ts[pl_st + 11] & 0xfc) >> 2 |
                                        ((ts[pl_st + 10] & 3) << 6);
                                p3 = (ts[pl_st + 10] & 0xfc) >> 2 |
                                        ((ts[pl_st + 9] & 6) << 5);
                                p4 = (ts[pl_st + 9] & 0x08) >> 3;

                                pts = p0 | (p1 << 8) | (p2 << 16) |
                                        (p3 << 24) | (p4 << 32);

                                if (verbose >= 2)
                                        printf("pts: %" PRIu64 "\n", pts);
                        }
                        else if (systime)
                                pts = time(NULL) * PTS_HZ;

                        if (!last_pts)
                                last_pts = pts;

                        pts_delta = pts - last_pts;

                        if (llabs(pts_delta) / PTS_HZ > DELTA_THRESHOLD)
                                disco = 1;
                        else
                                cur_seg += pts_delta;

                        last_pts = pts;
                }

                if (!limbo && (pid == tpid || systime) &&
                                ((systime && cur_seg / PTS_HZ >= seg_len * 0.9 &&
                                  time(NULL) % seg_len == 0) ||
                                 (!systime && (afl && ts[5] & 0x40) &&
                                  (cur_seg / PTS_HZ >= seg_len * 0.9)) ||
                                 (cur_seg / PTS_HZ >= seg_len * 1.3))) {
                        limbo = 1;
                        cur_seg = 0;
                }
                else if (!limbo && (pid == tpid || systime))
                        last_seg = cur_seg;

                if (limbo && !pesses) {
                        if (count)
                                alarm(seg_len * 2);

                        if (keepalive && count &&
                                        (stat(keepalive, &sb) == -1 ||
                                         (sb.st_mtime + (count * seg_len) <
                                          time(NULL)))) {
                                if (verbose)
                                        printf("keepalive file too old, bailing out..\n");

                                break;
                        }

                        if (keycmd) {
                                i = AES_BLOCK_SIZE - (wbuf_n % AES_BLOCK_SIZE);

                                memset(&wbuf[wbuf_n], i, i);
                                wbuf_n += i;

                                AES_cbc_encrypt(wbuf, ebuf, wbuf_n, &aes_key,
                                                iv, AES_ENCRYPT);
                        }

                        if (write(fd, keycmd ? ebuf : wbuf, wbuf_n) != wbuf_n) {
                                fprintf(stderr, "write failed\n");
                                return EXIT_FAILURE;
                        }

                        wbuf_n = 0;

                        close(fd);

                        if (!force && !(stat(fname, &sb) == -1 &&
                                                errno == ENOENT)) {
                                fprintf(stderr, "file exists %s\n", fname);
                                return EXIT_FAILURE;
                        }

                        if (rename(tmpfile, fname) == -1) {
                                fprintf(stderr, "could not rename file %s %s\n",
                                                tmpfile, fname);
                                return EXIT_FAILURE;
                        }

                        seg_push(&seg, index, last_seg, nameindex, keyindex,
                                        iv_init, disco);

                        disco = 0;

                        if (count && index > count) {
                                sprintf(fname, fmt, seg->nameindex);

                                tmpseg = seg;
                                seg = seg->next;
                                free(tmpseg);
                        }

                        if (plist) {
                                if (verbose)
                                        printf("writing %s\n", plist);

                                playlist(seg, plist, fmt, url_prefix, key_url,
                                                count, 0);
                        }

                        if (count && index > count) {
                                if (verbose)
                                        printf("removing %s\n", fname);

                                unlink(fname);
                        }

                        index++;
                        nameindex = epoch ? time(NULL) : index;

                        if (keycmd && rotate && ++kr_count >= rotate) {
                                keyindex = nameindex;
                                kr_count = 0;

                                sprintf(tmpcmd, keycmd, keyindex);

                                if (!(fp = popen(tmpcmd, "r"))) {
                                        fprintf(stderr, "could not run key command\n");
                                        return EXIT_FAILURE;
                                }

                                for (i = 0; i < KEY_SIZE; i++) {
                                        key[i] = fgetc(fp);

                                        if (feof(fp)) {
                                                fprintf(stderr, "error reading encryption key.\n");
                                                return EXIT_FAILURE;
                                        }
                                }

                                if (pclose(fp) != EXIT_SUCCESS) {
                                        fprintf(stderr, "error running key command.\n");
                                        return EXIT_FAILURE;
                                }

                                AES_set_encrypt_key(key, 128, &aes_key);
                        }

                        if (keycmd) {
                                if (random_iv) {
                                        if (RAND_bytes(iv_init, AES_BLOCK_SIZE)
                                                        <= 0) {
                                                fprintf(stderr, "%s\n",
                                                                ERR_error_string(
                                                                        ERR_get_error(),
                                                                        NULL));
                                                return EXIT_FAILURE;
                                        }
                                }
                                else {
                                        iv_init[AES_BLOCK_SIZE - 1] =
                                                index & 0xff;
                                        iv_init[AES_BLOCK_SIZE - 2] =
                                                (index >> 8) & 0xff;
                                        iv_init[AES_BLOCK_SIZE - 3] =
                                                (index >> 16) & 0xff;
                                        iv_init[AES_BLOCK_SIZE - 4] =
                                                (index >> 24) & 0xff;
                                }

                                memcpy(iv, iv_init, AES_BLOCK_SIZE);
                        }

                        sprintf(fname, fmt, nameindex);
                        sprintf(tmpfile, "%s.part", fname);

                        if (!force && !(stat(tmpfile, &sb) == -1 &&
                                                errno == ENOENT)) {
                                fprintf(stderr, "file exists %s\n", tmpfile);
                                return EXIT_FAILURE;
                        }

                        if (1ll * bits * PTS_HZ / last_seg > bandwidth)
                                bandwidth = 1ll * bits * PTS_HZ / last_seg;

                        fd = open(tmpfile, O_CREAT | O_WRONLY,
                                        S_IRUSR | S_IWUSR |
                                        S_IRGRP | S_IROTH);

                        if (fd == -1) {
                                fprintf(stderr, "could not open file %s\n",
                                                tmpfile);
                                return EXIT_FAILURE;
                        }

                        if (verbose)
                                printf("writing %s ..\n", fname);

                        i = 0;

                        while (i < buf_n) {
                                if (buf_n - i >= WBUF_SIZE) {
                                        if (keycmd)
                                                AES_cbc_encrypt(&buf[i], ebuf,
                                                                WBUF_SIZE,
                                                                &aes_key, iv,
                                                                AES_ENCRYPT);

                                        if (write(fd, keycmd ? ebuf : &buf[i],
                                                                WBUF_SIZE) != WBUF_SIZE) {
                                                fprintf(stderr, "write failed\n");
                                                return EXIT_FAILURE;
                                        }

                                        i += WBUF_SIZE;
                                }
                                else {
                                        memcpy(wbuf, &buf[i], buf_n - i);
                                        wbuf_n = buf_n - i;

                                        break;
                                }
                        }

                        bits = buf_n * 8;
                        buf_n = 0;
                        limbo = 0;

                        if (pmtpid) {
                                patpmt[3] = (patpmt[3] & 0xf0) |
                                        ((index + 1) & 0x0f);
                                patpmt[TS_SIZE + 3] = (patpmt[TS_SIZE + 3] & 0xf0) |
                                        ((index + 1) & 0x0f);

                                memcpy(buf, patpmt, TS_SIZE * 2);
                                buf_n = TS_SIZE * 2;
                        }
                }

                if (ts[1] & 0x40 &&
                                ts[pl_st] == 0x00 &&
                                ts[pl_st + 1] == 0x00 &&
                                ts[pl_st + 2] == 0x01) {
                        if (limbo) {
                                if (pes_remaining[pid] > 0)
                                        pesses--;

                                pes_remaining[pid] = 0;
                        }
                        else {
                                if (pes_remaining[pid] <= 0)
                                        pesses++;

                                pes_remaining[pid] = ((ts[pl_st + 4] << 8) |
                                                ts[pl_st + 5]) + 6;
                        }
                }

                if (!limbo || pes_remaining[pid] > 0) {
                        memcpy(&wbuf[wbuf_n], ts, TS_SIZE);
                        wbuf_n += TS_SIZE;

                        if (wbuf_n == WBUF_SIZE) {
                                if (keycmd)
                                        AES_cbc_encrypt(wbuf, ebuf,
                                                        WBUF_SIZE, &aes_key,
                                                        iv, AES_ENCRYPT);

                                if (write(fd, keycmd ? ebuf : wbuf,
                                                        WBUF_SIZE) != WBUF_SIZE) {
                                        fprintf(stderr, "write failed\n");
                                        return EXIT_FAILURE;
                                }

                                wbuf_n = 0;
                        }

                        if (tpid)
                                bits += 1504;

                        if (pes_remaining[pid] > 0) {
                                pes_remaining[pid] -= TS_SIZE - pl_st;

                                if (pes_remaining[pid] <= 0)
                                        pesses--;
                        }
                }
                else {
                        if (buf_n + TS_SIZE > buf_size) {
                                buf_size *= 2;

                                if (!(buf = realloc(buf, sizeof (uint8_t) * buf_size))) {
                                        fprintf(stderr, "realloc error.\n");
                                        return EXIT_FAILURE;
                                }

                                if (verbose)
                                        printf("limbo buffer now %d bytes\n",
                                                        buf_size);
                        }

                        memcpy(&buf[buf_n], ts, TS_SIZE);
                        buf_n += TS_SIZE;
                }
        }

        if (input != STDIN_FILENO)
                close(input);

        if (keycmd) {
                i = AES_BLOCK_SIZE - (wbuf_n % AES_BLOCK_SIZE);

                memset(&wbuf[wbuf_n], i, i);
                wbuf_n += i;

                AES_cbc_encrypt(wbuf, ebuf, wbuf_n, &aes_key,
                                iv, AES_ENCRYPT);
        }

        if (write(fd, keycmd ? ebuf : wbuf, wbuf_n) != wbuf_n) {
                fprintf(stderr, "write failed\n");
                return EXIT_FAILURE;
        }

        close(fd);

        if (!force && !(stat(fname, &sb) == -1 && errno == ENOENT)) {
                fprintf(stderr, "file exists %s\n", fname);
                return EXIT_FAILURE;
        }

        if (rename(tmpfile, fname) == -1) {
                fprintf(stderr, "could not rename file %s %s\n",
                                tmpfile, fname);
                return EXIT_FAILURE;
        }

        seg_push(&seg, index, cur_seg, nameindex, keyindex, iv_init, disco);

        if (count && index > count) {
                sprintf(fname, fmt, seg->nameindex);

                tmpseg = seg;
                seg = seg->next;
                free(tmpseg);
        }

        if (plist) {
                if (verbose)
                        printf("writing %s\n", plist);

                playlist(seg, plist, fmt, url_prefix, key_url, count, 1);
        }

        if (count && index > count) {
                if (verbose)
                        printf("removing %s\n", fname);

                unlink(fname);
        }

        printf("BANDWIDTH=%u\n", bandwidth);

        free(fname);
        free(tmpfile);

        while (seg) {
                tmpseg = seg;
                seg = seg->next;
                free(tmpseg);
        }

        return EXIT_SUCCESS;
}
