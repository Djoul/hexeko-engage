<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CreditAccountService;
use Exception;
use Illuminate\Console\Command;

class AddUserCreditsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:add-credits
                            {email : The email of the user}
                            {amount : The amount to add in euros}
                            {--type=cash : The type of credit to add}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add credits to a user account for testing purposes';

    /**
     * Execute the console command.
     */
    public function handle(CreditAccountService $creditService): int
    {
        $email = $this->argument('email');
        if (! is_string($email)) {
            $this->error('Invalid email argument');

            return 1;
        }

        $amount = (float) $this->argument('amount');
        $type = $this->option('type');
        if (! is_string($type)) {
            $this->error('Invalid type option');

            return 1;
        }

        $this->info("Adding {$amount}€ of {$type} credits to user: {$email}");

        // Find the user
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: {$email}");

            return 1;
        }

        $this->info("User found: {$user->email} (ID: {$user->id})");

        try {
            // Add credits (convert to cents)
            $creditService->addCredit(User::class, (string) $user->id, $type, (int) ($amount * 100));

            // Refresh and check balance
            $user->refresh();
            $credits = $user->credits;

            $this->info('✅ Credits added successfully!');
            $this->newLine();

            $this->table(
                ['Type', 'Balance (€)'],
                $credits->map(function ($credit): array {
                    return [
                        $credit->type,
                        number_format($credit->balance / 100, 2),
                    ];
                })->toArray()
            );

            return 0;
        } catch (Exception $e) {
            $this->error('Failed to add credits: '.$e->getMessage());

            return 1;
        }
    }
}
