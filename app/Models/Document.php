<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Document
 *
 * @mixin Model
 *
 * @property-read int $id
 * @property-read string $name
 * @property-read Carbon $expires_at
 * @property int $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 *
 * @method static Builder|Document newModelQuery()
 * @method static Builder|Document newQuery()
 * @method static Builder|Document query()
 * @method static Builder|Document whereCreatedAt($value)
 * @method static Builder|Document whereExpiresAt($value)
 * @method static Builder|Document whereId($value)
 * @method static Builder|Document whereName($value)
 * @method static Builder|Document whereOwnerId($value)
 * @method static Builder|Document whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'expires_at',
        'archived_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->whereNull('archived_at');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
