<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait GeneratesCustomId
{
    /**
     * Automatically generate a custom ID (e.g., "USR0001").
     *
     * @param string $column The column name (e.g., 'user_id')
     * @param string $prefix The prefix to use (e.g., 'USR')
     * @param int $padLength How many digits to pad (e.g., 4)
     */
    protected static function bootGeneratesCustomId()
    {
        static::creating(function ($model) {
            $column = property_exists($model, 'customIdColumn') ? $model->customIdColumn : 'id';
            $prefix = property_exists($model, 'customIdPrefix') ? $model->customIdPrefix : 'ID';
            $padLength = property_exists($model, 'customIdPadLength') ? $model->customIdPadLength : 4;

            // Lock the table to ensure safe concurrent inserts
            $latest = static::lockForUpdate()
                ->orderBy($column, 'desc')
                ->first();

            if (!$latest) {
                $nextNumber = 1;
            } else {
                // Extract numeric part (e.g., USR0005 -> 5)
                $nextNumber = intval(substr($latest->$column, strlen($prefix))) + 1;
            }

            // Assign formatted ID
            $model->$column = $prefix . str_pad($nextNumber, $padLength, '0', STR_PAD_LEFT);
        });
    }
}
