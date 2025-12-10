<!-- Sync Modal -->
<div id="syncModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('syncModal')"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="syncForm" action="{{ route('admin.translation-migrations.sync') }}" method="POST">
                @csrf
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-neutral-900">
                                Sync Migrations from S3
                            </h3>
                            <div class="mt-4 space-y-4">
                                <!-- Interface Selection -->
                                <div>
                                    <label for="sync_interface" class="block text-sm font-medium text-neutral-700">
                                        Select Interface
                                    </label>
                                    <select id="sync_interface"
                                            name="interface"
                                            required
                                            class="mt-1 block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                        <option value="">Choose an interface...</option>
                                        <option value="mobile">Mobile</option>
                                        <option value="web_financer">Web Financer</option>
                                        <option value="web_beneficiary">Web Beneficiary</option>
                                    </select>
                                </div>

                                <!-- Auto Process Option -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="auto_process"
                                               name="auto_process"
                                               type="checkbox"
                                               value="1"
                                               class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-neutral-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="auto_process" class="font-medium text-neutral-700">
                                            Auto-process migrations
                                        </label>
                                        <p class="text-neutral-500">
                                            Automatically apply pending migrations after sync
                                        </p>
                                    </div>
                                </div>

                                <!-- Progress Indicator (hidden by default) -->
                                <div id="syncProgress" class="hidden">
                                    <div class="bg-info-50 border border-info-200 rounded-md p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-info-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-info-700">
                                                    Syncing migrations from S3...
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Start Sync
                    </button>
                    <button type="button"
                            onclick="closeModal('syncModal')"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
