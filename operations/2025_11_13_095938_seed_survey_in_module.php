<?php

use App\Models\Division;
use App\Models\Financer;
use App\Models\Integration;
use App\Models\Module;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    /**
     * Determine if the operation is being processed asynchronously.
     */
    protected bool $async = true;

    /**
     * The queue that the job will be dispatched to.
     */
    protected string $queue = 'default';

    /**
     * A tag name, that this operation can be filtered by.
     */
    protected ?string $tag = null;

    public function __construct()
    {
        $this->queue = config('queue.connections.sqs.queue');
    }

    /**
     * Process the operation.
     */
    public function process(): void
    {
        // if module already exists, return
        if (! DB::table('modules')->where('id', '019a7c8a-9e05-737b-93ce-fa3299d62ba7')->exists()) {
            // Create module
            DB::table('modules')->insert([
                'id' => '019a7c8a-9e05-737b-93ce-fa3299d62ba7',
                'name' => '{"en-GB":"Survey","fr-FR":"Sondage","fr-BE":"Sondage","fr-CA":"Sondage","fr-CH":"Sondage","de-DE":"Umfrage","de-AT":"Umfrage","de-CH":"Umfrage","es-ES":"Encuesta","es-AR":"Encuesta","es-CO":"Encuesta","es-MX":"Encuesta","it-IT":"Sondaggio","pt-PT":"Sondage","pt-BR":"Sondage","nl-NL":"Sondage","nl-BE":"Sondage","pl-PL":"Badanie","ro-RO":"Sondaj","ru-RU":"Опрос","uk-UA":"Опитування"}',
                'description' => '{"en-GB":"This module is about survey","fr-FR":"Ce module concerne le sondage","fr-BE":"Ce module concerne le sondage","fr-CA":"Ce module concerne le sondage","fr-CH":"Ce module concerne le sondage","de-DE":"Dieses Modul behandelt Umfragen","de-AT":"Dieses Modul behandelt Umfragen","de-CH":"Dieses Modul behandelt Umfragen","es-ES":"Este módulo trata sobre encuestas","es-AR":"Este módulo trata sobre encuestas","es-CO":"Este módulo trata sobre encuestas","es-MX":"Este módulo trata sobre encuestas","it-IT":"Questo modulo riguarda i sondaggi","pt-PT":"Este módulo é sobre enquêtes","pt-BR":"Este módulo é sobre enquêtes","nl-NL":"Deze module gaat over enquêtes","nl-BE":"Deze module gaat over enquêtes","pl-PL":"Ten moduł dotyczy badania","ro-RO":"Acest modul este despre sondaj","ru-RU":"Этот модуль о просмотрех","uk-UA":"Цей модуль про опитування"}',
                'active' => true,
                'settings' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'category' => 'enterprise_life',
                'is_core' => true,
            ]);
        }

        if (! DB::table('integrations')->where('id', '019a7ca4-406b-727a-b1a0-a9c4d160ede3')->exists()) {
            // Create integration
            DB::table('integrations')->insert([
                'id' => '019a7ca4-406b-727a-b1a0-a9c4d160ede3',
                'module_id' => '019a7c8a-9e05-737b-93ce-fa3299d62ba7',
                'name' => 'Survey',
                'type' => 'embedded',
                'description' => null,
                'active' => true,
                'settings' => '{"namespace":"app\/integrations\/Survey","db_prefix":"int_survey","route_prefix":"survey"}',
                'api_endpoint' => 'survey',
                'front_endpoint' => 'surveys',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        Division::get()->each(function (Division $division): void {
            Module::get()
                ->each(function (Module $module) use ($division): void {
                    if (! $division->modules()->wherePivot(
                        'module_id', $module->id)->exists()) {
                        $division->modules()
                            ->attach(
                                $module->id,
                                [
                                    'active' => true,
                                    'price_per_beneficiary' => $module->is_core ? null : 100,
                                ]

                            );
                    }
                });
        });

        Financer::get()->each(function (Financer $financer): void {
            Module::get()
                ->each(function (Module $module) use ($financer): void {
                    if (! $financer->modules()->wherePivot('module_id', $module->id)->exists()) {
                        $financer->modules()
                            ->attach(
                                $module->id,
                                [
                                    'active' => true,
                                    'price_per_beneficiary' => $module->is_core ? null : 200,
                                ]
                            );
                    }
                });
        });

        Financer::get()->each(function (Financer $financer): void {
            Integration::get()->each(function (Integration $integration) use ($financer): void {
                if (! $financer->integrations()->wherePivot('integration_id', $integration->id)->exists()) {
                    $financer->integrations()->attach($integration->id, ['active' => true]);
                }
            });
        });
    }
};
