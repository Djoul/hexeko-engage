<?php

declare(strict_types=1);

namespace App\Models\Traits\Division;

trait DivisionAccessorsAndHelpers
{
    /**
     * @param  array<string, string|bool>  $pivot
     */
    public function attachModule(string $moduleId, array $pivot): void
    {
        $this->modules()->attach($moduleId, $pivot !== [] ? $pivot : ['active' => true]);

        activity('division')
            ->performedOn($this)
            ->log("Module ID {$moduleId} attaché à la division {$this->name}");
    }

    public function detachModule(string $moduleId): void
    {
        $this->modules()->detach($moduleId);

        activity('division')
            ->performedOn($this)
            ->log("Module ID {$moduleId} détaché de la division {$this->name}");
    }

    /**
     * @param  array<string,bool>  $pivot
     * */
    public function attachIntegration(string $integrationId, array $pivot): void
    {
        $this->integrations()->attach($integrationId, $pivot !== [] ? $pivot : ['active' => true]);

        activity('division')
            ->performedOn($this)
            ->log("Integration ID {$integrationId} attachée à la division {$this->name}");
    }

    public function detachIntegration(string $integrationId): void
    {
        $this->integrations()->detach($integrationId);

        activity('division')
            ->performedOn($this)
            ->log("Integration ID {$integrationId} détachée de la division {$this->name}");
    }
}
