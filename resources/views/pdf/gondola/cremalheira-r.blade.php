<div style="position: absolute; top: 0; bottom: 0; right: 0; width: {{ $section->cremalheira_width * $gondola->scale_factor }}px; z-index: 30; border: 1px solid #475569; background-color: #334155;">
    @if($holes = data_get($section->settings, 'holes'))
    @foreach ($holes as $hole)
    @php
    // Ajustar a posição do furo para considerar a largura da cremalheira
    $adjustedPosition = $hole['position'] * $gondola->scale_factor;
    $holeHeight = $hole['height'] * $gondola->scale_factor;
    $holeWidth = $hole['width'] * $gondola->scale_factor;
    @endphp
    <div data-furo-cremalheira="true" style="position: absolute; width: {{ $holeWidth }}px; height: {{ $holeHeight }}px; top: {{ $adjustedPosition }}px; left: 50%; transform: translateX(-50%); border: 1px solid #64748b; background-color: #94a3b8;"></div>
    @endforeach
    @endif
    @php
    $baseHeight = ($section->base_height ?? 8) * $gondola->scale_factor;
    @endphp
    <div data-base-cremalheira="true" style="position: absolute; bottom: 0; left: 0; width: 100%; height: {{ $baseHeight }}px; border-top: 1px solid #475569; background-color: #334155;"></div>
</div>