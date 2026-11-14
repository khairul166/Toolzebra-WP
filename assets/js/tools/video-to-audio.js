/**
 * ToolZebra — YouTube Video & Audio Downloader (Multiple Qualities & File Types)
 * + Local Video to High-Quality Audio Extractor (/assets/js/tools/video-to-audio.js)
 */
(function () {
  'use strict';

  const ytUrlInput = document.getElementById('tn-v2a-yt-url');
  const ytPasteBtn = document.getElementById('tn-v2a-yt-paste-btn');
  const ytFetchBtn = document.getElementById('tn-v2a-yt-fetch-btn');
  const ytSampleBtn = document.getElementById('tn-v2a-yt-sample-btn');
  const ytLoadingEl = document.getElementById('tn-v2a-yt-loading');
  const ytErrorEl = document.getElementById('tn-v2a-yt-error');
  const ytResultWrap = document.getElementById('tn-v2a-yt-result');

  if (!ytUrlInput && !document.getElementById('tn-v2a-dropzone')) return;

  const ytThumbImg = document.getElementById('tn-v2a-yt-thumb');
  const ytDurationBadge = document.getElementById('tn-v2a-yt-duration-badge');
  const ytViewsBadge = document.getElementById('tn-v2a-yt-views-badge');
  const ytTitleEl = document.getElementById('tn-v2a-yt-title');
  const ytAuthorEl = document.getElementById('tn-v2a-yt-author');
  const ytIdEl = document.getElementById('tn-v2a-yt-id');
  const ytThumbLinks = document.getElementById('tn-v2a-yt-thumb-links');
  const audioSection = document.getElementById('tn-v2a-audio-section');
  const videoSection = document.getElementById('tn-v2a-video-section');
  const audioTbody = document.getElementById('tn-v2a-audio-tbody');
  const videoTbody = document.getElementById('tn-v2a-video-tbody');
  const filterButtons = document.querySelectorAll('[data-tn-yt-filter]');

  let currentVideoData = null;

  function getAjaxUrl() {
    return window.toolnestAjaxUrl || '/wp-admin/admin-ajax.php';
  }

  function showToast(msg, tone) {
    if (window.ToolZebra && typeof window.ToolZebra.showToast === 'function') {
      window.ToolZebra.showToast(msg, tone);
    }
  }

  function escapeHtml(str) {
    if (window.ToolZebra && typeof window.ToolZebra.escapeHtml === 'function') {
      return window.ToolZebra.escapeHtml(str);
    }
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function extractYouTubeId(url) {
    const clean = (url || '').trim();
    if (!clean) return null;
    const regExp = /(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/;
    const match = clean.match(regExp);
    if (match && match[1]) return match[1];
    if (/^[a-zA-Z0-9_-]{11}$/.test(clean)) return clean;
    return null;
  }

  function buildFormatBadge(format, isAudio) {
    const fmt = (format || '').toUpperCase();
    let colorClass = isAudio ? 'bg-primary-subtle text-primary border-primary-subtle' : 'bg-success-subtle text-success border-success-subtle';
    if (fmt === 'WAV' || fmt === 'FLAC') {
      colorClass = 'bg-warning-subtle text-dark border-warning-subtle';
    } else if (fmt === 'WEBM' || fmt === 'OPUS') {
      colorClass = 'bg-info-subtle text-dark border-info-subtle';
    }
    const icon = isAudio ? 'fa-solid fa-music' : 'fa-solid fa-video';
    return (
      '<span class="badge border fw-bold px-2 py-1 ' + colorClass + '">' +
      '<i class="' + icon + ' me-1" aria-hidden="true"></i>' +
      escapeHtml(fmt) +
      '</span>'
    );
  }

  function renderFormatRows(items, tbody, isAudio) {
    if (!tbody) return;
    tbody.innerHTML = items
      .map(function (item) {
        const subBadge = item.badge
          ? '<span class="badge bg-secondary-subtle text-secondary ms-2">' + escapeHtml(item.badge) + '</span>'
          : '';
        return (
          '<tr data-format-row="' + escapeHtml(item.id) + '">' +
          '<td>' + buildFormatBadge(item.format, isAudio) + '</td>' +
          '<td><span class="fw-semibold">' + escapeHtml(item.quality) + '</span>' + subBadge + '</td>' +
          '<td class="text-muted small fw-medium">' + escapeHtml(item.size || 'Auto') + '</td>' +
          '<td class="text-end">' +
          '<div class="tn-yt-action-cell" id="tn-yt-cell-' + escapeHtml(item.id) + '">' +
          '<button type="button" class="btn ' + (isAudio ? 'tn-btn--primary' : 'tn-btn--success') + ' btn-sm px-3 tn-yt-dl-btn" ' +
          'data-id="' + escapeHtml(item.id) + '" ' +
          'data-api-format="' + escapeHtml(item.api_format) + '" ' +
          'data-audio-quality="' + escapeHtml(item.audio_quality || 320) + '" ' +
          'data-ext="' + escapeHtml(item.ext || 'mp3') + '" ' +
          'data-label="' + escapeHtml(item.format + ' ' + (item.bitrate || item.resolution || item.quality)) + '" ' +
          'data-direct-url="' + escapeHtml(item.direct_url || '') + '">' +
          '<i class="bi bi-download me-1" aria-hidden="true"></i> Download .' + escapeHtml((item.ext || 'mp3').toUpperCase()) +
          '</button>' +
          '</div>' +
          '</td>' +
          '</tr>'
        );
      })
      .join('');

    const buttons = tbody.querySelectorAll('.tn-yt-dl-btn');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        startFormatDownload(btn);
      });
    });
  }

  function triggerBrowserDownload(url) {
    const a = document.createElement('a');
    a.href = url;
    a.setAttribute('download', '');
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  async function pollConversionJob(jobId, cellEl, label, fallbackBtnHtml) {
    let attempts = 0;
    const maxAttempts = 240;

    while (attempts < maxAttempts) {
      attempts++;
      await new Promise(function (r) { setTimeout(r, 650); });

      try {
        const params = new URLSearchParams({
          action: 'toolnest_yt_progress',
          id: jobId
        });
        const res = await fetch(getAjaxUrl() + '?' + params.toString());
        const json = await res.json();

        if (json && json.success === false) {
          const msg = (json.data && json.data.message) ? json.data.message : 'Could not convert this format.';
          showToast(msg, 'danger');
          cellEl.innerHTML = fallbackBtnHtml;
          const retryBtn = cellEl.querySelector('.tn-yt-dl-btn');
          if (retryBtn) {
            retryBtn.addEventListener('click', function () {
              startFormatDownload(retryBtn);
            });
          }
          return;
        }

        if (json && json.success && json.data) {
          const pct = Math.max(8, json.data.percent || 10);
          const statusText = json.data.statusText || 'Converting...';

          if (json.data.finished && json.data.downloadUrl) {
            const dlUrl = json.data.downloadUrl;
            cellEl.innerHTML =
              '<a href="' + escapeHtml(dlUrl) + '" class="btn tn-btn--success btn-sm px-3 fw-bold">' +
              '<i class="bi bi-check2-circle me-1"></i> Save ' + escapeHtml(label) +
              '</a>';
            showToast(label + ' is ready! Starting download...', 'success');
            triggerBrowserDownload(dlUrl);
            return;
          }

          cellEl.innerHTML =
            '<div class="text-end">' +
            '<div class="small fw-semibold text-primary mb-1">' + escapeHtml(statusText) + ' (' + pct + '%)</div>' +
            '<div class="progress" style="height: 8px;">' +
            '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: ' + pct + '%;"></div>' +
            '</div>' +
            '</div>';
        }
      } catch (e) {
        // continue polling
      }
    }

    cellEl.innerHTML = fallbackBtnHtml;
    const retryBtn = cellEl.querySelector('.tn-yt-dl-btn');
    if (retryBtn) {
      retryBtn.addEventListener('click', function () {
        startFormatDownload(retryBtn);
      });
    }
    showToast('Conversion timed out. Please click Download again.', 'warning');
  }

  async function startFormatDownload(btn) {
    if (!currentVideoData) return;

    const itemId = btn.getAttribute('data-id');
    const apiFormat = btn.getAttribute('data-api-format') || 'mp3';
    const audioQuality = btn.getAttribute('data-audio-quality') || '320';
    const ext = btn.getAttribute('data-ext') || 'mp3';
    const label = btn.getAttribute('data-label') || ext.toUpperCase();
    const directUrl = btn.getAttribute('data-direct-url') || '';
    const cellEl = document.getElementById('tn-yt-cell-' + itemId);

    if (!cellEl) return;
    const originalHtml = cellEl.innerHTML;

    cellEl.innerHTML =
      '<div class="text-end">' +
      '<div class="small fw-semibold text-primary mb-1"><span class="spinner-border spinner-border-sm me-1"></span> Preparing ' + escapeHtml(label) + '...</div>' +
      '<div class="progress" style="height: 8px;">' +
      '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 15%;"></div>' +
      '</div>' +
      '</div>';

    try {
      const formData = new URLSearchParams({
        action: 'toolnest_yt_convert',
        url: currentVideoData.url,
        format: apiFormat,
        audio_quality: audioQuality,
        ext: ext,
        title: currentVideoData.title || 'youtube-video',
        direct_url: directUrl
      });

      const res = await fetch(getAjaxUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: formData.toString()
      });
      const json = await res.json();

      if (json && json.success && json.data) {
        if (json.data.mode === 'direct' && json.data.downloadUrl) {
          cellEl.innerHTML =
            '<a href="' + escapeHtml(json.data.downloadUrl) + '" class="btn tn-btn--success btn-sm px-3 fw-bold">' +
            '<i class="bi bi-check2-circle me-1"></i> Save ' + escapeHtml(label) +
            '</a>';
          showToast('Starting direct download for ' + label + '!', 'success');
          triggerBrowserDownload(json.data.downloadUrl);
          return;
        }

        if (json.data.mode === 'job' && json.data.jobId) {
          await pollConversionJob(json.data.jobId, cellEl, label, originalHtml);
          return;
        }
      }

      const errMsg = (json && json.data && json.data.message) ? json.data.message : 'Could not prepare download.';
      showToast(errMsg, 'danger');
      cellEl.innerHTML = originalHtml;
      const retryBtn = cellEl.querySelector('.tn-yt-dl-btn');
      if (retryBtn) {
        retryBtn.addEventListener('click', function () {
          startFormatDownload(retryBtn);
        });
      }
    } catch (err) {
      showToast('Network error while starting conversion. Please try again.', 'danger');
      cellEl.innerHTML = originalHtml;
      const retryBtn = cellEl.querySelector('.tn-yt-dl-btn');
      if (retryBtn) {
        retryBtn.addEventListener('click', function () {
          startFormatDownload(retryBtn);
        });
      }
    }
  }

  async function fetchYouTubeFormats(rawUrl) {
    if (!ytErrorEl || !ytResultWrap) return;
    ytErrorEl.classList.add('d-none');

    const videoId = extractYouTubeId(rawUrl);
    if (!videoId) {
      ytResultWrap.classList.add('d-none');
      ytErrorEl.textContent = 'Please paste a valid YouTube video or Shorts URL (e.g., https://www.youtube.com/watch?v=... or https://youtu.be/...).';
      ytErrorEl.classList.remove('d-none');
      return;
    }

    if (ytLoadingEl) ytLoadingEl.classList.remove('d-none');
    ytResultWrap.classList.add('d-none');
    if (ytFetchBtn) ytFetchBtn.disabled = true;

    try {
      const params = new URLSearchParams({
        action: 'toolnest_yt_info',
        url: rawUrl.trim()
      });
      const res = await fetch(getAjaxUrl() + '?' + params.toString());
      const json = await res.json();

      if (!json || !json.success || !json.data) {
        throw new Error((json && json.data && json.data.message) || 'Could not fetch video details.');
      }

      const data = json.data;
      currentVideoData = data;

      if (ytThumbImg) {
        ytThumbImg.src = data.thumbnails.maxres;
        ytThumbImg.onerror = function () {
          ytThumbImg.src = data.thumbnails.hq;
        };
      }

      if (ytIdEl) ytIdEl.textContent = data.videoId;
      if (ytTitleEl) ytTitleEl.textContent = data.title || ('YouTube Video (' + data.videoId + ')');
      if (ytAuthorEl) ytAuthorEl.textContent = data.author || 'YouTube Channel';

      if (ytDurationBadge) {
        if (data.duration) {
          ytDurationBadge.textContent = data.duration;
          ytDurationBadge.classList.remove('d-none');
        } else {
          ytDurationBadge.classList.add('d-none');
        }
      }

      if (ytViewsBadge) {
        if (data.views) {
          ytViewsBadge.innerHTML = '<i class="bi bi-eye me-1"></i>' + escapeHtml(data.views) + ' views';
          ytViewsBadge.classList.remove('d-none');
        } else {
          ytViewsBadge.classList.add('d-none');
        }
      }

      if (ytThumbLinks && data.thumbnails) {
        ytThumbLinks.innerHTML =
          '<a href="' + escapeHtml(data.thumbnails.maxres) + '" target="_blank" rel="noopener noreferrer" class="btn tn-btn--outline btn-sm"><i class="fa-solid fa-image me-1"></i> HD 1280×720</a>' +
          '<a href="' + escapeHtml(data.thumbnails.hq) + '" target="_blank" rel="noopener noreferrer" class="btn tn-btn--outline btn-sm"><i class="fa-solid fa-image me-1"></i> HQ 480×360</a>' +
          '<a href="' + escapeHtml(data.thumbnails.mq) + '" target="_blank" rel="noopener noreferrer" class="btn tn-btn--outline btn-sm"><i class="fa-solid fa-image me-1"></i> MQ 320×180</a>';
      }

      renderFormatRows(data.audioFormats || [], audioTbody, true);
      renderFormatRows(data.videoFormats || [], videoTbody, false);

      if (ytLoadingEl) ytLoadingEl.classList.add('d-none');
      ytResultWrap.classList.remove('d-none');
    } catch (err) {
      if (ytLoadingEl) ytLoadingEl.classList.add('d-none');
      ytErrorEl.textContent = err && err.message ? err.message : 'Failed to fetch YouTube download options.';
      ytErrorEl.classList.remove('d-none');
    } finally {
      if (ytFetchBtn) ytFetchBtn.disabled = false;
    }
  }

  // Filter Tabs (All / Audio Only / Video Only)
  filterButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const mode = btn.getAttribute('data-tn-yt-filter') || 'all';
      filterButtons.forEach(function (b) {
        b.classList.remove('btn-primary', 'active');
        b.classList.add('tn-btn--outline');
      });
      btn.classList.remove('tn-btn--outline');
      btn.classList.add('btn-primary', 'active');

      if (audioSection) {
        audioSection.classList.toggle('d-none', mode === 'video');
      }
      if (videoSection) {
        videoSection.classList.toggle('d-none', mode === 'audio');
      }
    });
  });

  if (ytFetchBtn && ytUrlInput) {
    ytFetchBtn.addEventListener('click', function () {
      fetchYouTubeFormats(ytUrlInput.value);
    });
    ytUrlInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        fetchYouTubeFormats(ytUrlInput.value);
      }
    });
    ytUrlInput.addEventListener('paste', function () {
      setTimeout(function () {
        if (extractYouTubeId(ytUrlInput.value)) {
          fetchYouTubeFormats(ytUrlInput.value);
        }
      }, 80);
    });
  }

  if (ytPasteBtn && ytUrlInput) {
    ytPasteBtn.addEventListener('click', async function () {
      if (navigator.clipboard && navigator.clipboard.readText) {
        try {
          const text = await navigator.clipboard.readText();
          if (text) {
            ytUrlInput.value = text.trim();
            fetchYouTubeFormats(ytUrlInput.value);
          }
        } catch (e) {
          ytUrlInput.focus();
          showToast('Press Ctrl+V to paste your link.', 'warning');
        }
      } else {
        ytUrlInput.focus();
      }
    });
  }

  if (ytSampleBtn && ytUrlInput) {
    ytSampleBtn.addEventListener('click', function () {
      ytUrlInput.value = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ';
      fetchYouTubeFormats(ytUrlInput.value);
    });
  }

  // =========================================================================
  // TAB 2: Local Video File to High-Quality Audio Extractor (MP3 320kbps/WAV)
  // =========================================================================
  const dropzone = document.getElementById('tn-v2a-dropzone');
  const fileInput = document.getElementById('tn-v2a-file');
  if (!dropzone || !fileInput) return;

  const workspacePanel = document.getElementById('tn-v2a-editor');
  const videoPreview = document.getElementById('tn-v2a-video-preview');
  const fileNameEl = document.getElementById('tn-v2a-file-name');
  const fileMetaEl = document.getElementById('tn-v2a-file-meta');
  const waveformCanvas = document.getElementById('tn-v2a-waveform');

  const formatSelect = document.getElementById('tn-v2a-format');
  const sampleRateSelect = document.getElementById('tn-v2a-samplerate');
  const channelSelect = document.getElementById('tn-v2a-channels');
  const volumeSelect = document.getElementById('tn-v2a-volume');
  const fadeInSelect = document.getElementById('tn-v2a-fadein');
  const fadeOutSelect = document.getElementById('tn-v2a-fadeout');
  const normalizeCheck = document.getElementById('tn-v2a-normalize');

  const startInput = document.getElementById('tn-v2a-start');
  const endInput = document.getElementById('tn-v2a-end');
  const durationBadge = document.getElementById('tn-v2a-trim-duration');

  const extractBtn = document.getElementById('tn-v2a-extract-btn');
  const downloadBtn = document.getElementById('tn-v2a-download-btn');
  const resetBtn = document.getElementById('tn-v2a-reset-btn');

  const progressWrap = document.getElementById('tn-v2a-progress-wrap');
  const progressBar = document.getElementById('tn-v2a-progress-bar');
  const progressLabel = document.getElementById('tn-v2a-progress-label');
  const statusEl = document.getElementById('tn-v2a-status');

  const resultBox = document.getElementById('tn-v2a-result-box');
  const audioPreview = document.getElementById('tn-v2a-audio-preview');
  const resultSummary = document.getElementById('tn-v2a-result-summary');

  let currentFile = null;
  let decodedBuffer = null;
  let outputBlob = null;
  let outputFilename = 'extracted-audio.mp3';
  let videoObjectUrl = null;
  let audioObjectUrl = null;
  let lameLoadedPromise = null;

  function formatBytes(bytes) {
    if (window.ToolZebra && typeof window.ToolZebra.formatBytes === 'function') {
      return window.ToolZebra.formatBytes(bytes);
    }
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
  }

  function formatTime(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) return '00:00';
    const mins = Math.floor(seconds / 60);
    const secs = (seconds % 60).toFixed(1);
    return String(mins).padStart(2, '0') + ':' + (secs < 10 ? '0' + secs : secs);
  }

  function setStatus(text, isError) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.className = 'small mb-3 ' + (isError ? 'text-danger fw-semibold' : 'text-muted');
  }

  function setProgress(percent, label) {
    if (!progressWrap || !progressBar) return;
    if (percent === null) {
      progressWrap.classList.add('d-none');
      return;
    }
    progressWrap.classList.remove('d-none');
    const clamped = Math.max(0, Math.min(100, Math.round(percent)));
    progressBar.style.width = clamped + '%';
    progressBar.setAttribute('aria-valuenow', String(clamped));
    if (progressLabel && label) {
      progressLabel.textContent = label + ' (' + clamped + '%)';
    }
  }

  function ensureLameJs() {
    if (window.lamejs && window.lamejs.Mp3Encoder) {
      return Promise.resolve(window.lamejs);
    }
    if (lameLoadedPromise) return lameLoadedPromise;

    lameLoadedPromise = new Promise(function (resolve, reject) {
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/lamejs@1.2.1/lame.min.js';
      script.async = true;
      script.onload = function () {
        if (window.lamejs && window.lamejs.Mp3Encoder) {
          resolve(window.lamejs);
        } else {
          reject(new Error('MP3 encoder library loaded but lamejs was not found.'));
        }
      };
      script.onerror = function () {
        lameLoadedPromise = null;
        reject(new Error('Could not load MP3 encoder library. Check your connection or select WAV format.'));
      };
      document.head.appendChild(script);
    });

    return lameLoadedPromise;
  }

  function drawWaveform(audioBuffer, trimStart, trimEnd) {
    if (!waveformCanvas || !audioBuffer) return;
    const ctx = waveformCanvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const width = waveformCanvas.clientWidth || 640;
    const height = waveformCanvas.clientHeight || 96;

    waveformCanvas.width = width * dpr;
    waveformCanvas.height = height * dpr;
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, width, height);

    const totalDuration = audioBuffer.duration || 1;
    const startRatio = Math.max(0, Math.min(1, trimStart / totalDuration));
    const endRatio = Math.max(startRatio, Math.min(1, trimEnd / totalDuration));

    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, width, height);

    const startX = startRatio * width;
    const endX = endRatio * width;
    ctx.fillStyle = 'rgba(37, 99, 235, 0.08)';
    ctx.fillRect(startX, 0, Math.max(2, endX - startX), height);

    const channelData = audioBuffer.getChannelData(0);
    const barCount = Math.min(180, Math.floor(width / 4));
    const step = Math.max(1, Math.floor(channelData.length / barCount));
    const midY = height / 2;

    for (let i = 0; i < barCount; i++) {
      let maxPeak = 0;
      const offset = i * step;
      for (let j = 0; j < step && offset + j < channelData.length; j += 4) {
        const abs = Math.abs(channelData[offset + j]);
        if (abs > maxPeak) maxPeak = abs;
      }
      const x = (i / barCount) * width;
      const inTrim = x >= startX && x <= endX;
      ctx.fillStyle = inTrim ? '#2563eb' : '#cbd5e1';

      const barHeight = Math.max(4, maxPeak * (height * 0.84));
      const barWidth = Math.max(2, (width / barCount) * 0.65);
      ctx.fillRect(x, midY - barHeight / 2, barWidth, barHeight);
    }

    ctx.strokeStyle = '#1d4ed8';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(startX, 0);
    ctx.lineTo(startX, height);
    ctx.moveTo(endX, 0);
    ctx.lineTo(endX, height);
    ctx.stroke();
  }

  function getTrimBounds() {
    if (!decodedBuffer) return { start: 0, end: 0, duration: 0 };
    const total = decodedBuffer.duration;
    let start = parseFloat(startInput.value);
    let end = parseFloat(endInput.value);
    if (!Number.isFinite(start) || start < 0) start = 0;
    if (!Number.isFinite(end) || end <= 0 || end > total) end = total;
    if (start >= end) start = Math.max(0, end - 0.5);
    return {
      start: start,
      end: end,
      duration: Math.max(0.1, end - start)
    };
  }

  function updateTrimUI() {
    if (!decodedBuffer) return;
    const bounds = getTrimBounds();
    if (durationBadge) {
      durationBadge.textContent =
        'Selected Length: ' +
        formatTime(bounds.duration) +
        ' (' +
        formatTime(bounds.start) +
        ' – ' +
        formatTime(bounds.end) +
        ')';
    }
    drawWaveform(decodedBuffer, bounds.start, bounds.end);
  }

  async function handleFileSelected(files) {
    if (!files || !files.length) return;
    const file = files[0];
    currentFile = file;

    if (videoObjectUrl) URL.revokeObjectURL(videoObjectUrl);
    videoObjectUrl = URL.createObjectURL(file);

    if (videoPreview) {
      videoPreview.src = videoObjectUrl;
      videoPreview.classList.remove('d-none');
    }

    if (fileNameEl) fileNameEl.textContent = file.name;
    if (fileMetaEl) fileMetaEl.textContent = formatBytes(file.size) + ' • Decoding audio stream...';

    workspacePanel.classList.remove('d-none');
    resultBox.classList.add('d-none');
    downloadBtn.disabled = true;
    extractBtn.disabled = true;
    setProgress(15, 'Reading & decoding video audio track');
    setStatus('Decoding audio stream locally inside your browser...', false);

    try {
      const arrayBuffer = await file.arrayBuffer();
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      const audioCtx = new AudioCtx();
      setProgress(55, 'Analyzing audio waveform & channels');
      decodedBuffer = await audioCtx.decodeAudioData(arrayBuffer);
      if (typeof audioCtx.close === 'function') {
        audioCtx.close();
      }

      const dur = decodedBuffer.duration;
      startInput.value = '0';
      startInput.max = dur.toFixed(1);
      endInput.value = dur.toFixed(1);
      endInput.max = dur.toFixed(1);

      if (fileMetaEl) {
        fileMetaEl.textContent =
          formatBytes(file.size) +
          ' • Duration: ' +
          formatTime(dur) +
          ' • ' +
          decodedBuffer.sampleRate +
          ' Hz (' +
          decodedBuffer.numberOfChannels +
          ' ch)';
      }

      updateTrimUI();
      setProgress(null);
      extractBtn.disabled = false;
      resetBtn.disabled = false;
      setStatus('Ready! Choose your quality settings (e.g. 320 kbps MP3 or WAV) and click Extract High-Quality Audio.', false);
    } catch (err) {
      setProgress(null);
      setStatus('Could not decode an audio track from this file. Please select a valid MP4, WebM, MOV, MKV, or audio file.', true);
      showToast('Failed to decode audio from file.', 'danger');
    }
  }

  async function renderProcessedAudioBuffer() {
    const bounds = getTrimBounds();
    const targetSampleRate = parseInt(sampleRateSelect.value, 10) || 44100;
    const targetChannels = parseInt(channelSelect.value, 10) || 2;
    const volumeMult = (parseFloat(volumeSelect.value) || 100) / 100;
    const fadeInSec = parseFloat(fadeInSelect.value) || 0;
    const fadeOutSec = parseFloat(fadeOutSelect.value) || 0;
    const shouldNormalize = normalizeCheck && normalizeCheck.checked;

    const frameCount = Math.max(1, Math.ceil(bounds.duration * targetSampleRate));
    const offlineCtx = new OfflineAudioContext(targetChannels, frameCount, targetSampleRate);

    const source = offlineCtx.createBufferSource();
    source.buffer = decodedBuffer;

    const gainNode = offlineCtx.createGain();
    let baseGain = volumeMult;

    if (shouldNormalize) {
      let maxSample = 0;
      const startSample = Math.floor(bounds.start * decodedBuffer.sampleRate);
      const endSample = Math.min(decodedBuffer.length, Math.ceil(bounds.end * decodedBuffer.sampleRate));
      for (let ch = 0; ch < decodedBuffer.numberOfChannels; ch++) {
        const data = decodedBuffer.getChannelData(ch);
        for (let i = startSample; i < endSample; i += 2) {
          const abs = Math.abs(data[i]);
          if (abs > maxSample) maxSample = abs;
        }
      }
      if (maxSample > 0.001) {
        baseGain = (0.96 / maxSample) * volumeMult;
      }
    }

    if (fadeInSec > 0 && fadeInSec < bounds.duration) {
      gainNode.gain.setValueAtTime(0.0001, 0);
      gainNode.gain.linearRampToValueAtTime(baseGain, fadeInSec);
    } else {
      gainNode.gain.setValueAtTime(baseGain, 0);
    }

    if (fadeOutSec > 0 && fadeOutSec < bounds.duration) {
      const fadeOutStart = Math.max(0, bounds.duration - fadeOutSec);
      gainNode.gain.setValueAtTime(baseGain, fadeOutStart);
      gainNode.gain.linearRampToValueAtTime(0.0001, bounds.duration);
    }

    source.connect(gainNode);
    gainNode.connect(offlineCtx.destination);
    source.start(0, bounds.start, bounds.duration);

    return offlineCtx.startRendering();
  }

  function floatTo16BitPCM(float32Array) {
    const int16 = new Int16Array(float32Array.length);
    for (let i = 0; i < float32Array.length; i++) {
      const s = Math.max(-1, Math.min(1, float32Array[i]));
      int16[i] = s < 0 ? s * 0x8000 : s * 0x7fff;
    }
    return int16;
  }

  function encodeWavFromBuffer(audioBuffer) {
    const numChannels = audioBuffer.numberOfChannels;
    const sampleRate = audioBuffer.sampleRate;
    const format = 1;
    const bitDepth = 16;
    const bytesPerSample = bitDepth / 8;
    const blockAlign = numChannels * bytesPerSample;
    const samples = audioBuffer.length;
    const dataSize = samples * blockAlign;
    const buffer = new ArrayBuffer(44 + dataSize);
    const view = new DataView(buffer);

    function writeStr(offset, str) {
      for (let i = 0; i < str.length; i++) {
        view.setUint8(offset + i, str.charCodeAt(i));
      }
    }

    writeStr(0, 'RIFF');
    view.setUint32(4, 36 + dataSize, true);
    writeStr(8, 'WAVE');
    writeStr(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, format, true);
    view.setUint16(22, numChannels, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * blockAlign, true);
    view.setUint16(32, blockAlign, true);
    view.setUint16(34, bitDepth, true);
    writeStr(36, 'data');
    view.setUint32(40, dataSize, true);

    const channels = [];
    for (let ch = 0; ch < numChannels; ch++) {
      channels.push(audioBuffer.getChannelData(ch));
    }

    let offset = 44;
    for (let i = 0; i < samples; i++) {
      for (let ch = 0; ch < numChannels; ch++) {
        const sample = Math.max(-1, Math.min(1, channels[ch][i]));
        view.setInt16(offset, sample < 0 ? sample * 0x8000 : sample * 0x7fff, true);
        offset += 2;
      }
    }

    return new Blob([buffer], { type: 'audio/wav' });
  }

  async function encodeMp3FromBuffer(audioBuffer, kbps) {
    const lame = await ensureLameJs();
    const numChannels = audioBuffer.numberOfChannels;
    const sampleRate = audioBuffer.sampleRate;
    const mp3encoder = new lame.Mp3Encoder(numChannels, sampleRate, kbps);

    const leftPCM = floatTo16BitPCM(audioBuffer.getChannelData(0));
    const rightPCM = numChannels > 1 ? floatTo16BitPCM(audioBuffer.getChannelData(1)) : null;

    const sampleBlockSize = 1152 * 16;
    const mp3Data = [];
    const totalSamples = leftPCM.length;

    for (let i = 0; i < totalSamples; i += sampleBlockSize) {
      const leftChunk = leftPCM.subarray(i, i + sampleBlockSize);
      let mp3buf;
      if (numChannels > 1 && rightPCM) {
        const rightChunk = rightPCM.subarray(i, i + sampleBlockSize);
        mp3buf = mp3encoder.encodeBuffer(leftChunk, rightChunk);
      } else {
        mp3buf = mp3encoder.encodeBuffer(leftChunk);
      }
      if (mp3buf.length > 0) {
        mp3Data.push(mp3buf);
      }

      if (i % (sampleBlockSize * 8) === 0) {
        const pct = 25 + Math.round((i / totalSamples) * 70);
        setProgress(pct, 'Encoding ' + kbps + ' kbps MP3 audio');
        await new Promise(function (r) { setTimeout(r, 0); });
      }
    }

    const endBuf = mp3encoder.flush();
    if (endBuf.length > 0) {
      mp3Data.push(endBuf);
    }

    return new Blob(mp3Data, { type: 'audio/mpeg' });
  }

  async function extractAudio() {
    if (!decodedBuffer || !currentFile) return;

    extractBtn.disabled = true;
    downloadBtn.disabled = true;

    const formatVal = formatSelect.value || 'mp3-320';
    const baseName = currentFile.name.replace(/\.[^/.]+$/, '');

    try {
      setProgress(10, 'Rendering high-resolution audio buffer');
      setStatus('Processing audio channel mix, trim & normalization...', false);

      const renderedBuffer = await renderProcessedAudioBuffer();

      let blob;
      let ext = 'mp3';
      let qualityLabel = '320 kbps MP3';

      if (formatVal === 'wav-pcm') {
        setProgress(70, 'Packaging 16-bit lossless PCM WAV');
        blob = encodeWavFromBuffer(renderedBuffer);
        ext = 'wav';
        qualityLabel = '16-Bit Lossless WAV';
      } else {
        const kbps = parseInt(formatVal.split('-')[1], 10) || 320;
        qualityLabel = kbps + ' kbps MP3';
        setProgress(22, 'Loading MP3 encoder (' + qualityLabel + ')');
        blob = await encodeMp3FromBuffer(renderedBuffer, kbps);
        ext = 'mp3';
      }

      outputBlob = blob;
      outputFilename = baseName + '-' + formatVal + '.' + ext;

      if (audioObjectUrl) URL.revokeObjectURL(audioObjectUrl);
      audioObjectUrl = URL.createObjectURL(blob);

      if (audioPreview) {
        audioPreview.src = audioObjectUrl;
      }

      if (resultSummary) {
        resultSummary.textContent =
          outputFilename +
          ' • ' +
          qualityLabel +
          ' • ' +
          renderedBuffer.sampleRate +
          ' Hz • ' +
          formatBytes(blob.size) +
          ' (Duration: ' +
          formatTime(renderedBuffer.duration) +
          ')';
      }

      resultBox.classList.remove('d-none');
      downloadBtn.disabled = false;
      extractBtn.disabled = false;
      setProgress(null);
      setStatus('Audio extraction complete! Preview below or click Download Audio.', false);
      showToast('High-quality audio extracted!', 'success');
    } catch (err) {
      extractBtn.disabled = false;
      setProgress(null);
      setStatus(err && err.message ? err.message : 'Failed to extract audio.', true);
      showToast('Audio extraction failed.', 'danger');
    }
  }

  function resetAll() {
    currentFile = null;
    decodedBuffer = null;
    outputBlob = null;
    fileInput.value = '';
    if (videoObjectUrl) {
      URL.revokeObjectURL(videoObjectUrl);
      videoObjectUrl = null;
    }
    if (audioObjectUrl) {
      URL.revokeObjectURL(audioObjectUrl);
      audioObjectUrl = null;
    }
    if (videoPreview) {
      videoPreview.removeAttribute('src');
      videoPreview.classList.add('d-none');
    }
    if (audioPreview) {
      audioPreview.removeAttribute('src');
    }
    workspacePanel.classList.add('d-none');
    resultBox.classList.add('d-none');
    extractBtn.disabled = true;
    downloadBtn.disabled = true;
    resetBtn.disabled = true;
    setProgress(null);
    setStatus('Select or drop a video file above to extract 320 kbps MP3 or Lossless WAV audio.', false);
  }

  if (window.ToolZebra && typeof window.ToolZebra.setupDropzone === 'function') {
    window.ToolZebra.setupDropzone(dropzone, fileInput, handleFileSelected);
  } else {
    fileInput.addEventListener('change', function () {
      if (fileInput.files && fileInput.files.length) {
        handleFileSelected(Array.from(fileInput.files));
      }
    });
  }

  if (startInput) startInput.addEventListener('input', updateTrimUI);
  if (endInput) endInput.addEventListener('input', updateTrimUI);
  if (extractBtn) extractBtn.addEventListener('click', extractAudio);
  if (resetBtn) resetBtn.addEventListener('click', resetAll);

  if (downloadBtn) {
    downloadBtn.addEventListener('click', function () {
      if (!outputBlob) return;
      if (window.ToolZebra && typeof window.ToolZebra.downloadBlob === 'function') {
        window.ToolZebra.downloadBlob(outputBlob, outputFilename);
      } else {
        const url = URL.createObjectURL(outputBlob);
        const a = document.createElement('a');
        a.href = url;
        a.download = outputFilename;
        a.click();
        URL.revokeObjectURL(url);
      }
    });
  }

  window.addEventListener('resize', function () {
    if (decodedBuffer) updateTrimUI();
  });
})();
