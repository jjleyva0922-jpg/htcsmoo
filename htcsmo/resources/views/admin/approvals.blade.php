<x-app-layout>
    <x-slot name="header">
        {{ Auth::user()->role == 'smo' ? __('Fulfillment Queue') : __('Pending Request Approvals') }}
    </x-slot>

    <div class="container-fluid py-2 approval-workspace"
         x-data="approvalManager()" data-filter-workspace="approvals"
         @if($focusedRequest) data-focused-request-id="{{ $focusedRequest->id }}" @endif>
        <!-- ALERTS -->
        @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if(session('success'))
            <div role="status" class="alert alert-success border-0 shadow-sm mb-4 rounded-4 d-flex align-items-center">
                <i data-lucide="check-circle" class="me-2" style="width: 20px;"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div role="alert" class="alert alert-danger border-0 shadow-sm mb-4 rounded-4 d-flex align-items-center">
                <i data-lucide="alert-triangle" class="me-2" style="width: 20px;"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        @include('admin.partials.approval-filters')

        <div id="approval-results" class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white" data-live-region="approval-queue" data-filter-region="queue" data-result-count="{{ $clarificationRequests->total() + (Auth::user()->role === 'smo' ? $pendingMinor->total() + $pendingMajor->total() + $activeWork->total() : $pendingRequests->total()) }}" data-filter-results tabindex="-1" aria-label="Approval queue results">
            <div class="card-header bg-white border-0 p-4 pb-2">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold mb-0">Requisition Queue</h5>
                        <p class="text-muted small mb-0">Review approvals, prepare all requested stock, and confirm pickup. Amounts are requested values, not actual expenditure.</p>
                    </div>
                </div>

                <!-- TABS NAVIGATION -->
                @php
                    $queueTabs = Auth::user()->role === 'smo'
                        ? ['minor' => ['Minor waiting', $pendingMinor], 'major' => ['Major waiting', $pendingMajor], 'work' => ['Active work / pickup', $activeWork]]
                        : ['pending' => ['Awaiting your review', $pendingRequests]];
                    $queueTabs['clarification'] = ['Clarification / inquiry', $clarificationRequests];
                @endphp
                <nav class="nav nav-pills gap-2 flex-wrap" aria-label="Requisition queue groups">
                    @foreach($queueTabs as $tab => [$label, $records])
                        <a id="{{ $tab }}-tab" class="nav-link rounded-pill {{ $selectedTab === $tab ? 'active' : '' }}"
                           href="{{ route('admin.approvals', [...request()->except('request'), 'tab' => $tab]) }}#approval-results"
                           data-filter-link @if($selectedTab === $tab) aria-current="page" @endif>
                            {{ $label }} <span class="badge bg-light text-dark">{{ $records->total() }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="card-body p-0">
                <div class="tab-content">
                    @if(Auth::user()->role == 'smo')
                        <!-- TAB 1: MINOR (SMO ONLY) -->
                        <div class="{{ $selectedTab === 'minor' ? 'd-block' : 'd-none' }}" id="minor" role="region" aria-labelledby="minor-tab">
                            @include('admin.partials.approvals-table', ['requests' => $pendingMinor])
                        </div>
                        <!-- TAB 2: MAJOR (SMO ONLY) -->
                        <div class="{{ $selectedTab === 'major' ? 'd-block' : 'd-none' }}" id="major" role="region" aria-labelledby="major-tab">
                            @include('admin.partials.approvals-table', ['requests' => $pendingMajor])
                        </div>
                        <div class="{{ $selectedTab === 'work' ? 'd-block' : 'd-none' }}" id="work" role="region" aria-labelledby="work-tab">
                            @include('admin.partials.approvals-table', ['requests' => $activeWork])
                        </div>
                    @else
                        <!-- STANDARD VIEW FOR SIGNATORIES -->
                        <div class="{{ $selectedTab === 'pending' ? 'd-block' : 'd-none' }}" id="pending" role="region" aria-labelledby="pending-tab">
                            @include('admin.partials.approvals-table', ['requests' => $pendingRequests])
                        </div>
                    @endif

                    <!-- TAB 3: FOR OFFICE CLARIFICATION (BOTH SMO & SIGNATORIES) -->
                    <div class="{{ $selectedTab === 'clarification' ? 'd-block' : 'd-none' }}" id="clarification" role="region" aria-labelledby="clarification-tab">
                        @if($clarificationRequests->total() > 0)
                            <div class="p-3 bg-warning-subtle text-dark border-bottom small fw-semibold d-flex align-items-center">
                                <i data-lucide="alert-circle" class="me-2 text-warning" style="width: 18px;"></i>
                                The items below have been flagged for inquiry. The requestor and the SMO In-Charge have been asked to appear at the signatory's office for a consultation.
                            </div>
                        @endif
                        @include('admin.partials.approvals-table', ['requests' => $clarificationRequests])
                    </div>
                </div>
            </div>
        </div>

        <!-- REVIEW MODAL -->
        <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true" aria-labelledby="reviewModalTitle">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-white">
                    <div class="modal-header border-0 bg-light p-4">
                        <div>
                            <h5 class="modal-title fw-bold" id="reviewModalTitle">Review Requisition
                                <span class="text-success font-monospace" x-text="selectedReq.tracking_code"></span>
                            </h5>
                            <span class="badge bg-dark rounded-pill px-3 mt-1" style="font-size: var(--text-caption);" x-text="(selectedReq.request_type || '').toUpperCase() + ' TIER'"></span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close requisition review"></button>
                    </div>

                    <div class="modal-body p-4">
                        <!-- REQUESTOR INFO -->
                        <div class="row mb-4 bg-light p-3 rounded-4 g-2">
                            <div class="col-md-6">
                                <small class="text-muted text-uppercase fw-bold" style="font-size: var(--text-caption);">Official Requestor</small>
                                <div class="fw-bold fs-6 text-dark" x-text="requestorName"></div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <small class="text-muted text-uppercase fw-bold" style="font-size: var(--text-caption);">Department / Unit</small>
                                <div class="fw-bold fs-6 text-success" x-text="requestorDept"></div>
                            </div>
                        </div>

                        <!-- REQUESTOR'S STATED PURPOSE -->
                        <div class="approval-purpose mb-4">
                            <div class="approval-purpose-icon"><i data-lucide="message-square-text"></i></div>
                            <div class="flex-grow-1">
                                <small class="text-uppercase fw-bold">Reason for Request</small>
                                <p class="mb-0" x-text="selectedReq.purpose || 'No reason was recorded for this requisition.'"></p>
                            </div>
                        </div>

                        <!-- DYNAMIC TIMELINE (Alpine Real-Time for all Signatories) -->
                        <div class="workflow-stepper-scroll mb-4 px-2" tabindex="0" aria-label="Approval flow; scroll horizontally to see every step">
                            <div class="tracking-stepper">
                                <!-- Step 1 -->
                                <div class="step-item completed">
                                    <div class="step-icon"><i data-lucide="send"></i></div>
                                    <div class="step-label">Submitted</div>
                                </div>

                                <!-- Step 2: Dept Head -->
                                <div class="step-item"
                                    :class="{
                                        'completed': ['approved_dept', 'approved_vp', 'approved_provost', 'approved_president', 'processing', 'ready_for_pickup', 'completed', 'released'].includes(selectedReq.status)
                                            || (selectedReq.status === 'for_clarification' && ['vp_finance', 'vp_admin', 'provost', 'president'].includes(questioningRole)),
                                        'questioning': selectedReq.status === 'for_clarification' && (!questioningRole || questioningRole === 'dept_head'),
                                        'active': selectedReq.status === 'pending'
                                    }">
                                    <div class="step-icon">
                                        <i :data-lucide="selectedReq.status === 'for_clarification' && (!questioningRole || questioningRole === 'dept_head') ? 'help-circle' : 'user-check'"></i>
                                    </div>
                                    <div class="step-label" x-text="selectedReq.status === 'for_clarification' && (!questioningRole || questioningRole === 'dept_head') ? 'Dept Head (Questioning)' : 'Dept Head'"></div>
                                </div>

                                <!-- Step 3: VP Finance / Admin -->
                                <div class="step-item"
                                    :class="{
                                        'completed': ['approved_vp', 'approved_provost', 'approved_president', 'processing', 'ready_for_pickup', 'completed', 'released'].includes(selectedReq.status)
                                            || (selectedReq.status === 'for_clarification' && ['provost', 'president'].includes(questioningRole)),
                                        'questioning': selectedReq.status === 'for_clarification' && ['vp_finance', 'vp_admin'].includes(questioningRole),
                                        'active': selectedReq.status === 'approved_dept'
                                    }">
                                    <div class="step-icon">
                                        <i :data-lucide="selectedReq.status === 'for_clarification' && ['vp_finance', 'vp_admin'].includes(questioningRole) ? 'help-circle' : 'shield-check'"></i>
                                    </div>
                                    <div class="step-label" x-text="(selectedReq.request_type == 'minor' ? 'VP Finance' : 'VP Admin') + (selectedReq.status === 'for_clarification' && ['vp_finance', 'vp_admin'].includes(questioningRole) ? ' (Questioning)' : '')"></div>
                                </div>

                                <!-- Step 4: Provost (Major Only) -->
                                <template x-if="selectedReq.request_type == 'major'">
                                    <div class="step-item"
                                        :class="{
                                            'completed': ['approved_provost', 'approved_president', 'processing', 'ready_for_pickup', 'completed', 'released'].includes(selectedReq.status)
                                                || (selectedReq.status === 'for_clarification' && questioningRole === 'president'),
                                            'questioning': selectedReq.status === 'for_clarification' && questioningRole === 'provost',
                                            'active': selectedReq.status === 'approved_vp'
                                        }">
                                        <div class="step-icon">
                                            <i :data-lucide="selectedReq.status === 'for_clarification' && questioningRole === 'provost' ? 'help-circle' : 'graduation-cap'"></i>
                                        </div>
                                        <div class="step-label" x-text="'Provost' + (selectedReq.status === 'for_clarification' && questioningRole === 'provost' ? ' (Questioning)' : '')"></div>
                                    </div>
                                </template>

                                <!-- Step 5: President (Major Only) -->
                                <template x-if="selectedReq.request_type == 'major'">
                                    <div class="step-item"
                                        :class="{
                                            'completed': ['approved_president', 'processing', 'ready_for_pickup', 'completed', 'released'].includes(selectedReq.status),
                                            'questioning': selectedReq.status === 'for_clarification' && questioningRole === 'president',
                                            'active': selectedReq.status === 'approved_provost'
                                        }">
                                        <div class="step-icon">
                                            <i :data-lucide="selectedReq.status === 'for_clarification' && questioningRole === 'president' ? 'help-circle' : 'award'"></i>
                                        </div>
                                        <div class="step-label" x-text="'President' + (selectedReq.status === 'for_clarification' && questioningRole === 'president' ? ' (Questioning)' : '')"></div>
                                    </div>
                                </template>

                                <!-- Step 6: SMO processing -->
                                <div class="step-item" :class="{'completed': ['ready_for_pickup', 'completed', 'released'].includes(selectedReq.status), 'active': selectedReq.status === 'processing'}">
                                    <div class="step-icon"><i data-lucide="package-open"></i></div>
                                    <div class="step-label">Processing</div>
                                </div>

                                <!-- Step 7: Pickup -->
                                <div class="step-item" :class="{'completed': ['completed', 'released'].includes(selectedReq.status), 'active': selectedReq.status === 'ready_for_pickup'}">
                                    <div class="step-icon"><i data-lucide="package-check"></i></div>
                                    <div class="step-label">Ready for Pickup</div>
                                </div>

                                <!-- Step 8: Completed -->
                                <div class="step-item" :class="['completed', 'released'].includes(selectedReq.status) ? 'completed' : ''">
                                    <div class="step-icon"><i data-lucide="circle-check"></i></div>
                                    <div class="step-label">Completed</div>
                                </div>
                            </div>
                        </div>

                        <section class="mb-4" aria-labelledby="recordedHistoryTitle">
                            <h6 id="recordedHistoryTitle" class="fw-bold">Recorded workflow history</h6>
                            <p class="small text-muted">The route starts after the requestor's own approval office. The entries below show recorded actions and remarks.</p>
                            <p class="small">Submitted: <span x-text="formatTimestamp(selectedReq.submitted_at || selectedReq.created_at)"></span> · Current status: <strong x-text="selectedReq.queue_status"></strong></p>
                            <ol class="small ps-3">
                                <template x-for="log in sortedLogs()" :key="log.id">
                                    <li class="mb-2">
                                        <strong x-text="log.action"></strong>
                                        <div><span x-text="formatTimestamp(log.created_at)"></span> · <span x-text="log.actor?.name || log.role"></span></div>
                                        <p class="mb-0 queue-remarks" x-text="log.remarks || 'No remarks recorded.'"></p>
                                    </li>
                                </template>
                            </ol>
                            <p class="small queue-remarks" x-show="selectedReq.remarks" x-text="selectedReq.remarks"></p>
                        </section>
                        @if(Auth::user()->role === 'smo')
                            <div class="alert alert-warning" role="status" x-show="selectedReq.can_start_processing || selectedReq.status === 'processing'">
                                <strong>Stock readiness snapshot</strong>
                                <p class="small mb-1">Available stock is shared. Combined demand includes duplicate lines in this request. Stock is checked again under lock before the full request is marked ready.</p>
                                <ul class="mb-0"><template x-for="(error, index) in (selectedReq.stock_errors || [])" :key="index"><li x-text="error"></li></template></ul>
                                <span x-show="!(selectedReq.stock_errors || []).length">All lines currently have sufficient catalog stock.</span>
                            </div>
                            <p class="small text-muted" x-show="selectedReq.status === 'ready_for_pickup'">Stock was deducted when this request became ready. Confirming pickup will not deduct it again.</p>
                            <p class="alert alert-info" x-show="selectedReq.status === 'for_clarification'">Coordinate the recorded inquiry with the requestor and reviewing office. SMO cannot approve or fulfill a request while clarification is pending.</p>
                        @endif

                        <!-- REQUESTED ITEMS TABLE -->
                        <h6 class="fw-bold mb-3 text-dark">Requested Item Breakdown</h6>
                        <div class="table-responsive border rounded-3 mb-4">
                            <table class="table table-sm mb-0 workflow-responsive-table approval-items-table">
                                <thead class="bg-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-3 py-2">Item Name & Specs</th>
                                        <th class="py-2 text-center">Qty</th>
                                        <th class="py-2 text-end">Unit price</th>
                                        @if(Auth::user()->role === 'smo')<th class="py-2">Available / combined need</th>@endif
                                        <th class="text-end pe-3 py-2">Requested subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="item in selectedItems" :key="item.id">
                                        <tr>
                                            <td class="ps-3 py-2" data-label="Item">
                                                <div class="fw-bold text-dark" x-text="item.item_name"></div>
                                                <small class="text-muted" x-text="item.specifications"></small>
                                            </td>
                                            <td class="text-center py-2" data-label="Quantity" x-text="item.quantity + ' ' + (item.unit || 'pc')"></td>
                                            <td class="text-end py-2" data-label="Unit price" x-text="'₱' + Number(item.unit_price || 0).toLocaleString(undefined, {minimumFractionDigits:2})"></td>
                                            @if(Auth::user()->role === 'smo')
                                                <td class="py-2" data-label="Available / combined need">
                                                    <span x-text="item.stock_readiness?.available ?? 'Unavailable'"></span> /
                                                    <span x-text="item.stock_readiness?.requested_total ?? 'Unresolved'"></span>
                                                    <strong class="d-block" x-text="item.stock_readiness?.shortage ? 'Shortage / unresolved' : 'Sufficient now'"></strong>
                                                </td>
                                            @endif
                                            <td class="text-end pe-3 py-2 fw-bold text-dark" data-label="Requested subtotal" x-text="'₱' + parseFloat(item.subtotal).toLocaleString(undefined, {minimumFractionDigits:2})"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-light fw-bold">
                                    <tr>
                                        <td colspan="{{ Auth::user()->role === 'smo' ? 4 : 3 }}" class="ps-3 py-2 text-uppercase small text-dark">Requested total</td>
                                        <td class="text-end pe-3 py-2 text-success h6 mb-0 fw-black" x-text="'₱' + parseFloat(selectedReq.grand_total || 0).toLocaleString(undefined, {minimumFractionDigits:2})"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- ACTION FORMS -->
                        <div class="mt-4">
                            @if(Auth::user()->role == 'smo')
                                <template x-if="selectedReq.can_start_processing">
                                    <form :action="actionUrl('processing')" method="POST" data-action-progress data-loading-message="Starting SMO processing..." :data-confirm="'Start preparing supplies for ' + (selectedReq.tracking_code || ('Requisition #' + selectedReq.id)) + '?'" data-confirm-title="Start processing?" data-confirm-accept="Start processing">
                                        @csrf
                                        <input type="hidden" name="queue_query" :value="queueQuery">
                                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm d-flex align-items-center justify-content-center">
                                            <i data-lucide="package-open" class="me-2" style="width:18px;"></i> START PROCESSING
                                        </button>
                                    </form>
                                </template>
                                <template x-if="selectedReq.status === 'processing'">
                                    <form :action="actionUrl('ready-for-pickup')" method="POST" data-action-progress data-loading-message="Marking supplies ready for pickup..." @submit="handleReleaseSubmit($event)" @htc:session-check-failed="releaseConfirmed = false; releaseForm = null">
                                        @csrf
                                        <input type="hidden" name="queue_query" :value="queueQuery">
                                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm d-flex align-items-center justify-content-center">
                                            <i data-lucide="package-check" class="me-2" style="width:18px;"></i> MARK READY FOR PICKUP
                                        </button>
                                    </form>
                                </template>
                                <template x-if="selectedReq.status === 'ready_for_pickup'">
                                    <form :action="actionUrl('complete')" method="POST" data-action-progress data-loading-message="Confirming pickup and completion..." :data-confirm="'Confirm that the requestor has collected the supplies for ' + (selectedReq.tracking_code || ('Requisition #' + selectedReq.id)) + '. This will mark the requisition as completed.'" data-confirm-title="Confirm pickup and complete?" data-confirm-accept="Complete requisition">
                                        @csrf
                                        <input type="hidden" name="queue_query" :value="queueQuery">
                                        <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-3 shadow-sm d-flex align-items-center justify-content-center">
                                            <i data-lucide="circle-check" class="me-2" style="width:18px;"></i> CONFIRM PICKUP &amp; COMPLETE
                                        </button>
                                    </form>
                                </template>
                            @else
                                <!-- SIGNATORY 3-BUTTON DECISION FORM -->
                                <!-- SIGNATORY DECISION FORM -->
                                <form :action="actionUrl('status')" method="POST" data-action-progress data-loading-message="Saving your decision...">
                                    @csrf
                                        <input type="hidden" name="queue_query" :value="queueQuery">
                                    <div class="mb-3">
                                        <label for="approvalRemarks" class="form-label small fw-bold text-muted text-uppercase label-letter-spacing" style="font-size: var(--text-caption);">
                                            Signatory Remarks / Decision Notes
                                        </label>
                                        <textarea id="approvalRemarks" name="remarks" maxlength="1000" class="form-control border-0 bg-light rounded-3 shadow-none" rows="2" placeholder="State reason if rejecting or requesting office inquiry..."></textarea>
                                    </div>

                                    <div class="row g-2">
                                        <!-- 1. REJECT BUTTON -->
                                        <div :class="selectedReq.status === 'for_clarification' ? 'col-6' : 'col-md-4'">
                                            <button name="status" value="rejected" type="submit" data-loading-text="Recording your rejection..." class="btn btn-outline-danger fw-bold w-100 py-2 rounded-3" :data-confirm="'Reject ' + (selectedReq.tracking_code || ('Requisition #' + selectedReq.id)) + '? Your remarks will be recorded and the requestor will be notified.'" data-confirm-title="Reject this requisition?" data-confirm-accept="Reject requisition" data-confirm-tone="danger">
                                                <i data-lucide="x-circle" class="me-1" style="width:14px; vertical-align: middle;"></i> Reject
                                            </button>
                                        </div>

                                        <!-- 2. OFFICE INQUIRY (Hides automatically if ALREADY under clarification) -->
                                        <div class="col-md-4" x-show="selectedReq.status !== 'for_clarification'">
                                            <button name="status" value="clarification" type="submit" data-loading-text="Requesting office clarification..." class="btn btn-warning fw-bold text-dark w-100 py-2 rounded-3 shadow-sm" :data-confirm="'Request clarification for ' + (selectedReq.tracking_code || ('Requisition #' + selectedReq.id)) + '? Your remarks will be sent to the requestor.'" data-confirm-title="Request office inquiry?" data-confirm-accept="Request clarification">
                                                <i data-lucide="help-circle" class="me-1" style="width:14px; vertical-align: middle;"></i> Office Inquiry
                                            </button>
                                        </div>

                                        <!-- 3. APPROVE BUTTON -->
                                        <div :class="selectedReq.status === 'for_clarification' ? 'col-6' : 'col-md-4'">
                                            <button name="status" value="approved" type="submit" data-loading-text="Recording your approval..." class="btn btn-success fw-bold w-100 py-2 shadow-sm rounded-3" :data-confirm="'Record your approval for ' + (selectedReq.tracking_code || ('Requisition #' + selectedReq.id)) + '? The request will advance to the next stage.'" data-confirm-title="Approve this requisition?" data-confirm-accept="Approve requisition">
                                                <i data-lucide="check-circle" class="me-1" style="width:14px; vertical-align: middle;"></i>
                                                <span x-text="selectedReq.status === 'for_clarification' ? 'Approve (Satisfied)' : 'Approve'"></span>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(Auth::user()->role == 'smo')
            <!-- RELEASE CONFIRMATION MODAL -->
            <div class="modal fade" id="releaseConfirmationModal" tabindex="-1" aria-hidden="true" aria-labelledby="releaseConfirmationTitle">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="modal-body p-4 p-md-5 text-center">
                            <div class="release-confirmation-icon mx-auto mb-3" aria-hidden="true">
                                <i data-lucide="package-check"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-2" id="releaseConfirmationTitle">Mark supplies ready for pickup?</h5>
                            <p class="text-muted small mb-3">
                                You are about to prepare
                                <strong class="text-dark" x-text="selectedReq.tracking_code || ('Requisition #' + selectedReq.id)"></strong>
                                for <strong class="text-dark" x-text="requestorName"></strong>.
                            </p>
                            <div class="alert alert-warning border-0 rounded-3 small text-start mb-4">
                                <i data-lucide="triangle-alert" class="me-2" style="width:16px; vertical-align:middle;"></i>
                                Inventory quantities will be deducted and this requisition will be marked ready for pickup.
                            </div>
                            <div class="d-flex flex-column-reverse flex-sm-row gap-2 justify-content-center">
                                <button type="button" class="btn btn-light border rounded-3 px-4" @click="cancelReleaseConfirmation()">
                                    Cancel
                                </button>
                                <button type="button" class="btn btn-primary rounded-3 px-4 fw-bold" @click="submitConfirmedRelease()">
                                    <i data-lucide="package-check" class="me-2" style="width:16px;"></i>
                                    Yes, mark as ready
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @pushOnce('styles', 'page-styles:admin/approvals.blade.php')
    <style>
        .queue-remarks { white-space: pre-wrap; overflow-wrap: anywhere; }
        .fulfillment-table td { max-width: 19rem; overflow-wrap: anywhere; font-size: var(--text-secondary); }
        .fulfillment-table th:last-child, .fulfillment-table td[data-label="Action"] { min-width: 8rem; }
        .fulfillment-table [data-review-request] { display: inline-flex; align-items: center; justify-content: center; min-width: 5.5rem; white-space: nowrap; overflow-wrap: normal; word-break: normal; }
        @media (max-width: 767.98px) {
            .fulfillment-table td { max-width: none; }
            .fulfillment-table td > * { grid-column: 2; }
            .approval-workspace .nav-link { white-space: normal; }
        }
        .approval-purpose { display: flex; align-items: flex-start; gap: var(--space-12); padding: var(--space-16); border: 1px solid #dce9e1; border-radius: 14px; background: #f5faf7; }
        .approval-purpose-icon { width: 36px; height: 36px; flex: 0 0 36px; display: grid; place-items: center; border-radius: 10px; background: #dff2e6; color: var(--htc-green); }
        .approval-purpose-icon svg { width: 17px; height: 17px; }
        .approval-purpose small { display: block; margin-bottom: var(--space-4); color: #718078; font-size: var(--text-caption); letter-spacing: var(--tracking-label); }
        .approval-purpose p { color: #26382e; font-size: var(--text-body); line-height: var(--line-body); white-space: pre-line; overflow-wrap: anywhere; }
        .release-confirmation-icon { width: 58px; height: 58px; display: grid; place-items: center; border-radius: 18px; background: #e3f4e9; color: #187044; }
        .release-confirmation-icon svg { width: 28px; height: 28px; }
    </style>
    @endPushOnce

    <script>
        const approvalActionBase = {{ Illuminate\Support\Js::from(url('/admin/requests')) }};
        const focusedApprovalRequest = {{ Illuminate\Support\Js::from($focusedRequest?->toArray()) }};

        function approvalManager() {
            return {
                selectedReq: {},
                selectedItems: [],
                requestorName: '',
                requestorDept: '',
                questioningRole: null,
                releaseForm: null,
                releaseConfirmed: false,

                init() {
                    if (focusedApprovalRequest) this.$nextTick(() => this.openDetails(focusedApprovalRequest));
                },

                queueQuery: '',
                reviewTrigger: null,

                formatTimestamp(value) {
                    if (!value || Number.isNaN(new Date(value).getTime())) return 'Not recorded';
                    return new Intl.DateTimeFormat(undefined, {dateStyle: 'medium', timeStyle: 'short'}).format(new Date(value));
                },

                sortedLogs() {
                    return [...(this.selectedReq.logs || [])].sort((a, b) => Number(a.id) - Number(b.id));
                },

                actionUrl(action) {
                    return approvalActionBase + '/' + this.selectedReq.id + '/' + action;
                },

                restoreReviewFocus() {
                    document.getElementById('reviewModal').addEventListener('hidden.bs.modal', () => {
                        if (!this.releaseForm) this.reviewTrigger?.focus({preventScroll: true});
                    }, {once: true});
                },

                openDetails(req) {
                    this.selectedReq = req;
                    this.selectedItems = req.items || [];
                    this.requestorName = req.queue_requestor?.name || 'Unknown requestor';
                    this.requestorDept = req.queue_requestor?.department || 'Unassigned';
                    this.releaseForm = null;
                    this.releaseConfirmed = false;
                    this.reviewTrigger = document.activeElement;
                    const query = new URLSearchParams(window.location.search);
                    query.delete('request');
                    const tab = document.getElementById('approvalFilters')?.elements.namedItem('tab')?.value;
                    if (tab) query.set('tab', tab);
                    this.queueQuery = query.toString();
                    this.questioningRole = req.status === 'for_clarification'
                        ? [...(req.logs || [])].sort((a, b) => Number(b.id) - Number(a.id)).find(log => log.action.includes('Clarification'))?.role || null
                        : null;

                    const modalEl = document.getElementById('reviewModal');
                    this.restoreReviewFocus();
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();

                    // Re-render icons cleanly
                    setTimeout(() => lucide.createIcons(), 100);
                    setTimeout(() => lucide.createIcons(), 350);
                },

                handleReleaseSubmit(event) {
                    if (this.releaseConfirmed) return;

                    event.preventDefault();
                    this.releaseForm = event.currentTarget;

                    const reviewElement = document.getElementById('reviewModal');
                    const confirmationElement = document.getElementById('releaseConfirmationModal');
                    if (!reviewElement || !confirmationElement) return;

                    // Cancel, Escape, and backdrop dismissal all return to the
                    // review. Accepted confirmations proceed to the POST.
                    confirmationElement.addEventListener('hidden.bs.modal', () => {
                        if (this.releaseConfirmed) return;
                        this.releaseForm = null;
                        this.restoreReviewFocus();
                        reviewElement.addEventListener('shown.bs.modal', () => reviewElement.querySelector('form button[type="submit"]')?.focus(), {once: true});
                        bootstrap.Modal.getOrCreateInstance(reviewElement).show();
                    }, {once: true});

                    reviewElement.addEventListener('hidden.bs.modal', () => {
                        bootstrap.Modal.getOrCreateInstance(confirmationElement).show();
                        setTimeout(() => lucide.createIcons(), 100);
                    }, {once: true});

                    bootstrap.Modal.getOrCreateInstance(reviewElement).hide();
                },

                cancelReleaseConfirmation() {
                    const confirmationElement = document.getElementById('releaseConfirmationModal');
                    if (confirmationElement) bootstrap.Modal.getOrCreateInstance(confirmationElement).hide();
                },

                submitConfirmedRelease() {
                    if (!this.releaseForm || this.releaseConfirmed) return;

                    this.releaseConfirmed = true;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('releaseConfirmationModal')).hide();
                    this.$nextTick(() => this.releaseForm.requestSubmit());
                }
            }
        }
    </script>
</x-app-layout>
