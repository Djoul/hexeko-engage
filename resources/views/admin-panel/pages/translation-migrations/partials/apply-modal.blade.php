<!-- Apply Modal -->
<div id="applyModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('applyModal')"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="applyForm" method="POST">
                @csrf
                <input type="hidden" id="applyMigrationId" name="migration_id">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-success-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-neutral-900">
                                Apply Migration
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-neutral-500">
                                    Are you sure you want to apply this migration? This action will update the translation files for the selected interface.
                                </p>
                            </div>
                            <div class="mt-4 space-y-4">
                                <!-- Create Backup Option -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="create_backup" 
                                               name="create_backup" 
                                               type="checkbox" 
                                               value="1"
                                               checked
                                               class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-neutral-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="create_backup" class="font-medium text-neutral-700">
                                            Create backup
                                        </label>
                                        <p class="text-neutral-500">
                                            Save current translations before applying changes
                                        </p>
                                    </div>
                                </div>

                                <!-- Validate Checksum Option -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="validate_checksum" 
                                               name="validate_checksum" 
                                               type="checkbox" 
                                               value="1"
                                               checked
                                               class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-neutral-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="validate_checksum" class="font-medium text-neutral-700">
                                            Validate checksum
                                        </label>
                                        <p class="text-neutral-500">
                                            Ensure file integrity before applying
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-success-600 text-base font-medium text-white hover:bg-success-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-success-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Apply Migration
                    </button>
                    <button type="button" 
                            onclick="closeModal('applyModal')"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('applyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const migrationId = document.getElementById('applyMigrationId').value;
        this.action = `/admin-panel/translation-migrations/${migrationId}/apply`;
        this.submit();
    });
</script>