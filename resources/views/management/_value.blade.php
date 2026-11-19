{{-- Formats a KPI value by its format ($value, $format). --}}
@if($value === null)—@else{{ match($format) {
    "money" => money((float) $value),
    "percent" => qty((float) $value, 1) . "%",
    "days" => qty((float) $value, 1) . " " . __('app.days_short'),
    "hours" => qty((float) $value, 1) . " h",
    "kg" => qty((float) $value, 1) . " kg",
    "decimal" => qty((float) $value),
    default => num((float) $value),
} }}@endif
