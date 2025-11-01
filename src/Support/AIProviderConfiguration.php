<?php

namespace AIToolkit\AIToolkit\Support;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIProviderConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'api_key',
        'default_model',
        'default_max_tokens',
        'default_temperature',
        'is_default',
        'is_enabled',
        'notes',
    ];

    protected $casts = [
        'default_max_tokens' => 'integer',
        'default_temperature' => 'float',
        'is_default' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    /**
     * Scope to get enabled providers
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope to get the default provider
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Boot method to ensure only one default provider
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->is_default) {
                static::where('is_default', true)
                    ->where('id', '!=', $model->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
