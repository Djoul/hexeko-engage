<?php

declare(strict_types=1);

namespace App\Integrations\HRTools\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleLinkPinAction
{
    public function execute(User $user, string $linkId): bool
    {
        /** @var object{pinned: bool}|null $record */
        $record = DB::table('int_outils_rh_link_user')
            ->where('user_id', $user->id)
            ->where('link_id', $linkId)
            ->first();

        if ($record) {
            // Toggle the pinned status
            $newPinnedStatus = ! $record->pinned;

            DB::table('int_outils_rh_link_user')
                ->where('user_id', $user->id)
                ->where('link_id', $linkId)
                ->update([
                    'pinned' => $newPinnedStatus,
                    'updated_at' => now(),
                ]);

            return $newPinnedStatus;
        }
        // Create new record with pinned = true
        DB::table('int_outils_rh_link_user')->insert([
            'user_id' => $user->id,
            'link_id' => $linkId,
            'pinned' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true; // Pinned
    }
}
