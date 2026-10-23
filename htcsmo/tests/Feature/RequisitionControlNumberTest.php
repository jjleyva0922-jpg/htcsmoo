<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\User;
use Database\Seeders\BackfillTrackingNumbersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequisitionControlNumberTest extends TestCase
{
    use RefreshDatabase;

    public static function tiers(): array
    {
        return [
            'minor' => ['minor', 'MIN-CETE-2026100001'],
            'major' => ['major', 'MAJ-CETE-2026200001'],
        ];
    }

    #[DataProvider('tiers')]
    public function test_control_number_is_saved_with_year_tier_and_five_digit_id(string $tier, string $expected): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22));
        $owner = User::factory()->create(['department' => 'CETE']);
        $requisition = Requisition::create([
            'user_id' => $owner->id,
            'request_type' => $tier,
            'status' => 'pending',
        ]);

        $this->assertSame($expected, $requisition->tracking_number);
        $this->assertSame($expected, $requisition->fresh()->tracking_code);
        $this->assertDatabaseHas('requisitions', ['id' => $requisition->id, 'tracking_number' => $expected]);

        $this->actingAs($owner)->get(route('requests.index', ['search' => $expected]))
            ->assertOk()->assertSee($expected);

        $this->travelTo(now()->setDate(2027, 1, 1));
        $owner->update(['department' => 'CBMA']);
        $requisition->update(['purpose' => 'An updated purpose.']);

        $this->assertSame($expected, $requisition->fresh()->tracking_code);
    }

    public function test_fallback_and_backfill_use_the_historical_year(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 1));
        $owner = User::factory()->create(['department' => 'CETE']);
        $submitted = Requisition::create([
            'user_id' => $owner->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'submitted_at' => '2026-12-31 10:00:00',
        ]);
        $legacy = Requisition::create([
            'user_id' => $owner->id,
            'request_type' => 'major',
            'status' => 'pending',
        ]);
        DB::table('requisitions')->where('id', $submitted->id)->update(['tracking_number' => null]);
        DB::table('requisitions')->where('id', $legacy->id)->update([
            'tracking_number' => null,
            'submitted_at' => null,
            'created_at' => '2025-12-31 10:00:00',
        ]);

        $this->assertSame('MIN-CETE-2026100001', $submitted->fresh()->tracking_code);
        $this->assertSame('MAJ-CETE-2025200002', $legacy->fresh()->tracking_code);

        $this->seed(BackfillTrackingNumbersSeeder::class);

        $this->assertSame('MIN-CETE-2026100001', $submitted->fresh()->tracking_number);
        $this->assertSame('MAJ-CETE-2025200002', $legacy->fresh()->tracking_number);
    }

    public function test_existing_assigned_control_numbers_are_preserved(): void
    {
        $owner = User::factory()->create(['department' => 'CETE']);
        $requisition = Requisition::create([
            'user_id' => $owner->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'tracking_number' => 'MIN-CETE-20000001',
        ]);

        $this->seed(BackfillTrackingNumbersSeeder::class);

        $this->assertSame('MIN-CETE-20000001', $requisition->fresh()->tracking_code);
    }

    public function test_control_number_uses_htc_without_a_department_and_does_not_truncate_large_ids(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22));
        $owner = User::factory()->create(['department_id' => null, 'department' => null]);
        $requisition = new Requisition([
            'user_id' => $owner->id,
            'request_type' => 'minor',
            'submitted_at' => now(),
        ]);
        $requisition->id = 100000;

        $this->assertSame('MIN-HTC-20261100000', $requisition->tracking_code);
    }
}
