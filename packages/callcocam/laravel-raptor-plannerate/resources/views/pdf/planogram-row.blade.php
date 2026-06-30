{{--
    PDF da gôndola — modo "em linha" (A4 landscape, todos os módulos lado a lado).
    Reproduz a tela PdfPreview (row) usando posicionamento absoluto (dompdf).

    Variáveis:
      $gondola      — array gondola de prepareGondolaData()
      $layout       — saída de PlanogramPdfLayoutService::buildRowLayout()
      $tenantName   — nome do tenant
      $responsavel  — responsável
      $flowLabel    — rótulo do fluxo já traduzido
      $isLeftToRight — bool
      $observacoes  — texto de observações
--}}
@php
    $planogram = $gondola['planogram'] ?? null;
    $title = $planogram['name'] ?? __('plannerate.print.preview.exposure_planogram');
    $meta = [
        [__('plannerate.print.preview.client'), $tenantName ?: '—'],
        [__('plannerate.print.share.module'), $gondola['name'] ?? '—'],
        [__('plannerate.print.labels.store'), $gondola['location'] ?? '—'],
        [__('plannerate.print.labels.category'), $planogram['category']['name'] ?? '—'],
        [__('plannerate.print.preview.modules'), (string) count($layout['modules'])],
        [__('plannerate.print.labels.publication'), $planogram['start_date'] ?? '—'],
        [__('plannerate.print.labels.responsible'), $responsavel ?: '—'],
        [__('plannerate.print.labels.flow'), $flowLabel],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8px 10px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #0f172a; margin: 0; }
        .header { border-bottom: 1px solid #e2e8f0; padding: 6px 4px 8px; }
        .header td { vertical-align: middle; }
        .brand-title { font-size: 16px; font-weight: bold; text-transform: uppercase; color: #0f172a; }
        .tenant { font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #64748b; }
        .meta-label { font-size: 7px; letter-spacing: 0.5px; text-transform: uppercase; color: #94a3b8; }
        .meta-value { font-size: 9px; font-weight: bold; color: #334155; }
        .version { background: #84cc16; color: #fff; text-align: center; border-radius: 4px; padding: 4px 8px; }
        .version small { font-size: 7px; text-transform: uppercase; }
        .version b { font-size: 13px; }
        .flow { border-top: 1px solid #f1f5f9; padding: 3px 8px; text-align: center; }
        .flow .marker { font-size: 11px; font-weight: bold; color: #84cc16; }
        .flow .label { font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #94a3b8; }
        .footer { border-top: 1px solid #f1f5f9; padding: 6px 10px; }
        .footer .obs-title { font-size: 9px; font-weight: bold; color: #334155; }
        .footer .obs-text { font-size: 8px; color: #64748b; }
        .footer-bar { height: 14px; background: #0f172a; }
    </style>
</head>
<body>
    {{-- Cabeçalho --}}
    <div class="header">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                @if (! empty($logo))
                    <td width="90" style="vertical-align:middle;">
                        <img src="{{ $logo }}" alt="Plannerate" style="height:30px; width:auto;" />
                    </td>
                @endif
                <td width="24%">
                    @if ($tenantName)<div class="tenant">{{ $tenantName }}</div>@endif
                    <div class="brand-title">{{ $title }}</div>
                </td>
                <td>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            @foreach ($meta as $item)
                                <td align="center" style="padding:0 3px;">
                                    <div class="meta-label">{{ $item[0] }}</div>
                                    <div class="meta-value">{{ $item[1] }}</div>
                                </td>
                            @endforeach
                        </tr>
                    </table>
                </td>
                <td width="50" align="right">
                    <div class="version">
                        <small>{{ __('plannerate.print.labels.version') }}</small><br>
                        <b>V1.0</b>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Indicador de fluxo --}}
    <div class="flow">
        <span class="marker">{{ $isLeftToRight ? '★' : '☆' }}</span>
        <span class="marker">{{ $isLeftToRight ? '→' : '←' }}</span>
        <span class="label">{{ __('plannerate.print.labels.gondola_flow') }}</span>
        <span class="marker">{{ $isLeftToRight ? '→' : '←' }}</span>
        <span class="marker">{{ $isLeftToRight ? '☆' : '★' }}</span>
    </div>

    {{-- Faixa de módulos --}}
    <div style="position:relative; width:{{ $layout['bandWidth'] }}px; height:{{ $layout['bandHeight'] }}px; margin:6px auto 0;">
        @foreach ($layout['modules'] as $module)
            @include('plannerate::pdf.partials._module-section', ['module' => $module, 'mode' => 'row'])
        @endforeach
    </div>

    {{-- Rodapé --}}
    <div class="footer">
        <span class="obs-title">{{ __('plannerate.print.labels.observations') }}:</span>
        <span class="obs-text">{{ $observacoes }}</span>
    </div>
    <div class="footer-bar"></div>
</body>
</html>
