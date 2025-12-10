<div class="flex flex-col items-center justify-center min-h-[60vh]">
    <div class="max-w-lg mx-auto text-center p-8">
        {{-- Icon --}}
        <div class="mb-6">
            <div class="inline-flex items-center justify-center w-24 h-24 bg-yellow-100 rounded-full">
                <svg class="w-12 h-12 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>

        {{-- Title --}}
        <h2 class="text-3xl font-bold text-gray-900 mb-4">
            {{ $title }}
        </h2>

        {{-- Description --}}
        <p class="text-lg text-gray-600 mb-6">
            {{ $description }}
        </p>

        {{-- Expected Date --}}
        @if($expectedDate)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-800">
                    <strong>Expected completion:</strong> {{ $expectedDate }}
                </p>
            </div>
        @endif

        {{-- Coming Features --}}
        @if(!empty($features))
            <div class="bg-gray-50 rounded-lg p-6 text-left">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">
                    Coming Features
                </h3>
                <ul class="space-y-2">
                    @foreach($features as $feature)
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Back Button --}}
        <div class="mt-8">
            <button onclick="history.back()"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Go Back
            </button>
        </div>
    </div>
</div>