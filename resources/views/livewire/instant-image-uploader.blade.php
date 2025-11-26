<div wire:poll.2s="refreshProcessingStatus" x-data="uploadManager()" @keydown.escape="clearSelection" @paste.window="handlePaste($event)">
    <!-- Header with Statistics -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 500; color: #202124; display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 2rem;">⚡</span>
                Instant Upload
            </h1>
            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--secondary-color);">
                Upload files instantly with AI-powered analysis happening in the background
            </p>
        </div>

        @if (!empty($uploaded_files))
            <div style="display: flex; gap: 0.75rem;">
                <div style="padding: 0.75rem 1rem; background: #e6f4ea; border-radius: 8px; text-align: center;">
                    <div style="font-size: 0.75rem; color: #137333; font-weight: 500;">Completed</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #137333;">{{ $upload_statistics['success_count'] }}</div>
                </div>
                @if ($upload_statistics['failed_count'] > 0)
                    <div style="padding: 0.75rem 1rem; background: #fce8e6; border-radius: 8px; text-align: center;">
                        <div style="font-size: 0.75rem; color: #d93025; font-weight: 500;">Failed</div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #d93025;">{{ $upload_statistics['failed_count'] }}</div>
                    </div>
                @endif
                <div style="padding: 0.75rem 1rem; background: #e8f0fe; border-radius: 8px; text-align: center;">
                    <div style="font-size: 0.75rem; color: #1967d2; font-weight: 500;">Total Size</div>
                    <div style="font-size: 1rem; font-weight: 700; color: #1967d2;">{{ number_format($upload_statistics['total_size'] / 1048576, 1) }} MB</div>
                </div>
            </div>
        @endif
    </div>

    <!-- Validation Errors -->
    @if ($errors->any())
        <div class="alert alert-error" style="margin-bottom: 1.5rem;">
            <span class="material-symbols-outlined">error</span>
            <div>
                <strong>Validation errors</strong>
                <ul style="margin: 0.5rem 0 0 1.25rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- Upload Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <form wire:submit.prevent="uploadInstantly">
            <!-- Drag and Drop Upload Area -->
            <label
                for="files"
                class="file-upload-area"
                style="min-height: 280px; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0; border-radius: 0; border: none; border-bottom: 1px solid var(--border-color);"
                @dragover="$el.style.borderColor = 'var(--primary-color)'; $el.style.background = '#e8f0fe'"
                @dragleave="$el.style.borderColor = 'var(--border-color)'; $el.style.background = '#fafafa'"
                @drop="$el.style.borderColor = 'var(--border-color)'; $el.style.background = '#fafafa'"
            >
                <!-- Loading State -->
                <div wire:loading wire:target="files" style="text-align: center;">
                    <div class="spinner"></div>
                    <p style="color: var(--secondary-color); font-weight: 500;">Loading files...</p>
                </div>

                <!-- Upload Icon -->
                <div wire:loading.remove wire:target="files">
                    @if (!empty($files))
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #34a853, #137333); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <span class="material-symbols-outlined" style="font-size: 40px; color: white;">check</span>
                        </div>
                        <h3 style="font-size: 1.5rem; font-weight: 500; color: #202124; margin-bottom: 0.5rem;">
                            {{ count($files) }} {{ Str::plural('file', count($files)) }} ready to upload
                        </h3>
                        <p style="color: var(--secondary-color);">Click "Upload Instantly" to start processing</p>
                    @else
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #1a73e8, #1765cc); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <span class="material-symbols-outlined" style="font-size: 40px; color: white;">cloud_upload</span>
                        </div>
                        <h3 style="font-size: 1.5rem; font-weight: 500; color: #202124; margin-bottom: 0.5rem;">
                            Drop files here or click to browse
                        </h3>
                        <p style="color: var(--secondary-color); margin-bottom: 1rem;">
                            Supports images, videos, documents, audio, and more • Up to 500MB per file
                        </p>
                        <div style="display: flex; align-items: center; justify-content: center; gap: 2rem; color: var(--secondary-color); font-size: 0.875rem;">
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="material-symbols-outlined" style="font-size: 20px;">image</span>
                                Images
                            </span>
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="material-symbols-outlined" style="font-size: 20px;">videocam</span>
                                Videos
                            </span>
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="material-symbols-outlined" style="font-size: 20px;">description</span>
                                Documents
                            </span>
                        </div>
                    @endif
                </div>

                <!-- Keyboard Shortcut Hint -->
                <div wire:loading.remove wire:target="files" style="margin-top: 2rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #9aa0a6;">
                    <kbd style="padding: 0.25rem 0.5rem; background: #f1f3f4; border: 1px solid var(--border-color); border-radius: 4px; font-family: monospace;">Ctrl</kbd>
                    <span>+</span>
                    <kbd style="padding: 0.25rem 0.5rem; background: #f1f3f4; border: 1px solid var(--border-color); border-radius: 4px; font-family: monospace;">V</kbd>
                    <span>to paste from clipboard</span>
                </div>
            </label>

            <input type="file" id="files" wire:model="files" multiple style="display: none;">

            <!-- Action Buttons -->
            <div style="padding: 1.25rem 1.5rem; background: #f8f9fa; display: flex; gap: 1rem;">
                <button
                    type="submit"
                    class="btn btn-primary"
                    style="flex: 1; padding: 0.875rem 1.5rem; font-size: 1rem;"
                    wire:loading.attr="disabled"
                    wire:target="files,uploadInstantly"
                    @disabled($uploading || empty($files))
                >
                    <span wire:loading.remove wire:target="uploadInstantly" class="material-symbols-outlined" style="font-size: 20px;">bolt</span>
                    <span wire:loading wire:target="uploadInstantly" class="spinner" style="width: 20px; height: 20px; border-width: 2px; margin: 0;"></span>
                    <span wire:loading.remove wire:target="uploadInstantly">Upload Instantly</span>
                    <span wire:loading wire:target="uploadInstantly">Uploading...</span>
                </button>

                @if (!empty($uploaded_files))
                    <button
                        type="button"
                        wire:click="clearUploaded"
                        class="btn btn-secondary"
                        style="padding: 0.875rem 1.5rem;"
                        wire:loading.attr="disabled"
                    >
                        <span class="material-symbols-outlined" style="font-size: 20px;">refresh</span>
                        Clear All
                    </button>
                @endif
            </div>
        </form>

        <!-- Upload Progress Bar -->
        @if ($uploading && $total_files > 0)
            <div style="padding: 1rem 1.5rem; background: #e8f0fe; border-top: 1px solid #d2e3fc;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.875rem; font-weight: 500; color: #1967d2;">Uploading files...</span>
                    <span style="font-size: 1rem; font-weight: 700; color: #1967d2;">{{ $uploaded_count }} / {{ $total_files }}</span>
                </div>
                <div style="width: 100%; height: 8px; background: #c2d9fc; border-radius: 4px; overflow: hidden;">
                    <div style="height: 100%; background: linear-gradient(90deg, #1a73e8, #1967d2); border-radius: 4px; transition: width 0.3s ease; width: {{ $total_files > 0 ? ($uploaded_count / $total_files) * 100 : 0 }}%;"></div>
                </div>
            </div>
        @endif
    </div>

    <!-- Processing Status Section -->
    @if (!empty($uploaded_files))
        <!-- Success Banner -->
        <div class="alert alert-success" style="margin-top: 2rem; padding: 1.25rem;">
            <span class="material-symbols-outlined" style="font-size: 32px;">check_circle</span>
            <div style="flex: 1;">
                <strong style="font-size: 1rem;">{{ count($uploaded_files) }} {{ Str::plural('file', count($uploaded_files)) }} uploaded successfully!</strong>
                <p style="margin-top: 0.25rem; font-size: 0.875rem; opacity: 0.9;">
                    AI analysis is processing in the background. Real-time updates below. You can continue browsing.
                </p>
            </div>
            <a wire:navigate href="{{ route('processing-status') }}" class="btn btn-secondary" style="background: white;">
                <span class="material-symbols-outlined" style="font-size: 18px;">analytics</span>
                View All
            </a>
        </div>

        <!-- File Processing Grid -->
        <div style="margin-top: 1.5rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
            @foreach ($uploaded_files as $file)
                <div class="card" style="padding: 0; overflow: hidden;">
                    <!-- File Preview -->
                    <div style="position: relative; aspect-ratio: 16/10; background: #f1f3f4;">
                        @if ($file['url'])
                            <img src="{{ $file['url'] }}" alt="{{ $file['filename'] }}" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                        @else
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                <span class="material-symbols-outlined" style="font-size: 48px; color: #9aa0a6;">description</span>
                            </div>
                        @endif

                        <!-- Status Overlay -->
                        @if ($file['status'] !== 'completed')
                            <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                @if ($file['status'] === 'processing')
                                    <div class="spinner" style="border-top-color: #1a73e8; margin: 0;"></div>
                                    <p style="margin-top: 0.75rem; color: white; font-weight: 500; font-size: 0.875rem;">
                                        {{ ucfirst(str_replace('_', ' ', $file['processing_stage'] ?? 'Processing')) }}
                                    </p>
                                    @if (isset($file['elapsed']))
                                        <p style="margin-top: 0.25rem; color: rgba(255,255,255,0.7); font-size: 0.75rem;">{{ $file['elapsed'] }}</p>
                                    @endif
                                @elseif ($file['status'] === 'failed')
                                    <span class="material-symbols-outlined" style="font-size: 40px; color: #ea4335;">error</span>
                                    <p style="margin-top: 0.5rem; color: white; font-weight: 500; font-size: 0.875rem;">Failed</p>
                                @else
                                    <div class="spinner" style="border-top-color: #9aa0a6; margin: 0;"></div>
                                    <p style="margin-top: 0.75rem; color: white; font-weight: 500; font-size: 0.875rem;">Queued</p>
                                @endif
                            </div>
                        @endif

                        <!-- Status Badge -->
                        <div style="position: absolute; top: 0.75rem; right: 0.75rem;">
                            @if ($file['status'] === 'completed')
                                <span class="tag" style="background: #34a853; color: white; font-size: 0.7rem;">
                                    <span class="material-symbols-outlined" style="font-size: 14px; margin-right: 0.25rem;">check</span>
                                    Complete
                                </span>
                            @elseif ($file['status'] === 'processing')
                                <span class="tag" style="background: #1a73e8; color: white; font-size: 0.7rem;">
                                    <span style="width: 6px; height: 6px; background: white; border-radius: 50%; margin-right: 0.25rem; animation: pulse 1s infinite;"></span>
                                    Processing
                                </span>
                            @elseif ($file['status'] === 'failed')
                                <span class="tag" style="background: #ea4335; color: white; font-size: 0.7rem;">
                                    <span class="material-symbols-outlined" style="font-size: 14px; margin-right: 0.25rem;">close</span>
                                    Failed
                                </span>
                            @else
                                <span class="tag" style="background: #5f6368; color: white; font-size: 0.7rem;">
                                    <span class="material-symbols-outlined" style="font-size: 14px; margin-right: 0.25rem;">schedule</span>
                                    Pending
                                </span>
                            @endif
                        </div>

                        <!-- Remove Button -->
                        <button
                            wire:click="removeUploadedFile({{ $file['id'] }})"
                            style="position: absolute; top: 0.75rem; left: 0.75rem; width: 28px; height: 28px; background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;"
                            onmouseover="this.style.background='#ea4335'"
                            onmouseout="this.style.background='rgba(0,0,0,0.6)'"
                            title="Remove from list"
                        >
                            <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                        </button>
                    </div>

                    <!-- File Info -->
                    <div style="padding: 1rem;">
                        <h4 style="font-weight: 500; color: #202124; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $file['filename'] }}">
                            {{ $file['filename'] }}
                        </h4>
                        <div style="margin-top: 0.5rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: var(--secondary-color);">
                            <span style="text-transform: capitalize;">{{ str_replace('_', ' ', $file['media_type']) }}</span>
                            @if (isset($file['file_size_human']))
                                <span>{{ $file['file_size_human'] }}</span>
                            @endif
                        </div>

                        <!-- Error Message -->
                        @if ($file['status'] === 'failed' && isset($file['error']))
                            <div style="margin-top: 0.75rem; padding: 0.5rem 0.75rem; background: #fce8e6; border-radius: 4px; font-size: 0.75rem; color: #d93025;">
                                {{ $file['error'] }}
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem;">
                            @if ($file['status'] === 'failed' && $file['id'])
                                <button
                                    wire:click="retryFile({{ $file['id'] }})"
                                    class="btn btn-primary"
                                    style="flex: 1; padding: 0.5rem 0.75rem; font-size: 0.75rem;"
                                >
                                    <span class="material-symbols-outlined" style="font-size: 16px;">refresh</span>
                                    Retry
                                </button>
                            @endif
                            @if ($file['status'] === 'completed')
                                <a wire:navigate href="{{ route('gallery') }}" class="btn btn-primary" style="flex: 1; padding: 0.5rem 0.75rem; font-size: 0.75rem; text-decoration: none; justify-content: center;">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                                    View
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Info Panel -->
        <div class="alert alert-info" style="margin-top: 2rem; align-items: flex-start;">
            <span class="material-symbols-outlined" style="font-size: 24px;">info</span>
            <div style="flex: 1;">
                <strong>Background AI Processing Active</strong>
                <p style="margin-top: 0.5rem; font-size: 0.875rem;">Your files are being analyzed with advanced AI processing:</p>
                <ul style="margin-top: 0.5rem; margin-left: 1.25rem; font-size: 0.875rem;">
                    <li>Content analysis and intelligent captioning</li>
                    <li>Vector embeddings for semantic search</li>
                    <li>Comprehensive metadata extraction (EXIF, duration, properties)</li>
                    <li>AI-generated tags and detailed descriptions</li>
                    <li>Face detection and recognition (for images)</li>
                </ul>
            </div>
        </div>
    @endif

    <script>
    function uploadManager() {
        return {
            handlePaste(e) {
                const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                for (let item of items) {
                    if (item.kind === 'file') {
                        console.log('File pasted from clipboard');
                    }
                }
            },
            clearSelection() {
                console.log('Escape pressed');
            }
        }
    }

    document.addEventListener('livewire:initialized', () => {
        Livewire.on('upload-complete', (event) => {
            console.log(`${event.count} files uploaded and queued for processing`);
        });

        Livewire.on('file-retried', (event) => {
            console.log(`File ${event.fileId} retry initiated`);
        });
    });
    </script>

    <style>
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    </style>
</div>
