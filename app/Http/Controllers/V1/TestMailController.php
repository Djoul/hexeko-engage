<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Log;

class TestMailController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $to = $request->input('to');

        Log::info('Sending test email', ['to' => $to]);

        $defaultTo = config('mail.from.address');
        $safeTo = (is_string($to) && ! empty($to)) ? $to : (is_string($defaultTo) ? $defaultTo : '');

        try {
            Mail::to($safeTo)->send(
                new class extends Mailable
                {
                    public function build(): self
                    {
                        return $this->subject('Test Email')->view('emails.test', ['date' => now()->toDateTimeString()]);
                    }
                }
            );
        } catch (Exception $e) {
            Log::error('Error sending test email', [
                'to' => $safeTo,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Test email sent to '.$safeTo]);
    }
}
