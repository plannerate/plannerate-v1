{{--
    PDF da gôndola — modo "em linha" (A4 landscape, todos os módulos lado a lado).
    Reproduz a tela PdfPreview (row): cabeçalho (PdfGondolaHeader), indicador de
    fluxo (PdfFlowIndicator) e rodapé (PdfPageFooter). Layout em posicionamento
    absoluto / tabelas porque o dompdf não suporta flexbox nem SVG inline (os
    ícones lucide são embutidos como PNG base64 via $icons).

    Variáveis:
      $gondola      — array gondola de prepareGondolaData()
      $layout       — saída de PlanogramPdfLayoutService::buildRowLayout()
      $logo         — logo Plannerate em base64
      $icons        — mapa nome => data-URI (ícones lucide rasterizados)
      $tenantName   — nome do tenant
      $responsavel  — responsável
      $flowLabel    — rótulo do fluxo já traduzido
      $isLeftToRight — bool
      $observacoes  — texto de observações
--}}
@php
$planogram = $gondola['planogram'] ?? null;
$title = $planogram['name'] ?? __('plannerate.print.preview.exposure_planogram');

// Cada item: [ícone, rótulo, valor]. Ordem e ícones idênticos ao
// PdfGondolaHeader.vue (metaItems).
$meta = [
['building-2', __('plannerate.print.preview.client'), $tenantName ?: '—'],
['layout-grid', __('plannerate.print.share.module'), $gondola['name'] ?? '—'],
['store', __('plannerate.print.labels.store'), $gondola['location'] ?? '—'],
['package', __('plannerate.print.labels.category'), $planogram['category']['name'] ?? '—'],
['layers', __('plannerate.print.preview.modules'), (string) count($layout['modules'])],
['calendar-days', __('plannerate.print.labels.publication'), $planogram['start_date'] ?? '—'],
['user', __('plannerate.print.labels.responsible'), $responsavel ?: '—']
];

// Indicador de fluxo (PdfFlowIndicator.vue): o lado ATIVO é o início do
// fluxo. Esquerda→direita ativa a esquerda (★); direita→esquerda ativa a
// direita. O lado inativo mostra ☆ e a cor slate.
$startLabel = __('plannerate.indicator.start_flow');
$endLabel = __('plannerate.indicator.end');
$arrow = $isLeftToRight ? '→' : '←';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">

    <link rel="icon" href="/img/logo.jpg" sizes="any">
    <link rel="icon" href="/img/logo.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/img/logo.jpg">
    <style>
        @page {
            margin: 8px 10px;
        }

        * {
            box-sizing: border-box;
        }

        /* Fundo slate-50 = área do "canvas" (módulos) da tela; as faixas de
           cabeçalho/fluxo/rodapé recebem branco explícito por cima. */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0f172a;
            margin: 0;
            background: #f8fafc;
        }

        /* ---------- Cabeçalho (PdfGondolaHeader) ---------- */
        .header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 6px;
        }

        .header td {
            vertical-align: middle;
        }

        .tenant {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #64748b;
            padding-bottom: 2px;
        }

        .brand-title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #0f172a;
            line-height: 1;
        }

        .meta-label {
            font-size: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #94a3b8;
            white-space: nowrap;
        }

        /* Ícone lucide (verde) coladinho ao rótulo, centralizado na linha. */
        .meta-label img {
            height: 9px;
            width: 9px;
            vertical-align: middle;
            margin-right: 2px;
        }

        .meta-label span {
            vertical-align: middle;
        }

        .meta-value {
            display: inline-block;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 1px;
            font-size: 11px;
            font-weight: bold;
            color: #334155;
        }

        .version {
            background: #64a333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 8px;
        }

        .version small {
            font-size: 7px;
            text-transform: uppercase;
        }

        .version b {
            font-size: 15px;
        }

        /* ---------- Indicador de fluxo (PdfFlowIndicator) ---------- */
        .flow {
            background: #fff;
            border-top: 1px solid #f1f5f9;
            padding: 5px 10px;
        }

        .flow-badge {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 9999px;
            text-align: center;
            line-height: 18px;
            font-size: 10px;
            font-weight: bold;
            vertical-align: middle;
        }

        .flow-side-label {
            font-size: 11px;
            font-weight: bold;
            vertical-align: middle;
        }

        .flow-line {
            border-bottom: 1px solid #cbd5e1;
            font-size: 0;
            line-height: 1px;
        }

        .flow-arrow {
            font-size: 12px;
            font-weight: bold;
            color: #64a333;
        }

        .flow-center-label {
            font-size: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #94a3b8;
        }

        /* ---------- Rodapé (PdfPageFooter) ---------- */
        .footer {
            background: #fff;
            border-top: 1px solid #f1f5f9;
            padding: 8px 10px 6px;
        }

        .footer .obs-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
        }

        .footer .obs-text {
            font-size: 10px;
            color: #64748b;
            padding-top: 2px;
        }

        .footer-bar {
            position: relative;
            height: 34px;
            background: #0f172a;
            overflow: hidden;
        }

        .footer-bar .corner {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 60px;
            height: 60px;
            background: #64a333;
            border-top-left-radius: 9999px;
        }
    </style>
</head>

<body>
    {{-- Cabeçalho --}}
    <div class="header">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                @if (! empty($logo))
                <td width="46">
                    <img src="{{ $logo }}" alt="Plannerate" style="height:38px; width:auto;" />
                </td>
                @endif

                <td style="border-left:1px solid #e2e8f0; padding-left:12px;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            @foreach ($meta as $item)
                            <td style="text-align:right; vertical-align:top; padding:0 6px;">
                                <span class="meta-label">@if (! empty($icons[$item[0]]))<img src="{{ $icons[$item[0]] }}" alt="" style="height:9px; width:9px; vertical-align:middle; margin-right:3px;" />@endif<span style="vertical-align:middle;">{{ $item[1] }}</span></span><br>
                                <span class="meta-value">{{ $item[2] }}</span>
                            </td>
                            @endforeach
                        </tr>
                    </table>
                </td>
                <td width="54" align="right">
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
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                {{-- Lado esquerdo (início quando esquerda→direita). --}}
                <td width="20%" style="vertical-align:middle;">
                    <span class="flow-badge"
                        style="background:{{ $isLeftToRight ? '#64a333' : '#e2e8f0' }}; color:{{ $isLeftToRight ? '#fff' : '#94a3b8' }};">{{ $isLeftToRight ? '★' : '☆' }}</span>
                    <span class="flow-side-label"
                        style="color:{{ $isLeftToRight ? '#64a333' : '#94a3b8' }};">{{ $isLeftToRight ? $startLabel : $endLabel }}</span>
                </td>
                {{-- Centro: linha — seta — rótulo — seta — linha. --}}
                <td style="vertical-align:middle;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="flow-line">&nbsp;</td>
                            <td style="white-space:nowrap; text-align:center; padding:0 6px;">
                                <span class="flow-arrow">{{ $arrow }}</span>
                                <span class="flow-center-label">{{ __('plannerate.print.labels.gondola_flow') }}</span>
                                <span class="flow-arrow">{{ $arrow }}</span>
                            </td>
                            <td class="flow-line">&nbsp;</td>
                        </tr>
                    </table>
                </td>
                {{-- Lado direito (início quando direita→esquerda). --}}
                <td width="20%" align="right" style="vertical-align:middle;">
                    <span class="flow-side-label"
                        style="color:{{ ! $isLeftToRight ? '#64a333' : '#94a3b8' }};">{{ ! $isLeftToRight ? $startLabel : $endLabel }}</span>
                    <span class="flow-badge"
                        style="background:{{ ! $isLeftToRight ? '#64a333' : '#e2e8f0' }}; color:{{ ! $isLeftToRight ? '#fff' : '#94a3b8' }};">{{ ! $isLeftToRight ? '★' : '☆' }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Faixa de módulos --}}
    <div style="position:relative; width:{{ $layout['bandWidth'] }}px; height:{{ $layout['bandHeight'] }}px; margin:10px auto 0;">
        @foreach ($layout['modules'] as $module)
        @include('plannerate::pdf.partials._module-section', ['module' => $module, 'mode' => 'row'])
        @endforeach
    </div>

    {{-- Rodapé --}}
    <div style="position:absolute; bottom:0; left:0; width:100%;">
        <div class="footer">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="52" style="vertical-align:middle;">
                        <div style="width:38px; height:38px; border-radius:9999px; background:#64a333; text-align:center;">
                            @if (! empty($icons['clipboard-list-white']))
                            <img src="{{ $icons['clipboard-list-white'] }}" style="height:19px; width:19px; margin-top:9px;" />
                            @endif
                        </div>
                    </td>
                    <td style="vertical-align:middle;">
                        <div class="obs-title">{{ __('plannerate.print.labels.observations') }}:</div>
                        @if ($tenantName)
                        <div class="tenant">{{ $tenantName }}</div>
                        @endif
                        <div class="brand-title">{{ $title }}</div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="footer-bar">
            <div class="corner"></div>
        </div>
    </div>
</body>

</html>