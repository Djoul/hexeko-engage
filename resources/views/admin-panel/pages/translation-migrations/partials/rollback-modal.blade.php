<!-- Rollback Modal -->
<div id="rollbackModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('rollbackModal')"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="rollbackForm" method="POST">
                @csrf
                <input type="hidden" id="rollbackMigrationId" name="migration_id">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-warning-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-warning-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-neutral-900">
                                Rollback Migration
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-neutral-500">
                                    This action will restore the translation files to their previous state. Please provide a reason for the rollback.
                                </p>
                            </div>
                            <div class="mt-4">
                                <label for="rollback_reason" class="block text-sm font-medium text-neutral-700">
                                    Rollback Reason <span class="text-error-500">*</span>
                                </label>
                                <textarea id="rollback_reason" 
                                          name="reason" 
                                          rows="3" 
                                          required
                                          minlength="10"
                                          maxlength="500"
                                          placeholder="Describe why this migration needs to be rolled back..."
                                          class="mt-1 block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:outline-none focus:ring-warning-500 focus:border-warning-500"></textarea>
                                <p class="mt-1 text-sm text-neutral-500">
                                    Minimum 10 characters, maximum 500 characters
                                </p>
                            </div>
                            
                            <!-- Warning Message -->
                            <div class="mt-4 bg-warning-50 border-l-4 border-warning-400 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-warning-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-warning-700">
                                            <strong>Warning:</strong> This action cannot be undone. Make sure you understand the implications before proceeding.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-warning-600 text-base font-medium text-white hover:bg-warning-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-warning-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Confirm Rollback
                    </button>
                    <button type="button" 
                            onclick="closeModal('rollbackModal')"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('rollbackForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const migrationId = document.getElementById('rollbackMigrationId').value;
        this.action = `/admin-panel/translation-migrations/${migrationId}/rollback`;
        this.submit();
    });
</script>