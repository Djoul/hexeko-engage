<?php

namespace App\Integrations\HRTools\Services;

use App\Integrations\HRTools\Models\Link;
use App\Services\Media\HeicImageConversionService;
use Arr;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class HRToolsLinkService
{
    public function __construct(
        private readonly HeicImageConversionService $heicConversionService
    ) {}

    /**
     * Récupère tous les liens.
     *
     * @param  array<int, string>  $relations
     * @return \Illuminate\Support\Collection<int, Link>
     */
    public function all(int $perPage = 15, int $page = 1, array $relations = []): \Illuminate\Support\Collection
    {
        /** @var Collection<int, Link> $allItems */
        $allItems = Link::query()
            ->with(['financer'])
            ->pipeFiltered()
            ->get();

        // Calculate offset for pagination
        $offset = ($page - 1) * $perPage;

        /** @var \Illuminate\Support\Collection<int, Link> $collection */
        $collection = $allItems->skip($offset)->take($perPage);

        return $collection;
    }

    /**
     * Crée un nouveau lien.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeLink(array $data): Link
    {
        // Déterminer la position la plus élevée pour le financeur
        $maxPosition = Link::where('financer_id', $data['financer_id'])->max('position');

        // Définir la position du nouveau lien comme la position maximale + 1
        if (! array_key_exists('position', $data)) {
            // Convertir explicitement en entier pour éviter les erreurs de type
            $nextPosition = 0;
            if ($maxPosition !== null) {
                /** @var int|float $maxPosition */
                $nextPosition = (int) $maxPosition;
                $nextPosition++;
            }
            $data['position'] = $nextPosition;
        }

        $link = Link::create(Arr::except($data, 'logo'));

        if (array_key_exists('logo', $data) && $data['logo'] !== null && ($data['logo'] instanceof UploadedFile || is_string($data['logo']))) {
            // Convert HEIC to JPG if needed (only for base64 strings, not UploadedFile)
            $logoData = $data['logo'];
            if (is_string($logoData)) {
                $logoData = $this->heicConversionService->processImage($logoData);
            }

            $link->addMediaFromBase64($logoData)->toMediaCollection('logo');
        }

        return $link;
    }

    /**
     * Met à jour un lien existant.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Exception Si le lien n'est pas trouvé
     */
    public function updateLink(array $data, string $id): Link
    {
        $link = self::find($id);

        if (! $link instanceof Link) {
            throw new Exception('Link not found');
        }

        if (array_key_exists('logo', $data) && $data['logo'] !== null && ($data['logo'] instanceof UploadedFile || is_string($data['logo']))) {
            // Convert HEIC to JPG if needed (only for base64 strings, not UploadedFile)
            $logoData = $data['logo'];
            if (is_string($logoData)) {
                $logoData = $this->heicConversionService->processImage($logoData);
            }

            $link->addMediaFromBase64($logoData)->toMediaCollection('logo');
        }

        $link->update(Arr::except($data, 'logo'));

        return $link;
    }

    /**
     * Récupère un lien par son ID.
     */
    public function find(string $id): ?Link
    {
        // First try to find with related scope
        $link = Link::query()
            ->with(['financer'])
            ->find($id);

        if (! $link instanceof Link) {
            throw new ModelNotFoundException('Link not found');
        }

        return $link;
    }

    /**
     * Supprime un lien.
     *
     * @throws Exception Si le lien n'est pas trouvé
     */
    public function deleteLink(string $id): bool
    {
        $link = self::find($id);

        if ($link instanceof Link) {
            return (bool) Link::destroy($id);
        }
        throw new Exception('Link not found');
    }

    /**
     * Récupère les liens par l'ID du financeur.
     *
     * @return Collection<int, Link>
     */
    public function getLinkByFinancerId(string $financerId): Collection
    {
        return Link::where('financer_id', $financerId)
            ->orderBy('position')
            ->get();
    }

    /**
     * Réordonne les liens.
     *
     * @param  array<int, array<string, mixed>>  $links
     */
    public function reorderLinks(array $links): bool
    {

        DB::beginTransaction();

        try {
            // Extract link IDs to fetch all links in a single query (fixes N+1 issue)
            $linkIds = array_column($links, 'id');

            // Fetch all links at once
            $existingLinks = Link::whereIn('id', $linkIds)->get()->keyBy('id');

            // Verify all links exist first
            foreach ($links as $linkData) {
                /** @var string $linkId */
                $linkId = $linkData['id'];

                if (! $existingLinks->has($linkId)) {
                    throw new ModelNotFoundException("Link with ID $linkId not found");
                }
            }

            // Perform bulk updates to avoid N+1 queries from individual saves
            foreach ($links as $linkData) {
                /** @var string $linkId */
                $linkId = $linkData['id'];

                // Convertir explicitement en entier pour éviter les erreurs de type
                $position = 0;
                if (array_key_exists('position', $linkData)) {
                    /** @var int|float|string $positionValue */
                    $positionValue = $linkData['position'];
                    $position = (int) $positionValue;
                }

                // Update directly with query builder to avoid model events and reloading
                Link::where('id', $linkId)->update([
                    'position' => $position,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get links by financer ID with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, Link>
     */
    public function getByFinancerId(string $financerId, array $filters = []): \Illuminate\Support\Collection
    {
        $query = Link::where('financer_id', $financerId);

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('position')->get();
    }

    /**
     * Update configuration for a financer.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function updateConfiguration(string $financerId, array $configuration): bool
    {
        // This would typically update configuration in a separate configuration table
        // For now, we'll just return true to satisfy the interface
        return true;
    }

    /**
     * Get user's pinned links.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getUserPinnedLinks(string $userId): \Illuminate\Support\Collection
    {
        /** @var \Illuminate\Support\Collection<int, string> */
        $result = collect(
            DB::table('int_outils_rh_link_user')
                ->where('user_id', $userId)
                ->pluck('link_id')
        );

        return $result;
    }

    /**
     * Update link by external ID.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateByExternalId(string $externalId, array $data): bool
    {
        return Link::where('external_id', $externalId)->update($data) > 0;
    }

    /**
     * Create link from external data.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromExternalData(array $data, string $financerId): Link
    {
        $data['financer_id'] = $financerId;

        return $this->storeLink($data);
    }

    /**
     * Get link by external ID.
     */
    public function getByExternalId(string $externalId): ?Link
    {
        return Link::where('external_id', $externalId)->first();
    }

    /**
     * Sync user's pinned links.
     *
     * @param  array<int, string>  $pinnedLinkIds
     */
    public function syncUserPinnedLinks(string $userId, array $pinnedLinkIds): bool
    {
        DB::table('int_outils_rh_link_user')
            ->where('user_id', $userId)
            ->delete();

        foreach ($pinnedLinkIds as $linkId) {
            DB::table('int_outils_rh_link_user')->insert([
                'user_id' => $userId,
                'link_id' => $linkId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return true;
    }
}
