{{--
    PDF da gôndola — modo "por módulo" (A4 portrait, 1 página por módulo).
    Reproduz o essencial de PdfModulePage usando posicionamento absoluto (dompdf).

    Variáveis:
      $gondola      — array gondola de prepareGondolaData()
      $pages        — saída de PlanogramPdfLayoutService::buildModulesLayout()
      $tenantName   — nome do tenant
      $responsavel  — responsável
      $isLeftToRight — bool
      $observacoes  — texto de observações
--}}
@php
    $planogram = $gondola['planogram'] ?? null;
    $total = count($pages);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #0f172a; margin: 0; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .tenant { font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #64748b; }
        .infobar { border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; background: #f8fafc; padding: 5px 0; margin-top: 6px; }
        .infobar td { padding: 0 8px; border-right: 1px solid #e2e8f0; }
        .infobar td:last-child { border-right: 0; }
        .lbl { font-size: 7px; letter-spacing: 0.5px; text-transform: uppercase; color: #94a3b8; }
        .val { font-size: 10px; font-weight: bold; color: #334155; }
        .module-caption { text-align: center; font-size: 9px; font-weight: bold; text-transform: uppercase; color: #334155; margin-top: 6px; }
        .footer { border-top: 1px solid #e2e8f0; padding-top: 5px; margin-top: 8px; }
        .footer .lbl { font-size: 7px; }
        .footer .val { font-size: 9px; }
        .version { background: #84cc16; color: #fff; text-align: center; border-radius: 4px; padding: 3px 6px; }
        .version small { font-size: 7px; text-transform: uppercase; }
        .version b { font-size: 12px; }
    </style>
</head>
<body>
    @foreach ($pages as $module)
        <div class="page">
            {{-- Cabeçalho --}}
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    @if (! empty($logo))
                        <td width="120" style="vertical-align:middle;">
                            <img src="{{ $logo }}" alt="Plannerate" style="height:34px; width:auto;" />
                        </td>
                    @endif
                    <td>
                        @if ($tenantName)<div class="tenant">{{ $tenantName }}</div>@endif
                        <div class="title">{{ $planogram['name'] ?? __('plannerate.print.preview.exposure_planogram') }}</div>
                    </td>
                    <td width="120" align="right">
                        <div class="lbl">{{ __('plannerate.print.labels.publication') }}</div>
                        <div class="val">{{ $planogram['start_date'] ?? '—' }}</div>
                    </td>
                </tr>
            </table>

            {{-- Barra de informações --}}
            <div class="infobar">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <div class="lbl">{{ __('plannerate.print.labels.category') }}</div>
                            <div class="val">{{ $planogram['category']['name'] ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="lbl">{{ __('plannerate.print.labels.store') }}</div>
                            <div class="val">{{ $gondola['location'] ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="lbl">{{ __('plannerate.print.labels.module') }}</div>
                            <div class="val">{{ $module['ordering'] }} / {{ $total }}</div>
                        </td>
                        <td>
                            <div class="lbl">{{ __('plannerate.print.labels.flow') }}</div>
                            <div class="val">{{ $isLeftToRight ? '→' : '←' }}</div>
                        </td>
                        <td>
                            <div class="lbl">A &times; L &times; P</div>
                            <div class="val">{{ rtrim(rtrim(number_format($module['rawHeightCm'], 1), '0'), '.') }} &times; {{ rtrim(rtrim(number_format($module['rawWidthCm'], 1), '0'), '.') }} &times; {{ rtrim(rtrim(number_format($module['rawDepthCm'], 1), '0'), '.') }} cm</div>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- Visual do módulo --}}
            <div style="margin-top:10px;">
                @include('plannerate::pdf.partials._module-section', ['module' => $module, 'mode' => 'column'])
            </div>

            <div class="module-caption">
                {{ __('plannerate.print.labels.module') }} #{{ $module['ordering'] }}
            </div>

            {{-- Rodapé --}}
            <div class="footer">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <div class="lbl">{{ __('plannerate.print.labels.responsible') }}</div>
                            <div class="val">{{ $responsavel ?: '—' }}</div>
                        </td>
                        <td>
                            <div class="lbl">{{ __('plannerate.print.labels.observations') }}</div>
                            <div class="val" style="font-weight:normal;">{{ $observacoes }}</div>
                        </td>
                        <td width="60" align="right">
                            <div class="version">
                                <small>{{ __('plannerate.print.labels.version') }}</small><br>
                                <b>V1.0</b>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    @endforeach
</body>
</html>
