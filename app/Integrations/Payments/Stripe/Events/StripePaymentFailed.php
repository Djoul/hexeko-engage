<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Events;

use App\Integrations\Payments\Stripe\Models\StripePayment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StripePaymentFailed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public StripePayment $payment
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->payment->user_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'stripe_payment_id' => $this->payment->stripe_payment_id,
            'status' => $this->payment->status,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'metadata' => $this->payment->metadata,
            'error_message' => $this->payment->error_message,
        ];
    }

    /**
     * The name of the broadcast event.
     */
    public function broadcastAs(): string
    {
        return 'stripe.payment.failed';
    }
}
