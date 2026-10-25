{{-- 🚀 GLOBAL BACKGROUND PACKING VIDEO UPLOADER & FLOATING WIDGET --}}
<style>
    .global-upload-widget {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 1055;
        width: 340px;
        max-width: calc(100vw - 32px);
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.18), 0 4px 6px -2px rgba(15, 23, 42, 0.05);
        font-family: inherit;
        overflow: hidden;
        transition: transform 0.25s ease, opacity 0.25s ease, bottom 0.25s ease;
    }
    .global-upload-widget.minimized {
        width: auto;
        min-width: 200px;
    }
    .global-upload-widget.minimized .upload-widget-body {
        display: none !important;
    }
    .global-upload-header {
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #0f172a;
        color: #f8fafc;
        border-bottom: 1px solid #1e293b;
        cursor: pointer;
    }
    .global-upload-header .upload-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.3px;
        display: flex;
        align-items: center;
    }
    .global-upload-header .live-pulse-dot {
        width: 8px;
        height: 8px;
        background-color: #38bdf8;
        border-radius: 50%;
        margin-right: 8px;
        box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7);
        animation: globalPulse 1.6s infinite cubic-bezier(0.66, 0, 0, 1);
        flex-shrink: 0;
    }
    .global-upload-header .live-pulse-dot.success {
        background-color: #22c55e;
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
    }
    .global-upload-header .live-pulse-dot.failed {
        background-color: #ef4444;
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    }
    @keyframes globalPulse {
        to { box-shadow: 0 0 0 10px rgba(56, 189, 248, 0); }
    }
    .upload-widget-body {
        padding: 14px;
    }
    .global-upload-progress {
        height: 10px;
        border-radius: 6px;
        background-color: #f1f5f9;
        overflow: hidden;
    }
    .global-upload-progress .progress-bar {
        background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%);
        transition: width 0.3s ease;
    }

    /* 📱 RESPONSIVE MOBILE STYLING: Di atas navbar bawah HP agar tidak bertabrakan */
    @media (max-width: 767.98px) {
        .global-upload-widget {
            bottom: calc(68px + env(safe-area-inset-bottom, 0px)) !important;
            right: 12px;
            left: 12px;
            width: auto;
            max-width: calc(100vw - 24px);
            border-radius: 14px;
        }
        .global-upload-widget.minimized {
            left: auto;
            right: 12px;
            bottom: calc(66px + env(safe-area-inset-bottom, 0px)) !important;
            width: auto;
            min-width: 190px;
            max-width: calc(100vw - 24px);
            border-radius: 20px;
        }
    }
</style>

<div id="globalUploadWidget" class="global-upload-widget shadow-lg" style="display: none;">
    <div class="global-upload-header" onclick="GlobalPackingUploader.toggleMinimize()">
        <div class="upload-title text-truncate mr-2">
            <span class="live-pulse-dot" id="globalUploadPulse"></span>
            <span id="globalUploadHeaderTitle">Upload Video Packing</span>
            <span class="badge badge-info ml-2 px-1.5 py-0.5 text-xs font-weight-bold" id="globalUploadCountBadge" style="display: none;">1</span>
        </div>
        <div class="d-flex align-items-center" onclick="event.stopPropagation()">
            <button type="button" class="btn btn-link text-white-50 p-0 mr-2" id="globalUploadMinimizeBtn" onclick="GlobalPackingUploader.toggleMinimize()" title="Perkecil / Perbesar">
                <i class="fas fa-minus text-xs"></i>
            </button>
            <button type="button" class="btn btn-link text-white-50 p-0" id="globalUploadCloseBtn" onclick="GlobalPackingUploader.closeWidget()" title="Tutup" style="display: none;">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    </div>
    
    <div class="upload-widget-body" id="globalUploadBody">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div class="text-truncate mr-2">
                <strong class="text-dark text-sm" id="globalUploadOrderSn">SPXID...</strong>
                <span class="badge badge-light border text-muted ml-1" id="globalUploadOrderTag" style="font-size: 10px;">-</span>
            </div>
            <span class="badge badge-primary font-weight-bold text-xs px-2 py-1" id="globalUploadPercentBadge">0%</span>
        </div>

        <div class="progress global-upload-progress mb-2">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="globalUploadProgressBar" style="width: 0%;"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center text-xs text-muted">
            <span id="globalUploadSizeText"><i class="fas fa-file-video mr-1 text-primary"></i> 0.0 MB / 0.0 MB</span>
            <span id="globalUploadDurationText"><i class="fas fa-clock mr-1"></i> 0s</span>
        </div>

        <!-- Failure Action View -->
        <div id="globalUploadFailedView" class="mt-2 pt-2 border-top text-xs" style="display: none;">
            <div class="text-danger font-weight-bold mb-2" id="globalUploadErrorMsg">
                <i class="fas fa-exclamation-triangle mr-1"></i> Upload terputus.
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-xs btn-outline-danger" onclick="GlobalPackingUploader.cancelCurrentJob()" title="Hapus dari antrean agar hemat memori HP">
                    <i class="fas fa-trash-alt mr-1"></i> Hapus Video
                </button>
                <button type="button" class="btn btn-xs btn-primary font-weight-bold" onclick="GlobalPackingUploader.retryCurrentJob()">
                    <i class="fas fa-redo mr-1"></i> Coba Lagi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // =========================================================================
    // 🌐 GLOBAL PACKING VIDEO UPLOADER (IndexedDB Persistent Across All Pages)
    // =========================================================================
    const GlobalPackingUploader = {
        dbName: 'MedinaPackingUploadDB',
        dbVersion: 1,
        storeName: 'upload_jobs',
        activeXhr: null,
        currentJobId: null,
        currentJob: null,
        isUploading: false,
        channel: (typeof BroadcastChannel !== 'undefined') ? new BroadcastChannel('packing_upload_channel') : null,
        heartbeatTimer: null,
        tabId: 'tab_' + Math.random().toString(36).substr(2, 9),

        // 1. Initialize IndexedDB and begin queue processing on ANY page
        init() {
            this.setupBroadcast();
            this.setupStorageListener();

            // Jalankan cleanup kedaluwarsa & proses antrean
            setTimeout(async () => {
                await this.purgeExpiredJobs();
                this.processQueue();
            }, 500);

            // Cek periodik setiap 5 detik untuk multi-tab handoff & pembersihan memori
            setInterval(() => {
                if (!this.isUploading) {
                    this.processQueue();
                }
            }, 5000);

            // Pembersihan memori HP setiap 3 menit
            setInterval(() => {
                this.purgeExpiredJobs();
            }, 180000);
        },

        // Open IndexedDB connection
        openDB() {
            return new Promise((resolve, reject) => {
                if (!window.indexedDB) {
                    console.warn('Browser tidak mendukung IndexedDB');
                    return resolve(null);
                }
                const req = indexedDB.open(this.dbName, this.dbVersion);
                req.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains(this.storeName)) {
                        db.createObjectStore(this.storeName, { keyPath: 'id' });
                    }
                };
                req.onsuccess = (e) => resolve(e.target.result);
                req.onerror = (e) => {
                    console.warn('IndexedDB open error:', e);
                    resolve(null);
                };
            });
        },

        // Save a new packing job with binary Blob into IndexedDB
        async enqueue(job) {
            const db = await this.openDB();
            if (!db) {
                console.error('Cannot enqueue: IndexedDB tidak tersedia');
                return;
            }

            const record = {
                id: job.id || ('up_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6)),
                order_sn: job.order_sn,
                tracking_number: job.tracking_number || '',
                packer_name: job.packer_name || 'Staff Packing',
                duration: job.duration || 0,
                mode: job.mode || 'packing',
                items_checked: job.items_checked || [],
                blob: job.blob,
                mime: job.mime || 'video/webm',
                ext: job.ext || 'webm',
                size: job.blob ? job.blob.size : 0,
                percent: 0,
                loadedMB: '0.0',
                totalMB: job.blob ? (job.blob.size / (1024 * 1024)).toFixed(1) : '0.0',
                status: 'pending',
                retries: 0,
                errorMessage: null,
                created_at: Date.now(),
                updated_at: Date.now()
            };

            await new Promise((resolve) => {
                try {
                    const tx = db.transaction([this.storeName], 'readwrite');
                    const store = tx.objectStore(this.storeName);
                    store.put(record);
                    tx.oncomplete = () => resolve();
                    tx.onerror = () => resolve();
                } catch (e) {
                    console.warn('IndexedDB put error:', e);
                    resolve();
                }
            });

            this.syncLocalStorageMetadata();
            this.processQueue();
        },

        // Get all stored jobs from IndexedDB
        async getAllJobs() {
            const db = await this.openDB();
            if (!db) return [];
            return new Promise((resolve) => {
                try {
                    const tx = db.transaction([this.storeName], 'readonly');
                    const store = tx.objectStore(this.storeName);
                    const req = store.getAll();
                    req.onsuccess = () => resolve(req.result || []);
                    req.onerror = () => resolve([]);
                } catch (e) {
                    resolve([]);
                }
            });
        },

        // Update single job record in IndexedDB
        async updateJob(job) {
            const db = await this.openDB();
            if (!db) return;
            return new Promise((resolve) => {
                try {
                    job.updated_at = Date.now();
                    const tx = db.transaction([this.storeName], 'readwrite');
                    tx.objectStore(this.storeName).put(job);
                    tx.oncomplete = () => resolve();
                    tx.onerror = () => resolve();
                } catch (e) {
                    resolve();
                }
            });
        },

        // 🧹 HAPUS JOB & BLOB DARI INDEXEDDB SETELAH SUKSES (Menghemat Memori HP)
        async deleteJob(id) {
            const db = await this.openDB();
            if (!db) return;
            return new Promise((resolve) => {
                try {
                    const tx = db.transaction([this.storeName], 'readwrite');
                    tx.objectStore(this.storeName).delete(id);
                    tx.oncomplete = () => resolve();
                    tx.onerror = () => resolve();
                } catch (e) {
                    resolve();
                }
            });
        },

        // 🧹 KEBIJAKAN PEMBERSIHAN OTOMATIS: Hapus job selesai atau gagal kedaluwarsa (> 2 jam)
        async purgeExpiredJobs() {
            try {
                const jobs = await this.getAllJobs();
                const now = Date.now();
                for (const j of jobs) {
                    // Hapus jika status completed masih tertinggal
                    if (j.status === 'completed') {
                        await this.deleteJob(j.id);
                        continue;
                    }
                    // Hapus job gagal yang sudah terbengkalai lebih dari 2 jam (menyelamatkan memori HP)
                    if (j.status === 'failed' && (now - (j.updated_at || j.created_at || 0)) > 7200000) {
                        console.log('Purging abandoned failed video job to free phone storage:', j.order_sn);
                        await this.deleteJob(j.id);
                        continue;
                    }
                    // Hapus job tanpa blob (corrupt)
                    if (!j.blob || (j.blob && j.blob.size === 0)) {
                        await this.deleteJob(j.id);
                    }
                }
                this.syncLocalStorageMetadata();
            } catch (e) {}
        },

        // Check queue and start upload if idle
        async processQueue() {
            if (this.isUploading) return;

            // Simple lock across browser tabs: only 1 tab uploads at a time
            const now = Date.now();
            const activeTabInfo = JSON.parse(localStorage.getItem('medina_upload_lock') || '{}');
            if (activeTabInfo.tabId && activeTabInfo.tabId !== this.tabId && (now - (activeTabInfo.time || 0)) < 4000) {
                // Tab lain sedang aktif mengunggah; tab ini cukup menampilkan progress
                return;
            }

            const jobs = await this.getAllJobs();
            const activeJobs = jobs.filter(j => j.status !== 'completed');

            if (activeJobs.length === 0) {
                this.updateUIIdle();
                return;
            }

            // Pilih job pending atau job gagal yang belum mencapai batas retry
            const nextJob = activeJobs.find(j => j.status === 'pending') 
                || activeJobs.find(j => j.status === 'uploading') 
                || activeJobs.find(j => j.status === 'failed' && (j.retries || 0) < 3)
                || activeJobs[0];

            if (nextJob.status === 'failed' && (nextJob.retries || 0) >= 3) {
                this.updateUIFailed(nextJob);
                return;
            }

            this.startUpload(nextJob);
        },

        // Perform the upload via XMLHttpRequest (TETAP BERJALAN KETIKA BERPINDAH MENU!)
        async startUpload(job) {
            this.isUploading = true;
            this.currentJobId = job.id;
            this.currentJob = job;
            job.status = 'uploading';
            await this.updateJob(job);
            this.claimLock();

            this.updateUIProgress(job);

            // 1. Daftarkan status 'in_progress' ke server (tidak membatalkan upload jika file baru tersedia)
            try {
                await this.callInitUpload(job);
            } catch (err) {
                console.warn('initUpload register notice:', err);
            }

            // 2. Pastikan file blob video valid sebelum mengirim
            if (!job.blob || job.blob.size === 0) {
                console.warn('Job blob is empty, removing job:', job.id);
                await this.deleteJob(job.id);
                this.releaseLock();
                this.isUploading = false;
                this.currentJobId = null;
                this.currentJob = null;
                this.processQueue();
                return;
            }

            // 3. Kirim data video utuh via XHR
            const formData = new FormData();
            formData.append('video', job.blob, `pack_${job.order_sn}.${job.ext || 'webm'}`);
            formData.append('order_sn', job.order_sn);
            formData.append('tracking_number', job.tracking_number || '');
            formData.append('duration', job.duration || 0);
            formData.append('packer_name', job.packer_name || 'Staff Packing');
            formData.append('items_checked', JSON.stringify(job.items_checked || []));
            formData.append('mode', job.mode || 'packing');

            const xhr = new XMLHttpRequest();
            this.activeXhr = xhr;

            xhr.open('POST', "{{ route('packing.upload') }}", true);
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.timeout = 240000; // 4 menit timeout untuk file video besar

            xhr.upload.onprogress = (e) => {
                this.claimLock();
                if (e.lengthComputable && e.total > 0) {
                    job.percent = Math.min(99, Math.round((e.loaded / e.total) * 100));
                    job.loadedMB = (e.loaded / (1024 * 1024)).toFixed(1);
                    job.totalMB = (e.total / (1024 * 1024)).toFixed(1);
                }
                this.updateUIProgress(job);
                this.broadcastProgress(job);
                this.syncLocalStorageMetadata();
            };

            xhr.onload = async () => {
                let res;
                try {
                    res = JSON.parse(xhr.responseText);
                } catch (err) {
                    res = { status: 'error', message: 'Respon server tidak valid' };
                }

                // ⚠️ HANYA HAPUS VIDEO SETELAH SERVER BENAR-BENAR MENGONFIRMASI SUKSES!
                if (xhr.status >= 200 && xhr.status < 300 && res.status === 'success') {
                    const videoUrl = res.video_url || (res.data && res.data.video_url) || null;
                    job.percent = 100;
                    job.status = 'completed';
                    job.video_url = videoUrl;

                    // 🧹 Hapus Blob video sumber dari IndexedDB secara permanen
                    await this.deleteJob(job.id);
                    job.blob = null; // Bebaskan memori RAM browser

                    this.releaseLock();
                    this.isUploading = false;
                    this.activeXhr = null;
                    this.currentJobId = null;
                    this.currentJob = null;

                    // Simpan data riwayat ringkas di sessionStorage (hanya string metadata, bukan binary)
                    this.saveRecentCompleted(job);

                    this.updateUISuccess(job);
                    this.broadcastCompleted(job);
                    this.syncLocalStorageMetadata();

                    // Notifikasi Toast
                    if (typeof Swal !== 'undefined') {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'bottom-end',
                            showConfirmButton: false,
                            timer: 4500,
                            timerProgressBar: true
                        });
                        Toast.fire({
                            icon: 'success',
                            title: `✅ Video packing [${job.tracking_number || job.order_sn}] tersimpan di server!`
                        });
                    }

                    // Lanjutkan antrean berikutnya jika ada
                    setTimeout(() => {
                        this.processQueue();
                    }, 800);
                } else {
                    this.handleUploadError(job, res.message || 'Gagal menyimpan video ke server.');
                }
            };

            xhr.onerror = () => {
                this.handleUploadError(job, 'Koneksi terputus saat mengunggah video.');
            };

            xhr.ontimeout = () => {
                this.handleUploadError(job, 'Waktu upload melebihi batas (timeout).');
            };

            xhr.send(formData);
        },

        // Call init-upload so server knows upload started & checks existing completion
        // Call init-upload so server knows upload started & marks in_progress
        async callInitUpload(job) {
            const res = await fetch("{{ route('packing.initUpload') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    order_sn: job.order_sn,
                    tracking_number: job.tracking_number,
                    packer_name: job.packer_name,
                    duration: job.duration,
                    mode: job.mode,
                    force_new: true
                })
            });
            return await res.json();
        },

        // ⚠️ TANGANI KEGAGALAN UPLOAD: Jangan hapus video sumber, pertahankan data retry
        async handleUploadError(job, errMsg) {
            this.releaseLock();
            this.isUploading = false;
            this.activeXhr = null;
            job.retries = (job.retries || 0) + 1;
            job.errorMessage = errMsg;

            if (job.retries <= 2) {
                console.log(`Auto-retrying upload ${job.order_sn} (Percobaan ke-${job.retries})...`);
                await this.updateJob(job);
                this.updateUIProgress(job);
                const headerTitle = document.getElementById('globalUploadHeaderTitle');
                if (headerTitle) headerTitle.innerText = `Mencoba Ulang (${job.retries}/3)...`;
                setTimeout(() => {
                    this.processQueue();
                }, 3000);
            } else {
                job.status = 'failed';
                await this.updateJob(job);
                this.updateUIFailed(job);
                this.syncLocalStorageMetadata();
            }
        },

        // Manual retry from widget button
        async retryCurrentJob() {
            if (!this.currentJobId) {
                const jobs = await this.getAllJobs();
                const failed = jobs.find(j => j.status === 'failed');
                if (failed) {
                    failed.status = 'pending';
                    failed.retries = 0;
                    failed.errorMessage = null;
                    await this.updateJob(failed);
                    this.processQueue();
                }
                return;
            }
            const jobs = await this.getAllJobs();
            const job = jobs.find(j => j.id === this.currentJobId);
            if (job) {
                job.status = 'pending';
                job.retries = 0;
                job.errorMessage = null;
                await this.updateJob(job);
                this.processQueue();
            }
        },

        // Batal & Hapus video secara eksplisit atas kehendak pengguna
        async cancelCurrentJob() {
            if (this.activeXhr) {
                try { this.activeXhr.abort(); } catch (e) {}
            }
            this.isUploading = false;
            this.activeXhr = null;
            if (this.currentJobId) {
                await this.deleteJob(this.currentJobId);
                this.currentJobId = null;
                this.currentJob = null;
            }
            this.releaseLock();
            this.syncLocalStorageMetadata();
            this.updateUIIdle();
            this.processQueue();
        },

        // Simpan data riwayat ringkas di sessionStorage (hanya string kecil, tanpa blob)
        saveRecentCompleted(job) {
            try {
                let completedList = JSON.parse(sessionStorage.getItem('packing_recent_completed') || '[]');
                completedList.unshift({
                    order_sn: job.order_sn,
                    tracking_number: job.tracking_number,
                    video_url: job.video_url,
                    packer_name: job.packer_name,
                    duration: job.duration ? `${job.duration}s` : '0s',
                    completed_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
                });
                if (completedList.length > 25) completedList = completedList.slice(0, 25);
                sessionStorage.setItem('packing_recent_completed', JSON.stringify(completedList));
            } catch (e) {}
        },

        // Query active job by tracking number or order sn
        getActiveJobFor(key) {
            if (!key) return null;
            if (this.currentJob && (this.currentJob.tracking_number === key || this.currentJob.order_sn === key)) {
                return this.currentJob;
            }
            return null;
        },

        // UI Helpers
        updateUIProgress(job) {
            const widget = document.getElementById('globalUploadWidget');
            if (!widget) return;
            widget.style.display = 'block';

            const pulse = document.getElementById('globalUploadPulse');
            if (pulse) pulse.className = 'live-pulse-dot';

            const headerTitle = document.getElementById('globalUploadHeaderTitle');
            if (headerTitle) {
                headerTitle.innerText = widget.classList.contains('minimized') 
                    ? `[${job.percent || 0}%] ${job.tracking_number || job.order_sn}`
                    : 'Mengunggah Video Packing';
            }

            const orderSnEl = document.getElementById('globalUploadOrderSn');
            if (orderSnEl) orderSnEl.innerText = job.tracking_number || job.order_sn;

            const orderTagEl = document.getElementById('globalUploadOrderTag');
            if (orderTagEl) {
                orderTagEl.innerText = job.tracking_number ? job.order_sn : (job.mode === 'return_unboxing' ? 'RETUR' : 'PACKING');
            }

            const percentBadge = document.getElementById('globalUploadPercentBadge');
            if (percentBadge) {
                percentBadge.className = 'badge badge-primary font-weight-bold text-xs px-2 py-1';
                percentBadge.innerText = `${job.percent || 0}%`;
            }

            const pBar = document.getElementById('globalUploadProgressBar');
            if (pBar) {
                pBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
                pBar.style.width = `${job.percent || 0}%`;
            }

            const sizeText = document.getElementById('globalUploadSizeText');
            if (sizeText) {
                sizeText.innerHTML = `<i class="fas fa-file-video mr-1 text-primary"></i> ${job.loadedMB || '0.0'} MB / ${job.totalMB || '0.0'} MB`;
            }

            const durText = document.getElementById('globalUploadDurationText');
            if (durText) {
                durText.innerHTML = `<i class="fas fa-clock mr-1"></i> ${job.duration || 0}s`;
            }

            const failedView = document.getElementById('globalUploadFailedView');
            if (failedView) failedView.style.display = 'none';

            const closeBtn = document.getElementById('globalUploadCloseBtn');
            if (closeBtn) closeBtn.style.display = 'none';
        },

        updateUISuccess(job) {
            const widget = document.getElementById('globalUploadWidget');
            if (!widget) return;

            const pulse = document.getElementById('globalUploadPulse');
            if (pulse) pulse.className = 'live-pulse-dot success';

            const headerTitle = document.getElementById('globalUploadHeaderTitle');
            if (headerTitle) headerTitle.innerText = 'Video Berhasil Tersimpan';

            const percentBadge = document.getElementById('globalUploadPercentBadge');
            if (percentBadge) {
                percentBadge.className = 'badge badge-success font-weight-bold text-xs px-2 py-1';
                percentBadge.innerHTML = '<i class="fas fa-check mr-1"></i> 100%';
            }

            const pBar = document.getElementById('globalUploadProgressBar');
            if (pBar) {
                pBar.className = 'progress-bar bg-success';
                pBar.style.width = '100%';
            }

            const closeBtn = document.getElementById('globalUploadCloseBtn');
            if (closeBtn) closeBtn.style.display = 'inline-block';

            // Auto-hide widget setelah 4 detik jika antrean sudah bersih
            setTimeout(async () => {
                const remaining = await this.getAllJobs();
                if (remaining.length === 0) {
                    widget.style.display = 'none';
                }
            }, 4000);
        },

        updateUIFailed(job) {
            const widget = document.getElementById('globalUploadWidget');
            if (!widget) return;
            widget.style.display = 'block';

            const pulse = document.getElementById('globalUploadPulse');
            if (pulse) pulse.className = 'live-pulse-dot failed';

            const headerTitle = document.getElementById('globalUploadHeaderTitle');
            if (headerTitle) headerTitle.innerText = 'Upload Video Terputus';

            const percentBadge = document.getElementById('globalUploadPercentBadge');
            if (percentBadge) {
                percentBadge.className = 'badge badge-danger font-weight-bold text-xs px-2 py-1';
                percentBadge.innerText = 'Gagal';
            }

            const pBar = document.getElementById('globalUploadProgressBar');
            if (pBar) {
                pBar.className = 'progress-bar bg-danger';
            }

            const failedView = document.getElementById('globalUploadFailedView');
            if (failedView) {
                failedView.style.display = 'block';
                const msg = document.getElementById('globalUploadErrorMsg');
                if (msg) msg.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> ${job.errorMessage || 'Koneksi terputus. Klik Coba Lagi untuk melanjutkan.'}`;
            }

            const closeBtn = document.getElementById('globalUploadCloseBtn');
            if (closeBtn) closeBtn.style.display = 'inline-block';
        },

        async updateUIIdle() {
            const widget = document.getElementById('globalUploadWidget');
            if (widget && !this.isUploading) {
                const jobs = await this.getAllJobs();
                const active = jobs.filter(j => j.status !== 'completed');
                if (active.length === 0) {
                    widget.style.display = 'none';
                }
            }
        },

        toggleMinimize() {
            const widget = document.getElementById('globalUploadWidget');
            if (!widget) return;
            widget.classList.toggle('minimized');
            const icon = document.querySelector('#globalUploadMinimizeBtn i');
            if (icon) {
                if (widget.classList.contains('minimized')) {
                    icon.className = 'fas fa-chevron-up text-xs';
                } else {
                    icon.className = 'fas fa-minus text-xs';
                }
            }
            if (this.currentJob && this.isUploading) {
                const headerTitle = document.getElementById('globalUploadHeaderTitle');
                if (headerTitle) {
                    headerTitle.innerText = widget.classList.contains('minimized') 
                        ? `[${this.currentJob.percent || 0}%] ${this.currentJob.tracking_number || this.currentJob.order_sn}`
                        : 'Mengunggah Video Packing';
                }
            }
        },

        closeWidget() {
            const widget = document.getElementById('globalUploadWidget');
            if (widget) widget.style.display = 'none';
        },

        // Broadcast & Multi-tab coordination
        claimLock() {
            try {
                localStorage.setItem('medina_upload_lock', JSON.stringify({
                    tabId: this.tabId,
                    time: Date.now()
                }));
            } catch (e) {}
        },

        releaseLock() {
            try {
                const info = JSON.parse(localStorage.getItem('medina_upload_lock') || '{}');
                if (info.tabId === this.tabId) {
                    localStorage.removeItem('medina_upload_lock');
                }
            } catch (e) {}
        },

        broadcastProgress(job) {
            const payload = {
                type: 'progress',
                id: job.id,
                order_sn: job.order_sn,
                tracking_number: job.tracking_number,
                progress: job.percent,
                percent: job.percent,
                loadedMB: job.loadedMB,
                totalMB: job.totalMB,
                duration: job.duration,
                status: 'uploading'
            };
            if (this.channel) {
                try { this.channel.postMessage(payload); } catch (e) {}
            }
        },

        broadcastCompleted(job) {
            const payload = {
                type: 'completed',
                id: job.id,
                order_sn: job.order_sn,
                tracking_number: job.tracking_number,
                progress: 100,
                percent: 100,
                status: 'completed',
                video_url: job.video_url,
                packer_name: job.packer_name,
                duration: `${job.duration}s`
            };
            if (this.channel) {
                try { this.channel.postMessage(payload); } catch (e) {}
            }
        },

        setupBroadcast() {
            if (!this.channel) return;
            this.channel.onmessage = (e) => {
                const data = e.data;
                if (!data) return;
                if (data.type === 'progress' && !this.isUploading) {
                    this.updateUIProgress(data);
                } else if (data.type === 'completed' && !this.isUploading) {
                    this.updateUISuccess(data);
                }
            };
        },

        setupStorageListener() {
            window.addEventListener('storage', (e) => {
                if (e.key === 'packing_upload_queue' && !this.isUploading) {
                    this.processQueue();
                }
            });
        },

        async syncLocalStorageMetadata() {
            try {
                const jobs = await this.getAllJobs();
                const metadata = jobs.map(j => ({
                    id: j.id,
                    order_sn: j.order_sn,
                    tracking_number: j.tracking_number,
                    status: j.status,
                    percent: j.percent,
                    loadedMB: j.loadedMB,
                    totalMB: j.totalMB,
                    duration: j.duration,
                    timestamp: j.created_at
                }));
                localStorage.setItem('packing_upload_queue', JSON.stringify(metadata));
            } catch (e) {}
        }
    };

    window.GlobalPackingUploader = GlobalPackingUploader;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            GlobalPackingUploader.init();
        });
    } else {
        GlobalPackingUploader.init();
    }
</script>
