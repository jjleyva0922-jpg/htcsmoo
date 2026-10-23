@props(['from' => '', 'to' => ''])
<noscript>
    <style>[data-custom-date-field] { display: none !important; }</style>
    <label>Custom range start <input type="date" name="date_from" value="{{ $from }}" class="form-control"></label>
    <label>Custom range end <input type="date" name="date_to" value="{{ $to }}" class="form-control"></label>
</noscript>
