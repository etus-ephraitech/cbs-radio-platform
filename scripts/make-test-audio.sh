#!/usr/bin/env bash
#
# Regenerates the audio fixtures used by the voice-note tests (tests/_support/Audio).
# Needs ffmpeg. The files are committed, so you only run this to change them.
#
# Every "valid" file is a 6-second tone, so it passes the default 5-30 second window. They are made the way real
# devices make them, because the container, not the file name, decides what PHP's file-type detection reports:
#
#   chrome-recording.webm   Chrome / Edge / Android recording        detected as video/webm
#   firefox-recording.ogg   Firefox recording                        audio/ogg
#   whatsapp-voice.opus     WhatsApp voice note                      audio/ogg
#   voice-memo.m4a          phone voice memo                         audio/x-m4a
#   safari-recording.m4a    Safari / iPhone recording (MP4)          video/mp4
#   recording.mp3 / .wav / .aac
#
# The "bad" ones: a 40-second and a 2-second file (outside the window), a real video with a sound track, a text
# file, random bytes and a truncated MP3.
set -euo pipefail

cd "$(dirname "$0")/../tests/_support/Audio"

TONE_6="-f lavfi -i sine=frequency=440:duration=6 -ar 48000 -ac 1 -loglevel error -y"

ffmpeg $TONE_6 -c:a libopus -b:a 24k chrome-recording.webm
ffmpeg $TONE_6 -c:a libopus -b:a 24k firefox-recording.ogg
ffmpeg $TONE_6 -c:a libopus -b:a 24k whatsapp-voice.opus
ffmpeg $TONE_6 -c:a aac -b:a 48k voice-memo.m4a
ffmpeg $TONE_6 -c:a aac -b:a 48k -f mp4 safari-recording.m4a
ffmpeg $TONE_6 -c:a libmp3lame -b:a 48k recording.mp3
ffmpeg -f lavfi -i sine=frequency=440:duration=6 -ar 8000 -ac 1 -loglevel error -y -c:a pcm_u8 recording.wav
ffmpeg $TONE_6 -c:a aac -b:a 48k -f adts recording.aac

ffmpeg -f lavfi -i sine=frequency=440:duration=40 -ar 16000 -ac 1 -loglevel error -y -c:a libopus -b:a 12k too-long-40s.webm
ffmpeg -f lavfi -i sine=frequency=440:duration=2  -ar 48000 -ac 1 -loglevel error -y -c:a libopus -b:a 24k too-short-2s.webm

# A real video with a sound track, in an MP4 container (-f mp4: a ".m4a" name alone picks an audio-only muxer).
ffmpeg -f lavfi -i testsrc=duration=6:size=64x64:rate=5 -f lavfi -i sine=duration=6 -loglevel error -y \
       -c:v mpeg4 -b:v 20k -c:a aac -b:a 32k -f mp4 video-with-sound.m4a

printf 'This is not audio at all.\n' > not-audio.txt
head -c 3000 /dev/urandom > random-bytes.bin
head -c 300 recording.mp3 > truncated.mp3

ls -l
