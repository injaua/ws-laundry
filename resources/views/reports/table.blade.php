@extends("layouts.document")

{{-- A ReportTable as an A4 document (PDF export of the accounting / payroll reports). --}}
@section("title", $table->title)
@section("doc_type", $table->title)
@section("doc_meta")
    <div>{{ $table->subtitle }}</div>
@endsection
@section("no_bank_details", "1")

@section("content")
@if($table->summary)
    <table class="summary">
        @foreach($table->summary as [$label, $value, $type])
            <tr>
                <td>{{ $label }}</td>
                <td class="r"><strong>{{ $type === "money" ? money($value) : $value }}</strong></td>
            </tr>
        @endforeach
    </table>
@endif

<table class="report">
    <thead>
        <tr>
            @foreach($table->columns as $i => [$label, $type])
                <th class="{{ in_array($type, ["money", "number"], true) ? "r" : "" }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($table->rows as $row)
            @if($row["style"] === "section")
                <tr class="section"><td colspan="{{ count($table->columns) }}">{{ $row["cells"][0] }}</td></tr>
            @else
                <tr class="{{ $row["style"] }}">
                    @foreach($row["cells"] as $i => $value)
                        @php($type = $table->type($i))
                        <td class="{{ in_array($type, ["money", "number"], true) ? "r" : "" }} {{ $row["style"] === "indent" && $i === 1 ? "indent" : "" }}">
                            @if($value === null || $value === "")
                            @elseif($type === "money"){{ num($value) }}
                            @elseif($type === "number"){{ qty((float) $value) }}
                            @else{{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        @empty
            <tr><td colspan="{{ count($table->columns) }}" class="muted">{{ __('app.no_data') }}</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
