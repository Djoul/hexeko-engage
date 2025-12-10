<?php

namespace Database\Seeders;

use App\Enums\OrigineInterfaces;
use App\Models\TranslationMigration;
use Illuminate\Database\Seeder;

class TranslationMigrationSeeder extends Seeder
{
    public function run(): void
    {
        // Create some sample migrations for each interface
        $interfaces = [
            OrigineInterfaces::MOBILE,
            OrigineInterfaces::WEB_FINANCER,
            OrigineInterfaces::WEB_BENEFICIARY,
        ];

        $statuses = ['pending', 'completed', 'failed', 'rolled_back'];

        foreach ($interfaces as $interface) {
            foreach ($statuses as $index => $status) {
                $migration = TranslationMigration::create([
                    'interface_origin' => $interface,
                    'version' => sprintf('1.%d.0', $index),
                    'filename' => sprintf('%s_v1_%d_0_translations.json', $interface, $index),
                    'checksum' => md5(uniqid((string) time(), true)),
                    'status' => $status,
                    'batch_number' => $index + 1,
                    'metadata' => [
                        's3_path' => sprintf('translations/%s/v1_%d_0.json', $interface, $index),
                        'changes_count' => rand(10, 100),
                        'affected_keys' => rand(5, 50),
                        'backup_path' => $status === 'completed' ? sprintf('backups/%s_backup_%s.json', $interface, time()) : null,
                        'rollback_reason' => $status === 'rolled_back' ? 'Test rollback reason' : null,
                    ],
                ]);

                // Set dates based on status
                if ($status === 'completed') {
                    $migration->executed_at = now()->subHours(rand(1, 48));
                    $migration->save();
                } elseif ($status === 'rolled_back') {
                    $migration->executed_at = now()->subHours(rand(48, 96));
                    $migration->rolled_back_at = now()->subHours(rand(1, 24));
                    $migration->save();
                }
            }
        }

        $this->command->info('Translation migrations seeded successfully!');
    }
}
