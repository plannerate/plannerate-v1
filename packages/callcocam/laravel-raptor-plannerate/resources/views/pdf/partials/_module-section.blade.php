{{--
    Render de UM módulo da gôndola para o PDF (dompdf).

    Recebe:
      $module — view-model de PlanogramPdfLayoutService::buildModule()
      $mode   — 'row' (posiciona em absoluto pela esquerda) ou 'column' (centralizado)

    Todo o posicionamento interno é absoluto (dompdf não suporta flexbox).
    As cores reproduzem as classes slate-* usadas nos componentes Vue.
--}}
@php
    // Posicionamento do contêiner do módulo conforme o modo.
    $moduleStyle = $mode === 'row'
        ? "position:absolute; left:{$module['left']}px; bottom:0; width:{$module['width']}px; height:{$module['height']}px;"
        : "position:relative; margin:0 auto; width:{$module['width']}px; height:{$module['height']}px;";
@endphp

<div style="{{ $moduleStyle }}">
    {{-- Cremalheira esquerda (apenas no primeiro módulo em modo linha). --}}
    @if ($module['showLeftCremalheira'])
        <div style="position:absolute; top:0; bottom:0; left:0; width:{{ $module['cremalheiraWidth'] }}px;">
            <div style="position:absolute; bottom:0; width:100%; height:{{ $module['sectionHeight'] }}px; background:#334155; border:1px solid #475569; box-sizing:border-box;">
                @foreach ($module['holes'] as $hole)
                    <div style="position:absolute; left:{{ max(0, ($module['cremalheiraWidth'] - $hole['width']) / 2 - 1) }}px; top:{{ $hole['top'] }}px; width:{{ $hole['width'] }}px; height:{{ $hole['height'] }}px; background:#94a3b8; border:1px solid #64748b; box-sizing:border-box;"></div>
                @endforeach
                <div style="position:absolute; bottom:0; left:0; width:100%; height:{{ $module['baseHeight'] }}px; background:#334155; border-top:1px solid #475569;"></div>
            </div>
        </div>
    @endif

    {{-- Cremalheira direita (sempre). --}}
    <div style="position:absolute; top:0; bottom:0; right:0; width:{{ $module['cremalheiraWidth'] }}px;">
        <div style="position:absolute; bottom:0; width:100%; height:{{ $module['sectionHeight'] }}px; background:#334155; border:1px solid #475569; box-sizing:border-box;">
            @foreach ($module['holes'] as $hole)
                <div style="position:absolute; left:{{ max(0, ($module['cremalheiraWidth'] - $hole['width']) / 2 - 1) }}px; top:{{ $hole['top'] }}px; width:{{ $hole['width'] }}px; height:{{ $hole['height'] }}px; background:#94a3b8; border:1px solid #64748b; box-sizing:border-box;"></div>
            @endforeach
            <div style="position:absolute; bottom:0; left:0; width:100%; height:{{ $module['baseHeight'] }}px; background:#334155; border-top:1px solid #475569;"></div>
        </div>
    </div>

    {{-- Prateleiras (só os produtos aqui). --}}
    @foreach ($module['shelves'] as $shelf)
        <div style="position:absolute; top:{{ $shelf['areaTop'] }}px; left:{{ $shelf['areaLeft'] }}px; width:{{ $shelf['areaWidth'] }}px; height:{{ $shelf['areaHeight'] }}px;">
            @foreach ($shelf['cells'] as $cell)
                @php
                    $vertical = $cell['anchor'] === 'top'
                        ? "top:{$cell['top']}px;"
                        : "bottom:{$cell['bottom']}px;";
                @endphp
                @if (! empty($cell['image']))
                    <img src="{{ $cell['image'] }}" alt="{{ $cell['name'] }}"
                        style="position:absolute; left:{{ $cell['left'] }}px; {{ $vertical }} width:{{ $cell['width'] }}px; height:{{ $cell['height'] }}px;" />
                @else
                    <div style="position:absolute; left:{{ $cell['left'] }}px; {{ $vertical }} width:{{ $cell['width'] }}px; height:{{ $cell['height'] }}px; background:#f1f5f9; border:1px dashed #cbd5e1; overflow:hidden;">
                        <span style="font-size:6px; color:#64748b; line-height:1.1;">{{ \Illuminate\Support\Str::limit($cell['name'], 24) }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach

    {{-- Barras + rótulo "Prat #N" das prateleiras.
         Renderizadas no nível do MÓDULO (não dentro da área da prateleira) e por
         ÚLTIMO, em coordenada absoluta (areaTop + barTop). Assim a barra pode
         crescer para baixo (altura mínima de 9px para o texto caber direto nela,
         sem fundo próprio — igual ao editor) sem ser cortada pelo box da área
         nem coberta pela área da prateleira vizinha no dompdf. O topo da barra
         permanece na linha onde os produtos se apoiam. --}}
    @foreach ($module['shelves'] as $shelf)
        @php $barH = max($shelf['barHeight'], 10); @endphp
        <div style="position:absolute; top:{{ $shelf['areaTop'] + $shelf['barTop'] }}px; left:{{ $shelf['areaLeft'] }}px; width:{{ $shelf['areaWidth'] }}px; height:{{ $barH }}px; background:#1e293b; border-top:2px solid #334155;">
            <table cellpadding="0" cellspacing="0" style="width:100%; height:{{ $barH }}px;"><tr>
                <td style="text-align:center; vertical-align:middle; font-size:7px; color:#cbd5e1;">{{ __('plannerate.print.preview.shelf_short') }} #{{ $shelf['displayNumber'] }}</td>
            </tr></table>
        </div>
    @endforeach

    {{-- Rótulo do módulo. --}}
    <div style="position:absolute; bottom:0; left:0; width:100%; text-align:center;">
        <span style="font-size:8px; color:#94a3b8;">{{ __('plannerate.print.labels.module') }} #{{ $module['ordering'] }}</span>
    </div>
</div>
