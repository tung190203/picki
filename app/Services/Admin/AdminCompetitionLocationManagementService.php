<?php

namespace App\Services\Admin;

use App\Http\Resources\Admin\AdminCompetitionLocationResource;
use App\Models\CompetitionLocation;
use App\Models\CompetitionLocationYard;
use App\Services\ImageOptimizationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AdminCompetitionLocationManagementService
{
    public function search(
        int $page,
        int $limit,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDir = 'desc'
    ): LengthAwarePaginator {
        $query = CompetitionLocation::query()
            ->withAdminRelations()
            ->filterForAdmin($filters)
            ->sortForAdmin($sortBy, $sortDir);

        $paginated = $query->paginate($limit, ['*'], 'page', $page);

        return $paginated->setCollection(
            collect(AdminCompetitionLocationResource::collection($paginated->getCollection())->resolve())
        );
    }

    public function getOne(int $id): array
    {
        $location = CompetitionLocation::query()
            ->withAdminRelations()
            ->find($id);

        if (!$location) {
            throw new ModelNotFoundException("Competition location with ID {$id} not found.");
        }

        return (new AdminCompetitionLocationResource($location))->resolve();
    }

    public function updateStatus(CompetitionLocation $location, string $status): CompetitionLocation
    {
        if (!in_array($status, ['active', 'banned'], true)) {
            throw new \InvalidArgumentException("Invalid status value: {$status}. Allowed: active, banned.");
        }

        $location->status = $status;
        $location->save();

        return $location;
    }

    public function toggleBan(CompetitionLocation $location, bool $isBanned): CompetitionLocation
    {
        $location->is_banned = $isBanned;
        $location->save();

        return $location;
    }

    public function createLocation(array $data, $imageFile = null): CompetitionLocation
    {
        return DB::transaction(function () use ($data, $imageFile) {
            $locationData = [
                'name' => $data['name'],
                'location_id' => $data['location_id'] ?? null,
                'address' => $data['address'],
                'phone' => $data['phone'] ?? null,
                'opening_time' => $data['opening_time'] ?? null,
                'closing_time' => $data['closing_time'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'website' => $data['website'] ?? null,
                'note_booking' => $data['note_booking'] ?? null,
                'is_banned' => false,
            ];

            if ($imageFile instanceof UploadedFile) {
                $imagePath = app(ImageOptimizationService::class)->optimize($imageFile, 'competition_locations');
                $locationData['image'] = 'storage/' . $imagePath;
            } elseif (!empty($data['image']) && is_string($data['image'])) {
                $locationData['image'] = $data['image'];
            }

            $location = CompetitionLocation::create($locationData);

            if (isset($data['sport_ids']) && is_array($data['sport_ids'])) {
                $location->sports()->sync($data['sport_ids']);
            }

            if (isset($data['facility_ids']) && is_array($data['facility_ids'])) {
                $location->facilities()->sync($data['facility_ids']);
            }

            if (!empty($data['yards']) && is_array($data['yards'])) {
                foreach ($data['yards'] as $yard) {
                    $yardNumber = trim($yard['yard_number'] ?? '');
                    if ($yardNumber !== '') {
                        $location->competitionLocationYards()->create([
                            'yard_number' => $yardNumber,
                            'yard_type' => (int) ($yard['yard_type'] ?? CompetitionLocationYard::TYPE_INDOOR),
                        ]);
                    }
                }
            }

            return $location->fresh(['location', 'sports', 'facilities', 'competitionLocationYards']);
        });
    }

    public function updateLocation(CompetitionLocation $location, array $data, $imageFile = null): CompetitionLocation
    {
        return DB::transaction(function () use ($location, $data, $imageFile) {
            $locationData = [];
            foreach (['name', 'location_id', 'address', 'phone', 'opening_time', 'closing_time', 'latitude', 'longitude', 'website', 'note_booking'] as $field) {
                if (array_key_exists($field, $data)) {
                    $locationData[$field] = $data[$field];
                }
            }

            if ($imageFile instanceof UploadedFile) {
                $imagePath = app(ImageOptimizationService::class)->optimize($imageFile, 'competition_locations');
                $locationData['image'] = 'storage/' . $imagePath;
            } elseif (array_key_exists('image', $data) && is_string($data['image'])) {
                $locationData['image'] = $data['image'];
            }

            if (!empty($locationData)) {
                $location->update($locationData);
            }

            if (isset($data['sport_ids']) && is_array($data['sport_ids'])) {
                $location->sports()->sync($data['sport_ids']);
            }

            if (isset($data['facility_ids']) && is_array($data['facility_ids'])) {
                $location->facilities()->sync($data['facility_ids']);
            }

            if (isset($data['yards']) && is_array($data['yards'])) {
                $existingYards = $location->competitionLocationYards()->get()->keyBy('id');
                $keepYardIds = [];

                foreach ($data['yards'] as $yardData) {
                    $yardId = $yardData['id'] ?? null;
                    $yardNumber = trim($yardData['yard_number'] ?? '');
                    $yardType = (int) ($yardData['yard_type'] ?? CompetitionLocationYard::TYPE_INDOOR);

                    if ($yardNumber === '') {
                        continue;
                    }

                    if ($yardId && $existingYards->has($yardId)) {
                        $existingYard = $existingYards->get($yardId);
                        $existingYard->update([
                            'yard_number' => $yardNumber,
                            'yard_type' => $yardType,
                        ]);
                        $keepYardIds[] = $yardId;
                    } else {
                        $newYard = $location->competitionLocationYards()->create([
                            'yard_number' => $yardNumber,
                            'yard_type' => $yardType,
                        ]);
                        $keepYardIds[] = $newYard->id;
                    }
                }

                $location->competitionLocationYards()
                    ->whereNotIn('id', $keepYardIds)
                    ->delete();
            }

            return $location->fresh(['location', 'sports', 'facilities', 'competitionLocationYards']);
        });
    }

    public function deleteLocation(CompetitionLocation $location): bool
    {
        return DB::transaction(function () use ($location) {
            $location->sports()->detach();
            $location->facilities()->detach();
            $location->competitionLocationYards()->delete();
            return (bool) $location->delete();
        });
    }
}
