<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extract 11-character YouTube Video ID from any YouTube URL or ID string.
 */
function toolnest_extract_youtube_id($input)
{
    $input = trim((string) $input);
    if ('' === $input) {
        return '';
    }

    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
        return $input;
    }

    if (preg_match('/(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $input, $matches)) {
        return $matches[1];
    }

    return '';
}

/**
 * Format bytes into human-readable MB/KB/GB string.
 */
function toolnest_format_stream_size($bytes)
{
    $bytes = (float) $bytes;
    if ($bytes <= 0) {
        return 'Auto';
    }
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    return number_format($bytes / 1024, 0) . ' KB';
}

/**
 * Locate bundled yt-dlp binary in theme inc/bin/ or system PATH.
 */
function toolnest_get_ytdlp_binary()
{
    $win_bin = get_theme_file_path('/inc/bin/yt-dlp.exe');
    if (file_exists($win_bin)) {
        return $win_bin;
    }

    $unix_bin = get_theme_file_path('/inc/bin/yt-dlp');
    if (file_exists($unix_bin)) {
        return $unix_bin;
    }

    return 'yt-dlp';
}

/**
 * Locate Node.js runtime for yt-dlp signature cipher solving.
 */
function toolnest_get_node_runtime_path()
{
    $candidates = [
        'C:/Program Files/nodejs/node.exe',
        'C:/Program Files (x86)/nodejs/node.exe',
        '/usr/local/bin/node',
        '/usr/bin/node',
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            return str_replace('\\', '/', $candidate);
        }
    }

    return '';
}

/**
 * Return temporary directory for active YouTube conversion jobs and clean up files older than 1 hour.
 */
function toolnest_get_yt_temp_dir()
{
    $upload_dir = wp_upload_dir();
    $base_dir = !empty($upload_dir['basedir']) ? $upload_dir['basedir'] : sys_get_temp_dir();
    $temp_dir = trailingslashit($base_dir) . 'toolnest-yt-temp';

    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }

    // Periodic cleanup of files and job folders older than 1 hour
    $files = @glob($temp_dir . '/*');
    if (is_array($files)) {
        $cutoff = time() - 3600;
        foreach ($files as $file) {
            if (@filemtime($file) < $cutoff) {
                if (is_file($file)) {
                    @unlink($file);
                } elseif (is_dir($file)) {
                    $subfiles = @glob($file . '/*');
                    if (is_array($subfiles)) {
                        foreach ($subfiles as $sf) {
                            if (is_file($sf)) {
                                @unlink($sf);
                            }
                        }
                    }
                    @rmdir($file);
                }
            }
        }
    }

    return $temp_dir;
}

/**
 * Fetch YouTube video metadata and available Video & Audio formats in multiple qualities.
 */
function toolnest_ajax_yt_info()
{
    $raw_url = isset($_REQUEST['url']) ? sanitize_text_field(wp_unslash($_REQUEST['url'])) : '';
    $video_id = toolnest_extract_youtube_id($raw_url);

    if ('' === $video_id) {
        wp_send_json_error([
            'message' => __('Please enter a valid YouTube video or Shorts URL.', 'toolnest'),
        ], 400);
    }

    $canonical_url = 'https://www.youtube.com/watch?v=' . $video_id;
    $title = 'YouTube Video (' . $video_id . ')';
    $author = 'YouTube Channel';
    $duration_seconds = 210;
    $duration_formatted = '';
    $view_count = 0;

    // 1. Fetch oEmbed for fast, reliable title & channel name
    $oembed_url = 'https://www.youtube.com/oembed?url=' . rawurlencode($canonical_url) . '&format=json';
    $oembed_res = wp_remote_get($oembed_url, [
        'timeout' => 8,
        'sslverify' => false,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ]);

    if (!is_wp_error($oembed_res) && 200 === wp_remote_retrieve_response_code($oembed_res)) {
        $oembed_data = json_decode(wp_remote_retrieve_body($oembed_res), true);
        if (is_array($oembed_data)) {
            if (!empty($oembed_data['title'])) {
                $title = $oembed_data['title'];
            }
            if (!empty($oembed_data['author_name'])) {
                $author = $oembed_data['author_name'];
            }
        }
    }

    // 2. Fetch watch page HTML for exact lengthSeconds and viewCount in < 1s
    $watch_res = wp_remote_get($canonical_url . '&hl=en', [
        'timeout' => 8,
        'sslverify' => false,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ]);

    $has_exact_duration = false;
    if (!is_wp_error($watch_res) && 200 === wp_remote_retrieve_response_code($watch_res)) {
        $html = wp_remote_retrieve_body($watch_res);
        if (preg_match('/"lengthSeconds"\s*:\s*"(\d+)"/', $html, $m)) {
            $duration_seconds = max(1, (int) $m[1]);
            $has_exact_duration = true;
        }
        if (preg_match('/"viewCount"\s*:\s*"(\d+)"/', $html, $vm)) {
            $view_count = (int) $vm[1];
        }
    }

    if ($has_exact_duration) {
        $hrs = floor($duration_seconds / 3600);
        $mins = floor(($duration_seconds % 3600) / 60);
        $secs = $duration_seconds % 60;
        $duration_formatted = $hrs > 0
            ? sprintf('%d:%02d:%02d', $hrs, $mins, $secs)
            : sprintf('%02d:%02d', $mins, $secs);
    }

    // 3. Pre-warm yt-dlp .info.json in background so clicking Download starts immediately
    toolnest_prewarm_yt_info_json($video_id);

    // Build Multi-Quality Video Formats List (MP4 & WEBM)
    $video_formats = [
        [
            'id' => 'mp4-2160',
            'format' => 'MP4',
            'ext' => 'mp4',
            'quality' => '2160p (4K Ultra HD)',
            'badge' => '4K UHD',
            'resolution' => '3840×2160',
            'api_format' => '4k',
            'size' => toolnest_format_stream_size($duration_seconds * 2200000),
            'direct_url' => '',
        ],
        [
            'id' => 'mp4-1440',
            'format' => 'MP4',
            'ext' => 'mp4',
            'quality' => '1440p (2K QHD)',
            'badge' => '2K QHD',
            'resolution' => '2560×1440',
            'api_format' => '1440',
            'size' => toolnest_format_stream_size($duration_seconds * 1100000),
            'direct_url' => '',
        ],
        [
            'id' => 'mp4-1080',
            'format' => 'MP4',
            'ext' => 'mp4',
            'quality' => '1080p (Full HD)',
            'badge' => 'Full HD',
            'resolution' => '1920×1080',
            'api_format' => '1080',
            'size' => toolnest_format_stream_size($duration_seconds * 520000),
            'direct_url' => '',
        ],
        [
            'id' => 'mp4-720',
            'format' => 'MP4',
            'ext' => 'mp4',
            'quality' => '720p (HD Ready)',
            'badge' => 'HD',
            'resolution' => '1280×720',
            'api_format' => '720',
            'size' => toolnest_format_stream_size($duration_seconds * 260000),
            'direct_url' => '',
        ],
        [
            'id' => 'mp4-480',
            'format' => 'MP4',
            'ext' => 'mp4',
            'quality' => '480p (Standard SD)',
            'badge' => 'SD',
            'resolution' => '854×480',
            'api_format' => '480',
            'size' => toolnest_format_stream_size($duration_seconds * 140000),
            'direct_url' => '',
        ],
        [
            'id' => 'mp4-360',
            'format' => 'MP4',
            'ext' => 'mp4',
            'quality' => '360p (Medium / Fast)',
            'badge' => 'Fast',
            'resolution' => '640×360',
            'api_format' => '360',
            'size' => toolnest_format_stream_size($duration_seconds * 85000),
            'direct_url' => '',
        ],
        [
            'id' => 'webm-1080',
            'format' => 'WEBM',
            'ext' => 'webm',
            'quality' => '1080p WEBM (VP9 HD)',
            'badge' => 'VP9',
            'resolution' => '1920×1080',
            'api_format' => 'webm',
            'size' => toolnest_format_stream_size($duration_seconds * 410000),
            'direct_url' => '',
        ],
    ];

    // Build Multi-Quality & Multi-File-Type Audio Formats List (MP3, M4A, WAV, FLAC, OPUS, OGG)
    $audio_formats = [
        [
            'id' => 'mp3-320',
            'format' => 'MP3',
            'ext' => 'mp3',
            'quality' => '320 kbps (Studio Master Quality)',
            'badge' => 'Best MP3',
            'bitrate' => '320 kbps',
            'api_format' => 'mp3',
            'audio_quality' => 320,
            'size' => toolnest_format_stream_size($duration_seconds * (320000 / 8)),
            'direct_url' => '',
        ],
        [
            'id' => 'mp3-256',
            'format' => 'MP3',
            'ext' => 'mp3',
            'quality' => '256 kbps (High Quality)',
            'badge' => 'HQ',
            'bitrate' => '256 kbps',
            'api_format' => 'mp3',
            'audio_quality' => 256,
            'size' => toolnest_format_stream_size($duration_seconds * (256000 / 8)),
            'direct_url' => '',
        ],
        [
            'id' => 'mp3-192',
            'format' => 'MP3',
            'ext' => 'mp3',
            'quality' => '192 kbps (Standard Quality)',
            'badge' => 'Standard',
            'bitrate' => '192 kbps',
            'api_format' => 'mp3',
            'audio_quality' => 192,
            'size' => toolnest_format_stream_size($duration_seconds * (192000 / 8)),
            'direct_url' => '',
        ],
        [
            'id' => 'mp3-128',
            'format' => 'MP3',
            'ext' => 'mp3',
            'quality' => '128 kbps (Compact Size)',
            'badge' => 'Compact',
            'bitrate' => '128 kbps',
            'api_format' => 'mp3',
            'audio_quality' => 128,
            'size' => toolnest_format_stream_size($duration_seconds * (128000 / 8)),
            'direct_url' => '',
        ],
        [
            'id' => 'm4a-256',
            'format' => 'M4A',
            'ext' => 'm4a',
            'quality' => 'M4A / AAC — 256 kbps (Apple & Mobile)',
            'badge' => 'AAC',
            'bitrate' => '256 kbps',
            'api_format' => 'm4a',
            'audio_quality' => 256,
            'size' => toolnest_format_stream_size($duration_seconds * (160000 / 8)),
            'direct_url' => '',
        ],
        [
            'id' => 'opus-160',
            'format' => 'OPUS',
            'ext' => 'opus',
            'quality' => 'OPUS / WEBM — 160 kbps (Original Stream)',
            'badge' => 'Native',
            'bitrate' => '160 kbps',
            'api_format' => 'opus',
            'audio_quality' => 160,
            'size' => toolnest_format_stream_size($duration_seconds * (160000 / 8)),
            'direct_url' => '',
        ],
        [
            'id' => 'wav-lossless',
            'format' => 'WAV',
            'ext' => 'wav',
            'quality' => 'WAV — 1411 kbps (16-Bit PCM Lossless)',
            'badge' => 'Lossless',
            'bitrate' => '1411 kbps',
            'api_format' => 'wav',
            'audio_quality' => 320,
            'size' => toolnest_format_stream_size($duration_seconds * 176400),
            'direct_url' => '',
        ],
        [
            'id' => 'flac-lossless',
            'format' => 'FLAC',
            'ext' => 'flac',
            'quality' => 'FLAC — Studio Lossless Audio',
            'badge' => 'Hi-Res',
            'bitrate' => '~900 kbps',
            'api_format' => 'flac',
            'audio_quality' => 320,
            'size' => toolnest_format_stream_size($duration_seconds * 110000),
            'direct_url' => '',
        ],
        [
            'id' => 'ogg-192',
            'format' => 'OGG',
            'ext' => 'ogg',
            'quality' => 'OGG Vorbis — 192 kbps',
            'badge' => 'Vorbis',
            'bitrate' => '192 kbps',
            'api_format' => 'ogg',
            'audio_quality' => 192,
            'size' => toolnest_format_stream_size($duration_seconds * (192000 / 8)),
            'direct_url' => '',
        ],
    ];

    wp_send_json_success([
        'videoId' => $video_id,
        'url' => $canonical_url,
        'title' => $title,
        'author' => $author,
        'duration' => $duration_formatted,
        'durationSeconds' => $duration_seconds,
        'views' => $view_count > 0 ? number_format_i18n($view_count) : '',
        'thumbnails' => [
            'maxres' => 'https://i.ytimg.com/vi/' . $video_id . '/maxresdefault.jpg',
            'hq' => 'https://i.ytimg.com/vi/' . $video_id . '/hqdefault.jpg',
            'mq' => 'https://i.ytimg.com/vi/' . $video_id . '/mqdefault.jpg',
        ],
        'audioFormats' => $audio_formats,
        'videoFormats' => $video_formats,
    ]);
}
add_action('wp_ajax_toolnest_yt_info', 'toolnest_ajax_yt_info');
add_action('wp_ajax_nopriv_toolnest_yt_info', 'toolnest_ajax_yt_info');

/**
 * Pre-warm yt-dlp .info.json in the background as soon as a user inspects a video link,
 * so clicking Download skips YouTube signature solving and starts downloading immediately.
 *
 * @param string $video_id 11-char YouTube video ID.
 */
function toolnest_prewarm_yt_info_json($video_id)
{
    $ytdlp_bin = toolnest_get_ytdlp_binary();
    if ('' === $ytdlp_bin || '' === $video_id) {
        return;
    }

    $temp_dir = toolnest_get_yt_temp_dir();
    $info_prefix = $temp_dir . DIRECTORY_SEPARATOR . 'info_' . $video_id;
    $info_json_file = $info_prefix . '.info.json';

    // Already warm and fresh (< 20 minutes old)
    if (file_exists($info_json_file) && filesize($info_json_file) > 1000 && (time() - (int) @filemtime($info_json_file)) < 1200) {
        return;
    }

    $node_path = toolnest_get_node_runtime_path();
    $canonical_url = 'https://www.youtube.com/watch?v=' . $video_id;

    $args = [
        '"' . $ytdlp_bin . '"',
        '--no-playlist',
        '--extractor-args "youtube:player_client=default,-visionos"',
        '--no-warnings',
        '--no-check-certificates',
        '--skip-download',
        '--write-info-json',
    ];
    if ('' !== $node_path) {
        $args[] = '--js-runtimes "node:' . $node_path . '"';
    }
    $args[] = '-o "' . $info_prefix . '"';
    $args[] = '"' . $canonical_url . '"';

    $cmd_line = implode(' ', $args);

    if (DIRECTORY_SEPARATOR === '\\') {
        $bat_file = $temp_dir . DIRECTORY_SEPARATOR . 'warm_' . $video_id . '.bat';
        $bat_safe_cmd = str_replace('%', '%%', $cmd_line);
        file_put_contents($bat_file, "@echo off\r\n" . $bat_safe_cmd . " >NUL 2>&1\r\n");
        @pclose(@popen('start /B "" cmd /c "' . $bat_file . '"', 'r'));
    } else {
        @exec($cmd_line . ' >/dev/null 2>&1 &');
    }
}

/**
 * Build a direct ultra-fast FFmpeg command using pre-warmed signed stream URLs from .info.json,
 * bypassing yt-dlp's Python startup overhead completely.
 */
function toolnest_build_direct_ffmpeg_cmd($info_json_file, $ffmpeg_bin, $format, $audio_quality, $is_audio_request, $target_file)
{
    if (!file_exists($info_json_file) || !file_exists($ffmpeg_bin)) {
        return '';
    }

    $data = json_decode((string) @file_get_contents($info_json_file), true);
    if (!is_array($data) || empty($data['formats']) || !is_array($data['formats'])) {
        return '';
    }

    $m4a_url = '';
    $opus_url = '';
    $best_audio_url = '';

    foreach ($data['formats'] as $f) {
        if (empty($f['url']) || ($f['protocol'] ?? '') !== 'https') {
            continue;
        }
        $fid = (string) ($f['format_id'] ?? '');
        if ('140' === $fid) {
            $m4a_url = $f['url'];
        } elseif ('251' === $fid) {
            $opus_url = $f['url'];
        }
        if (($f['vcodec'] ?? 'none') === 'none' && ($f['acodec'] ?? 'none') !== 'none') {
            $best_audio_url = $f['url'];
        }
    }

    $audio_src = $m4a_url ?: ($opus_url ?: $best_audio_url);
    if ('' === $audio_src) {
        return '';
    }

    $ff = '"' . $ffmpeg_bin . '" -y -hide_banner -loglevel warning';

    if ($is_audio_request) {
        $br = in_array($audio_quality, [128, 160, 192, 256, 320], true) ? $audio_quality : 320;
        if ('m4a' === $format && '' !== $m4a_url) {
            return $ff . ' -i "' . $m4a_url . '" -vn -c:a copy "' . $target_file . '"';
        }
        if ('opus' === $format && '' !== $opus_url) {
            return $ff . ' -i "' . $opus_url . '" -vn -c:a copy "' . $target_file . '"';
        }
        if ('mp3' === $format) {
            return $ff . ' -i "' . $audio_src . '" -vn -c:a libmp3lame -b:a ' . $br . 'k -compression_level 0 -threads 0 "' . $target_file . '"';
        }
        if ('wav' === $format) {
            return $ff . ' -i "' . $audio_src . '" -vn -c:a pcm_s16le -threads 0 "' . $target_file . '"';
        }
        if ('flac' === $format) {
            return $ff . ' -i "' . $audio_src . '" -vn -c:a flac -compression_level 1 -threads 0 "' . $target_file . '"';
        }
        if ('ogg' === $format) {
            return $ff . ' -i "' . $audio_src . '" -vn -c:a libvorbis -b:a 192k -threads 0 "' . $target_file . '"';
        }
    } else {
        $height_map = [
            '4k' => 2160,
            '2160' => 2160,
            '1440' => 1440,
            '1080' => 1080,
            'webm' => 1080,
            'webm720' => 720,
            '720' => 720,
            '480' => 480,
            '360' => 360,
        ];
        $max_h = isset($height_map[$format]) ? $height_map[$format] : 720;
        $want_webm = ('webm' === $format || 'webm720' === $format);
        $best_video_url = '';
        $best_h = 0;

        foreach ($data['formats'] as $f) {
            if (empty($f['url']) || ($f['protocol'] ?? '') !== 'https') {
                continue;
            }
            if (($f['vcodec'] ?? 'none') === 'none') {
                continue;
            }
            $fh = isset($f['height']) ? (int) $f['height'] : 0;
            if ($fh <= 0 || $fh > $max_h) {
                continue;
            }
            $fext = strtolower((string) ($f['ext'] ?? ''));
            if ($want_webm && 'webm' !== $fext) {
                continue;
            }
            if (!$want_webm && 'mp4' !== $fext && $max_h <= 1080) {
                continue;
            }
            if ($fh >= $best_h) {
                $best_h = $fh;
                $best_video_url = $f['url'];
            }
        }

        if ('' !== $best_video_url) {
            $v_audio = $want_webm ? ($opus_url ?: $audio_src) : ($m4a_url ?: $audio_src);
            $mux_flags = $want_webm ? '-c copy -threads 0' : '-c copy -movflags +faststart -threads 0';
            return $ff . ' -i "' . $best_video_url . '" -i "' . $v_audio . '" -map 0:v:0 -map 1:a:0 ' . $mux_flags . ' "' . $target_file . '"';
        }
    }

    return '';
}

/**
 * Start a background yt-dlp + ffmpeg download/conversion job for the selected Video or Audio format & quality.
 */
function toolnest_ajax_yt_convert()
{
    $raw_url = isset($_REQUEST['url']) ? sanitize_text_field(wp_unslash($_REQUEST['url'])) : '';
    $format = isset($_REQUEST['format']) ? sanitize_text_field(wp_unslash($_REQUEST['format'])) : 'mp3';
    $audio_quality = isset($_REQUEST['audio_quality']) ? absint($_REQUEST['audio_quality']) : 320;
    $title = isset($_REQUEST['title']) ? sanitize_text_field(wp_unslash($_REQUEST['title'])) : 'youtube-media';
    $ext = isset($_REQUEST['ext']) ? sanitize_key(wp_unslash($_REQUEST['ext'])) : 'mp3';

    $video_id = toolnest_extract_youtube_id($raw_url);
    if ('' === $video_id) {
        wp_send_json_error(['message' => __('Invalid YouTube URL.', 'toolnest')], 400);
    }

    $canonical_url = 'https://www.youtube.com/watch?v=' . $video_id;
    $ytdlp_bin = toolnest_get_ytdlp_binary();
    $bin_dir = get_theme_file_path('/inc/bin');
    $ffmpeg_bin = $bin_dir . DIRECTORY_SEPARATOR . (DIRECTORY_SEPARATOR === '\\' ? 'ffmpeg.exe' : 'ffmpeg');
    $has_ffmpeg = file_exists($ffmpeg_bin);
    $node_path = toolnest_get_node_runtime_path();

    $temp_dir = toolnest_get_yt_temp_dir();
    $job_id = 'yt_' . $video_id . '_' . sanitize_key($format) . '_' . $audio_quality . '_' . wp_generate_password(6, false, false);
    $log_file = $temp_dir . DIRECTORY_SEPARATOR . $job_id . '.log';
    $output_template = $temp_dir . DIRECTORY_SEPARATOR . $job_id . '.%(ext)s';

    $info_json_file = $temp_dir . DIRECTORY_SEPARATOR . 'info_' . $video_id . '.info.json';
    $warm_bat_file = $temp_dir . DIRECTORY_SEPARATOR . 'warm_' . $video_id . '.bat';

    // If background pre-warm was started in the last 10s, wait up to 2.5s for it to finish
    if (!file_exists($info_json_file) && file_exists($warm_bat_file) && (time() - (int) @filemtime($warm_bat_file)) < 10) {
        for ($w = 0; $w < 10; $w++) {
            usleep(250000);
            clearstatcache(true, $info_json_file);
            if (file_exists($info_json_file) && filesize($info_json_file) > 1000) {
                break;
            }
        }
    }

    $use_cached_info = (
        file_exists($info_json_file)
        && filesize($info_json_file) > 1000
        && (time() - (int) @filemtime($info_json_file)) < 1200
    );

    $audio_formats_list = ['mp3', 'm4a', 'wav', 'flac', 'opus', 'ogg', 'aac'];
    $is_audio_request = in_array($format, $audio_formats_list, true);
    $direct_target_file = $temp_dir . DIRECTORY_SEPARATOR . $job_id . '.' . $ext;
    $direct_ffmpeg_cmd = $use_cached_info
        ? toolnest_build_direct_ffmpeg_cmd($info_json_file, $ffmpeg_bin, $format, $audio_quality, $is_audio_request, $direct_target_file)
        : '';

    $args = [
        '"' . $ytdlp_bin . '"',
        '--no-playlist',
        '--concurrent-fragments 16',
        '--http-chunk-size 10M',
        '--buffer-size 64K',
        '--fixup never',
        '--newline',
        '--no-warnings',
        '--no-check-certificates',
    ];

    if (!$use_cached_info) {
        $args[] = '--extractor-args "youtube:player_client=default,-visionos"';
        if ('' !== $node_path) {
            $args[] = '--js-runtimes "node:' . $node_path . '"';
        }
    }

    if ($has_ffmpeg) {
        $args[] = '--ffmpeg-location "' . $bin_dir . '"';
    }

    if ($is_audio_request) {
        if ('m4a' === $format) {
            // Zero-transcode fast path: YouTube stream 140 is already native M4A (AAC)
            $args[] = '-f "bestaudio[ext=m4a]/bestaudio/best"';
        } elseif ($has_ffmpeg) {
            $target_audio_fmt = ('ogg' === $format) ? 'vorbis' : $format;
            $quality_arg = in_array($audio_quality, [128, 160, 192, 256, 320], true) ? ($audio_quality . 'K') : '0';
            $args[] = '-f "bestaudio[ext=m4a]/bestaudio/best"';
            $args[] = '-x';
            $args[] = '--audio-format ' . escapeshellarg($target_audio_fmt);
            $args[] = '--audio-quality ' . escapeshellarg($quality_arg);
            $args[] = '--postprocessor-args "ExtractAudio:-threads 0 -compression_level 0"';
        } else {
            if ('opus' === $format) {
                $args[] = '-f "bestaudio[ext=webm]/bestaudio/best"';
                $ext = 'webm';
            } else {
                $args[] = '-f "bestaudio[ext=m4a]/bestaudio/best"';
                $ext = 'm4a';
            }
        }
    } else {
        $height_map = [
            '4k' => 2160,
            '2160' => 2160,
            '1440' => 1440,
            '1080' => 1080,
            'webm' => 1080,
            'webm720' => 720,
            '720' => 720,
            '480' => 480,
            '360' => 360,
        ];
        $max_h = isset($height_map[$format]) ? $height_map[$format] : 720;

        if ('webm' === $format || 'webm720' === $format) {
            if ($has_ffmpeg) {
                $args[] = '-f "bestvideo[height<=' . $max_h . '][ext=webm]+bestaudio[ext=webm]/best[height<=' . $max_h . '][ext=webm]/bestvideo[height<=' . $max_h . ']+bestaudio/best"';
                $args[] = '--merge-output-format webm';
                $args[] = '--postprocessor-args "Merger:-threads 0 -c copy"';
            } else {
                $args[] = '-f "best[height<=' . $max_h . ']/18/best"';
            }
        } else {
            if ($has_ffmpeg) {
                $args[] = '-f "bestvideo[height<=' . $max_h . '][ext=mp4]+bestaudio[ext=m4a]/best[height<=' . $max_h . '][ext=mp4]/bestvideo[height<=' . $max_h . ']+bestaudio/best[height<=' . $max_h . ']/best"';
                $args[] = '--merge-output-format mp4';
                $args[] = '--postprocessor-args "Merger:-threads 0 -c copy -movflags +faststart"';
            } else {
                $args[] = '-f "best[height<=' . $max_h . '][ext=mp4]/18/best[ext=mp4]/best"';
            }
        }
    }

    $args[] = '-o "' . $output_template . '"';
    if ($use_cached_info) {
        $args[] = '--load-info-json "' . $info_json_file . '"';
    } else {
        $args[] = '"' . $canonical_url . '"';
    }

    $cmd_line = implode(' ', $args);

    // Save job metadata in transient for 30 minutes
    set_transient('tn_yt_job_' . $job_id, [
        'video_id' => $video_id,
        'title' => $title,
        'requested_ext' => $ext,
        'format_label' => strtoupper($ext) . ($is_audio_request ? (' ' . $audio_quality . 'kbps') : (' ' . $format)),
        'temp_dir' => $temp_dir,
        'log_file' => $log_file,
    ], 30 * MINUTE_IN_SECONDS);

    // Launch background process so the HTTP request returns immediately
    if (DIRECTORY_SEPARATOR === '\\') {
        $bat_file = $temp_dir . DIRECTORY_SEPARATOR . $job_id . '.bat';
        $bat_safe_cmd = str_replace('%', '%%', $cmd_line);
        if ('' !== $direct_ffmpeg_cmd) {
            $bat_direct_safe = str_replace('%', '%%', $direct_ffmpeg_cmd);
            $bat_content = "@echo off\r\n"
                . "echo [download] 65.0%% > \"" . $log_file . "\"\r\n"
                . $bat_direct_safe . " >> \"" . $log_file . "\" 2>&1\r\n"
                . "if not exist \"" . $direct_target_file . "\" (\r\n"
                . "    " . $bat_safe_cmd . " >> \"" . $log_file . "\" 2>&1\r\n"
                . ")\r\n"
                . "echo __TN_JOB_FINISHED__ >> \"" . $log_file . "\"\r\n";
        } else {
            $bat_content = "@echo off\r\n"
                . $bat_safe_cmd . " > \"" . $log_file . "\" 2>&1\r\n"
                . "echo __TN_JOB_FINISHED__ >> \"" . $log_file . "\"\r\n";
        }
        file_put_contents($bat_file, $bat_content);
        $bg_cmd = 'start /B "" cmd /c "' . $bat_file . '"';
        @pclose(@popen($bg_cmd, 'r'));
    } else {
        $sh_cmd = '(' . $cmd_line . ' > ' . escapeshellarg($log_file) . ' 2>&1; echo "__TN_JOB_FINISHED__" >> ' . escapeshellarg($log_file) . ') >/dev/null 2>&1 &';
        @exec($sh_cmd);
    }

    wp_send_json_success([
        'mode' => 'job',
        'jobId' => $job_id,
    ]);
}
add_action('wp_ajax_toolnest_yt_convert', 'toolnest_ajax_yt_convert');
add_action('wp_ajax_nopriv_toolnest_yt_convert', 'toolnest_ajax_yt_convert');

/**
 * Find the output media file produced for a given job ID.
 */
function toolnest_find_yt_job_output_file($temp_dir, $job_id)
{
    $matches = @glob($temp_dir . DIRECTORY_SEPARATOR . $job_id . '.*');
    if (!is_array($matches)) {
        return '';
    }

    foreach ($matches as $file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (in_array($ext, ['log', 'bat', 'part', 'ytdl', 'temp'], true)) {
            continue;
        }
        if (is_file($file_path) && filesize($file_path) > 0) {
            return $file_path;
        }
    }

    return '';
}

/**
 * Poll conversion progress for an active job ID.
 */
function toolnest_ajax_yt_progress()
{
    $job_id = isset($_REQUEST['id']) ? sanitize_text_field(wp_unslash($_REQUEST['id'])) : '';
    if ('' === $job_id || 0 !== strpos($job_id, 'yt_')) {
        wp_send_json_error(['message' => __('Invalid job ID.', 'toolnest')], 400);
    }

    $job_meta = get_transient('tn_yt_job_' . $job_id);
    if (empty($job_meta) || !is_array($job_meta)) {
        wp_send_json_error(['message' => __('Job expired or not found.', 'toolnest')], 404);
    }

    $log_file = $job_meta['log_file'];
    $temp_dir = $job_meta['temp_dir'];
    $log_content = file_exists($log_file) ? (string) @file_get_contents($log_file) : '';

    $percent = 12;
    $status_text = __('Fetching stream...', 'toolnest');

    if (preg_match_all('/\[download\]\s+(\d+(?:\.\d+)?)%/', $log_content, $pct_matches) && !empty($pct_matches[1])) {
        $last_pct = (float) end($pct_matches[1]);
        $percent = max(15, min(92, (int) round($last_pct * 0.85)));
        $status_text = __('Downloading stream...', 'toolnest');
    }

    if (false !== strpos($log_content, '[ExtractAudio]') || false !== strpos($log_content, '[Merger]')) {
        $percent = 95;
        $status_text = __('Converting & finalizing file...', 'toolnest');
    }

    $is_done = (false !== strpos($log_content, '__TN_JOB_FINISHED__'));
    $output_file = $is_done ? toolnest_find_yt_job_output_file($temp_dir, $job_id) : '';

    if ($is_done && '' !== $output_file) {
        $stream_token = wp_create_nonce('toolnest_yt_stream_' . $job_id);
        $download_url = add_query_arg([
            'action' => 'toolnest_yt_stream',
            'job' => $job_id,
            'token' => $stream_token,
        ], admin_url('admin-ajax.php'));

        wp_send_json_success([
            'percent' => 100,
            'statusText' => __('Ready!', 'toolnest'),
            'finished' => true,
            'downloadUrl' => $download_url,
        ]);
    }

    if ($is_done && '' === $output_file) {
        wp_send_json_error([
            'message' => __('Could not convert this format. Please try another quality option.', 'toolnest'),
        ], 500);
    }

    wp_send_json_success([
        'percent' => $percent,
        'statusText' => $status_text,
        'finished' => false,
        'downloadUrl' => '',
    ]);
}
add_action('wp_ajax_toolnest_yt_progress', 'toolnest_ajax_yt_progress');
add_action('wp_ajax_nopriv_toolnest_yt_progress', 'toolnest_ajax_yt_progress');

/**
 * Stream the finished local media file to the user's browser with Content-Disposition: attachment.
 */
function toolnest_ajax_yt_stream()
{
    $job_id = isset($_GET['job']) ? sanitize_text_field(wp_unslash($_GET['job'])) : '';
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

    if ('' === $job_id || !wp_verify_nonce($token, 'toolnest_yt_stream_' . $job_id)) {
        wp_die(esc_html__('Invalid or expired download link.', 'toolnest'), 403);
    }

    $job_meta = get_transient('tn_yt_job_' . $job_id);
    if (empty($job_meta) || !is_array($job_meta)) {
        wp_die(esc_html__('Download session expired. Please fetch the video again.', 'toolnest'), 410);
    }

    $output_file = toolnest_find_yt_job_output_file($job_meta['temp_dir'], $job_id);
    if ('' === $output_file || !is_file($output_file)) {
        wp_die(esc_html__('File not found on server.', 'toolnest'), 404);
    }

    $actual_ext = strtolower(pathinfo($output_file, PATHINFO_EXTENSION)) ?: $job_meta['requested_ext'];
    $clean_title = preg_replace('/[^\p{L}\p{N}\s\-_()]/u', '', (string) $job_meta['title']);
    $clean_title = trim(preg_replace('/\s+/', ' ', $clean_title)) ?: ('youtube-' . $job_meta['video_id']);
    $filename = $clean_title . ' (' . $job_meta['format_label'] . ').' . $actual_ext;

    $mime_map = [
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'wav' => 'audio/wav',
        'flac' => 'audio/flac',
        'opus' => 'audio/opus',
        'ogg' => 'audio/ogg',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',
    ];
    $content_type = isset($mime_map[$actual_ext]) ? $mime_map[$actual_ext] : 'application/octet-stream';
    $filesize = filesize($output_file);

    if (ob_get_level() > 0) {
        @ob_end_clean();
    }

    nocache_headers();
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    if ($filesize > 0) {
        header('Content-Length: ' . $filesize);
    }

    $fp = fopen($output_file, 'rb');
    if ($fp) {
        while (!feof($fp)) {
            echo fread($fp, 65536); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            flush();
        }
        fclose($fp);
    }
    exit;
}
add_action('wp_ajax_toolnest_yt_stream', 'toolnest_ajax_yt_stream');
add_action('wp_ajax_nopriv_toolnest_yt_stream', 'toolnest_ajax_yt_stream');

/**
 * Extract a YouTube Playlist ID from any YouTube playlist or watch+list URL.
 *
 * @param string $url Raw URL or playlist ID.
 * @return string Clean playlist ID or empty string.
 */
function toolnest_extract_youtube_playlist_id($url)
{
    $url = trim((string) $url);
    if ('' === $url) {
        return '';
    }

    if (preg_match('/[?&]list=([a-zA-Z0-9_-]{10,64})/', $url, $matches)) {
        return $matches[1];
    }

    if (preg_match('/^[a-zA-Z0-9_-]{13,64}$/', $url)) {
        return $url;
    }

    return '';
}

/**
 * Fetch YouTube Playlist metadata, track list, and multi-format Audio/Video ZIP options.
 */
function toolnest_ajax_yt_playlist_info()
{
    $raw_url = isset($_REQUEST['url']) ? sanitize_text_field(wp_unslash($_REQUEST['url'])) : '';
    $playlist_id = toolnest_extract_youtube_playlist_id($raw_url);

    if ('' === $playlist_id) {
        wp_send_json_error([
            'message' => __('Please enter a valid YouTube Playlist URL (containing ?list=... or &list=...).', 'toolnest'),
        ], 400);
    }

    $canonical_playlist_url = 'https://www.youtube.com/playlist?list=' . rawurlencode($playlist_id);
    $ytdlp_bin = toolnest_get_ytdlp_binary();
    $node_path = toolnest_get_node_runtime_path();

    if ('' === $ytdlp_bin) {
        wp_send_json_error([
            'message' => __('Playlist engine binary is missing.', 'toolnest'),
        ], 500);
    }

    $js_arg = ('' !== $node_path) ? (' --js-runtimes "node:' . $node_path . '"') : '';
    $cmd = '"' . $ytdlp_bin . '" --flat-playlist --playlist-end 100 --dump-single-json --no-warnings --no-check-certificates'
        . $js_arg . ' "' . $canonical_playlist_url . '" 2>NUL';

    $raw_json = @shell_exec($cmd);
    $info = $raw_json ? json_decode($raw_json, true) : null;

    if (!is_array($info) || empty($info['entries']) || !is_array($info['entries'])) {
        wp_send_json_error([
            'message' => __('Could not load playlist songs. Please verify the playlist is public or unlisted.', 'toolnest'),
        ], 404);
    }

    $playlist_title = !empty($info['title']) ? sanitize_text_field($info['title']) : ('YouTube Playlist (' . $playlist_id . ')');
    $playlist_channel = !empty($info['channel'])
        ? sanitize_text_field($info['channel'])
        : (!empty($info['uploader']) ? sanitize_text_field($info['uploader']) : 'YouTube Playlist');

    $items = [];
    $total_duration = 0;
    $idx = 1;

    foreach ($info['entries'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $vid = !empty($entry['id']) ? sanitize_text_field($entry['id']) : '';
        if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $vid)) {
            continue;
        }
        $item_title = !empty($entry['title']) ? sanitize_text_field($entry['title']) : ('Track ' . $idx);
        if (in_array(strtolower($item_title), ['[private video]', '[deleted video]'], true)) {
            continue;
        }
        $item_dur = !empty($entry['duration']) ? max(1, (int) $entry['duration']) : 210;
        $item_channel = !empty($entry['channel'])
            ? sanitize_text_field($entry['channel'])
            : (!empty($entry['uploader']) ? sanitize_text_field($entry['uploader']) : $playlist_channel);

        $total_duration += $item_dur;
        $items[] = [
            'index' => $idx++,
            'videoId' => $vid,
            'title' => $item_title,
            'channel' => $item_channel,
            'duration' => $item_dur,
            'durationFormatted' => gmdate($item_dur >= 3600 ? 'H:i:s' : 'i:s', $item_dur),
            'thumbnail' => 'https://i.ytimg.com/vi/' . $vid . '/mqdefault.jpg',
        ];
    }

    if (empty($items)) {
        wp_send_json_error([
            'message' => __('No playable public videos were found in this playlist.', 'toolnest'),
        ], 404);
    }

    $cover_thumb = $items[0]['thumbnail'];
    if (!empty($info['thumbnails']) && is_array($info['thumbnails'])) {
        $last_t = end($info['thumbnails']);
        if (!empty($last_t['url'])) {
            $cover_thumb = esc_url_raw($last_t['url']);
        }
    }

    $audio_formats = [
        [
            'id' => 'zip-mp3-320',
            'fileType' => 'MP3 ZIP',
            'ext' => 'mp3',
            'quality' => 'MP3 — 320 kbps (Studio Master Quality)',
            'badge' => 'Best Audio',
            'format' => 'mp3',
            'audioQuality' => 320,
            'kbps' => 320,
        ],
        [
            'id' => 'zip-mp3-256',
            'fileType' => 'MP3 ZIP',
            'ext' => 'mp3',
            'quality' => 'MP3 — 256 kbps (High Quality)',
            'badge' => 'HQ',
            'format' => 'mp3',
            'audioQuality' => 256,
            'kbps' => 256,
        ],
        [
            'id' => 'zip-mp3-192',
            'fileType' => 'MP3 ZIP',
            'ext' => 'mp3',
            'quality' => 'MP3 — 192 kbps (Standard Stereo)',
            'badge' => 'Standard',
            'format' => 'mp3',
            'audioQuality' => 192,
            'kbps' => 192,
        ],
        [
            'id' => 'zip-mp3-128',
            'fileType' => 'MP3 ZIP',
            'ext' => 'mp3',
            'quality' => 'MP3 — 128 kbps (Compact Fast Size)',
            'badge' => 'Fast',
            'format' => 'mp3',
            'audioQuality' => 128,
            'kbps' => 128,
        ],
        [
            'id' => 'zip-m4a-192',
            'fileType' => 'M4A ZIP',
            'ext' => 'm4a',
            'quality' => 'M4A (AAC) — ~192 kbps (Apple / Original Stream)',
            'badge' => 'AAC',
            'format' => 'm4a',
            'audioQuality' => 192,
            'kbps' => 192,
        ],
        [
            'id' => 'zip-opus-160',
            'fileType' => 'OPUS ZIP',
            'ext' => 'opus',
            'quality' => 'OPUS — ~160 kbps (YouTube High-Efficiency Master)',
            'badge' => 'Master',
            'format' => 'opus',
            'audioQuality' => 160,
            'kbps' => 160,
        ],
        [
            'id' => 'zip-wav-lossless',
            'fileType' => 'WAV ZIP',
            'ext' => 'wav',
            'quality' => 'WAV — 1411 kbps (Lossless Uncompressed PCM)',
            'badge' => 'Lossless',
            'format' => 'wav',
            'audioQuality' => 1411,
            'kbps' => 1411,
        ],
        [
            'id' => 'zip-flac-lossless',
            'fileType' => 'FLAC ZIP',
            'ext' => 'flac',
            'quality' => 'FLAC — ~900 kbps (Lossless Compressed)',
            'badge' => 'Lossless',
            'format' => 'flac',
            'audioQuality' => 900,
            'kbps' => 900,
        ],
        [
            'id' => 'zip-ogg-192',
            'fileType' => 'OGG ZIP',
            'ext' => 'ogg',
            'quality' => 'OGG Vorbis — 192 kbps (Open Audio Format)',
            'badge' => 'OGG',
            'format' => 'ogg',
            'audioQuality' => 192,
            'kbps' => 192,
        ],
    ];

    $video_formats = [
        [
            'id' => 'zip-mp4-2160',
            'fileType' => 'MP4 ZIP',
            'ext' => 'mp4',
            'quality' => '2160p (4K Ultra HD)',
            'badge' => '4K UHD',
            'format' => '4k',
            'audioQuality' => 0,
            'kbps' => 14500,
        ],
        [
            'id' => 'zip-mp4-1440',
            'fileType' => 'MP4 ZIP',
            'ext' => 'mp4',
            'quality' => '1440p (2K QHD)',
            'badge' => '2K QHD',
            'format' => '1440',
            'audioQuality' => 0,
            'kbps' => 7500,
        ],
        [
            'id' => 'zip-mp4-1080',
            'fileType' => 'MP4 ZIP',
            'ext' => 'mp4',
            'quality' => '1080p (Full HD)',
            'badge' => 'Full HD',
            'format' => '1080',
            'audioQuality' => 0,
            'kbps' => 3600,
        ],
        [
            'id' => 'zip-mp4-720',
            'fileType' => 'MP4 ZIP',
            'ext' => 'mp4',
            'quality' => '720p (HD Ready)',
            'badge' => 'HD',
            'format' => '720',
            'audioQuality' => 0,
            'kbps' => 1850,
        ],
        [
            'id' => 'zip-mp4-480',
            'fileType' => 'MP4 ZIP',
            'ext' => 'mp4',
            'quality' => '480p (Standard SD)',
            'badge' => 'SD',
            'format' => '480',
            'audioQuality' => 0,
            'kbps' => 1000,
        ],
        [
            'id' => 'zip-mp4-360',
            'fileType' => 'MP4 ZIP',
            'ext' => 'mp4',
            'quality' => '360p (Medium / Fast)',
            'badge' => 'Fast',
            'format' => '360',
            'audioQuality' => 0,
            'kbps' => 620,
        ],
        [
            'id' => 'zip-webm-1080',
            'fileType' => 'WEBM ZIP',
            'ext' => 'webm',
            'quality' => '1080p WEBM (VP9 HD)',
            'badge' => 'VP9',
            'format' => 'webm',
            'audioQuality' => 0,
            'kbps' => 2800,
        ],
        [
            'id' => 'zip-webm-720',
            'fileType' => 'WEBM ZIP',
            'ext' => 'webm',
            'quality' => '720p WEBM (VP9 HD)',
            'badge' => 'VP9',
            'format' => 'webm720',
            'audioQuality' => 0,
            'kbps' => 1500,
        ],
    ];

    wp_send_json_success([
        'playlistId' => $playlist_id,
        'title' => $playlist_title,
        'channel' => $playlist_channel,
        'thumbnail' => $cover_thumb,
        'totalCount' => count($items),
        'totalDurationSeconds' => $total_duration,
        'totalDurationFormatted' => gmdate($total_duration >= 3600 ? 'H:i:s' : 'i:s', $total_duration),
        'items' => $items,
        'audioFormats' => $audio_formats,
        'videoFormats' => $video_formats,
    ]);
}
add_action('wp_ajax_toolnest_yt_playlist_info', 'toolnest_ajax_yt_playlist_info');
add_action('wp_ajax_nopriv_toolnest_yt_playlist_info', 'toolnest_ajax_yt_playlist_info');

/**
 * Start a background YouTube Playlist batch download job that will be packaged into a single .ZIP file.
 */
function toolnest_ajax_yt_playlist_zip_start()
{
    $raw_ids = isset($_REQUEST['video_ids']) ? sanitize_text_field(wp_unslash($_REQUEST['video_ids'])) : '';
    $format = isset($_REQUEST['format']) ? sanitize_text_field(wp_unslash($_REQUEST['format'])) : 'mp3';
    $audio_quality = isset($_REQUEST['audio_quality']) ? absint($_REQUEST['audio_quality']) : 320;
    $playlist_title = isset($_REQUEST['title']) ? sanitize_text_field(wp_unslash($_REQUEST['title'])) : 'YouTube-Playlist';
    $ext = isset($_REQUEST['ext']) ? sanitize_key(wp_unslash($_REQUEST['ext'])) : 'mp3';

    $id_parts = array_filter(array_map('trim', explode(',', $raw_ids)));
    $valid_ids = [];
    foreach ($id_parts as $vid) {
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $vid)) {
            $valid_ids[] = $vid;
        }
        if (count($valid_ids) >= 50) {
            break;
        }
    }

    if (empty($valid_ids)) {
        wp_send_json_error(['message' => __('Please select at least one song or video from the playlist.', 'toolnest')], 400);
    }

    $ytdlp_bin = toolnest_get_ytdlp_binary();
    if ('' === $ytdlp_bin) {
        wp_send_json_error(['message' => __('Playlist downloader binary is missing.', 'toolnest')], 500);
    }

    $bin_dir = get_theme_file_path('/inc/bin');
    $ffmpeg_bin = $bin_dir . DIRECTORY_SEPARATOR . (DIRECTORY_SEPARATOR === '\\' ? 'ffmpeg.exe' : 'ffmpeg');
    $has_ffmpeg = file_exists($ffmpeg_bin);
    $node_path = toolnest_get_node_runtime_path();

    $temp_dir = toolnest_get_yt_temp_dir();
    $job_id = 'ytpl_' . sanitize_key($format) . '_' . wp_generate_password(8, false, false);
    $job_dir = $temp_dir . DIRECTORY_SEPARATOR . $job_id;
    wp_mkdir_p($job_dir);

    $zip_file = $temp_dir . DIRECTORY_SEPARATOR . $job_id . '.zip';

    $audio_formats_list = ['mp3', 'm4a', 'wav', 'flac', 'opus', 'ogg', 'aac'];
    $is_audio_request = in_array($format, $audio_formats_list, true);

    $base_args = [
        '"' . $ytdlp_bin . '"',
        '--no-playlist',
        '--extractor-args "youtube:player_client=default,-visionos"',
        '--concurrent-fragments 16',
        '--http-chunk-size 10M',
        '--buffer-size 64K',
        '--fixup never',
        '--ignore-errors',
        '--newline',
        '--no-warnings',
        '--no-check-certificates',
        '--restrict-filenames',
    ];

    if ('' !== $node_path) {
        $base_args[] = '--js-runtimes "node:' . $node_path . '"';
    }

    if ($has_ffmpeg) {
        $base_args[] = '--ffmpeg-location "' . $bin_dir . '"';
    }

    if ($is_audio_request) {
        if ('m4a' === $format) {
            $base_args[] = '-f "bestaudio[ext=m4a]/bestaudio/best"';
        } elseif ($has_ffmpeg) {
            $target_audio_fmt = ('ogg' === $format) ? 'vorbis' : $format;
            $quality_arg = in_array($audio_quality, [128, 160, 192, 256, 320], true) ? ($audio_quality . 'K') : '0';
            $base_args[] = '-f "bestaudio[ext=m4a]/bestaudio/best"';
            $base_args[] = '-x';
            $base_args[] = '--audio-format ' . escapeshellarg($target_audio_fmt);
            $base_args[] = '--audio-quality ' . escapeshellarg($quality_arg);
            $base_args[] = '--postprocessor-args "ExtractAudio:-threads 0 -compression_level 0"';
        } else {
            if ('opus' === $format) {
                $base_args[] = '-f "bestaudio[ext=webm]/bestaudio/best"';
            } else {
                $base_args[] = '-f "bestaudio[ext=m4a]/bestaudio/best"';
            }
        }
    } else {
        $height_map = [
            '4k' => 2160,
            '2160' => 2160,
            '1440' => 1440,
            '1080' => 1080,
            'webm' => 1080,
            'webm720' => 720,
            '720' => 720,
            '480' => 480,
            '360' => 360,
        ];
        $max_h = isset($height_map[$format]) ? $height_map[$format] : 720;

        if ('webm' === $format || 'webm720' === $format) {
            if ($has_ffmpeg) {
                $base_args[] = '-f "bestvideo[height<=' . $max_h . '][ext=webm]+bestaudio[ext=webm]/best[height<=' . $max_h . '][ext=webm]/bestvideo[height<=' . $max_h . ']+bestaudio/best"';
                $base_args[] = '--merge-output-format webm';
                $base_args[] = '--postprocessor-args "Merger:-threads 0 -c copy"';
            } else {
                $base_args[] = '-f "best[height<=' . $max_h . ']/18/best"';
            }
        } else {
            if ($has_ffmpeg) {
                $base_args[] = '-f "bestvideo[height<=' . $max_h . '][ext=mp4]+bestaudio[ext=m4a]/best[height<=' . $max_h . '][ext=mp4]/bestvideo[height<=' . $max_h . ']+bestaudio/best[height<=' . $max_h . ']/best"';
                $base_args[] = '--merge-output-format mp4';
                $base_args[] = '--postprocessor-args "Merger:-threads 0 -c copy -movflags +faststart"';
            } else {
                $base_args[] = '-f "best[height<=' . $max_h . '][ext=mp4]/18/best[ext=mp4]/best"';
            }
        }
    }

    // Split playlist tracks across up to 3 parallel background workers for 3x faster playlist downloading
    $worker_count = min(3, count($valid_ids));
    $chunks = array_chunk($valid_ids, (int) ceil(count($valid_ids) / $worker_count));
    $log_files = [];
    $start_num = 1;

    foreach ($chunks as $w_idx => $chunk_ids) {
        $w_urls_file = $job_dir . DIRECTORY_SEPARATOR . 'urls_' . $w_idx . '.txt';
        $w_log_file = $temp_dir . DIRECTORY_SEPARATOR . $job_id . '_w' . $w_idx . '.log';
        $log_files[] = $w_log_file;

        $w_lines = [];
        foreach ($chunk_ids as $vid) {
            $w_lines[] = 'https://www.youtube.com/watch?v=' . $vid;
        }
        file_put_contents($w_urls_file, implode("\r\n", $w_lines) . "\r\n");

        $w_args = $base_args;
        $w_args[] = '--autonumber-start ' . $start_num;
        $w_args[] = '-a "' . $w_urls_file . '"';
        $w_args[] = '-o "' . $job_dir . DIRECTORY_SEPARATOR . '%(autonumber)02d - %(title).90B.%(ext)s"';
        $start_num += count($chunk_ids);

        $w_cmd_line = implode(' ', $w_args);

        if (DIRECTORY_SEPARATOR === '\\') {
            $w_bat_file = $temp_dir . DIRECTORY_SEPARATOR . $job_id . '_w' . $w_idx . '.bat';
            $w_bat_safe = str_replace('%', '%%', $w_cmd_line);
            $w_bat_content = "@echo off\r\n"
                . $w_bat_safe . " > \"" . $w_log_file . "\" 2>&1\r\n"
                . "echo __TN_PLAYLIST_DL_DONE__ >> \"" . $w_log_file . "\"\r\n";
            file_put_contents($w_bat_file, $w_bat_content);
            @pclose(@popen('start /B "" cmd /c "' . $w_bat_file . '"', 'r'));
        } else {
            $sh_cmd = '(' . $w_cmd_line . ' > ' . escapeshellarg($w_log_file) . ' 2>&1; echo "__TN_PLAYLIST_DL_DONE__" >> ' . escapeshellarg($w_log_file) . ') >/dev/null 2>&1 &';
            @exec($sh_cmd);
        }
    }

    $format_label = strtoupper($ext) . ($is_audio_request ? (' ' . $audio_quality . 'kbps') : (' ' . strtoupper($format)));

    set_transient('tn_yt_pl_job_' . $job_id, [
        'title' => $playlist_title,
        'format_label' => $format_label,
        'total_tracks' => count($valid_ids),
        'job_dir' => $job_dir,
        'log_files' => $log_files,
        'zip_file' => $zip_file,
    ], 60 * MINUTE_IN_SECONDS);

    wp_send_json_success([
        'jobId' => $job_id,
        'totalTracks' => count($valid_ids),
    ]);
}
add_action('wp_ajax_toolnest_yt_playlist_zip_start', 'toolnest_ajax_yt_playlist_zip_start');
add_action('wp_ajax_nopriv_toolnest_yt_playlist_zip_start', 'toolnest_ajax_yt_playlist_zip_start');

/**
 * Helper to list finished media files inside a playlist job directory.
 */
function toolnest_get_playlist_job_media_files($job_dir)
{
    $all = @glob($job_dir . DIRECTORY_SEPARATOR . '*');
    if (!is_array($all)) {
        return [];
    }

    $media = [];
    foreach ($all as $file_path) {
        if (!is_file($file_path)) {
            continue;
        }
        $base = basename($file_path);
        if (0 === strpos($base, 'urls')) {
            continue;
        }
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (in_array($ext, ['part', 'ytdl', 'temp', 'log', 'bat', 'txt'], true)) {
            continue;
        }
        // Skip unmerged intermediate stream files like .f137.mp4 or .f140.m4a
        if (preg_match('/\.f\d+\.[a-z0-9]+$/i', $base)) {
            continue;
        }
        if (filesize($file_path) > 0) {
            $media[] = $file_path;
        }
    }

    sort($media);
    return $media;
}

/**
 * Poll conversion progress for a YouTube Playlist ZIP job and create the .ZIP archive when complete.
 */
function toolnest_ajax_yt_playlist_zip_progress()
{
    $job_id = isset($_REQUEST['id']) ? sanitize_text_field(wp_unslash($_REQUEST['id'])) : '';
    if ('' === $job_id || 0 !== strpos($job_id, 'ytpl_')) {
        wp_send_json_error(['message' => __('Invalid playlist job ID.', 'toolnest')], 400);
    }

    $job_meta = get_transient('tn_yt_pl_job_' . $job_id);
    if (empty($job_meta) || !is_array($job_meta)) {
        wp_send_json_error(['message' => __('Playlist job expired or not found.', 'toolnest')], 404);
    }

    $total_tracks = max(1, (int) $job_meta['total_tracks']);
    $job_dir = $job_meta['job_dir'];
    $log_files = !empty($job_meta['log_files']) && is_array($job_meta['log_files'])
        ? $job_meta['log_files']
        : (!empty($job_meta['log_file']) ? [$job_meta['log_file']] : []);
    $zip_file = $job_meta['zip_file'];

    $combined_log = '';
    $done_workers = 0;
    $active_pct_sum = 0.0;
    $active_workers = 0;

    foreach ($log_files as $lf) {
        $lc = file_exists($lf) ? (string) @file_get_contents($lf) : '';
        $combined_log .= "\n" . $lc;
        if (false !== strpos($lc, '__TN_PLAYLIST_DL_DONE__')) {
            $done_workers++;
        } else {
            $active_workers++;
            if (preg_match_all('/\[download\]\s+(\d+(?:\.\d+)?)%/', $lc, $pm) && !empty($pm[1])) {
                $active_pct_sum += (float) end($pm[1]);
            } else {
                $active_pct_sum += 12.0;
            }
        }
    }

    $media_files = toolnest_get_playlist_job_media_files($job_dir);
    $completed_count = count($media_files);

    $started_count = max(1, $completed_count + $active_workers);
    if (preg_match_all('/\[youtube\] Extracting URL:/', $combined_log, $ext_matches)) {
        $started_count = max($started_count, min($total_tracks, count($ext_matches[0])));
    }

    $avg_active_pct = $active_workers > 0 ? ($active_pct_sum / $active_workers) : 100.0;
    $progress_units = $completed_count + ($active_workers * ($avg_active_pct / 100.0));
    $overall_pct = (int) round(($progress_units / $total_tracks) * 92);
    $overall_pct = max(10, min(94, $overall_pct));

    $status_text = sprintf(
        /* translators: 1: completed tracks, 2: total tracks, 3: overall percentage */
        __('Parallel downloading tracks (%1$d/%2$d completed • %3$d%%)...', 'toolnest'),
        $completed_count,
        $total_tracks,
        $overall_pct
    );

    $is_dl_done = (!empty($log_files) && $done_workers >= count($log_files));

    if ($is_dl_done) {
        $media_files = toolnest_get_playlist_job_media_files($job_dir);
        if (empty($media_files)) {
            wp_send_json_error([
                'message' => __('Could not download playlist tracks. Please check the playlist URL and try again.', 'toolnest'),
            ], 500);
        }

        // Package all downloaded tracks into a single .ZIP file using ZipArchive::CM_STORE
        if (!file_exists($zip_file) || filesize($zip_file) === 0) {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if (true === $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                    foreach ($media_files as $idx => $mf) {
                        $entry_name = basename($mf);
                        $zip->addFile($mf, $entry_name);
                        $zip->setCompressionIndex($idx, ZipArchive::CM_STORE);
                    }
                    $zip->close();
                }
            }
        }

        if (file_exists($zip_file) && filesize($zip_file) > 0) {
            $stream_token = wp_create_nonce('toolnest_yt_pl_stream_' . $job_id);
            $download_url = add_query_arg([
                'action' => 'toolnest_yt_playlist_zip_stream',
                'job' => $job_id,
                'token' => $stream_token,
            ], admin_url('admin-ajax.php'));

            wp_send_json_success([
                'percent' => 100,
                'completedTracks' => count($media_files),
                'totalTracks' => $total_tracks,
                'statusText' => sprintf(
                    /* translators: %d: number of tracks packaged into ZIP */
                    __('Ready! Packaged %d tracks into .ZIP archive.', 'toolnest'),
                    count($media_files)
                ),
                'finished' => true,
                'downloadUrl' => $download_url,
            ]);
        }

        wp_send_json_error([
            'message' => __('Failed to build .ZIP archive on server.', 'toolnest'),
        ], 500);
    }

    wp_send_json_success([
        'percent' => $overall_pct,
        'completedTracks' => $completed_count,
        'currentTrack' => $started_count,
        'totalTracks' => $total_tracks,
        'statusText' => $status_text,
        'finished' => false,
        'downloadUrl' => '',
    ]);
}
add_action('wp_ajax_toolnest_yt_playlist_zip_progress', 'toolnest_ajax_yt_playlist_zip_progress');
add_action('wp_ajax_nopriv_toolnest_yt_playlist_zip_progress', 'toolnest_ajax_yt_playlist_zip_progress');

/**
 * Stream the finished Playlist .ZIP file to the browser.
 */
function toolnest_ajax_yt_playlist_zip_stream()
{
    $job_id = isset($_GET['job']) ? sanitize_text_field(wp_unslash($_GET['job'])) : '';
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

    if ('' === $job_id || !wp_verify_nonce($token, 'toolnest_yt_pl_stream_' . $job_id)) {
        wp_die(esc_html__('Invalid or expired playlist ZIP download link.', 'toolnest'), 403);
    }

    $job_meta = get_transient('tn_yt_pl_job_' . $job_id);
    if (empty($job_meta) || !is_array($job_meta)) {
        wp_die(esc_html__('Playlist download session expired. Please fetch the playlist again.', 'toolnest'), 410);
    }

    $zip_file = $job_meta['zip_file'];
    if ('' === $zip_file || !is_file($zip_file)) {
        wp_die(esc_html__('ZIP archive not found on server.', 'toolnest'), 404);
    }

    $clean_title = preg_replace('/[^\p{L}\p{N}\s\-_()]/u', '', (string) $job_meta['title']);
    $clean_title = trim(preg_replace('/\s+/', ' ', $clean_title)) ?: 'YouTube-Playlist';
    $filename = $clean_title . ' (' . $job_meta['format_label'] . ').zip';
    $filesize = filesize($zip_file);

    if (ob_get_level() > 0) {
        @ob_end_clean();
    }

    nocache_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    if ($filesize > 0) {
        header('Content-Length: ' . $filesize);
    }

    $fp = fopen($zip_file, 'rb');
    if ($fp) {
        while (!feof($fp)) {
            echo fread($fp, 65536); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            flush();
        }
        fclose($fp);
    }
    exit;
}
add_action('wp_ajax_toolnest_yt_playlist_zip_stream', 'toolnest_ajax_yt_playlist_zip_stream');
add_action('wp_ajax_nopriv_toolnest_yt_playlist_zip_stream', 'toolnest_ajax_yt_playlist_zip_stream');
