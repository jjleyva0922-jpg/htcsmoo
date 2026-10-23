<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supply_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['supply_id', 'name']);
        });

        Schema::create('supply_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supply_option_id')->constrained()->cascadeOnDelete();
            $table->string('value', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['supply_option_id', 'value']);
        });

        DB::table('supplies')->orderBy('id')->each(function ($supply): void {
            $options = json_decode($supply->configurable_options ?: '[]', true);
            $options = is_array($options) ? $options : [];

            if (empty($options['Brand']) && filled($supply->brand)) {
                $options = ['Brand' => array_values(array_filter(array_map('trim', explode(',', $supply->brand))))] + $options;
            }

            $this->storeOptions((int) $supply->id, $options);
        });

        Schema::table('supplies', function (Blueprint $table): void {
            $table->dropColumn(['brand', 'configurable_options']);
        });
    }

    public function down(): void
    {
        Schema::table('supplies', function (Blueprint $table): void {
            $table->string('brand')->nullable()->after('item_name');
            $table->json('configurable_options')->nullable()->after('specifications');
        });

        DB::table('supplies')->orderBy('id')->each(function ($supply): void {
            $options = DB::table('supply_options')->where('supply_id', $supply->id)
                ->orderBy('sort_order')->orderBy('id')->get()
                ->mapWithKeys(function ($option): array {
                    $values = DB::table('supply_option_values')->where('supply_option_id', $option->id)
                        ->orderBy('sort_order')->orderBy('id')->pluck('value')->all();

                    return [$option->name => $values];
                })->all();

            DB::table('supplies')->where('id', $supply->id)->update([
                'brand' => isset($options['Brand']) ? implode(', ', $options['Brand']) : null,
                'configurable_options' => $options === [] ? null : json_encode($options),
            ]);
        });

        Schema::dropIfExists('supply_option_values');
        Schema::dropIfExists('supply_options');
    }

    /** @param array<string, mixed> $options */
    private function storeOptions(int $supplyId, array $options): void
    {
        $optionOrder = 0;
        foreach ($options as $name => $values) {
            $name = trim((string) $name);
            $values = array_values(array_unique(array_filter(array_map(
                static fn ($value) => trim((string) $value),
                is_array($values) ? $values : [],
            ))));

            if ($name === '' || $values === []) {
                continue;
            }

            $optionId = DB::table('supply_options')->insertGetId([
                'supply_id' => $supplyId,
                'name' => $name,
                'sort_order' => $optionOrder++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($values as $valueOrder => $value) {
                DB::table('supply_option_values')->insert([
                    'supply_option_id' => $optionId,
                    'value' => $value,
                    'sort_order' => $valueOrder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
