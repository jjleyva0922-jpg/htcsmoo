<x-app-layout>
    <x-slot name="header">{{ __('Manage Stock') }}</x-slot>

    @php
        $totalItems = $supplies->count();
        $minorItems = $supplies->where('requisition_tier', 'minor')->values();
        $majorItems = $supplies->where('requisition_tier', 'major')->values();
        $attentionItems = $supplies->filter(fn ($item) => $item->quantity <= $item->min_stock_level)->values();
        $lowStockItems = $supplies->filter(fn ($item) => $item->quantity > 0 && $item->quantity <= $item->min_stock_level)->count();
        $outOfStockItems = $supplies->where('quantity', '<=', 0)->count();
        $inventoryValue = $supplies->sum(fn ($item) => $item->quantity * (float) $item->unit_price);
        $forecastRecommendations = $forecast['recommendations']->take(8);
        $catalogSelectionData = $supplies->map(function ($item) {
            $status = $item->quantity <= 0 ? 'out' : ($item->quantity <= $item->min_stock_level ? 'low' : 'healthy');

            return [
                'id' => $item->id,
                'tier' => $item->requisition_tier ?? 'minor',
                'status' => $status,
                'text' => strtolower(implode(' ', [$item->item_name, $item->brand, $item->model_number, $item->category, $item->specifications])),
            ];
        })->values();
    @endphp

    <div class="inventory-workspace" data-filter-workspace="inventory" x-data="inventoryWorkspace({{ Illuminate\Support\Js::from($catalogSelectionData) }})" @htc:filters-applied="syncFilters($event.detail.url)">
        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm mb-4 rounded-4 d-flex align-items-center gap-2"><i data-lucide="check-circle" style="width:18px"></i>{{ session('success') }}</div>
        @endif

        <div class="inventory-page-header">
            <div>
                <span class="inventory-eyebrow">SMO Inventory Control</span>
                <h3>Manage institutional stock</h3>
                <p>Monitor availability, organize request tiers, and prioritize items that need restocking.</p>
            </div>
            <a href="{{ route('inventory.create') }}" class="btn btn-htc inventory-add-btn"><i data-lucide="plus"></i> Register new item</a>
        </div>

        <nav class="inventory-section-nav" aria-label="Inventory workspace sections">
            <a href="#inventory-overview"><span>01</span><i data-lucide="layout-dashboard"></i><div><strong>Overview</strong><small>Stock at a glance</small></div></a>
            @if($attentionItems->isNotEmpty())
                <a href="#stock-alerts" class="has-alert"><span>02</span><i data-lucide="siren"></i><div><strong>Restock</strong><small>{{ $attentionItems->count() }} item(s) need action</small></div></a>
            @endif
            <a href="#stock-catalog"><span>{{ $attentionItems->isNotEmpty() ? '03' : '02' }}</span><i data-lucide="list-tree"></i><div><strong>Catalog</strong><small>Search and update items</small></div></a>
            <a href="#demand-forecast"><span>{{ $attentionItems->isNotEmpty() ? '04' : '03' }}</span><i data-lucide="chart-no-axes-combined"></i><div><strong>Forecast</strong><small>Plan future purchases</small></div></a>
        </nav>

        <section class="inventory-overview-section" id="inventory-overview">
            <div class="workspace-section-heading">
                <div><span class="section-number">01</span><div><span class="inventory-eyebrow">Inventory overview</span><h4>Stock at a glance</h4></div></div>
                <p>A quick summary of catalog size, availability, and current value.</p>
            </div>
            <div class="inventory-metrics">
                <div><span class="metric-icon total"><i data-lucide="boxes"></i></span><p>Total catalog</p><strong>{{ $totalItems }}</strong><small>registered items</small></div>
                <div><span class="metric-icon minor"><i data-lucide="shopping-basket"></i></span><p>Minor tier</p><strong>{{ $minorItems->count() }}</strong><small>routine supplies</small></div>
                <div><span class="metric-icon major"><i data-lucide="monitor-up"></i></span><p>Major tier</p><strong>{{ $majorItems->count() }}</strong><small>equipment & assets</small></div>
                <div class="{{ $attentionItems->isNotEmpty() ? 'attention' : '' }}"><span class="metric-icon warning"><i data-lucide="triangle-alert"></i></span><p>Needs attention</p><strong>{{ $attentionItems->count() }}</strong><small>{{ $outOfStockItems }} out · {{ $lowStockItems }} low</small></div>
                <div><span class="metric-icon value"><i data-lucide="badge-dollar-sign"></i></span><p>Stock value</p><strong>₱{{ number_format($inventoryValue, 0) }}</strong><small>estimated on hand</small></div>
            </div>
        </section>

        <section class="forecast-section" id="demand-forecast" data-filter-region="forecast" data-result-count="{{ $forecast['recommendations']->count() }}" data-result-label="forecast recommendations" tabindex="-1" aria-label="Inventory forecast">
            <div class="forecast-header">
                <div class="forecast-heading">
                    <span class="section-number">{{ $attentionItems->isNotEmpty() ? '04' : '03' }}</span>
                    <span class="forecast-icon"><i data-lucide="chart-no-axes-combined"></i></span>
                    <div>
                        <span class="inventory-eyebrow">Demand intelligence</span>
                        <h5>{{ $forecast['period_label'] }} purchase forecast</h5>
                        <p>Suggested orders use actual SMO releases, seasonal demand, recent consumption, and safety stock.</p>
                    </div>
                </div>
                <div class="forecast-readiness">
                    <form id="forecastFilters" method="GET" action="{{ route('inventory.index') }}#demand-forecast" class="forecast-period-form" data-dynamic-search data-filter-target="#demand-forecast">
                        @foreach(request()->only('search', 'stock', 'tier') as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                        <label for="forecastPeriod">Report period</label>
                        <select id="forecastPeriod" name="forecast_period">
                            <option value="monthly" @selected($forecast['period'] === 'monthly')>Monthly</option>
                            <option value="quarterly" @selected($forecast['period'] === 'quarterly')>Quarterly</option>
                        </select>
                        <x-filter-submit />
                        <a href="{{ route('inventory.index', request()->except('forecast_period')) }}#demand-forecast" data-filter-link data-filter-target="#demand-forecast">Reset period</a>
                    </form>
                    <a class="forecast-download" href="{{ route('inventory.forecast.report', ['period' => $forecast['period']]) }}" download>
                        <i data-lucide="file-down"></i> PDF report
                    </a>
                    <span><b>{{ $forecast['items_with_history'] }}</b> items learned</span>
                    <span><b>{{ $forecast['release_events'] }}</b> release records</span>
                    <details>
                        <summary>How it works</summary>
                        <div>The estimate blends the last three months of released quantities with the same calendar month from prior years. Quarterly reports combine three upcoming monthly forecasts, add one safety allowance, then deduct current stock. Low-confidence labels mean the system still needs more release history.</div>
                    </details>
                </div>
            </div>

            @if($forecastRecommendations->isNotEmpty())
                <div class="forecast-callout"><i data-lucide="lightbulb"></i><span><strong>Planning signal:</strong> Consider purchasing these items before the {{ strtolower($forecast['period']) }} demand period begins. Estimated purchase budget: <strong>₱{{ number_format($forecast['estimated_budget'], 2) }}</strong>.</span></div>
                <div class="forecast-grid">
                    @foreach($forecastRecommendations as $prediction)
                        @php($item = $prediction['supply'])
                        <article class="forecast-card {{ $prediction['urgency'] }}">
                            <div class="forecast-card-top">
                                <span class="forecast-urgency">
                                    {{ $prediction['urgency'] === 'critical' ? 'Critical' : ($prediction['urgency'] === 'buy_now' ? 'Buy now' : 'Plan purchase') }}
                                </span>
                                <span class="forecast-confidence {{ $prediction['confidence'] }}">{{ ucfirst($prediction['confidence']) }} confidence</span>
                            </div>
                            <div class="forecast-item-name">{{ $item->item_name }}</div>
                            <small>{{ strtoupper($item->requisition_tier ?? 'minor') }} · {{ $item->category }}</small>
                            <div class="forecast-quantity"><span>Suggested purchase</span><strong>{{ $prediction['recommended_order'] }} <em>{{ $item->unit }}</em></strong></div>
                            <div class="forecast-facts">
                                <div><span>Expected use</span><b>{{ $prediction['forecast_demand'] }}</b></div>
                                <div><span>On hand</span><b>{{ $item->quantity }}</b></div>
                                <div><span>Peak month</span><b>{{ $prediction['peak_month'] ?? 'Learning' }}</b></div>
                            </div>
                            <p><i data-lucide="sparkles"></i>{{ $prediction['reason'] }}</p>
                            <div class="forecast-card-footer"><span>{{ $prediction['months_observed'] }} month(s) observed</span><a href="{{ route('inventory.edit', $item->id) }}">Review item <i data-lucide="arrow-right"></i></a></div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="forecast-empty">
                    <span><i data-lucide="circle-check-big"></i></span>
                    <div><strong>No forecasted purchase gap for {{ $forecast['period_label'] }}</strong><p>Current quantities cover expected consumption and configured safety stock. The forecast will keep learning from each SMO release.</p></div>
                </div>
            @endif
        </section>

        @if($attentionItems->isNotEmpty())
            <section class="restock-section" id="stock-alerts">
                <div class="section-heading">
                    <div><span class="section-number">02</span><span class="section-icon warning"><i data-lucide="siren"></i></span><div><span class="inventory-eyebrow">Action required</span><h5>Restock priority</h5><p>Low and unavailable items are surfaced here first.</p></div></div>
                    <span class="priority-count">{{ $attentionItems->count() }} need attention</span>
                </div>
                <div class="restock-grid">
                    @foreach($attentionItems as $item)
                        <a href="{{ route('inventory.edit', $item->id) }}"
                           class="restock-card {{ $item->quantity <= 0 ? 'out' : 'low' }}"
                           @if($loop->index >= 4) x-cloak x-show="showAllRestock" x-transition.opacity @endif>
                            <span class="restock-status">{{ $item->quantity <= 0 ? 'Out of stock' : 'Low stock' }}</span>
                            <strong>{{ $item->item_name }}</strong>
                            <small>{{ strtoupper($item->requisition_tier ?? 'minor') }} · {{ $item->category }}</small>
                            <div><b>{{ $item->quantity }} {{ $item->unit }}</b><span>Threshold: {{ $item->min_stock_level }}</span></div>
                            <em>Update stock <i data-lucide="arrow-up-right"></i></em>
                        </a>
                    @endforeach
                </div>
                @if($attentionItems->count() > 4)
                    <div class="restock-more-bar">
                        <span x-show="!showAllRestock">Showing 4 of {{ $attentionItems->count() }} priority items</span>
                        <span x-cloak x-show="showAllRestock">Showing all {{ $attentionItems->count() }} priority items</span>
                        <button type="button" @click="showAllRestock = !showAllRestock" :aria-expanded="showAllRestock.toString()">
                            <span x-text="showAllRestock ? 'Show less' : 'See {{ $attentionItems->count() - 4 }} more'"></span>
                            <i data-lucide="chevron-down" :class="{ 'is-open': showAllRestock }"></i>
                        </button>
                    </div>
                @endif
            </section>
        @endif

        <section class="catalog-section" id="stock-catalog">
            <div class="catalog-controls">
                <div>
                    <span class="inventory-eyebrow">{{ $attentionItems->isNotEmpty() ? '03' : '02' }} · Catalog management</span>
                    <h5>Tier catalogs</h5>
                    <p>Out-of-stock and low-stock items always appear at the top of each list.</p>
                </div>
                <form id="inventoryFilters" class="inventory-filters" method="GET" action="{{ route('inventory.index') }}#inventory-results" data-dynamic-search data-filter-target="#inventory-results">
                    <input type="hidden" name="forecast_period" value="{{ $forecast['period'] }}">
                    <input type="hidden" name="tier" value="{{ request('tier', 'minor') }}">
                    <div class="inventory-search"><i data-lucide="search"></i><input type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search item, brand, model, category, or specifications…" aria-label="Search inventory"></div>
                    <select name="stock" aria-label="Filter by stock status">@foreach(['all' => 'All stock', 'attention' => 'Needs attention', 'out' => 'Out of stock', 'low' => 'Low stock', 'healthy' => 'Healthy stock'] as $key => $label)<option value="{{ $key }}" @selected(request('stock', 'all') === $key)>{{ $label }}</option>@endforeach</select>
                    <a href="{{ route('inventory.index', ['forecast_period' => $forecast['period']]) }}#inventory-results" data-filter-link data-filter-region="reset" data-filter-target="#inventory-results" class="btn btn-light border">Clear</a>
                    <x-filter-submit />
                </form>
            </div>

            <div id="inventory-results" data-filter-results data-filter-region="catalog" data-filter-catalog="{{ $catalogSelectionData->whereIn('id', $catalogSupplies->pluck('id'))->values()->toJson() }}" data-result-count="{{ $catalogSupplies->count() }}" tabindex="-1" aria-label="Matching catalog items">
            <div class="tier-tabs" data-filter-links>
                @foreach(['minor' => ['shopping-basket', 'Everyday supplies', 'Minor catalog'], 'major' => ['monitor-up', 'Equipment & assets', 'Major catalog']] as $tier => [$icon, $description, $label])
                    <a class="{{ request('tier', 'minor') === $tier ? 'active' : '' }}" href="{{ route('inventory.index', [...request()->only('search', 'stock', 'forecast_period'), 'tier' => $tier]) }}#inventory-results" data-filter-target="#inventory-results" @if(request('tier', 'minor') === $tier) aria-current="page" @endif><span class="tab-icon {{ $tier }}"><i data-lucide="{{ $icon }}"></i></span><span><small>{{ $description }}</small><strong>{{ $label }}</strong></span><em>{{ $catalogSupplies->where('requisition_tier', $tier)->count() }}</em></a>
                @endforeach
            </div>

            <div class="inventory-bulk-bar" x-cloak x-show="selected.length > 0" x-transition>
                <div>
                    <span class="bulk-count" x-text="selected.length"></span>
                    <strong x-text="selected.length === 1 ? 'item selected' : 'items selected'"></strong>
                    <small>Selection is kept when switching tiers or changing filters.</small>
                </div>
                <div class="inventory-bulk-actions">
                    <button type="button" class="btn bulk-clear" @click="clearSelection()">Clear selection</button>
                    <button type="button" class="btn bulk-delete" @click="confirmBulkDelete()"><i data-lucide="trash-2"></i> Delete selected</button>
                </div>
            </div>

            <div class="tab-content inventory-tab-content">
                <div class="tab-pane fade {{ request('tier', 'minor') === 'minor' ? 'show active' : '' }}" id="minorInventory">
                    @include('inventory.partials.tier-table', ['items' => $catalogSupplies->where('requisition_tier', 'minor'), 'tier' => 'minor'])
                </div>
                <div class="tab-pane fade {{ request('tier', 'minor') === 'major' ? 'show active' : '' }}" id="majorInventory">
                    @include('inventory.partials.tier-table', ['items' => $catalogSupplies->where('requisition_tier', 'major'), 'tier' => 'major'])
                </div>
            </div>
            </div>
        </section>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-body p-4 text-center">
            <div class="delete-icon"><i data-lucide="trash-2"></i></div><h5 class="fw-bold">Remove inventory item?</h5>
            <p class="text-muted small">This removes <strong id="deleteItemName"></strong> from the request catalog.</p>
            <form id="deleteForm" method="POST">@csrf @method('DELETE')<div class="d-grid gap-2"><button type="submit" class="btn btn-danger fw-bold py-2 rounded-3">Remove item</button><button type="button" class="btn btn-light fw-bold py-2 rounded-3 border" data-bs-dismiss="modal">Keep item</button></div></form>
        </div></div></div>
    </div>

    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-body p-4 text-center">
            <div class="delete-icon"><i data-lucide="trash-2"></i></div><h5 class="fw-bold">Remove selected items?</h5>
            <p class="text-muted small">This removes <strong x-text="selected.length"></strong> selected <span x-text="selected.length === 1 ? 'item' : 'items'"></span> from the request catalog.</p>
            <form method="POST" action="{{ route('inventory.bulk-destroy') }}">
                @csrf
                @method('DELETE')
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <div class="d-grid gap-2"><button type="submit" class="btn btn-danger fw-bold py-2 rounded-3">Remove selected items</button><button type="button" class="btn btn-light fw-bold py-2 rounded-3 border" data-bs-dismiss="modal">Keep items</button></div>
            </form>
        </div></div></div>
    </div>
    </div>

    @pushOnce('styles', 'page-styles:inventory/index.blade.php')
    <style>
        .inventory-workspace { display: flex; flex-direction: column; padding: var(--space-8); }
        .inventory-page-header { display: flex; justify-content: space-between; align-items: center; gap: var(--space-24); margin-bottom: var(--space-24); }
        .inventory-eyebrow { color: var(--htc-green); font-size: var(--text-caption); font-weight: 800; letter-spacing: var(--tracking-label); text-transform: uppercase; }
        .inventory-page-header h3 { margin: var(--space-4) 0; color: #17251d; font-weight: 800; }
        .inventory-page-header p, .catalog-controls p, .section-heading p { margin: 0; color: #7b8981; font-size: var(--text-secondary); }
        .inventory-add-btn { display: inline-flex; align-items: center; gap: var(--space-8); padding: var(--space-12) var(--space-16); border-radius: 12px; white-space: nowrap; }
        .inventory-add-btn svg { width: 17px; }
        .inventory-section-nav { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: var(--space-12); margin-bottom: var(--space-32); padding: var(--space-8); border: 1px solid #dfe8e2; border-radius: 16px; background: #f5f8f6; }
        .inventory-section-nav > a { min-width: 0; display: grid; grid-template-columns: 24px 34px minmax(0, 1fr); align-items: center; gap: var(--space-12); padding: var(--space-12); border: 1px solid transparent; border-radius: 11px; color: #53645a; background: transparent; text-decoration: none; transition: border-color .18s ease, background .18s ease, transform .18s ease; }
        .inventory-section-nav > a:hover { border-color: #d5e3da; color: #244c35; background: #fff; transform: translateY(-1px); }
        .inventory-section-nav > a:focus-visible { outline: 3px solid rgba(35, 113, 73, .2); outline-offset: 2px; }
        .inventory-section-nav > a > span { color: #9aa69e; font-size: var(--text-caption); font-weight: 900; letter-spacing: var(--tracking-label); }
        .inventory-section-nav > a > svg { width: 17px; height: 17px; color: var(--htc-green); }
        .inventory-section-nav > a.has-alert > svg { color: #b52d35; }
        .inventory-section-nav strong, .inventory-section-nav small { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .inventory-section-nav strong { color: #2b4034; font-size: var(--text-caption); }
        .inventory-section-nav small { margin-top: .1rem; color: #8b978f; font-size: var(--text-caption); }
        .inventory-overview-section { margin-bottom: var(--space-32); padding: var(--space-24); border: 1px solid #e1e9e4; border-radius: 18px; background: linear-gradient(145deg, #f8faf9, #fff); box-shadow: 0 8px 24px rgba(24, 55, 36, .04); scroll-margin-top: 1rem; }
        .workspace-section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-16); margin-bottom: var(--space-16); }
        .workspace-section-heading > div { display: flex; align-items: center; gap: var(--space-12); }
        .workspace-section-heading h4 { margin: .1rem 0 0; color: #203128; font-size: var(--text-heading-sm); font-weight: 800; }
        .workspace-section-heading p { max-width: 430px; margin: 0; color: #7f8d85; font-size: var(--text-secondary); text-align: right; }
        .section-number { width: 32px; height: 32px; flex: 0 0 32px; display: grid; place-items: center; border: 1px solid #d5e3da; border-radius: 9px; color: #397451; background: #f1f7f3; font-size: var(--text-caption); font-weight: 900; letter-spacing: var(--tracking-label); }
        .inventory-metrics { display: grid; grid-template-columns: repeat(5, 1fr); gap: var(--space-12); }
        .inventory-metrics > div { position: relative; padding: var(--space-16); border: 1px solid #e3eae6; border-radius: 15px; background: #fff; box-shadow: 0 5px 16px rgba(24, 55, 36, .04); }
        .inventory-metrics > div.attention { border-color: #f4c6c8; background: #fffafa; }
        .inventory-metrics p { margin: var(--space-12) 0 .1rem; color: #738078; font-size: var(--text-secondary); font-weight: 800; letter-spacing: var(--tracking-label); text-transform: uppercase; }
        .inventory-metrics strong { display: block; color: #1f3026; font-size: var(--text-heading-sm); line-height: var(--line-heading); }
        .inventory-metrics small { color: #96a199; font-size: var(--text-caption); }
        .metric-icon, .section-icon, .tab-icon { display: grid; place-items: center; border-radius: 10px; }
        .metric-icon { width: 34px; height: 34px; }
        .metric-icon svg { width: 17px; }
        .metric-icon.total { color: #355da8; background: #eaf0fb; }.metric-icon.minor { color: #17643d; background: #e6f5ec; }.metric-icon.major { color: #95610a; background: #fff3d9; }.metric-icon.warning { color: #b52d35; background: #fdebec; }.metric-icon.value { color: #6a45a5; background: #f1eafa; }
        .forecast-section { order: 3; margin-bottom: var(--space-24); border: 1px solid #dce8e0; border-radius: 18px; background: linear-gradient(145deg, #f5faf7, #fff 55%); box-shadow: 0 8px 24px rgba(24, 55, 36, .06); overflow: hidden; scroll-margin-top: 1rem; }
        .forecast-header { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-24); padding: var(--space-24) var(--space-24) var(--space-16); }
        .forecast-heading { display: flex; align-items: center; gap: var(--space-12); }.forecast-heading h5 { margin: .15rem 0 var(--space-4); color: #20352a; font-weight: 800; }.forecast-heading p { margin: 0; color: #7b8981; font-size: var(--text-secondary); }
        .forecast-icon { width: 46px; height: 46px; flex: 0 0 46px; display: grid; place-items: center; border-radius: 13px; background: #dff1e6; color: var(--htc-green); }.forecast-icon svg { width: 22px; }
        .forecast-readiness { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: var(--space-8); }.forecast-readiness > span { padding: var(--space-8) var(--space-12); border: 1px solid #dde7e1; border-radius: 9px; background: #fff; color: #7a8880; font-size: var(--text-caption); white-space: nowrap; }.forecast-readiness b { color: #2d4839; }
        .forecast-period-form { display: flex; align-items: center; gap: var(--space-8); padding: var(--space-4); border: 1px solid #d9e5dd; border-radius: 10px; background: #fff; }.forecast-period-form label { padding-left: var(--space-4); color: #66766c; font-size: var(--text-secondary); font-weight: 800; text-transform: uppercase; }.forecast-period-form select { min-height: 32px; padding: var(--space-4) var(--space-24) var(--space-4) var(--space-8); border: 1px solid #dfe7e2; border-radius: 7px; color: #405248; background-color: #f8faf9; font-size: var(--text-body); }.forecast-period-form button, .forecast-download { min-height: 32px; display: inline-flex; align-items: center; justify-content: center; gap: var(--space-4); padding: var(--space-4) var(--space-12); border: 0; border-radius: 7px; color: #fff; background: var(--htc-green); font-size: var(--text-caption); font-weight: 800; text-decoration: none; white-space: nowrap; }.forecast-period-form button svg, .forecast-download svg { width: 13px; height: 13px; }.forecast-download { background: #243d30; }
        .forecast-readiness details { position: relative; }.forecast-readiness summary { cursor: pointer; list-style: none; padding: var(--space-8) var(--space-12); border-radius: 9px; background: var(--htc-green); color: #fff; font-size: var(--text-caption); font-weight: 700; }.forecast-readiness details > div { position: absolute; z-index: 20; top: calc(100% + .5rem); right: 0; width: 290px; padding: var(--space-12); border: 1px solid #dbe5df; border-radius: 11px; background: #fff; color: #66756c; font-size: var(--text-caption); line-height: var(--line-body); box-shadow: 0 12px 28px rgba(25, 52, 35, .14); }
        .forecast-callout { display: flex; align-items: center; gap: var(--space-8); margin: 0 var(--space-24) var(--space-16); padding: var(--space-12) var(--space-12); border-radius: 10px; background: #eaf5ee; color: #39634b; font-size: var(--text-caption); }.forecast-callout svg { width: 16px; color: #267047; }
        .forecast-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--space-12); padding: 0 var(--space-24) var(--space-24); }
        .forecast-card { display: flex; flex-direction: column; min-height: 260px; padding: var(--space-16); border: 1px solid #dfe8e3; border-top: 3px solid #6c8e78; border-radius: 13px; background: #fff; }.forecast-card.critical { border-top-color: #c63c44; }.forecast-card.buy_now { border-top-color: #dc8a18; }
        .forecast-card-top { display: flex; justify-content: space-between; align-items: center; gap: var(--space-4); margin-bottom: var(--space-12); }.forecast-urgency, .forecast-confidence { font-size: var(--text-caption); font-weight: 800; letter-spacing: var(--tracking-label); text-transform: uppercase; }.forecast-urgency { color: #65766c; }.critical .forecast-urgency { color: #bd3038; }.buy_now .forecast-urgency { color: #a8690e; }.forecast-confidence { padding: var(--space-4) var(--space-8); border-radius: 999px; background: #eef2ef; color: #718078; }.forecast-confidence.high { background: #e2f3e8; color: #17643d; }.forecast-confidence.medium { background: #fff0ce; color: #8e5b08; }
        .forecast-item-name { color: #203128; font-weight: 800; }.forecast-card > small { color: #8a968f; font-size: var(--text-caption); }
        .forecast-quantity { margin: var(--space-16) 0; padding: var(--space-12); border-radius: 10px; background: #f5f8f6; }.forecast-quantity span { display: block; color: #839087; font-size: var(--text-caption); font-weight: 700; text-transform: uppercase; }.forecast-quantity strong { display: block; margin-top: .15rem; color: var(--htc-green); font-size: var(--text-heading-sm); }.forecast-quantity em { font-size: var(--text-caption); font-style: normal; font-weight: 600; }
        .forecast-facts { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4); }.forecast-facts div { min-width: 0; }.forecast-facts span { display: block; color: #98a19c; font-size: var(--text-caption); text-transform: uppercase; }.forecast-facts b { display: block; color: #405148; font-size: var(--text-caption); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .forecast-card > p { display: flex; gap: var(--space-8); margin: var(--space-12) 0; color: #6f7e75; font-size: var(--text-secondary); line-height: var(--line-body); }.forecast-card > p svg { width: 13px; height: 13px; flex: 0 0 13px; color: #71927d; }
        .forecast-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: var(--space-12); border-top: 1px solid #edf1ef; font-size: var(--text-caption); }.forecast-card-footer span { color: #96a199; }.forecast-card-footer a { display: flex; align-items: center; gap: var(--space-4); color: var(--htc-green); text-decoration: none; font-weight: 700; }.forecast-card-footer svg { width: 11px; }
        .forecast-empty { display: flex; align-items: center; gap: var(--space-16); margin: 0 var(--space-24) var(--space-24); padding: var(--space-16); border: 1px dashed #cfe0d5; border-radius: 13px; background: #fff; }.forecast-empty > span { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 50%; color: #277148; background: #e5f5eb; }.forecast-empty svg { width: 20px; }.forecast-empty strong { color: #284033; }.forecast-empty p { margin: var(--space-4) 0 0; color: #829087; font-size: var(--text-secondary); }
        .restock-section, .catalog-section { border: 1px solid #e2e9e5; border-radius: 18px; background: #fff; box-shadow: 0 8px 24px rgba(24, 55, 36, .05); overflow: hidden; scroll-margin-top: 1rem; }
        .restock-section { order: 1; margin-bottom: var(--space-32); }
        .catalog-section { order: 2; margin-bottom: var(--space-32); }
        .section-heading, .catalog-controls { display: flex; align-items: center; justify-content: space-between; gap: var(--space-16); padding: var(--space-16) var(--space-24); }
        .section-heading > div { display: flex; align-items: center; gap: var(--space-12); }.section-heading h5, .catalog-controls h5 { margin: .12rem 0 var(--space-4); color: #24372c; font-weight: 800; }
        .section-icon { width: 38px; height: 38px; color: #b52d35; background: #fdebec; }.section-icon svg { width: 18px; }
        .priority-count { padding: var(--space-4) var(--space-12); border-radius: 999px; background: #fdebec; color: #a72a31; font-size: var(--text-caption); font-weight: 800; }
        .restock-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--space-12); padding: 0 var(--space-24) var(--space-24); }
        .restock-card { display: flex; flex-direction: column; min-height: 150px; padding: var(--space-16); border: 1px solid #f2d4d5; border-radius: 13px; color: inherit; text-decoration: none; background: #fffafa; transition: .2s; }.restock-card.low { border-color: #f2dfad; background: #fffcf4; }.restock-card:hover { color: inherit; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(58, 44, 20, .08); }
        .restock-status { align-self: flex-start; margin-bottom: var(--space-12); color: #b52d35; font-size: var(--text-caption); font-weight: 800; text-transform: uppercase; }.restock-card.low .restock-status { color: #a36a09; }.restock-card strong { color: #26372e; }.restock-card > small { color: #89958e; font-size: var(--text-caption); }.restock-card > div { display: flex; justify-content: space-between; margin-top: auto; padding-top: var(--space-12); font-size: var(--text-caption); }.restock-card > div b { color: #b52d35; }.restock-card.low > div b { color: #9b680d; }.restock-card em { margin-top: var(--space-8); color: var(--htc-green); font-size: var(--text-caption); font-style: normal; font-weight: 700; }.restock-card em svg { width: 12px; }
        .restock-more-bar { min-height: 52px; display: flex; align-items: center; justify-content: space-between; gap: var(--space-16); padding: var(--space-12) var(--space-24); border-top: 1px solid #e8eeea; background: #fafcfb; }
        .restock-more-bar > span { color: #849087; font-size: var(--text-caption); }
        .restock-more-bar button { display: inline-flex; align-items: center; gap: var(--space-8); padding: var(--space-8) var(--space-12); border: 1px solid #cfe0d5; border-radius: 9px; color: #286846; background: #fff; font-size: var(--text-caption); font-weight: 800; }
        .restock-more-bar button:hover { border-color: #8eb39c; background: #f0f7f3; }
        .restock-more-bar button:focus-visible { outline: 3px solid rgba(35, 113, 73, .18); outline-offset: 2px; }
        .restock-more-bar svg { width: 14px; height: 14px; transition: transform .2s ease; }
        .restock-more-bar svg.is-open { transform: rotate(180deg); }
        .catalog-controls { border-bottom: 1px solid #edf1ef; }
        .inventory-filters { display: flex; flex-wrap: wrap; gap: var(--space-8); }.inventory-search { width: 280px; max-width: 100%; display: flex; align-items: center; gap: var(--space-8); padding: 0 var(--space-12); border: 1px solid #dce4df; border-radius: 10px; background: #f9fbfa; }.inventory-search svg { width: 15px; color: #8b9991; }.inventory-search input { width: 100%; padding: var(--space-12) 0; border: 0; outline: 0; background: transparent; font-size: var(--text-body); }.inventory-filters select { padding: var(--space-8) var(--space-32) var(--space-8) var(--space-12); border: 1px solid #dce4df; border-radius: 10px; color: #58675f; background-color: #fff; font-size: var(--text-body); }
        .tier-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-12); padding: var(--space-16) var(--space-24); background: #f8faf9; }.tier-tabs :is(button,a) { display: flex; align-items: center; gap: var(--space-12); padding: var(--space-12) var(--space-16); border: 1px solid #dfe7e2; border-radius: 12px; background: #fff; text-align: left; text-decoration: none; color: #65736b; }.tier-tabs :is(button,a).active { border-color: var(--htc-green); box-shadow: inset 0 0 0 1px var(--htc-green); }.tab-icon { width: 38px; height: 38px; }.tab-icon svg { width: 18px; }.tab-icon.minor { color: #17643d; background: #e6f5ec; }.tab-icon.major { color: #95610a; background: #fff3d9; }.tier-tabs :is(button,a) > span:nth-child(2) { display: flex; flex-direction: column; }.tier-tabs small { font-size: var(--text-caption); text-transform: uppercase; }.tier-tabs strong { color: #26372e; }.tier-tabs em { margin-left: auto; width: 28px; height: 28px; display: grid; place-items: center; border-radius: 50%; background: #eef2ef; font-size: var(--text-caption); font-style: normal; font-weight: 800; }
        .inventory-table thead th { padding-top: var(--space-12); padding-bottom: var(--space-12); border-bottom: 1px solid #e8eeea; background: #fff; color: #8a978f; font-size: var(--text-caption); letter-spacing: var(--tracking-label); text-transform: uppercase; }.inventory-table td { padding-top: var(--space-16); padding-bottom: var(--space-16); border-color: #edf1ef; }.inventory-row:hover { background: #fafcfb; }.stock-out-row { background: #fffafa; }.stock-low-row { background: #fffdf8; }
        .inventory-item-icon { width: 40px; height: 40px; flex: 0 0 40px; display: grid; place-items: center; border-radius: 11px; }.inventory-item-icon svg { width: 19px; }.inventory-item-icon.minor { color: #17643d; background: #e8f5ed; }.inventory-item-icon.major { color: #95610a; background: #fff3da; }.item-specification { max-width: 280px; margin-top: var(--space-4); color: #8a958f; font-size: var(--text-caption); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .inventory-category { display: inline-block; padding: var(--space-4) var(--space-8); border-radius: 999px; background: #f0f3f1; color: #536159; font-size: var(--text-caption); font-weight: 700; }.option-preview { display: flex; flex-direction: column; gap: .15rem; margin-top: var(--space-8); color: #859189; font-size: var(--text-caption); }
        .inventory-select-cell { width: 44px; text-align: center; }.inventory-select { width: 17px; height: 17px; cursor: pointer; accent-color: var(--htc-green); }.inventory-row.is-selected { background: #f0f8f3; box-shadow: inset 3px 0 #237149; }
        .inventory-bulk-bar { display: flex; align-items: center; justify-content: space-between; gap: var(--space-16); padding: var(--space-12) var(--space-24); border-top: 1px solid #d8e7dd; border-bottom: 1px solid #d8e7dd; background: #f1f8f4; }.inventory-bulk-bar > div:first-child { display: flex; align-items: center; gap: var(--space-8); }.inventory-bulk-bar strong { color: #294638; font-size: var(--text-caption); }.inventory-bulk-bar small { margin-left: var(--space-4); color: #78877f; font-size: var(--text-caption); }.bulk-count { min-width: 27px; height: 27px; display: inline-grid; place-items: center; padding: 0 var(--space-8); border-radius: 999px; background: var(--htc-green); color: #fff; font-size: var(--text-caption); font-weight: 900; }.inventory-bulk-actions { display: flex; gap: var(--space-8); }.inventory-bulk-actions .btn { min-height: 34px; padding: var(--space-8) var(--space-12); border-radius: 8px; font-size: var(--text-caption); font-weight: 800; }.bulk-clear { border: 1px solid #ccdbd1; color: #52675a; background: #fff; }.bulk-delete { display: inline-flex; align-items: center; gap: var(--space-4); color: #fff; background: #b82f38; }.bulk-delete:hover { color: #fff; background: #9f222b; }.bulk-delete svg { width: 13px; }
        .stock-badge { display: inline-flex; align-items: center; gap: var(--space-4); padding: var(--space-4) var(--space-8); border-radius: 999px; font-size: var(--text-caption); font-weight: 800; }.stock-badge svg { width: 13px; }.stock-badge.out { color: #ac2730; background: #fde9ea; }.stock-badge.low { color: #96610a; background: #fff0cc; }.stock-badge.healthy { color: #17643d; background: #e5f5eb; }.stock-threshold { display: block; margin-top: var(--space-4); color: #9aa39e; font-size: var(--text-caption); }
        .inventory-action { width: 32px; height: 32px; display: inline-grid; place-items: center; padding: 0; border: 1px solid #dce4df; border-radius: 9px; color: #5f6f66; background: #fff; }.inventory-action svg { width: 14px; }.inventory-action:hover { border-color: var(--htc-green); color: var(--htc-green); background: #eff7f2; }.inventory-action.danger:hover { border-color: #dc3545; color: #dc3545; background: #fff5f5; }
        .inventory-empty { height: 220px; text-align: center; color: #94a3b8; }.inventory-empty svg { display: block; width: 34px; margin: 0 auto var(--space-8); }.inventory-empty strong, .inventory-empty span { display: block; }.delete-icon { width: 58px; height: 58px; display: grid; place-items: center; margin: 0 auto var(--space-16); border-radius: 50%; color: #b52d35; background: #fdebec; }.delete-icon svg { width: 25px; }
        @media(max-width: 1199px) { .inventory-section-nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }.inventory-metrics { grid-template-columns: repeat(3, 1fr); }.restock-grid, .forecast-grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width: 767px) {
            .inventory-workspace { padding: 0; }
            .inventory-page-header, .catalog-controls, .forecast-header { align-items: stretch; flex-direction: column; }
            .inventory-add-btn { min-height: 44px; justify-content: center; }
            .inventory-section-nav { gap: var(--space-8); margin-bottom: var(--space-24); padding: var(--space-8); border-radius: 14px; }
            .inventory-section-nav > a { grid-template-columns: 20px 26px minmax(0, 1fr); gap: var(--space-8); padding: var(--space-12) var(--space-8); }
            .inventory-section-nav > a > svg { width: 15px; }
            .inventory-section-nav small { display: none; }
            .inventory-overview-section { margin-bottom: var(--space-24); padding: var(--space-16); }
            .workspace-section-heading { align-items: flex-start; flex-direction: column; }
            .workspace-section-heading p { text-align: left; }
            .inventory-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-12); }
            .inventory-metrics > div { padding: var(--space-12); }
            .inventory-metrics > div:last-child { grid-column: 1 / -1; }
            .forecast-header, .section-heading, .catalog-controls { padding: var(--space-16); }
            .forecast-heading { align-items: flex-start; }
            .forecast-readiness { flex-wrap: wrap; }
            .forecast-period-form { width: 100%; display: grid; grid-template-columns: auto 1fr auto; }
            .forecast-download { flex: 1; min-height: 40px; }
            .forecast-readiness > span { flex: 1; text-align: center; }
            .forecast-readiness details { flex-basis: 100%; }
            .forecast-readiness summary { min-height: 40px; display: grid; place-items: center; }
            .forecast-readiness details > div { position: static; width: 100%; margin-top: var(--space-8); }
            .forecast-callout { align-items: flex-start; margin: 0 var(--space-16) var(--space-16); }
            .forecast-grid, .restock-grid {
                grid-template-columns: none;
                grid-auto-flow: column;
                grid-auto-columns: minmax(270px, 86vw);
                gap: var(--space-12);
                padding: 0 var(--space-16) var(--space-16);
                overflow-x: auto;
                scroll-snap-type: x proximity;
                scrollbar-width: thin;
            }
            .forecast-card, .restock-card { scroll-snap-align: start; }
            .forecast-card { min-height: 245px; }
            .section-heading { align-items: flex-start; }
            .section-heading > div { align-items: flex-start; }
            .restock-section, .catalog-section { margin-bottom: var(--space-24); }
            .restock-more-bar { padding-inline: var(--space-16); }
            .priority-count { white-space: nowrap; }
            .inventory-filters { flex-direction: column; }
            .inventory-search { width: 100%; min-height: 44px; }
            .inventory-filters select { min-height: 44px; }
            .inventory-bulk-bar { align-items: stretch; flex-direction: column; padding: var(--space-12) var(--space-16); }.inventory-bulk-bar > div:first-child { flex-wrap: wrap; }.inventory-bulk-bar small { flex-basis: 100%; margin-left: 0; }.inventory-bulk-actions { display: grid; grid-template-columns: 1fr 1fr; }.inventory-bulk-actions .btn { min-height: 42px; justify-content: center; }
            .tier-tabs { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-8); padding: var(--space-12); }
            .tier-tabs :is(button,a) { min-width: 0; padding: var(--space-12); }
            .tier-tabs :is(button,a) > span:nth-child(2) { min-width: 0; }
            .tier-tabs small { display: none; }
            .tier-tabs strong { font-size: var(--text-caption); }
            .tier-tabs em { width: 24px; height: 24px; flex: 0 0 24px; }
            .tab-icon { width: 34px; height: 34px; flex: 0 0 34px; }

            .inventory-tab-content .table-responsive { overflow: visible; }
            .inventory-table { display: block; min-width: 0 !important; background: #f5f8f6; }
            .inventory-table thead { display: none; }
            .inventory-table tbody { display: grid; gap: var(--space-12); padding: var(--space-12); }
            .inventory-table tbody tr { display: block; overflow: hidden; border: 1px solid #e0e8e3; border-radius: 14px; background: #fff; box-shadow: 0 3px 12px rgba(24, 58, 37, .05); }
            .inventory-table tbody tr.stock-out-row { border-left: 4px solid #dc3545; }
            .inventory-table tbody tr.stock-low-row { border-left: 4px solid #e4a11b; }
            .inventory-table tbody td {
                display: grid;
                grid-template-columns: minmax(88px, 34%) minmax(0, 1fr);
                align-items: center;
                gap: var(--space-12);
                width: 100%;
                padding: var(--space-12) var(--space-12) !important;
                text-align: right !important;
                overflow-wrap: anywhere;
            }
            .inventory-table tbody td::before { content: attr(data-label); color: #7b8981; font-size: var(--text-caption); font-weight: 800; text-align: left; text-transform: uppercase; }
            .inventory-table tbody td > * { justify-self: end; max-width: 100%; }
            .inventory-table tbody td.inventory-primary { display: block; text-align: left !important; }
            .inventory-table tbody td.inventory-primary::before { display: none; }
            .inventory-table tbody td.inventory-primary > * { justify-self: auto; }
            .inventory-table tbody td[colspan] { display: block; }
            .inventory-table tbody td[colspan]::before { display: none; }
            .inventory-table tbody td.inventory-select-cell { display: flex; justify-content: flex-end; width: 100%; }.inventory-table tbody td.inventory-select-cell::before { content: 'Select item'; margin-right: auto; }.inventory-table tbody td.inventory-select-cell .inventory-select { justify-self: auto; }
            .item-specification { max-width: 100%; white-space: normal; }
            .option-preview { align-items: flex-end; }
            .inventory-row-actions { justify-content: flex-end; }
            .inventory-action { width: 40px; height: 40px; }
        }

        @media(max-width: 420px) {
            .inventory-section-nav > a { grid-template-columns: 19px minmax(0, 1fr); }
            .inventory-section-nav > a > svg { display: none; }
            .inventory-metrics p { margin-top: var(--space-8); }
            .inventory-metrics strong { font-size: var(--text-heading-sm); }
            .tier-tabs :is(button,a) { gap: var(--space-8); }
            .tab-icon { display: none; }
        }
    </style>
    @endPushOnce

    <script>
        function inventoryWorkspace(catalog) {
            return {
                query: new URL(window.location.href).searchParams.get('search') || '',
                stockFilter: new URL(window.location.href).searchParams.get('stock') || 'all',
                catalog,
                selected: [],
                showAllRestock: false,
                syncFilters(url) {
                    const query = new URL(url).searchParams;
                    this.query = query.get('search') || '';
                    this.stockFilter = query.get('stock') || 'all';
                    this.catalog = JSON.parse(this.$el.querySelector('[data-filter-catalog]').dataset.filterCatalog);
                },
                matches(text, status) {
                    const matchesSearch = !this.query.trim() || text.includes(this.query.trim().toLowerCase());
                    const matchesStatus = this.stockFilter === 'all'
                        || (this.stockFilter === 'attention' && ['out', 'low'].includes(status))
                        || this.stockFilter === status;
                    return matchesSearch && matchesStatus;
                },
                matchingIds(tier) {
                    return this.catalog
                        .filter(item => item.tier === tier && this.matches(item.text, item.status))
                        .map(item => item.id);
                },
                allMatchingSelected(tier) {
                    const ids = this.matchingIds(tier);
                    return ids.length > 0 && ids.every(id => this.selected.includes(id));
                },
                someMatchingSelected(tier) {
                    const ids = this.matchingIds(tier);
                    return ids.some(id => this.selected.includes(id)) && !ids.every(id => this.selected.includes(id));
                },
                toggleMatching(tier) {
                    const ids = this.matchingIds(tier);
                    if (ids.every(id => this.selected.includes(id))) {
                        this.selected = this.selected.filter(id => !ids.includes(id));
                    } else {
                        this.selected = [...new Set([...this.selected, ...ids])];
                    }
                },
                clearSelection() {
                    this.selected = [];
                },
                confirmBulkDelete() {
                    if (this.selected.length === 0) return;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkDeleteModal')).show();
                    setTimeout(() => lucide.createIcons(), 50);
                }
            };
        }

        function confirmDelete(url, name) {
            document.getElementById('deleteItemName').textContent = name;
            document.getElementById('deleteForm').action = url;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
            setTimeout(() => lucide.createIcons(), 50);
        }
    </script>
</x-app-layout>
