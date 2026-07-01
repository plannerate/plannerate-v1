{{--
    PDF da gôndola — modo "por módulo" (A4 portrait, 1 módulo por página).
    Baseado no PdfModulePage.vue, com os ajustes pedidos: sem a barra lateral
    "Posição do Fluxo" e sem o indicador de fluxo; o módulo ocupa a maior parte
    da página e o rodapé (observações + aprovação + barra) fica FIXO no pé de
    toda página (position:fixed). Layout em tabelas (dompdf não tem flexbox); os
    ícones lucide são embutidos como PNG base64 via $icons.

    Variáveis:
      $gondola      — array gondola de prepareGondolaData()
      $pages        — saída de PlanogramPdfLayoutService::buildModulesLayout()
      $logo, $icons — logo + ícones em base64
      $tenantName, $responsavel, $isLeftToRight, $observacoes
--}}
@php
    $planogram = $gondola['planogram'] ?? null;

    // Formata uma dimensão (cm) removendo zeros decimais supérfluos.
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <style>
        /* Reserva no rodapé p/ o bloco fixo (obs + aprovação + barra) não
           sobrepor o conteúdo do módulo. */
        @page {
            margin: 12px 14px 150px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0f172a;
            margin: 0;
            font-size: 10px;
            line-height: 1.15;
        }

        .page {
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .lbl {
            font-size: 7px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .val {
            display: inline-block;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 1px;
            font-size: 10px;
            font-weight: bold;
            color: #334155;
        }

        /* ---------- Cabeçalho ---------- */
        .mp-datebox {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 8px;
        }

        .mp-datebox td {
            padding: 0 8px;
            vertical-align: top;
        }

        /* ---------- Barra de informações ---------- */
        .infobar {
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 5px 6px;
            margin-top: 8px;
        }

        .infobar td {
            padding: 0 8px;
            border-right: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .infobar td:last-child {
            border-right: 0;
        }

        .infobar .val {
            border-bottom-color: #94a3b8;
        }

        /* ---------- Identificação / largura ---------- */
        .mod-ident {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #334155;
        }

        .width-label {
            font-size: 9px;
            color: #475569;
            white-space: nowrap;
        }

        .flow-line {
            border-bottom: 1px solid #cbd5e1;
            font-size: 0;
            line-height: 1px;
        }

        /* ---------- Rodapé fixo (obs + aprovação + barra) ---------- */
        /* bottom negativo empurra o bloco p/ dentro da margem inferior
           reservada (dompdf posiciona fixed relativo à caixa de conteúdo). */
        .fixed-foot {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -138px;
        }

        .obs-title {
            display: inline-block;
            background: #64a333;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 3px 9px;
        }

        .obs-box {
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 6px 8px;
            margin-top: 5px;
            min-height: 36px;
        }

        .mp-footer {
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 6px;
            margin-top: 8px;
        }

        .mp-footer td {
            padding: 0 8px;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .mp-footer td.last {
            border-right: 0;
        }

        .version {
            background: #64a333;
            color: #fff;
            text-align: center;
            border-radius: 4px;
            padding: 4px 8px;
        }

        .version small {
            font-size: 8px;
            text-transform: uppercase;
        }

        .version b {
            font-size: 13px;
        }

        .deco-bar {
            position: relative;
            height: 30px;
            background: #0f172a;
            overflow: hidden;
            margin-top: 6px;
        }

        .deco-bar .corner {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 52px;
            height: 52px;
            background: #64a333;
            border-top-left-radius: 9999px;
        }
    </style>
</head>

<body>
    {{-- ---------- Rodapé fixo (repete no pé de toda página) ---------- --}}
    <div class="fixed-foot">
        {{-- Observações --}}
        <div>
            <span class="obs-title">{{ __('plannerate.print.labels.observations') }}</span>
            <div class="obs-box">
                <span style="font-size:9px; color:#64748b; line-height:1.4;">{{ $planogram['description'] ?? '' }}</span>
            </div>
        </div>

        {{-- Rodapé de aprovação --}}
        <div class="mp-footer">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <span class="lbl">{{ __('plannerate.print.labels.responsible') }}</span><br>
                        <span class="val">{{ $responsavel ?: '—' }}</span>
                    </td>
                    <td>
                        <span class="lbl">{{ __('plannerate.print.labels.approved_by') }}</span><br>
                        <span class="val">—</span>
                    </td>
                    <td>
                        <span class="lbl">{{ __('plannerate.print.labels.approval_date') }}</span><br>
                        <span class="val" style="border:0; font-weight:normal; color:#94a3b8;">—/—/—</span>
                    </td>
                    <td width="60" align="right" class="last">
                        <div class="version">
                            <small>{{ __('plannerate.print.labels.version') }}</small><br>
                            <b>V1.0</b>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Barra decorativa --}}
        <div class="deco-bar">
            <div class="corner"></div>
        </div>
    </div>

    @foreach ($pages as $module)
        @php
            $dims = __('plannerate.print.labels.height_short').': '.$fmt($module['rawHeightCm'])
                .'  '.__('plannerate.print.labels.width_short').': '.$fmt($module['rawWidthCm'])
                .'  '.__('plannerate.print.labels.depth_short').': '.$fmt($module['rawDepthCm']).' mm';
        @endphp
        <div class="page">
            {{-- ---------- Cabeçalho ---------- --}}
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="vertical-align:middle;">
                        @if (! empty($logo))
                            <img src="{{ $logo }}" alt="Plannerate" style="height:40px; width:auto;" />
                        @endif
                    </td>
                    <td align="right" style="vertical-align:middle;">
                        <table class="mp-datebox" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <span class="lbl">@if (! empty($icons['calendar-days']))<img src="{{ $icons['calendar-days'] }}" style="height:7px; width:7px; vertical-align:middle;" /> @endif{{ __('plannerate.print.labels.publication_date') }}</span><br>
                                    <span class="val">{{ $planogram['start_date'] ?? '—' }}</span>
                                </td>
                                <td>
                                    <span class="lbl">{{ __('plannerate.print.labels.store') }}</span><br>
                                    <span class="val">{{ $gondola['location'] ?? '—' }}</span>
                                </td>
                                <td>
                                    <span class="lbl">{{ __('plannerate.print.labels.store_code') }}</span><br>
                                    <span class="val">—</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- ---------- Barra de informações ---------- --}}
            <div class="infobar">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <span class="lbl">{{ __('plannerate.print.labels.category') }}</span><br>
                            <span class="val">{{ $planogram['category']['name'] ?? '—' }}</span>
                        </td>
                        <td>
                            <span class="lbl">{{ __('plannerate.print.labels.subcategory') }}</span><br>
                            <span class="val">{{ $gondola['side'] ?? '—' }}</span>
                        </td>
                        <td>
                            <span class="lbl">{{ __('plannerate.print.labels.gondola_type') }}</span><br>
                            <span class="val">{{ $gondola['name'] ?? '—' }}</span>
                        </td>
                        <td>
                            <span class="lbl">{{ __('plannerate.print.labels.module') }}</span><br>
                            <span class="val">{{ $module['ordering'] }} / {{ $module['total'] }}</span>
                        </td>
                        <td>
                            <span class="lbl">{{ __('plannerate.print.labels.module_dimensions') }}</span><br>
                            <span class="val">{{ $dims }}</span>
                        </td>
                        <td>
                            <span class="lbl">{{ __('plannerate.print.labels.execution_level') }}</span><br>
                            <span class="val">{{ $planogram['type'] ?? '—' }}</span>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- ---------- Visual do módulo + indicador de altura ----------
                 Par (módulo + indicador) centralizado como bloco: a tabela
                 interna encolhe ao conteúdo e é centralizada via align. --}}
            <div style="text-align:center; margin-top:10px;">
                <table align="center" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="vertical-align:top;">
                            @include('plannerate::pdf.partials._module-section', ['module' => $module, 'mode' => 'column'])
                        </td>
                        <td width="34" style="vertical-align:top; padding-left:6px;">
                            {{-- Indicador de altura (vertical) --}}
                            <div style="position:relative; width:28px; height:{{ $module['height'] }}px;">
                                <div style="position:absolute; top:0; left:0; width:100%; text-align:center; color:#94a3b8; font-size:9px;">▲</div>
                                <div style="position:absolute; top:14px; bottom:14px; left:13px; width:1px; background:#cbd5e1;"></div>
                                <div style="position:absolute; top:0; bottom:0; left:0; width:100%;">
                                    <div style="transform:rotate(-90deg); position:absolute; top:50%; left:-40px; width:120px; text-align:center; color:#64748b; font-size:8px; letter-spacing:0.5px; text-transform:uppercase;">
                                        {{ __('plannerate.print.module_page.total_height') }}: {{ $fmt($module['rawHeightCm']) }}mm
                                    </div>
                                </div>
                                <div style="position:absolute; bottom:0; left:0; width:100%; text-align:center; color:#94a3b8; font-size:9px;">▼</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- ---------- Identificação do módulo + largura ---------- --}}
            <div style="text-align:center; margin-top:8px;">
                <div class="mod-ident">
                    {{ __('plannerate.print.labels.module') }} #{{ $module['ordering'] }} — {{ __('plannerate.print.module_page.front') }}
                </div>
                <table cellpadding="0" cellspacing="0" style="margin:5px auto 0; width:60%;">
                    <tr>
                        <td class="flow-line">&nbsp;</td>
                        <td class="width-label" style="padding:0 6px;">{{ __('plannerate.print.product_detail.width') }}: {{ $fmt($module['rawWidthCm']) }}mm</td>
                        <td class="flow-line">&nbsp;</td>
                    </tr>
                </table>
            </div>
        </div>
    @endforeach
</body>

</html>
