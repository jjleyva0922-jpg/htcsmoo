<x-app-layout>
    <x-slot name="header">
        {{ __('Update Supply Specification') }}
    </x-slot>

    <div class="container-fluid py-2 inventory-form-page">
        <div class="row justify-content-center">
            <div class="col-xl-9 col-lg-11">

                <!-- Breadcrumb & Item Info -->
                <div class="inventory-form-meta d-flex justify-content-between align-items-center mb-4 gap-2">
                    <a href="{{ route('inventory.index') }}" class="btn btn-link text-decoration-none text-muted p-0 d-flex align-items-center">
                        <i data-lucide="chevron-left" class="me-1" style="width:18px"></i> Back to Inventory
                    </a>
                    <div class="text-end">
                        <span class="small text-muted text-uppercase fw-bold label-letter-spacing">Last Updated:</span>
                        <span class="small fw-bold">{{ $item->updated_at->format('M d, Y - h:i A') }}</span>
                    </div>
                </div>

                <!-- ERROR ALERT BANNER -->
                @if($errors->any())
                    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 p-3">
                        <div class="d-flex align-items-center mb-2">
                            <i data-lucide="alert-triangle" class="me-2 text-danger" style="width: 20px;"></i>
                            <strong class="text-danger">Failed to save changes:</strong>
                        </div>
                        <ul class="mb-0 small ps-4 text-danger">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('inventory.update', $item->id) }}" method="POST" data-confirm="Save changes to {{ $item->item_name }}? Updated stock, pricing, and options will apply to the request catalog." data-confirm-title="Update inventory item?" data-confirm-accept="Save changes">
                    @csrf
                    @method('PATCH')

                    <!-- ITEM STATUS HEADER -->
                    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
                        <div class="inventory-status-card card-body p-4 d-flex align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center">
                                <div class="p-3 rounded-circle bg-light me-3">
                                    <i data-lucide="package" class="text-success" style="width: 28px; height: 28px;"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-0 text-dark">{{ $item->item_name }}</h4>
                                    <span class="text-muted small">Current Stock: <strong>{{ $item->quantity }} {{ $item->unit }}</strong></span>
                                </div>
                            </div>
                            <div class="text-end">
                                @if($item->quantity <= 0)
                                    <span class="badge bg-danger px-3 py-2 rounded-pill">OUT OF STOCK</span>
                                @elseif($item->quantity <= $item->min_stock_level)
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">BELOW RESTOCK THRESHOLD</span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">HEALTHY STATUS</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- MAIN EDIT ISLAND -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                        <!-- Section 1: Identity -->
                        <div class="card-body p-4 p-md-5 border-bottom">
                            <div class="d-flex align-items-center mb-4">
                                <div class="p-2 bg-success-subtle text-success rounded-3 me-3">
                                    <i data-lucide="edit-3" style="width:20px"></i>
                                </div>
                                <h6 class="fw-bold mb-0">Modify Identity</h6>
                            </div>

                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-dark text-uppercase">Item Name <span class="text-danger">*</span></label>
                                    <input type="text" name="item_name" class="form-control border-0 bg-light py-2 rounded-3 shadow-none @error('item_name') is-invalid @enderror" value="{{ old('item_name', $item->item_name) }}" required>
                                    @error('item_name') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark text-uppercase">Institutional Category <span class="text-danger">*</span></label>
                                    <select name="category" class="form-select border-0 bg-light py-2 rounded-3 shadow-none @error('category') is-invalid @enderror" required>
                                        <option value="">-- Select Category --</option>
                                        @foreach([
                                            'Cleaning Supplies',
                                            'Construction Supplies',
                                            'Drugs and Medicines',
                                            'Maintenance Supplies',
                                            'Medical Supplies',
                                            'Non-Medical Supplies',
                                            'Office Supplies'
                                        ] as $cat)
                                            <option value="{{ $cat }}" {{ old('category', $item->category) == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                    @error('category') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark text-uppercase">Requisition Tier <span class="text-danger">*</span></label>
                                    <select name="requisition_tier" class="form-select border-0 bg-light py-2 rounded-3 shadow-none" required>
                                        <option value="minor" {{ old('requisition_tier', $item->requisition_tier ?? 'minor') === 'minor' ? 'selected' : '' }}>Minor — routine supplies up to ₱1,000</option>
                                        <option value="major" {{ old('requisition_tier', $item->requisition_tier) === 'major' ? 'selected' : '' }}>Major — equipment and high-value items</option>
                                    </select>
                                    <small class="text-muted">Requestors only see this item inside the selected tier.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark text-uppercase">Model / Series #</label>
                                    <input type="text" name="model_number" class="form-control border-0 bg-light py-2 rounded-3 shadow-none" value="{{ old('model_number', $item->model_number) }}">
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Detailed Specs (Unified with specifications column) -->
                        <div class="card-body p-4 p-md-5 border-bottom" style="background-color: #fafbfc;">
                            <div class="d-flex align-items-center mb-4">
                                <div class="p-2 bg-primary-subtle text-primary rounded-3 me-3">
                                    <i data-lucide="align-left" style="width:20px"></i>
                                </div>
                                <h6 class="fw-bold mb-0">Detailed Technical Specifications <span class="text-danger">*</span></h6>
                            </div>
                            <textarea name="specifications" class="form-control border-0 bg-white py-3 rounded-4 shadow-sm @error('specifications') is-invalid @enderror" rows="3" required>{{ old('specifications', $item->specifications) }}</textarea>
                            @error('specifications') <span class="text-danger small">{{ $message }}</span> @enderror

                            @php
                                $savedOptions = collect($item->requestOptions())->map(fn ($values, $name) => ['name' => $name, 'values' => implode(', ', $values)])->values();
                                if (! $savedOptions->contains(fn ($option) => $option['name'] === 'Brand')) {
                                    $savedOptions->prepend(['name' => 'Brand', 'values' => '']);
                                }
                                $oldNames = old('option_names');
                                $oldValues = old('option_values', []);
                                $optionRows = $oldNames !== null
                                    ? collect($oldNames)->map(fn ($name, $i) => ['name' => $name, 'values' => $oldValues[$i] ?? ''])->values()
                                    : $savedOptions;
                            @endphp
                            <div class="mt-4" x-data="{ rows: {{ Illuminate\Support\Js::from($optionRows) }} }">
                                <div class="inventory-options-header d-flex justify-content-between align-items-center mb-2 gap-2">
                                    <div>
                                        <label class="form-label mini-label mb-0">Requestor Choices</label>
                                        <div class="small text-muted">Brand is required. These choices appear after a requestor clicks this item.</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill" @click="rows.length < 5 && rows.push({name: '', values: ''})">
                                        <i data-lucide="plus" style="width:14px"></i> Add option
                                    </button>
                                </div>
                                <template x-for="(row, index) in rows" :key="index">
                                    <div class="inventory-option-row row g-2 mb-2">
                                        <div class="col-md-4"><input type="text" name="option_names[]" x-model="row.name" :readonly="row.name === 'Brand'" :class="row.name === 'Brand' ? 'fw-bold text-success' : ''" class="form-control border-0 bg-light" placeholder="Option name"></div>
                                        <div class="col-md-7"><input type="text" name="option_values[]" x-model="row.values" class="form-control border-0 bg-light" placeholder="Choice 1, Choice 2"></div>
                                        <div class="inventory-option-remove col-md-1 d-grid"><button type="button" class="btn btn-light text-danger" :disabled="row.name === 'Brand'" @click="rows.splice(index, 1)" aria-label="Remove option"><i data-lucide="x" style="width:15px"></i></button></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Section 3: Values & Parameters -->
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark text-uppercase">Current Qty <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" class="form-control border-0 bg-light py-2 rounded-3 shadow-none" value="{{ old('quantity', $item->quantity) }}" min="0" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark text-uppercase">Official Unit <span class="text-danger">*</span></label>
                                    <select name="unit" class="form-select border-0 bg-light py-2 rounded-3 shadow-none" required>
                                        @foreach(['Bottle', 'Box(es)', 'Can(s)', 'Gallon(s)', 'Kilogram(s)', 'Liter(s)', 'Meter(s)', 'Pack(s)', 'PC/PCS', 'Piece(s)', 'Ream(s)', 'Roll(s)', 'Set', 'Unit(s)'] as $u)
                                            <option value="{{ $u }}" {{ old('unit', $item->unit) == $u ? 'selected' : '' }}>{{ $u }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark text-uppercase text-success">Price (₱) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" name="unit_price" class="form-control border-0 bg-light py-2 shadow-none fw-bold text-success" value="{{ old('unit_price', $item->unit_price) }}" min="0.01" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark text-uppercase text-danger">Restock Threshold <span class="text-danger">*</span></label>
                                    <input type="number" name="min_stock_level" class="form-control border-0 bg-danger-subtle py-2 rounded-3 shadow-none fw-bold text-danger" value="{{ old('min_stock_level', $item->min_stock_level) }}" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="inventory-form-actions d-flex gap-3 justify-content-end mb-5">
                        <a href="{{ route('inventory.index') }}" class="btn btn-light px-5 py-3 rounded-pill fw-bold border">Discard Changes</a>
                        <button type="submit" class="btn btn-htc px-5 py-3 rounded-pill fw-bold shadow-lg d-flex align-items-center">
                            <i data-lucide="save" class="me-2"></i> UPDATE REGISTRY
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
