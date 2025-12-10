<?php

declare(strict_types=1);

namespace App\Console\Commands\DevTools;

use App\Integrations\Vouchers\Amilon\Documentation\AmilonApiDoc;
use Illuminate\Console\Command;
use ReflectionClass;
use ReflectionMethod;

class ShowApiDocCommand extends Command
{
    protected $signature = 'api:doc {provider?} {method?}
                            {--json : Output as JSON}
                            {--list : List all available providers}';

    protected $description = 'Affiche la documentation d\'une API tierce';

    /**
     * Mapping des providers vers leurs classes de documentation
     *
     * @var array<string, class-string>
     */
    private array $providerMappings = [
        'amilon' => AmilonApiDoc::class,
        // Ajoutez d'autres providers ici au fur et à mesure
        // 'stripe' => \App\Integrations\Payments\Stripe\Documentation\StripeApiDoc::class,
        // 'sendgrid' => \App\Integrations\Email\Sendgrid\Documentation\SendgridApiDoc::class,
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listProviders();
        }

        $provider = $this->argument('provider');

        if (! $provider) {
            // Mode interactif : demander à l'utilisateur de choisir
            $availableProviders = $this->getAvailableProviders();

            if ($availableProviders === []) {
                $this->error('Aucun provider disponible');

                return self::FAILURE;
            }

            $provider = $this->choice(
                'Sélectionnez un provider',
                array_keys($availableProviders),
                0
            );
        }

        $method = $this->argument('method');

        // Récupérer la classe de documentation
        // Provider should be string from argument, but ensure it is
        $providerStr = is_string($provider) ? $provider : '';
        $docClass = $this->getDocumentationClass($providerStr);

        if (in_array($docClass, [null, '', '0'], true)) {
            $providerStr = is_string($provider) ? $provider : 'unknown';
            $this->error("Documentation non trouvée pour {$providerStr}");
            $this->listProviders();

            return self::FAILURE;
        }

        // Si pas de méthode spécifiée, proposer la liste des méthodes disponibles
        if (! $method) {
            $endpoints = $this->getAvailableEndpoints($docClass);

            if ($endpoints !== []) {
                // Ajouter une option pour voir l'aperçu général
                array_unshift($endpoints, '📚 Vue d\'ensemble');

                $selected = $this->choice(
                    'Sélectionnez une méthode ou la vue d\'ensemble',
                    $endpoints,
                    0
                );

                if ($selected === '📚 Vue d\'ensemble') {
                    return $this->showProviderOverview($docClass);
                }

                if (is_array($selected)) {
                    $selected = implode('', $selected);
                }

                return $this->showMethodDoc($docClass, $selected);
            }
        }

        if ($method) {
            return $this->showMethodDoc($docClass, $method);
        }

        return $this->showProviderOverview($docClass);
    }

    /**
     * Récupère la classe de documentation pour un provider
     */
    private function getDocumentationClass(string $provider): ?string
    {
        $providerLower = strtolower($provider);

        if (array_key_exists($providerLower, $this->providerMappings)) {
            $class = $this->providerMappings[$providerLower];
            if (class_exists($class)) {
                return $class;
            }
        }

        // Essayer de deviner la classe si pas dans le mapping
        $possibleClasses = [
            "App\\Integrations\\Vouchers\\{$provider}\\Documentation\\{$provider}ApiDoc",
            "App\\Integrations\\{$provider}\\Documentation\\{$provider}ApiDoc",
            "App\\Documentation\\ThirdPartyApis\\{$provider}ApiDoc",
            "App\\Services\\{$provider}\\Documentation\\{$provider}ApiDoc",
        ];

        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Affiche la vue d'ensemble d'un provider
     */
    private function showProviderOverview(string $docClass): int
    {
        $this->info('📚 Documentation API : '.$docClass::getProviderName());
        $this->info('🔖 Version : '.$docClass::getApiVersion());
        $this->info('✅ Dernière vérification : '.$docClass::getLastVerified());
        $this->newLine();

        // Récupérer tous les endpoints disponibles
        $endpoints = $this->getAvailableEndpoints($docClass);

        if ($endpoints !== []) {
            $this->info('Endpoints disponibles :');
            foreach ($endpoints as $endpoint) {
                $this->line("  • {$endpoint}");
            }

            $this->newLine();
            $providerName = strtolower($docClass::getProviderName());
            $this->info("Utilisez 'php artisan api:doc {$providerName} <method>' pour voir les détails");
        }

        // Vérifier si le service est en ligne (si la méthode existe)
        if (method_exists($docClass, 'isHealthy')) {
            $isHealthy = $docClass::isHealthy();
            $this->newLine();
            $this->info('Status : '.($isHealthy ? '✅ En ligne' : '❌ Hors ligne'));
        }

        return self::SUCCESS;
    }

    /**
     * Affiche la documentation d'une méthode spécifique
     */
    private function showMethodDoc(string $docClass, string $method): int
    {
        if (! method_exists($docClass, $method)) {
            $this->error("Méthode {$method} non documentée");
            $endpoints = $this->getAvailableEndpoints($docClass);
            if ($endpoints !== []) {
                $this->info('Méthodes disponibles : '.implode(', ', $endpoints));
            }

            return self::FAILURE;
        }

        $doc = $docClass::$method();

        if ($this->option('json')) {
            $jsonOutput = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonOutput !== false) {
                $this->line($jsonOutput);
            }

            return self::SUCCESS;
        }

        // Afficher la description
        $this->info('📋 '.($doc['description'] ?? 'Pas de description'));
        $this->info('🔗 '.($doc['endpoint'] ?? 'N/A'));

        if (array_key_exists('documentation_url', $doc)) {
            $this->info('📖 Documentation : '.$doc['documentation_url']);
        }

        $this->newLine();

        // Afficher les paramètres
        if (array_key_exists('parameters', $doc) && ! empty($doc['parameters'])) {
            $this->info('Paramètres :');
            $tableData = [];
            foreach ($doc['parameters'] as $name => $param) {
                $tableData[] = [
                    $name,
                    $param['type'] ?? 'mixed',
                    array_key_exists('required', $param) && $param['required'] ? '✓' : '-',
                    $param['description'] ?? '-',
                ];
            }
            $this->table(
                ['Nom', 'Type', 'Requis', 'Description'],
                $tableData
            );
        }

        // Afficher les headers
        if (array_key_exists('headers', $doc) && ! empty($doc['headers'])) {
            $this->newLine();
            $this->info('Headers :');
            foreach ($doc['headers'] as $header => $value) {
                $this->line("  {$header}: {$value}");
            }
        }

        // Afficher le body (pour POST/PUT)
        if (array_key_exists('body', $doc) && ! empty($doc['body'])) {
            $this->newLine();
            $this->info('Body :');
            $bodyJson = json_encode($doc['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($bodyJson !== false) {
                $this->line($bodyJson);
            }
        }

        // Afficher l'exemple d'appel
        if (array_key_exists('example_call', $doc)) {
            $this->newLine();
            $this->info("Exemple d'appel :");
            $exampleJson = json_encode($doc['example_call'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($exampleJson !== false) {
                $this->line($exampleJson);
            }
        }

        // Afficher les réponses possibles
        if (array_key_exists('responses', $doc)) {
            $this->newLine();
            $this->info('Réponses possibles :');
            foreach ($doc['responses'] as $status => $response) {
                $this->line("  HTTP {$status}:");
                // Limiter l'affichage pour ne pas surcharger
                $display = is_array($response) ? array_slice($response, 0, 3) : $response;
                $displayJson = json_encode($display, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($displayJson !== false) {
                    $this->line($displayJson);
                }
                if (is_array($response) && count($response) > 3) {
                    $this->line('  ...');
                }
            }
        }

        // Afficher les notes
        if (array_key_exists('notes', $doc)) {
            $this->newLine();
            $this->info('Notes :');
            $notes = is_array($doc['notes']) ? $doc['notes'] : [$doc['notes']];
            foreach ($notes as $note) {
                $this->line("  • {$note}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Liste tous les providers disponibles
     */
    private function listProviders(): int
    {
        $this->info('Providers disponibles :');
        $this->newLine();

        foreach ($this->providerMappings as $provider => $class) {
            if (class_exists($class)) {
                $this->line("  • {$provider} - ".$class::getProviderName().' (v'.$class::getApiVersion().')');
            }
        }

        $this->newLine();
        $this->info("Utilisez 'php artisan api:doc <provider>' pour voir la documentation");

        return self::SUCCESS;
    }

    /**
     * Récupère la liste des endpoints disponibles pour une classe de documentation
     *
     * @return array<int, string>
     */
    private function getAvailableEndpoints(string $docClass): array
    {
        if (! class_exists($docClass)) {
            return [];
        }
        /** @var class-string $docClass */
        $reflection = new ReflectionClass($docClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC);

        $endpoints = [];
        $excludedMethods = ['getProviderName', 'getApiVersion', 'getLastVerified', 'getAllEndpoints', 'loadResponse'];

        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (! in_array($methodName, $excludedMethods) && ! str_starts_with($methodName, '__')) {
                $endpoints[] = $methodName;
            }
        }

        return $endpoints;
    }

    /**
     * Récupère la liste des providers disponibles
     *
     * @return array<string, class-string>
     */
    private function getAvailableProviders(): array
    {
        $providers = [];

        foreach ($this->providerMappings as $provider => $class) {
            if (class_exists($class)) {
                $providers[$provider] = $class;
            }
        }

        return $providers;
    }
}
