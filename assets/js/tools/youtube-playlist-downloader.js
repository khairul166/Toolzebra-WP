/**
 * ToolZebra — YouTube Playlist Downloader (Full Playlist Audio & Video .ZIP + Track Selector)
 */
(function () {
  'use strict';

  function formatDuration(totalSeconds) {
    var sec = Math.max(0, Math.round(Number(totalSeconds) || 0));
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    var pad = function (n) {
      return n < 10 ? '0' + n : String(n);
    };
    return h > 0 ? pad(h) + ':' + pad(m) + ':' + pad(s) : pad(m) + ':' + pad(s);
  }

  function estimateZipSize(durationSec, kbps) {
    var bytes = Math.max(1, durationSec) * ((kbps * 1000) / 8);
    var mb = bytes / (1024 * 1024);
    if (mb >= 1024) {
      return (mb / 1024).toFixed(2) + ' GB';
    }
    return Math.max(0.5, mb).toFixed(1) + ' MB';
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function getAjaxUrl() {
    if (window.toolnestAjaxUrl) {
      return window.toolnestAjaxUrl;
    }
    return '/wp-admin/admin-ajax.php';
  }

  function triggerBrowserDownload(url) {
    var link = document.createElement('a');
    link.href = url;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    setTimeout(function () {
      if (link.parentNode) {
        link.parentNode.removeChild(link);
      }
    }, 1500);
  }

  function initYoutubePlaylistDownloader() {
    var urlInput = document.getElementById('tn-ytpl-url');
    if (!urlInput) return;

    var fetchBtn = document.getElementById('tn-ytpl-fetch-btn');
    var sampleBtn = document.getElementById('tn-ytpl-sample-btn');
    var clearBtn = document.getElementById('tn-ytpl-clear-btn');
    var errorBox = document.getElementById('tn-ytpl-error');
    var loadingBox = document.getElementById('tn-ytpl-loading');
    var resultsBox = document.getElementById('tn-ytpl-results');

    var thumbImg = document.getElementById('tn-ytpl-thumb');
    var countBadge = document.getElementById('tn-ytpl-count-badge');
    var channelEl = document.getElementById('tn-ytpl-channel');
    var durationBadge = document.getElementById('tn-ytpl-duration-badge');
    var titleEl = document.getElementById('tn-ytpl-title');
    var selectedSummaryEl = document.getElementById('tn-ytpl-selected-summary');
    var jumpCountEl = document.getElementById('tn-ytpl-jump-count');

    var zipProgressCard = document.getElementById('tn-ytpl-zip-progress-card');
    var zipProgressTitle = document.getElementById('tn-ytpl-zip-progress-title');
    var zipProgressBadge = document.getElementById('tn-ytpl-zip-progress-badge');
    var zipProgressBar = document.getElementById('tn-ytpl-zip-progress-bar');
    var zipProgressStatus = document.getElementById('tn-ytpl-zip-progress-status');

    var filterAllBtn = document.getElementById('tn-ytpl-filter-all');
    var filterAudioBtn = document.getElementById('tn-ytpl-filter-audio');
    var filterVideoBtn = document.getElementById('tn-ytpl-filter-video');
    var audioSection = document.getElementById('tn-ytpl-audio-section');
    var videoSection = document.getElementById('tn-ytpl-video-section');

    var audioTbody = document.getElementById('tn-ytpl-audio-tbody');
    var videoTbody = document.getElementById('tn-ytpl-video-tbody');
    var tracksTbody = document.getElementById('tn-ytpl-tracks-tbody');

    var selectAllBtn = document.getElementById('tn-ytpl-select-all-btn');
    var select10Btn = document.getElementById('tn-ytpl-select-10-btn');
    var select25Btn = document.getElementById('tn-ytpl-select-25-btn');
    var deselectAllBtn = document.getElementById('tn-ytpl-deselect-all-btn');

    var currentPlaylist = null;

    function showError(msg) {
      if (!errorBox) return;
      errorBox.textContent = msg;
      errorBox.classList.remove('d-none');
    }

    function hideError() {
      if (!errorBox) return;
      errorBox.textContent = '';
      errorBox.classList.add('d-none');
    }

    function setFilter(mode) {
      if (filterAllBtn) {
        filterAllBtn.className = 'btn btn-sm ' + (mode === 'all' ? 'tn-btn--primary' : 'tn-btn--outline');
      }
      if (filterAudioBtn) {
        filterAudioBtn.className = 'btn btn-sm ' + (mode === 'audio' ? 'tn-btn--primary' : 'tn-btn--outline');
      }
      if (filterVideoBtn) {
        filterVideoBtn.className = 'btn btn-sm ' + (mode === 'video' ? 'tn-btn--primary' : 'tn-btn--outline');
      }
      if (audioSection) {
        audioSection.classList.toggle('d-none', mode === 'video');
      }
      if (videoSection) {
        videoSection.classList.toggle('d-none', mode === 'audio');
      }
    }

    if (filterAllBtn) {
      filterAllBtn.addEventListener('click', function () {
        setFilter('all');
      });
    }
    if (filterAudioBtn) {
      filterAudioBtn.addEventListener('click', function () {
        setFilter('audio');
      });
    }
    if (filterVideoBtn) {
      filterVideoBtn.addEventListener('click', function () {
        setFilter('video');
      });
    }

    function getSelectedItems() {
      if (!currentPlaylist || !Array.isArray(currentPlaylist.items)) {
        return [];
      }
      var checkboxes = tracksTbody ? tracksTbody.querySelectorAll('.tn-ytpl-track-check:checked') : [];
      var selectedIds = {};
      Array.prototype.forEach.call(checkboxes, function (cb) {
        selectedIds[cb.value] = true;
      });
      return currentPlaylist.items.filter(function (item) {
        return !!selectedIds[item.videoId];
      });
    }

    function updateZipTablesAndSummary() {
      if (!currentPlaylist) return;
      var selected = getSelectedItems();
      var selectedCount = selected.length;
      var totalCount = currentPlaylist.items.length;
      var selectedDuration = selected.reduce(function (acc, item) {
        return acc + (Number(item.duration) || 210);
      }, 0);

      if (selectedSummaryEl) {
        selectedSummaryEl.textContent =
          selectedCount +
          ' of ' +
          totalCount +
          ' songs selected for .ZIP archive • Selected Duration: ' +
          formatDuration(selectedDuration);
      }
      if (jumpCountEl) {
        jumpCountEl.textContent = selectedCount + '/' + totalCount;
      }

      renderZipFormatRows(audioTbody, currentPlaylist.audioFormats || [], selectedDuration, selectedCount, true);
      renderZipFormatRows(videoTbody, currentPlaylist.videoFormats || [], selectedDuration, selectedCount, false);
    }

    function renderZipFormatRows(tbody, formats, totalDurationSec, selectedCount, isAudio) {
      if (!tbody) return;
      tbody.innerHTML = '';

      formats.forEach(function (fmt) {
        var tr = document.createElement('tr');
        var estSize = selectedCount > 0 ? estimateZipSize(totalDurationSec, fmt.kbps || 192) : '0 MB';
        var iconClass = isAudio ? 'fa-solid fa-file-zipper' : 'fa-solid fa-file-zipper';
        var typeBadgeClass = isAudio ? 'bg-primary' : 'bg-success';
        var btnClass = isAudio ? 'tn-btn--primary' : 'tn-btn--success';

        tr.innerHTML =
          '<td>' +
          '<span class="badge ' +
          typeBadgeClass +
          '"><i class="' +
          iconClass +
          ' me-1" aria-hidden="true"></i>' +
          escapeHtml(fmt.fileType) +
          '</span>' +
          '</td>' +
          '<td>' +
          '<span class="fw-bold me-2">' +
          escapeHtml(fmt.quality) +
          '</span>' +
          (fmt.badge ? '<span class="badge bg-secondary">' + escapeHtml(fmt.badge) + '</span>' : '') +
          '</td>' +
          '<td class="fw-semibold text-muted">' +
          escapeHtml(estSize) +
          ' <span class="small">(' +
          selectedCount +
          ' files)</span></td>' +
          '<td class="text-end">' +
          '<button type="button" class="btn btn-sm ' +
          btnClass +
          ' tn-ytpl-zip-btn" ' +
          (selectedCount === 0 ? 'disabled ' : '') +
          'data-format="' +
          escapeHtml(fmt.format) +
          '" data-audio-quality="' +
          escapeHtml(fmt.audioQuality || 0) +
          '" data-ext="' +
          escapeHtml(fmt.ext) +
          '" data-label="' +
          escapeHtml(fmt.quality) +
          '">' +
          '<i class="fa-solid fa-file-zipper me-1" aria-hidden="true"></i> Download .' +
          escapeHtml(String(fmt.ext || 'zip').toUpperCase()) +
          ' .ZIP' +
          '</button>' +
          '</td>';

        var btn = tr.querySelector('.tn-ytpl-zip-btn');
        if (btn) {
          btn.addEventListener('click', function () {
            handlePlaylistZipDownload(btn, fmt);
          });
        }

        tbody.appendChild(tr);
      });
    }

    function renderTracksTable(items) {
      if (!tracksTbody) return;
      tracksTbody.innerHTML = '';

      items.forEach(function (item, index) {
        var tr = document.createElement('tr');
        // Select first 20 by default if playlist is huge so ZIP completes super fast, or all if <= 25
        var isChecked = index < 25;

        tr.innerHTML =
          '<td>' +
          '<div class="form-check">' +
          '<input class="form-check-input tn-ytpl-track-check" type="checkbox" value="' +
          escapeHtml(item.videoId) +
          '" id="tn-ytpl-chk-' +
          escapeHtml(item.videoId) +
          '" ' +
          (isChecked ? 'checked' : '') +
          '>' +
          '</div>' +
          '</td>' +
          '<td>' +
          '<div class="d-flex align-items-center gap-2">' +
          '<img src="' +
          escapeHtml(item.thumbnail) +
          '" alt="" class="rounded" style="width: 60px; height: 34px; object-fit: cover;">' +
          '<div>' +
          '<div class="fw-semibold small">' +
          escapeHtml(item.index + '. ' + item.title) +
          '</div>' +
          '<div class="text-muted" style="font-size: 0.75rem;">' +
          escapeHtml(item.channel) +
          '</div>' +
          '</div>' +
          '</div>' +
          '</td>' +
          '<td class="small fw-semibold text-muted">' +
          escapeHtml(item.durationFormatted) +
          '</td>' +
          '<td class="text-end">' +
          '<div class="d-inline-flex gap-1">' +
          '<button type="button" class="btn btn-sm tn-btn--outline tn-ytpl-single-btn" data-vid="' +
          escapeHtml(item.videoId) +
          '" data-title="' +
          escapeHtml(item.title) +
          '" data-format="mp3" data-aq="320" data-ext="mp3">' +
          '<i class="fa-solid fa-music me-1" aria-hidden="true"></i>MP3' +
          '</button>' +
          '<button type="button" class="btn btn-sm tn-btn--outline tn-ytpl-single-btn" data-vid="' +
          escapeHtml(item.videoId) +
          '" data-title="' +
          escapeHtml(item.title) +
          '" data-format="720" data-aq="0" data-ext="mp4">' +
          '<i class="fa-solid fa-video me-1" aria-hidden="true"></i>MP4' +
          '</button>' +
          '</div>' +
          '</td>';

        var checkbox = tr.querySelector('.tn-ytpl-track-check');
        if (checkbox) {
          checkbox.addEventListener('change', updateZipTablesAndSummary);
        }

        var singleBtns = tr.querySelectorAll('.tn-ytpl-single-btn');
        Array.prototype.forEach.call(singleBtns, function (sBtn) {
          sBtn.addEventListener('click', function () {
            handleSingleTrackDownload(sBtn);
          });
        });

        tracksTbody.appendChild(tr);
      });
    }

    function handleSingleTrackDownload(btn) {
      if (btn.disabled) return;
      var videoId = btn.getAttribute('data-vid');
      var title = btn.getAttribute('data-title') || 'youtube-track';
      var format = btn.getAttribute('data-format') || 'mp3';
      var aq = btn.getAttribute('data-aq') || '320';
      var ext = btn.getAttribute('data-ext') || 'mp3';
      var originalHtml = btn.innerHTML;

      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>0%';

      var body = new URLSearchParams();
      body.set('action', 'toolnest_yt_convert');
      body.set('url', 'https://www.youtube.com/watch?v=' + videoId);
      body.set('format', format);
      body.set('audio_quality', aq);
      body.set('title', title);
      body.set('ext', ext);

      fetch(getAjaxUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (res) {
          if (!res || !res.success || !res.data || !res.data.jobId) {
            throw new Error((res && res.data && res.data.message) || 'Conversion failed.');
          }
          return pollSingleJob(res.data.jobId, btn);
        })
        .then(function (downloadUrl) {
          btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Done';
          triggerBrowserDownload(downloadUrl);
          setTimeout(function () {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
          }, 3000);
        })
        .catch(function (err) {
          showError(err && err.message ? err.message : 'Could not download single track.');
          btn.disabled = false;
          btn.innerHTML = originalHtml;
        });
    }

    function pollSingleJob(jobId, btn) {
      return new Promise(function (resolve, reject) {
        var attempts = 0;
        var timer = setInterval(function () {
          attempts++;
          if (attempts > 180) {
            clearInterval(timer);
            reject(new Error('Single track conversion timed out.'));
            return;
          }
          var pUrl =
            getAjaxUrl() +
            '?action=toolnest_yt_progress&id=' +
            encodeURIComponent(jobId) +
            '&_t=' +
            Date.now();
          fetch(pUrl)
            .then(function (r) {
              return r.json();
            })
            .then(function (res) {
              if (!res || !res.success || !res.data) {
                clearInterval(timer);
                reject(new Error((res && res.data && res.data.message) || 'Failed.'));
                return;
              }
              var pct = res.data.percent || 15;
              btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + pct + '%';
              if (res.data.finished && res.data.downloadUrl) {
                clearInterval(timer);
                resolve(res.data.downloadUrl);
              }
            })
            .catch(function () {});
        }, 700);
      });
    }

    function handlePlaylistZipDownload(btn, fmt) {
      if (!currentPlaylist || btn.disabled) return;
      hideError();

      var selected = getSelectedItems();
      if (selected.length === 0) {
        showError('Please select at least one song or video from the playlist.');
        return;
      }

      var videoIds = selected
        .map(function (item) {
          return item.videoId;
        })
        .join(',');

      var originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Starting .ZIP...';

      if (zipProgressCard) {
        zipProgressCard.classList.remove('d-none');
        zipProgressCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      if (zipProgressTitle) {
        zipProgressTitle.textContent =
          'Building Playlist .ZIP (' + fmt.quality + ' • ' + selected.length + ' tracks)...';
      }
      if (zipProgressBadge) {
        zipProgressBadge.textContent = '5%';
      }
      if (zipProgressBar) {
        zipProgressBar.style.width = '5%';
        zipProgressBar.textContent = '5%';
      }
      if (zipProgressStatus) {
        zipProgressStatus.textContent = 'Starting batch download & conversion on server...';
      }

      var body = new URLSearchParams();
      body.set('action', 'toolnest_yt_playlist_zip_start');
      body.set('video_ids', videoIds);
      body.set('format', fmt.format);
      body.set('audio_quality', String(fmt.audioQuality || 0));
      body.set('ext', fmt.ext || 'mp3');
      body.set('title', currentPlaylist.title || 'YouTube-Playlist');

      fetch(getAjaxUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (res) {
          if (!res || !res.success || !res.data || !res.data.jobId) {
            throw new Error((res && res.data && res.data.message) || 'Could not start playlist ZIP job.');
          }
          return pollPlaylistZipJob(res.data.jobId, btn);
        })
        .then(function (downloadUrl) {
          btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>.ZIP Ready! Downloading...';
          if (zipProgressBadge) zipProgressBadge.textContent = '100%';
          if (zipProgressBar) {
            zipProgressBar.style.width = '100%';
            zipProgressBar.textContent = '100%';
          }
          if (zipProgressStatus) {
            zipProgressStatus.textContent = 'Playlist .ZIP archive ready! Your browser download has started.';
          }
          triggerBrowserDownload(downloadUrl);
          setTimeout(function () {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
          }, 4500);
        })
        .catch(function (err) {
          showError(err && err.message ? err.message : 'Playlist ZIP conversion failed.');
          btn.disabled = false;
          btn.innerHTML = originalHtml;
          if (zipProgressCard) {
            zipProgressCard.classList.add('d-none');
          }
        });
    }

    function pollPlaylistZipJob(jobId, btn) {
      return new Promise(function (resolve, reject) {
        var attempts = 0;
        var maxAttempts = 600; // up to ~15 minutes for multi-video playlists
        var timer = setInterval(function () {
          attempts++;
          if (attempts > maxAttempts) {
            clearInterval(timer);
            reject(new Error('Playlist .ZIP download timed out. Try selecting fewer videos per batch.'));
            return;
          }

          var pollUrl =
            getAjaxUrl() +
            '?action=toolnest_yt_playlist_zip_progress&id=' +
            encodeURIComponent(jobId) +
            '&_t=' +
            Date.now();

          fetch(pollUrl)
            .then(function (r) {
              return r.json();
            })
            .then(function (res) {
              if (!res || !res.success || !res.data) {
                clearInterval(timer);
                reject(new Error((res && res.data && res.data.message) || 'Playlist ZIP conversion failed.'));
                return;
              }
              var d = res.data;
              var pct = d.percent || 10;
              btn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span>' +
                (d.currentTrack ? 'Track ' + d.currentTrack + '/' + d.totalTracks + ' (' + pct + '%)' : pct + '%');

              if (zipProgressBadge) zipProgressBadge.textContent = pct + '%';
              if (zipProgressBar) {
                zipProgressBar.style.width = pct + '%';
                zipProgressBar.textContent = pct + '%';
              }
              if (zipProgressStatus && d.statusText) {
                zipProgressStatus.textContent = d.statusText;
              }

              if (d.finished && d.downloadUrl) {
                clearInterval(timer);
                resolve(d.downloadUrl);
              }
            })
            .catch(function () {});
        }, 750);
      });
    }

    function fetchPlaylistInfo(rawUrl) {
      hideError();
      var trimmed = String(rawUrl || '').trim();
      if (!trimmed) {
        showError('Please paste a YouTube Playlist link first.');
        return;
      }

      if (loadingBox) loadingBox.classList.remove('d-none');
      if (resultsBox) resultsBox.classList.add('d-none');
      if (fetchBtn) fetchBtn.disabled = true;

      var body = new URLSearchParams();
      body.set('action', 'toolnest_yt_playlist_info');
      body.set('url', trimmed);

      fetch(getAjaxUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (res) {
          if (!res || !res.success || !res.data) {
            throw new Error((res && res.data && res.data.message) || 'Failed to load YouTube playlist.');
          }
          currentPlaylist = res.data;

          if (thumbImg) thumbImg.src = currentPlaylist.thumbnail || '';
          if (countBadge) countBadge.textContent = currentPlaylist.totalCount + ' Songs';
          if (channelEl) channelEl.textContent = currentPlaylist.channel || 'YouTube';
          if (durationBadge) durationBadge.textContent = currentPlaylist.totalDurationFormatted || '00:00';
          if (titleEl) titleEl.textContent = currentPlaylist.title || 'YouTube Playlist';

          renderTracksTable(currentPlaylist.items || []);
          updateZipTablesAndSummary();

          if (resultsBox) resultsBox.classList.remove('d-none');
        })
        .catch(function (err) {
          showError(err && err.message ? err.message : 'Unable to fetch playlist details.');
        })
        .finally(function () {
          if (loadingBox) loadingBox.classList.add('d-none');
          if (fetchBtn) fetchBtn.disabled = false;
        });
    }

    if (fetchBtn) {
      fetchBtn.addEventListener('click', function () {
        fetchPlaylistInfo(urlInput.value);
      });
    }

    urlInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        fetchPlaylistInfo(urlInput.value);
      }
    });

    if (sampleBtn) {
      sampleBtn.addEventListener('click', function () {
        urlInput.value = 'https://www.youtube.com/playlist?list=PLFgquLnL59alCl_2TQvOiD5Vgm1hCaGSI';
        fetchPlaylistInfo(urlInput.value);
      });
    }

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        urlInput.value = '';
        currentPlaylist = null;
        hideError();
        if (resultsBox) resultsBox.classList.add('d-none');
        if (zipProgressCard) zipProgressCard.classList.add('d-none');
      });
    }

    function setTrackSelection(limit) {
      if (!tracksTbody) return;
      var boxes = tracksTbody.querySelectorAll('.tn-ytpl-track-check');
      Array.prototype.forEach.call(boxes, function (cb, idx) {
        cb.checked = idx < limit;
      });
      updateZipTablesAndSummary();
    }

    if (selectAllBtn) {
      selectAllBtn.addEventListener('click', function () {
        setTrackSelection(9999);
      });
    }
    if (select10Btn) {
      select10Btn.addEventListener('click', function () {
        setTrackSelection(10);
      });
    }
    if (select25Btn) {
      select25Btn.addEventListener('click', function () {
        setTrackSelection(25);
      });
    }
    if (deselectAllBtn) {
      deselectAllBtn.addEventListener('click', function () {
        setTrackSelection(0);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initYoutubePlaylistDownloader);
  } else {
    initYoutubePlaylistDownloader();
  }
})();
