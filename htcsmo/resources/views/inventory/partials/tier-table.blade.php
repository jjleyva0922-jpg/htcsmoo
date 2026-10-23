<div class="table-responsive">
    <table class="table inventory-table align-middle mb-0">
        <thead>
            <tr>
                <th class="inventory-select-cell">
                    <input type="checkbox"
                           class="inventory-select"
                           aria-label="Select all matching {{ $tier }} items"
                           title="Select all matching {{ $tier }} items"
                           :checked="allMatchingSelected('{{ $tier }}')"
                           :indeterminate="someMatchingSelected('{{ $tier }}')"
                           @change="toggleMatching('{{ $tier }}')">
                </th>
                <th class="ps-4">Item</th>
                <th>Category & choices</th>
                <th class="text-center">Stock</th>
                <th class="text-end">Unit cost</th>
                <th class="pe-4 text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                @php
                    $stockState = $item->quantity <= 0 ? 'out' : ($item->quantity <= $item->min_stock_level ? 'low' : 'healthy');
                    $searchText = strtolower(implode(' ', [$item->item_name, $item->brand, $item->model_number, $item->category, $item->specifications]));
                @endphp
                <tr x-show='matches({{ Illuminate\Support\Js::from($searchText) }}, "{{ $stockState }}")'
                    :class="{ 'is-selected': selected.includes({{ $item->id }}) }"
                    class="inventory-row {{ $stockState === 'out' ? 'stock-out-row' : ($stockState === 'low' ? 'stock-low-row' : '') }}">
                    <td class="inventory-select-cell" data-label="Select item">
                        <input type="checkbox"
                               class="inventory-select"
                               value="{{ $item->id }}"
                               x-model.number="selected"
                               aria-label="Select {{ $item->item_name }}">
                    </td>
                    <td class="ps-4 inventory-primary" data-label="Item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="inventory-item-icon {{ $tier }}"><i data-lucide="{{ $tier === 'major' ? 'monitor' : 'package' }}"></i></div>
                            <div>
                                <div class="fw-bold text-dark">{{ $item->item_name }}</div>
                                <small class="text-muted">{{ $item->brand ?: 'No brand' }}@if($item->model_number) · {{ $item->model_number }}@endif</small>
                                <div class="item-specification">{{ $item->specifications ?: 'No detailed specifications' }}</div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Category & choices">
                        <span class="inventory-category">{{ $item->category }}</span>
                        @if(!empty($item->configurable_options))
                            <div class="option-preview">
                                @foreach($item->configurable_options as $label => $choices)
                                    <span>{{ $label }}: {{ implode(', ', $choices) }}</span>
                                @endforeach
                            </div>
                        @else
                            <small class="text-muted d-block mt-2">Standard item · no choices</small>
                        @endif
                    </td>
                    <td class="text-center" data-label="Stock">
                        @if($stockState === 'out')
                            <span class="stock-badge out"><i data-lucide="circle-x"></i> Out of stock</span>
                        @elseif($stockState === 'low')
                            <span class="stock-badge low"><i data-lucide="triangle-alert"></i> {{ $item->quantity }} {{ $item->unit }}</span>
                            <small class="stock-threshold">Restock at {{ $item->min_stock_level }}</small>
                        @else
                            <span class="stock-badge healthy"><i data-lucide="circle-check"></i> {{ $item->quantity }} {{ $item->unit }}</span>
                        @endif
                    </td>
                    <td class="text-end" data-label="Unit cost">
                        <div class="fw-bold text-dark">₱{{ number_format($item->unit_price, 2) }}</div>
                        <small class="text-muted">per {{ strtolower($item->unit) }}</small>
                    </td>
                    <td class="pe-4 text-end" data-label="Actions">
                        <div class="inventory-row-actions d-inline-flex gap-2">
                            <a href="{{ route('inventory.edit', $item->id) }}" class="btn inventory-action" title="Edit {{ $item->item_name }}"><i data-lucide="pencil"></i></a>
                            <button type="button" class="btn inventory-action danger" data-delete-url="{{ route('inventory.destroy', $item->id) }}" data-item-name="{{ $item->item_name }}" onclick="confirmDelete(this.dataset.deleteUrl, this.dataset.itemName)" title="Delete {{ $item->item_name }}"><i data-lucide="trash-2"></i></button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="inventory-empty"><i data-lucide="package-open"></i><strong>{{ request()->filled('search') || request('stock', 'all') !== 'all' ? 'No matching '.$tier.' items' : 'No '.$tier.' items yet' }}</strong><span>Try another search or stock filter, or register an item in this tier.</span></td></tr>
            @endforelse
            @if($items->isNotEmpty())
                <tr x-cloak x-show="matchingIds('{{ $tier }}').length === 0">
                    <td colspan="6" class="inventory-empty"><i data-lucide="search-x"></i><strong>No matching {{ $tier }} items</strong><span>Try another search term or stock filter.</span></td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
