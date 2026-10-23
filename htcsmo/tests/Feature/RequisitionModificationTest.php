<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionModificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requestor_can_edit_and_withdraw_a_pending_requisition(): void
    {
        $requestor = User::factory()->create();
        $requisitionToEdit = $this->createRequisition($requestor, 'pending');
        $requisitionToDelete = $this->createRequisition($requestor, 'pending');

        $this
            ->actingAs($requestor)
            ->get(route('requisitions.edit', $requisitionToEdit))
            ->assertOk()
            ->assertSee('Add from the approved catalog')
            ->assertSee('Requested items');

        $this
            ->actingAs($requestor)
            ->delete(route('requisitions.destroy', $requisitionToDelete))
            ->assertRedirect(route('requests.archive', ['category' => 'withdrawn']));

        $this->assertDatabaseHas('requisitions', ['id' => $requisitionToDelete->id, 'status' => 'withdrawn']);
    }

    public function test_requestor_cannot_edit_update_or_delete_after_department_head_approval(): void
    {
        $requestor = User::factory()->create();
        $requisition = $this->createRequisition($requestor, 'approved_dept');

        $this
            ->actingAs($requestor)
            ->get(route('requisitions.edit', $requisition))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this
            ->from(route('requests.index'))
            ->actingAs($requestor)
            ->patch(route('requisitions.update', $requisition), [
                'request_type' => 'minor',
                'items' => [[
                    'name' => 'Changed item',
                    'specs' => null,
                    'qty' => 1,
                    'price' => 50,
                ]],
            ])
            ->assertRedirect(route('requests.index'))
            ->assertSessionHas('error');

        $this
            ->from(route('requests.index'))
            ->actingAs($requestor)
            ->delete(route('requisitions.destroy', $requisition))
            ->assertRedirect(route('requests.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'status' => 'approved_dept',
        ]);
    }

    public function test_requestor_can_add_edit_and_remove_catalog_items_while_pending(): void
    {
        $requestor = User::factory()->create();
        $requisition = $this->createRequisition($requestor, 'pending');
        $paper = $this->createSupply('Bond Paper', 120, 20);
        $pens = $this->createSupply('Ballpoint Pen', 25, 50);

        $oldItem = RequisitionItem::create([
            'requisition_id' => $requisition->id,
            'supply_id' => $paper->id,
            'item_name' => $paper->item_name,
            'quantity' => 1,
            'unit' => $paper->unit,
            'unit_price' => $paper->unit_price,
            'subtotal' => $paper->unit_price,
        ]);

        $this
            ->actingAs($requestor)
            ->patch(route('requisitions.update', $requisition), [
                'purpose' => 'Updated office supply requirements',
                'items' => [
                    ['supply_id' => $paper->id, 'qty' => 3],
                    ['supply_id' => $pens->id, 'qty' => 4],
                ],
            ])
            ->assertRedirect(route('requests.index'))
            ->assertSessionHas('success');

        $requisition->refresh();
        $this->assertSame(460.0, (float) $requisition->grand_total);
        $this->assertDatabaseMissing('requisition_items', ['id' => $oldItem->id]);
        $this->assertDatabaseHas('requisition_items', [
            'requisition_id' => $requisition->id,
            'supply_id' => $paper->id,
            'quantity' => 3,
            'subtotal' => 360,
        ]);
        $this->assertDatabaseHas('requisition_items', [
            'requisition_id' => $requisition->id,
            'supply_id' => $pens->id,
            'quantity' => 4,
            'subtotal' => 100,
        ]);

        $this
            ->actingAs($requestor)
            ->patch(route('requisitions.update', $requisition), [
                'purpose' => 'Updated office supply requirements',
                'items' => [['supply_id' => $pens->id, 'qty' => 2]],
            ])
            ->assertRedirect(route('requests.index'));

        $this->assertDatabaseMissing('requisition_items', [
            'requisition_id' => $requisition->id,
            'supply_id' => $paper->id,
        ]);
        $this->assertDatabaseHas('requisition_items', [
            'requisition_id' => $requisition->id,
            'supply_id' => $pens->id,
            'quantity' => 2,
        ]);
    }

    private function createRequisition(User $requestor, string $status): Requisition
    {
        return Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => $status,
            'grand_total' => 100,
        ]);
    }

    private function createSupply(string $name, float $price, int $stock): Supply
    {
        return Supply::create([
            'item_name' => $name,
            'category' => 'Office Supplies',
            'requisition_tier' => 'minor',
            'quantity' => $stock,
            'unit' => 'Piece',
            'unit_price' => $price,
            'min_stock_level' => 5,
        ]);
    }
}
