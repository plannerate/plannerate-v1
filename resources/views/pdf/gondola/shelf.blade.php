@php
$shelfWidth = $sectionWidth ;
$shelfHeight = $shelf->shelf_height * $gondola->scale_factor;
$shelfPosition = $shelf->shelf_position * $gondola->scale_factor;

$alignItems = match ($gondola->alignment ?? 'default') {
'left' => 'flex-start',
'right' => 'flex-end',
'center' => 'center',
'justify' => 'space-between',
'default' => 'flex-start',
};
@endphp
<div style="position: absolute; top: {{$shelfPosition}}px; width: {{$shelfWidth}}px; height: {{$shelfHeight}}px; left: {{$cremalheiraWidth}}px; right: {{$cremalheiraWidth}}px;">
    <div style="position: absolute; right: 0; bottom: 0; left: 0; display: flex; align-items: flex-end; gap: 2px; justify-content: {{ $alignItems }}; z-index: 50; pointer-events: none; padding-bottom: {{$shelfHeight}}px;">
        @if ($segments = $shelf->segments)
        @foreach ($segments as $segment)
        @include('pdf.gondola.shelf_segment', ['segment' => $segment, 'shelf' => $shelf, 'section' => $section, 'gondola' => $gondola])
        @endforeach
        @endif
    </div>
    <div style="position: absolute; right: 0; bottom: 0; left: 0; z-index: 100; height: {{ $shelf->shelf_height * $gondola->scale_factor }}px; border-top: 2px solid #334155; background-color: rgba(30, 41, 59, 0.95);">
        <div style="pointer-events: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; align-items: center; justify-content: center; font-size: 10px; z-index: 1;"><span style="padding-left: 8px; padding-right: 8px; font-weight: 500; color: #cbd5e1; display: flex; align-items: center;"> Prateleira #{{ $shelf->ordering }}</span></div>
    </div>
</div>