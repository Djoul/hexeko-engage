<div>
    <!-- Export Buttons -->
    <div class="flex space-x-3">
        <!-- Standard Export Button -->
        <button wire:click="openModal" type="button"
                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Export Translations
        </button>

        <!-- S3 Export Button -->
        <button wire:click="openS3Modal" type="button"
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            Export to S3
            @if($pendingExportsCount > 0)
                <span class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full">
                    {{ $pendingExportsCount }}
                </span>
            @endif
        </button>
    </div>

    <!-- Standard Export Modal -->
    @if($showExportModal)
        <div class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <!-- Modal header -->
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Export Translations
                        </h3>

                        <!-- Modal content -->
                        <div class="space-y-6">
                @if($successMessage)
                    <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-md">
                        {{ $successMessage }}
                    </div>
                @endif

                @if($errorMessage)
                    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-md">
                        {{ $errorMessage }}
                    </div>
                @endif

                <!-- Export Settings -->
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="exportFormat" class="block text-sm font-medium text-neutral-700 mb-2">
                            Export Format
                        </label>
                        <select wire:model="exportFormat" id="exportFormat"
                                class="w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <option value="json">JSON</option>
                            <option value="csv">CSV</option>
                            <option value="xlsx">Excel (XLSX)</option>
                        </select>
                    </div>

                    <div>
                        <label for="exportInterface" class="block text-sm font-medium text-neutral-700 mb-2">
                            Interface
                        </label>
                        <select wire:model.live="exportInterface" id="exportInterface"
                                class="w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @foreach($interfaces as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Locale Selection -->
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-3">
                        Languages to Export
                    </label>
                    <div class="bg-neutral-50 rounded-lg p-4">
                        <div class="flex justify-between mb-3">
                            <span class="text-sm text-neutral-600">Select languages to include:</span>
                            <div class="space-x-2">
                                <button wire:click="selectAllLocales" type="button" class="text-xs text-primary-600 hover:text-primary-800">
                                    Select All
                                </button>
                                <span class="text-neutral-400">|</span>
                                <button wire:click="deselectAllLocales" type="button" class="text-xs text-primary-600 hover:text-primary-800">
                                    Deselect All
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            @foreach($locales as $locale => $label)
                                <label class="inline-flex items-center">
                                    <input type="checkbox"
                                           wire:model="selectedLocales.{{ $locale }}"
                                           class="rounded border-neutral-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="space-y-3">
                    <label class="inline-flex items-center">
                        <input type="checkbox"
                               wire:model="includeEmptyTranslations"
                               class="rounded border-neutral-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-neutral-700">Include empty translations</span>
                    </label>

                    @if($exportInterface === 'all')
                        <label class="inline-flex items-center">
                            <input type="checkbox"
                                   wire:model="groupByInterface"
                                   class="rounded border-neutral-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-neutral-700">Group translations by interface</span>
                        </label>
                    @endif
                </div>

                <!-- Statistics -->
                @if(isset($statistics[$exportInterface]))
                    <div class="bg-blue-50 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-blue-900 mb-2">Export Statistics</h4>
                        <div class="text-sm text-blue-700">
                            <span>Total translations to export: </span>
                            <span class="font-semibold">{{ number_format($statistics[$exportInterface]) }}</span>
                        </div>
                    </div>
                @endif
                        </div>
                    </div>

                    <!-- Modal footer -->
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="export" wire:loading.attr="disabled"
                                @if($isProcessing || empty(array_filter($selectedLocales))) disabled @endif
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="export">Export</span>
                            <span wire:loading wire:target="export">Exporting...</span>
                        </button>
                        <button type="button" wire:click="closeModal" wire:loading.attr="disabled"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- S3 Export Modal -->
    @if($showExportToS3Modal)
        <div class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeS3Modal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <!-- Modal header -->
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Export Translations to S3
                        </h3>

                        <!-- Modal content -->
                        <div class="space-y-6">
                @if($successMessage)
                    <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-md">
                        {{ $successMessage }}
                    </div>
                @endif

                @if($errorMessage)
                    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-md">
                        {{ $errorMessage }}
                    </div>
                @endif

                <!-- Info Banner -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">
                                S3 Export for Migration System
                            </h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>Exports will be uploaded to S3 and made available for the translation migration system across all environments.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interface Selection -->
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-3">
                        Select Interfaces to Export
                    </label>
                    <div class="bg-neutral-50 rounded-lg p-4">
                        <div class="flex justify-between mb-3">
                            <span class="text-sm text-neutral-600">Choose interfaces to export to S3:</span>
                            <div class="space-x-2">
                                <button wire:click="selectAllInterfaces" type="button" class="text-xs text-primary-600 hover:text-primary-800">
                                    Select All
                                </button>
                                <span class="text-neutral-400">|</span>
                                <button wire:click="deselectAllInterfaces" type="button" class="text-xs text-primary-600 hover:text-primary-800">
                                    Deselect All
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach($interfaces as $key => $label)
                                @if($key !== 'all')
                                    <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-neutral-200">
                                        <label class="flex items-center cursor-pointer flex-1">
                                            <input type="checkbox"
                                                   wire:model="selectedInterfacesForExport.{{ $key }}"
                                                   class="rounded border-neutral-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                                            <span class="ml-3 font-medium text-neutral-900">{{ $label }}</span>
                                        </label>
                                        @if(isset($statistics[$key]))
                                            <span class="text-sm text-neutral-500">
                                                {{ number_format($statistics[$key]) }} translations
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Processing Status -->
                @if($pendingExportsCount > 0)
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    {{ $pendingExportsCount }} export(s) currently in progress. Check notifications for completion.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
                        </div>
                    </div>

                    <!-- Modal footer -->
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="exportToS3" wire:loading.attr="disabled"
                                @if($isProcessing || empty(array_filter($selectedInterfacesForExport))) disabled @endif
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="exportToS3">Export to S3</span>
                            <span wire:loading wire:target="exportToS3">Processing...</span>
                        </button>
                        <button type="button" wire:click="closeS3Modal" wire:loading.attr="disabled"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- JavaScript for file download -->
    @push('scripts')
    <script>
        window.addEventListener('download', event => {
            const link = document.createElement('a');
            const blob = new Blob([event.detail.content], { type: event.detail.contentType });
            link.href = window.URL.createObjectURL(blob);
            link.download = event.detail.filename;
            link.click();
            window.URL.revokeObjectURL(link.href);
        });

        // Refresh export status periodically
        setInterval(() => {
            @this.call('checkPendingExports');
        }, 30000); // Check every 30 seconds
    </script>
    @endpush
</div>