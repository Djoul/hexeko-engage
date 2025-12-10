<?php

namespace App\Console\Commands\DevTools;

use Illuminate\Console\Command;

class CheckReverbConnection extends Command
{
    protected $signature = 'reverb:check';

    protected $description = 'Vérifie la connexion à Reverb et suggère la configuration appropriée';

    public function handle(): int
    {
        $this->info('=== Test de connexion Reverb ===');
        $this->newLine();

        // Détection de l'environnement d'exécution
        $isRunningInDocker = file_exists('/.dockerenv') || getenv('DOCKER_CONTAINER') !== false;

        $this->info($isRunningInDocker ? '🐳 Exécution dans Docker détectée' : '💻 Exécution locale détectée');
        $this->newLine();

        // Configuration des hôtes à tester selon l'environnement
        if ($isRunningInDocker) {
            // Dans Docker, on teste les noms de service Docker
            $hosts = [
                'reverb_engage' => 'reverb_engage',  // Nom du service Docker
                'reverb' => 'reverb',                // Alias possible
                'localhost' => 'localhost',          // Localhost dans le conteneur
            ];
        } else {
            // En local, on teste les hôtes accessibles depuis l'extérieur
            $hosts = [
                'localhost' => 'localhost',
                'host.docker.internal' => 'host.docker.internal',
                '127.0.0.1' => '127.0.0.1',
            ];
        }

        $results = [];
        $workingHosts = [];

        foreach ($hosts as $name => $host) {
            $this->info("Test de {$host}:8080...");

            $errno = 0;
            $errstr = '';
            $connection = fsockopen($host, 8080, $errno, $errstr, 1);

            if ($connection) {
                $this->info("✅ {$host}:8080 - Connexion réussie");
                $results[$name] = true;
                $workingHosts[] = $host;
                fclose($connection);
            } else {
                $this->error("❌ {$host}:8080 - {$errstr}");
                $results[$name] = false;
            }
        }

        $this->newLine();

        // Configuration actuelle
        /** @var string $currentHost */
        $currentHost = config('reverb.apps.apps.0.options.host', 'non défini');
        $this->info("Configuration actuelle : REVERB_HOST={$currentHost}");

        // Recommandation basée sur l'environnement
        if ($workingHosts !== []) {
            $this->newLine();
            $this->info('✅ Hôtes fonctionnels trouvés :');

            foreach ($workingHosts as $host) {
                $this->line("  - {$host}");
            }

            $this->newLine();

            // Recommandation spécifique pour Docker
            if ($isRunningInDocker) {
                $recommendedHost = in_array('reverb_engage', $workingHosts) ? 'reverb_engage' : $workingHosts[0];
                $this->info('📋 Configuration recommandée pour l\'environnement Docker :');
                $this->line("REVERB_HOST=\"{$recommendedHost}\"");

                if ($currentHost !== $recommendedHost) {
                    $this->newLine();
                    $this->warn("⚠️  La configuration actuelle ({$currentHost}) ne correspond pas à la recommandation!");
                    $this->warn("Pour la communication inter-conteneurs Docker, utilisez : REVERB_HOST=\"{$recommendedHost}\"");
                } else {
                    $this->newLine();
                    $this->info('✅ La configuration actuelle est correcte pour Docker!');
                }
            } else {
                $recommendedHost = $workingHosts[0];
                $this->info('📋 Configuration recommandée pour l\'environnement local :');
                $this->line("REVERB_HOST=\"{$recommendedHost}\"");
            }
        } else {
            $this->newLine();
            $this->error('❌ Aucune connexion possible vers Reverb.');

            if ($isRunningInDocker) {
                $this->line('Vérifiez que :');
                $this->line('1. Le conteneur reverb_engage est en cours d\'exécution : docker compose ps');
                $this->line('2. Les conteneurs sont sur le même réseau Docker');
                $this->line('3. Le service Reverb est bien démarré dans le conteneur');
            } else {
                $this->line('Vérifiez que Reverb est lancé : php artisan reverb:start');
            }
        }

        // Test de la configuration actuelle
        if ($currentHost !== 'non défini' && $currentHost !== '') {
            $this->newLine();
            $this->info('Test de la configuration actuelle...');

            $errno = 0;
            $errstr = '';
            $connection = fsockopen($currentHost, 8080, $errno, $errstr, 1);

            if ($connection) {
                $this->info("✅ La configuration actuelle REVERB_HOST={$currentHost} fonctionne!");
                fclose($connection);
            } else {
                $this->error("❌ La configuration actuelle REVERB_HOST={$currentHost} ne fonctionne pas!");
                $this->error("Erreur: {$errstr}");
            }
        }

        // Informations supplémentaires pour Docker
        if ($isRunningInDocker) {
            $this->newLine();
            $this->info('💡 Note importante pour Docker :');
            $this->line('- Pour la communication entre conteneurs : utilisez "reverb_engage"');
            $this->line('- Pour l\'accès depuis le navigateur : le port 8080 doit être exposé');
            $this->line('- Ne pas utiliser "host.docker.internal" pour la communication inter-conteneurs');
        }

        return 0;
    }
}
