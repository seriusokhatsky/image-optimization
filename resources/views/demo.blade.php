<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Image Optimizer - Free Online Image Compression Tool</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .drag-over {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
        }
        .progress-bar {
            transition: width 0.3s ease;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .result-card {
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
                <i class="fas fa-compress-alt text-blue-600 mr-3"></i>
                Image Optimizer (by Xtemos)
            </h1>
            <p class="text-lg text-gray-600 mb-2">
                Compress JPEG, PNG, GIF and WebP images with optimal quality
            </p>
            <p class="text-sm text-gray-500">
                <i class="fas fa-shield-alt text-green-500"></i>
                Maximum 10 uploads per hour • Files deleted after 1 hour • Free to use
            </p>
        </div>

        <!-- Upload Area -->
        <div id="uploadArea" class="bg-white rounded-lg shadow-lg p-8 mb-8 border-2 border-dashed border-gray-300 hover:border-blue-400 transition-colors cursor-pointer">
            <div class="text-center">
                <i class="fas fa-cloud-upload-alt text-6xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Drop Your Images Here</h3>
                <p class="text-gray-500 mb-4">or click to browse files</p>
                
                <!-- Quality Slider -->
                <div class="mb-6 max-w-md mx-auto">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quality Setting</label>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">Lower</span>
                        <input type="range" id="qualitySlider" min="1" max="100" value="80" 
                               class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider">
                        <span class="text-sm text-gray-500">Higher</span>
                    </div>
                    <div class="text-center mt-2">
                        <span class="text-sm font-medium text-blue-600">Quality: <span id="qualityValue">80</span>%</span>
                    </div>
                </div>

                <div class="flex items-center justify-center space-x-4 text-sm text-gray-500">
                    <span><i class="fas fa-file-image text-blue-500"></i> JPEG, PNG, GIF, WebP</span>
                    <span><i class="fas fa-weight text-green-500"></i> Max 10MB</span>
                </div>
            </div>
            <input type="file" id="fileInput" multiple accept=".jpg,.jpeg,.png,.gif,.webp" class="hidden">
        </div>

        <!-- Upload Queue -->
        <div id="uploadQueue" class="space-y-4 mb-8" style="display: none;">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-list text-blue-600 mr-2"></i>
                Processing Queue
            </h3>
            <div id="queueItems" class="space-y-4"></div>
        </div>

        <!-- Error Messages -->
        <div id="errorContainer" class="mb-6" style="display: none;">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3 mt-1"></i>
                    <div>
                        <h4 class="text-red-800 font-medium">Error</h4>
                        <p id="errorMessage" class="text-red-700 text-sm"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rate Limit Info -->
        @if(session('error'))
        <div class="mb-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                    <i class="fas fa-clock text-yellow-500 mr-3 mt-1"></i>
                    <div>
                        <h4 class="text-yellow-800 font-medium">Rate Limit Reached</h4>
                        <p class="text-yellow-700 text-sm">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Info Section -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                How It Works
            </h2>
            <div class="grid md:grid-cols-3 gap-4 text-sm text-gray-600">
                <div class="text-center">
                    <i class="fas fa-upload text-blue-500 text-2xl mb-2"></i>
                    <h3 class="font-medium text-gray-800 mb-1">1. Upload</h3>
                    <p>Drag & drop or click to select your images</p>
                </div>
                <div class="text-center">
                    <i class="fas fa-cogs text-green-500 text-2xl mb-2"></i>
                    <h3 class="font-medium text-gray-800 mb-1">2. Optimize</h3>
                    <p>Our AI optimizes your images automatically</p>
                </div>
                <div class="text-center">
                    <i class="fas fa-download text-purple-500 text-2xl mb-2"></i>
                    <h3 class="font-medium text-gray-800 mb-1">3. Download</h3>
                    <p>Get your compressed images instantly</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // CSRF token setup for AJAX requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // DOM elements
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const qualitySlider = document.getElementById('qualitySlider');
        const qualityValue = document.getElementById('qualityValue');
        const uploadQueue = document.getElementById('uploadQueue');
        const queueItems = document.getElementById('queueItems');
        const errorContainer = document.getElementById('errorContainer');
        const errorMessage = document.getElementById('errorMessage');

        // Quality slider
        qualitySlider.addEventListener('input', function() {
            qualityValue.textContent = this.value;
        });

        // Prevent quality slider area from triggering file upload
        const qualityContainer = qualitySlider.closest('.mb-6');
        qualityContainer.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Upload area events
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleDrop);
        fileInput.addEventListener('change', handleFileSelect);

        function handleDragOver(e) {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            const files = Array.from(e.dataTransfer.files);
            processFiles(files);
        }

        function handleFileSelect(e) {
            const files = Array.from(e.target.files);
            processFiles(files);
        }

        function processFiles(files) {
            hideError();
            
            // Filter valid image files
            const validFiles = files.filter(file => {
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                const maxSize = 10 * 1024 * 1024; // 10MB
                
                if (!validTypes.includes(file.type)) {
                    showError(`File "${file.name}" is not a supported image format.`);
                    return false;
                }
                
                if (file.size > maxSize) {
                    showError(`File "${file.name}" is too large. Maximum size is 10MB.`);
                    return false;
                }
                
                return true;
            });

            if (validFiles.length === 0) return;

            uploadQueue.style.display = 'block';
            
            validFiles.forEach(file => uploadFile(file));
        }

        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('quality', qualitySlider.value);
            formData.append('_token', csrfToken);

            const queueItem = createQueueItem(file);
            queueItems.appendChild(queueItem);

            fetch('/demo/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateQueueItem(queueItem, 'processing', data);
                    pollStatus(data.task_id, queueItem);
                } else {
                    updateQueueItem(queueItem, 'error', data);
                    if (data.message) {
                        showError(data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                updateQueueItem(queueItem, 'error', { message: 'Upload failed. Please try again.' });
            });
        }

        function createQueueItem(file) {
            const div = document.createElement('div');
            div.className = 'bg-white rounded-lg shadow p-4 fade-in';
            div.innerHTML = `
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-file-image text-blue-500 text-xl"></i>
                        <div>
                            <h4 class="font-medium text-gray-800 truncate max-w-xs">${file.name}</h4>
                            <p class="text-sm text-gray-500">${formatBytes(file.size)}</p>
                        </div>
                    </div>
                    <div class="status-indicator">
                        <i class="fas fa-spinner fa-spin text-blue-500"></i>
                        <span class="text-sm text-blue-600 ml-2">Uploading...</span>
                    </div>
                </div>
                <div class="progress-container mb-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="progress-bar bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                    </div>
                </div>
                <div class="results mt-4" style="display: none;"></div>
            `;
            return div;
        }

        function updateQueueItem(queueItem, status, data) {
            const statusIndicator = queueItem.querySelector('.status-indicator');
            const progressBar = queueItem.querySelector('.progress-bar');
            const resultsDiv = queueItem.querySelector('.results');

            switch (status) {
                case 'processing':
                    statusIndicator.innerHTML = '<i class="fas fa-cogs fa-spin text-yellow-500"></i><span class="text-sm text-yellow-600 ml-2">Processing...</span>';
                    progressBar.style.width = '50%';
                    break;

                case 'completed':
                    statusIndicator.innerHTML = '<i class="fas fa-check-circle text-green-500"></i><span class="text-sm text-green-600 ml-2">Completed</span>';
                    progressBar.style.width = '100%';
                    progressBar.classList.remove('bg-blue-600');
                    progressBar.classList.add('bg-green-600');
                    
                    // Show results
                    resultsDiv.style.display = 'block';
                    resultsDiv.innerHTML = createResultsHTML(data);
                    break;

                case 'error':
                    statusIndicator.innerHTML = '<i class="fas fa-exclamation-circle text-red-500"></i><span class="text-sm text-red-600 ml-2">Failed</span>';
                    progressBar.style.width = '100%';
                    progressBar.classList.remove('bg-blue-600');
                    progressBar.classList.add('bg-red-600');
                    
                    if (data.message || data.error) {
                        resultsDiv.style.display = 'block';
                        resultsDiv.innerHTML = `<div class="text-sm text-red-600 mt-2">${data.message || data.error}</div>`;
                    }
                    break;
            }
        }

        function createResultsHTML(data) {
            const opt = data.optimization;
            let html = `
                <div class="grid md:grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h5 class="font-medium text-gray-800 mb-2">Optimization Results</h5>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Original Size:</span>
                                <span class="font-medium">${data.original_file.size_formatted}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Optimized Size:</span>
                                <span class="font-medium">${opt.optimized_size_formatted}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Size Reduction:</span>
                                <span class="font-medium text-green-600">${opt.size_reduction_formatted} (${opt.compression_ratio}%)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Algorithm:</span>
                                <span class="font-medium text-xs">${opt.algorithm}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col space-y-2">
                        <button onclick="downloadFile('${data.task_id}')" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i class="fas fa-download mr-2"></i>Download Optimized
                        </button>
            `;

            if (data.webp && data.webp.webp_size) {
                html += `
                        <button onclick="downloadFile('${data.task_id}', 'webp')" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i class="fas fa-download mr-2"></i>Download WebP (${data.webp.webp_size_formatted})
                        </button>
                `;
            }

            html += `
                    </div>
                </div>
            `;

            if (opt.size_increase_prevented) {
                html += `<div class="mt-2 text-xs text-yellow-600"><i class="fas fa-info-circle mr-1"></i>${opt.note}</div>`;
            }

            return html;
        }

        function pollStatus(taskId, queueItem) {
            const poll = () => {
                fetch(`/demo/status/${taskId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.status === 'completed') {
                                updateQueueItem(queueItem, 'completed', data);
                            } else if (data.status === 'failed') {
                                updateQueueItem(queueItem, 'error', data);
                            } else {
                                // Still processing, poll again
                                setTimeout(poll, 2000);
                            }
                        } else {
                            updateQueueItem(queueItem, 'error', data);
                        }
                    })
                    .catch(error => {
                        console.error('Status poll error:', error);
                        updateQueueItem(queueItem, 'error', { message: 'Status check failed' });
                    });
            };

            // Start polling after 1 second
            setTimeout(poll, 1000);
        }

        function downloadFile(taskId, type = '') {
            const url = type === 'webp' ? `/download/${taskId}/webp` : `/download/${taskId}`;
            window.open(url, '_blank');
        }

        function formatBytes(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' B';
            }
        }

        function showError(message) {
            errorMessage.textContent = message;
            errorContainer.style.display = 'block';
            setTimeout(hideError, 5000);
        }

        function hideError() {
            errorContainer.style.display = 'none';
        }
    </script>
</body>
</html> 