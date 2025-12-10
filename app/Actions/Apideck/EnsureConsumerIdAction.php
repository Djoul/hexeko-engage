<?php

namespace App\Actions\Apideck;

use App\Models\Financer;
use App\Models\User;
use Illuminate\Support\Str;

class EnsureConsumerIdAction
{
    public function execute(Financer $financer, User $user): string
    {
        // 1. Vérifier si un consumer_id existe déjà
        $externalId = $financer->external_id;

        // Handle case where external_id might be a JSON string
        if (is_string($externalId)) {
            $externalId = json_decode($externalId, true) ?? [];
        } elseif (! is_array($externalId)) {
            $externalId = [];
        }

        if (is_array($externalId) &&
            array_key_exists('sirh', $externalId) &&
            is_array($externalId['sirh']) &&
            array_key_exists('consumer_id', $externalId['sirh']) &&
            is_string($externalId['sirh']['consumer_id'])) {
            return $externalId['sirh']['consumer_id'];
        }

        // 2. Générer un nouveau consumer_id
        $consumerId = $this->generateConsumerId($financer);

        // 3. Préparer la structure de données
        if (! is_array($externalId)) {
            $externalId = [];
        }

        $externalId['sirh'] = [
            'consumer_id' => $consumerId,
            'created_at' => now()->toIso8601String(),
            'created_by' => $user->id,
            'provider' => 'apideck',
        ];

        // 4. Sauvegarder en base de données
        $financer->external_id = $externalId;
        $financer->save();

        // 5. Logger l'action
        activity()
            ->performedOn($financer)
            ->causedBy($user)
            ->event('consumer_id.created')
            ->withProperties([
                'consumer_id' => $consumerId,
                'method' => 'auto_generated',
            ])
            ->log('Consumer ID automatiquement généré pour le financeur');

        return $consumerId;
    }

    private function generateConsumerId(Financer $financer): string
    {
        $env = config('app.env');

        $slug = Str::slug($financer->name);

        // Tronquer le slug à 30 caractères si nécessaire
        if (strlen($slug) > 30) {
            $slug = substr($slug, 0, 30);
            // Remove trailing dash if present
            $slug = rtrim($slug, '-');
        }

        $shortUuid = Str::substr(Str::uuid()->toString(), 0, 8);

        return "{$env}-{$slug}-{$shortUuid}";
    }
}
