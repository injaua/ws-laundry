@props(["name", "data" => []])
{{-- Server data for a page script (resources/js/lib/dom.js → pageData(name)). JSON, never executed. --}}
<script type="application/json" data-page="{{ $name }}">@json($data)</script>
