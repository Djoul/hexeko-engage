<div class="group" x-data="{ copied: false }" style="width: 100%;">
    <div class="relative bg-neutral-900 text-white rounded-lg p-10" style="width: 100%; min-height: 60px;">
        <code class="text-sm sm:text-base font-mono whitespace-pre pr-12" style="display: block; line-height: 1.8; overflow-x: auto;">{{ $code }}</code>

        <button
            type="button"
            class="absolute top-10 right-10 p-2 text-neutral-400 hover:text-white transition-colors duration-200"
            wire:click="copyCode"
            @click="
                navigator.clipboard.writeText($wire.code);
                copied = true;
                setTimeout(() => copied = false, 2000);
            "
            :title="copied ? 'Copié!' : 'Copier'"
        >
            <svg x-show="!copied" style="width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path d="M8 2a1 1 0 000 2h2a1 1 0 100-2H8z"></path>
                <path d="M3 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6h-4.586l1.293-1.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L10.414 13H15v3a2 2 0 01-2 2H5a2 2 0 01-2-2V5zM15 11h2a1 1 0 110 2h-2v-2z"></path>
            </svg>
            <svg x-show="copied" style="width: 20px; height: 20px;" class="text-success-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('reset-copy-button', () => {
            setTimeout(() => {
                @this.call('resetCopyButton');
            }, 2000);
        });
    });
</script>
@endpush