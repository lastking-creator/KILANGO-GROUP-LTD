<?php

namespace App\Services;

use App\Models\StockLevel;
use App\Models\StockMovement;

/**
 * The one place that changes stock. Every change updates the stock level AND writes a
 * stock movement, so quantities can always be traced. Call inside a DB transaction.
 */
class StockService
{
    public function change(
        int $batchId,
        string $locationType,
        int $locationId,
        float $delta,
        string $movementType,
        ?string $referenceType,
        ?int $referenceId,
        int $userId,
        ?string $reason = null
    ): void {
        $level = StockLevel::where('batch_id', $batchId)
            ->where('location_type', $locationType)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        $new = (float) ($level->quantity ?? 0) + $delta;

        if ($new < -0.0005) {
            throw new InsufficientStockException('There is not enough stock to take this quantity out.');
        }

        if (! $level) {
            $level = new StockLevel([
                'batch_id' => $batchId,
                'location_type' => $locationType,
                'location_id' => $locationId,
            ]);
        }

        $level->quantity = max(0, round($new, 3));
        $level->save();

        StockMovement::create([
            'batch_id' => $batchId,
            'movement_type' => $movementType,
            'location_type' => $locationType,
            'location_id' => $locationId,
            'quantity' => $delta,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }
}
