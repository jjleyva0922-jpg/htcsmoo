<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supply extends Model
{
    use SoftDeletes;

    /** @var array<string, array<int, string>>|null */
    private ?array $pendingOptions = null;

    protected $fillable = [
        'supply_category_id',
        'supply_unit_id',
        'item_name',
        'brand',
        'model_number',
        'category', // Virtual compatibility input; persisted as supply_category_id.
        'requisition_tier',
        'specifications',
        'configurable_options',
        'quantity',
        'unit',
        'unit_price',
        'min_stock_level',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(function (Supply $supply): void {
            if ($supply->pendingOptions !== null) {
                $supply->syncNormalizedOptions($supply->pendingOptions);
                $supply->pendingOptions = null;
            }
        });
    }

    /**
     * Choices presented after a requestor opens this catalog item.
     * Brand is kept first so the experience reads Brand, Size, Color, etc.
     */
    public function requestOptions(): array
    {
        if ($this->pendingOptions !== null) {
            return $this->pendingOptions;
        }

        $options = $this->relationLoaded('options')
            ? $this->options
            : $this->options()->with('values')->get();

        return $options
            ->mapWithKeys(fn (SupplyOption $option) => [
                $option->name => $option->values->pluck('value')->all(),
            ])->all();
    }

    public function getConfigurableOptionsAttribute(): array
    {
        return $this->requestOptions();
    }

    /** @param array<string, mixed>|null $options */
    public function setConfigurableOptionsAttribute(?array $options): void
    {
        $brands = $this->pendingOptions['Brand'] ?? null;
        $this->pendingOptions = $this->normalizeOptionMap($options ?? []);

        if ($brands !== null && ! isset($this->pendingOptions['Brand'])) {
            $this->pendingOptions = ['Brand' => $brands] + $this->pendingOptions;
        }
    }

    public function getBrandAttribute(): ?string
    {
        $brands = $this->requestOptions()['Brand'] ?? [];

        return $brands === [] ? null : implode(', ', $brands);
    }

    public function setBrandAttribute(?string $brand): void
    {
        $brands = collect(explode(',', (string) $brand))
            ->map(fn ($value) => trim($value))
            ->filter()->unique()->values()->all();
        $this->pendingOptions ??= [];

        if ($brands === []) {
            unset($this->pendingOptions['Brand']);
        } else {
            $this->pendingOptions = ['Brand' => $brands] + $this->pendingOptions;
        }
    }

    public function supplyUnit(): BelongsTo
    {
        return $this->belongsTo(SupplyUnit::class);
    }

    public function getUnitAttribute(): ?string
    {
        return $this->supplyUnit?->unit_name;
    }

    public function setUnitAttribute(?string $name): void
    {
        $name = filled($name) ? trim($name) : null;
        $this->attributes['supply_unit_id'] = $name === null
            ? null
            : SupplyUnit::query()->firstOrCreate(['unit_name' => $name])->id;
        $this->unsetRelation('supplyUnit');
    }

    public function options(): HasMany
    {
        return $this->hasMany(SupplyOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @param array<string, mixed> $options */
    public function syncNormalizedOptions(array $options): void
    {
        $options = $this->normalizeOptionMap($options);
        $this->options()->delete();

        $optionOrder = 0;
        foreach ($options as $name => $values) {
            $option = $this->options()->create(['name' => $name, 'sort_order' => $optionOrder]);
            foreach ($values as $valueOrder => $value) {
                $option->values()->create(['value' => $value, 'sort_order' => $valueOrder]);
            }
            $optionOrder++;
        }

        $this->unsetRelation('options');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, array<int, string>>
     */
    private function normalizeOptionMap(array $options): array
    {
        return collect($options)->mapWithKeys(function ($values, $name): array {
            $name = trim((string) $name);
            $normalized = collect(is_array($values) ? $values : [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()->unique()->values()->all();

            return $name === '' || $normalized === [] ? [] : [$name => $normalized];
        })->all();
    }

    /**
     * Category that classifies this supply item.
     */
    public function supplyCategory(): BelongsTo
    {
        return $this->belongsTo(SupplyCategory::class, 'supply_category_id');
    }

    public function getCategoryAttribute(): ?string
    {
        return $this->supplyCategory?->category_name;
    }

    public function setCategoryAttribute(?string $name): void
    {
        $name = filled($name) ? trim($name) : null;
        $this->attributes['supply_category_id'] = $name === null
            ? null
            : SupplyCategory::query()->where('category_name', $name)->value('id');
        $this->unsetRelation('supplyCategory');
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(SupplyInventoryLog::class);
    }

    public function scopeInCategory(Builder $query, string $name): Builder
    {
        return $query->whereHas(
            'supplyCategory',
            fn (Builder $categories) => $categories->where('category_name', $name),
        );
    }
}
