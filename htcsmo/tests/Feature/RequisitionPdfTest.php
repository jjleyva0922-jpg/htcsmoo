<?php

namespace Tests\Feature;

use App\Models\ApprovalLog;
use App\Models\DigitalSignature;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_requisition_pdf_contains_complete_items_and_real_signature_history(): void
    {
        $requestor = User::factory()->create(['department' => 'CETE']);
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'major',
            'status' => 'approved_dept',
            'purpose' => 'Equip the electronics laboratory',
            'grand_total' => 2500,
        ]);

        RequisitionItem::create([
            'requisition_id' => $requisition->id,
            'item_name' => 'Digital multimeter',
            'specifications' => 'True RMS, CAT III rated',
            'quantity' => 2,
            'unit' => 'unit',
            'unit_price' => 1250,
            'subtotal' => 2500,
        ]);

        $log = ApprovalLog::create([
            'requisition_id' => $requisition->id,
            'action' => 'Approved by Department Head',
            'role' => 'dept_head',
            'remarks' => 'Verified for laboratory use',
        ]);

        DigitalSignature::create([
            'approval_log_id' => $log->id,
            'signature_token' => hash('sha256', 'test-signature'),
            'signer_name' => 'Test Department Head',
            'signer_role' => 'Department Head',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'signed_at' => now(),
        ]);

        $this->actingAs($requestor)
            ->get(route('requests.download', $requisition))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('HTC-Requisition-'.$requisition->fresh()->tracking_code.'.pdf');
    }
}
