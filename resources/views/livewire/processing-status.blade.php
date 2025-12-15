<div wire:poll.5s="loadStatus">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 500; color: #202124; margin-bottom: 0.5rem;">
                Background Processing Status
            </h1>
            <p style="font-size: 0.875rem; color: var(--secondary-color);">
                Auto-refreshes every 5 seconds • Click Refresh for instant update
            </p>
        </div>

        <button wire:click="loadStatus" class="btn btn-secondary">
            <span class="material-symbols-outlined" style="font-size: 1.125rem;">refresh</span>
            Refresh
        </button>
    </div>

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <!-- Pending -->
        <div class="card" wire:click="toggleSection('pending')"
             style="text-align: center; cursor: pointer; transition: all 0.2s; {{ $showPending ? 'background: #fef7e0; border: 2px solid #f9ab00; box-shadow: 0 4px 8px rgba(249,171,0,0.2);' : '' }}"
             onmouseover="if (!{{ $showPending ? 'true' : 'false' }}) { this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)'; }"
             onmouseout="if (!{{ $showPending ? 'true' : 'false' }}) { this.style.transform='translateY(0)'; this.style.boxShadow=''; }">
            <div style="font-size: 2rem; font-weight: 700; color: #f9ab00; margin-bottom: 0.5rem;">
                {{ $stats['pending'] }}
            </div>
            <div style="font-size: 0.875rem; color: {{ $showPending ? '#f9ab00' : 'var(--secondary-color)' }}; font-weight: {{ $showPending ? '600' : '400' }};">
                ⏳ Pending {{ $showPending ? '▼' : '▶' }}
            </div>
        </div>

        <!-- Processing -->
        <div class="card" wire:click="toggleSection('processing')"
             style="text-align: center; cursor: pointer; transition: all 0.2s; {{ $showProcessing ? 'background: #e8f0fe; border: 2px solid var(--primary-color); box-shadow: 0 4px 8px rgba(66,133,244,0.2);' : '' }}"
             onmouseover="if (!{{ $showProcessing ? 'true' : 'false' }}) { this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)'; }"
             onmouseout="if (!{{ $showProcessing ? 'true' : 'false' }}) { this.style.transform='translateY(0)'; this.style.boxShadow=''; }">
            <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;">
                {{ $stats['processing'] }}
            </div>
            <div style="font-size: 0.875rem; color: {{ $showProcessing ? 'var(--primary-color)' : 'var(--secondary-color)' }}; font-weight: {{ $showProcessing ? '600' : '400' }};">
                ⚙️ Processing {{ $showProcessing ? '▼' : '▶' }}
            </div>
        </div>

        <!-- Completed -->
        <div class="card" wire:click="toggleSection('completed')"
             style="text-align: center; cursor: pointer; transition: all 0.2s; {{ $showCompleted ? 'background: #e6f4ea; border: 2px solid #137333; box-shadow: 0 4px 8px rgba(19,115,51,0.2);' : '' }}"
             onmouseover="if (!{{ $showCompleted ? 'true' : 'false' }}) { this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)'; }"
             onmouseout="if (!{{ $showCompleted ? 'true' : 'false' }}) { this.style.transform='translateY(0)'; this.style.boxShadow=''; }">
            <div style="font-size: 2rem; font-weight: 700; color: #137333; margin-bottom: 0.5rem;">
                {{ $stats['completed'] }}
            </div>
            <div style="font-size: 0.875rem; color: {{ $showCompleted ? '#137333' : 'var(--secondary-color)' }}; font-weight: {{ $showCompleted ? '600' : '400' }};">
                ✅ Completed {{ $showCompleted ? '▼' : '▶' }}
            </div>
        </div>

        <!-- Failed -->
        <div class="card" wire:click="toggleSection('failed')"
             style="text-align: center; cursor: pointer; transition: all 0.2s; {{ $showFailed ? 'background: #fce8e6; border: 2px solid #d93025; box-shadow: 0 4px 8px rgba(217,48,37,0.2);' : '' }}"
             onmouseover="if (!{{ $showFailed ? 'true' : 'false' }}) { this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)'; }"
             onmouseout="if (!{{ $showFailed ? 'true' : 'false' }}) { this.style.transform='translateY(0)'; this.style.boxShadow=''; }">
            <div style="font-size: 2rem; font-weight: 700; color: #d93025; margin-bottom: 0.5rem;">
                {{ $stats['failed'] }}
            </div>
            <div style="font-size: 0.875rem; color: {{ $showFailed ? '#d93025' : 'var(--secondary-color)' }}; font-weight: {{ $showFailed ? '600' : '400' }};">
                ❌ Failed {{ $showFailed ? '▼' : '▶' }}
            </div>
        </div>
    </div>

    <!-- Pending Files -->
    @if (!empty($pending_files) && $showPending)
        <div class="card" style="margin-bottom: 2rem;" x-data x-show="true" x-transition>
            <h2 style="font-size: 1.25rem; font-weight: 500; color: #202124; margin-bottom: 1rem;">
                ⏳ Pending ({{ count($pending_files) }})
            </h2>

            @if (session()->has('message'))
                <div style="padding: 1rem; background: #e6f4ea; border-left: 4px solid #137333; border-radius: 4px; margin-bottom: 1rem;">
                    <span style="color: #137333;">✓</span> {{ session('message') }}
                </div>
            @endif

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach ($pending_files as $file)
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--hover-bg); border-radius: 8px;">
                        <!-- Thumbnail -->
                        <img src="{{ $file['url'] }}" alt="{{ $file['filename'] }}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">

                        <!-- File Info -->
                        <div style="flex: 1;">
                            <div style="font-weight: 500; color: #202124; margin-bottom: 0.25rem;">
                                {{ $file['filename'] }}
                            </div>

                            <!-- File Type Badge -->
                            <div style="display: inline-block; background: #f9ab00; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">
                                {{ $file['media_type'] }}
                            </div>

                            <!-- Upload Time -->
                            <div style="font-size: 0.875rem; color: var(--secondary-color);">
                                Uploaded: {{ $file['created_at'] }}
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div style="display: flex; gap: 0.5rem;">
                            <!-- Download Button -->
                            <button wire:click="downloadFile({{ $file['id'] }})" class="btn btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;" title="Download file">
                                <span class="material-symbols-outlined" style="font-size: 1rem;">download</span>
                            </button>

                            <!-- Reanalyze Button -->
                            <button wire:click="reanalyze({{ $file['id'] }})" class="btn btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;" title="Process now">
                                <span class="material-symbols-outlined" style="font-size: 1rem;">refresh</span>
                            </button>

                            <!-- Cancel Button -->
                            <button wire:click="cancelPending({{ $file['id'] }})" class="btn btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem; background: #fce8e6; color: #d93025;" title="Cancel and remove" onclick="return confirm('Are you sure you want to cancel and delete this file?')">
                                <span class="material-symbols-outlined" style="font-size: 1rem;">close</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Currently Processing -->
    @if (!empty($processing_files) && $showProcessing)
        <div class="card" style="margin-bottom: 2rem;" x-data x-show="true" x-transition>
            <h2 style="font-size: 1.25rem; font-weight: 500; color: #202124; margin-bottom: 1rem;">
                ⚙️ Currently Processing ({{ count($processing_files) }})
            </h2>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach ($processing_files as $img)
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--hover-bg); border-radius: 8px;">
                        <img src="{{ $img['url'] }}" alt="{{ $img['filename'] }}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">
                        
                        <div style="flex: 1;">
                            <div style="font-weight: 500; color: #202124; margin-bottom: 0.25rem;">
                                {{ $img['filename'] }}
                            </div>
                            <div style="font-size: 0.875rem; color: var(--secondary-color);">
                                Started: {{ $img['started_at'] }}
                            </div>
                        </div>
                        
                        <div class="spinner"></div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Recently Completed (Last 24h) -->
    @if (!empty($completed_files) && $showCompleted)
        <div class="card" style="margin-bottom: 2rem;" x-data x-show="true" x-transition>
            <h2 style="font-size: 1.25rem; font-weight: 500; color: #202124; margin-bottom: 1rem;">
                ✅ Recently Completed (Last 24h - {{ count($completed_files) }})
            </h2>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach ($completed_files as $file)
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #e6f4ea; border-radius: 8px; border-left: 4px solid #137333;">
                        <!-- Thumbnail -->
                        <img src="{{ $file['url'] }}" alt="{{ $file['filename'] }}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">

                        <!-- File Info -->
                        <div style="flex: 1;">
                            <div style="font-weight: 500; color: #202124; margin-bottom: 0.25rem;">
                                {{ $file['filename'] }}
                            </div>

                            <!-- File Type Badge -->
                            <div style="display: inline-block; background: #137333; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">
                                {{ $file['media_type'] }}
                            </div>

                            <!-- Processing Info -->
                            <div style="font-size: 0.875rem; color: var(--secondary-color);">
                                <span style="color: #137333;">✓</span> Completed {{ $file['completed_at'] }}
                                @if ($file['processing_time'])
                                    ({{ $file['processing_time'] }})
                                @endif
                            </div>

                            <!-- Description -->
                            @if ($file['description'])
                                <div style="font-size: 0.875rem; color: var(--secondary-color); margin-top: 0.25rem;">
                                    {{ Str::limit($file['description'], 100) }}
                                </div>
                            @endif
                        </div>

                        <!-- Quick Actions -->
                        <div style="display: flex; gap: 0.5rem;">
                            <!-- Download Button -->
                            <button wire:click="downloadFile({{ $file['id'] }})" class="btn btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;" title="Download file">
                                <span class="material-symbols-outlined" style="font-size: 1rem;">download</span>
                            </button>

                            <!-- Reanalyze Button -->
                            <button wire:click="reanalyze({{ $file['id'] }})" class="btn btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;" title="Reanalyze">
                                <span class="material-symbols-outlined" style="font-size: 1rem;">refresh</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Failed Processing -->
    @if (!empty($failed_files) && $showFailed)
        <div class="card" x-data x-show="true" x-transition>
            <h2 style="font-size: 1.25rem; font-weight: 500; color: #202124; margin-bottom: 1rem;">
                ❌ Failed Processing ({{ count($failed_files) }})
            </h2>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach ($failed_files as $file)
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #fce8e6; border-radius: 8px; border-left: 4px solid #d93025;">
                        <!-- Thumbnail -->
                        <img src="{{ $file['url'] }}" alt="{{ $file['filename'] }}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">

                        <!-- File Info -->
                        <div style="flex: 1;">
                            <div style="font-weight: 500; color: #202124; margin-bottom: 0.25rem;">
                                {{ $file['filename'] }}
                            </div>

                            <!-- File Type Badge -->
                            <div style="display: inline-block; background: #d93025; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">
                                {{ $file['media_type'] }}
                            </div>

                            <!-- Error Message -->
                            <div style="font-size: 0.875rem; color: #d93025; margin-top: 0.25rem;">
                                Error: {{ $file['error'] }}
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div style="display: flex; gap: 0.5rem;">
                            <!-- Download Button -->
                            <button wire:click="downloadFile({{ $file['id'] }})" class="btn btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;" title="Download file">
                                <span class="material-symbols-outlined" style="font-size: 1rem;">download</span>
                            </button>

                            <!-- Retry Button -->
                            <button wire:click="retryFailed({{ $file['id'] }})" class="btn btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;" title="Retry processing">
                                <span class="material-symbols-outlined" style="font-size: 1rem;">refresh</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Empty State -->
    @if (empty($pending_files) && empty($processing_files) && empty($completed_files) && empty($failed_files))
        <div class="empty-state">
            <div class="empty-state-icon">✅</div>
            <h2 class="empty-state-title">All Caught Up!</h2>
            <p class="empty-state-description">No files currently processing</p>
            <a wire:navigate href="{{ route('instant-upload') }}" class="btn btn-primary">
                <span class="material-symbols-outlined" style="font-size: 1.125rem;">bolt</span>
                Upload More Files
            </a>
        </div>
    @endif
</div>

