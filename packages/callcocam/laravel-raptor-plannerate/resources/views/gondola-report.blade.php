<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Reposição - {{ $gondola_name }}</title>
    <style>
        /* Reset básico */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Configurações de página */
        @page {
            margin: 20mm;
            size: A4;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            background: white;
        }

        /* Cabeçalho principal */
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #2c5530;
        }

        .header h1 {
            font-size: 20px;
            color: #080404;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header .subtitle {
            font-size: 12px;
            color: #666;
            font-style: italic;
        }

        /* Informações do planograma */
        .info-section {
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #2c5530;
        }

        .info-section h2 {
            font-size: 14px;
            color: #2c5530;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .info-row {
            margin-bottom: 5px;
            display: block;
        }

        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 120px;
        }

        .info-value {
            color: #333;
        }

        /* Seção de resumo */
        .summary-section {
            margin-bottom: 25px;
        }

        .summary-title {
            font-size: 14px;
            color: #080404;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
            border-bottom: 2px solid #080404;
            padding-bottom: 5px;
        }

        .summary-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .summary-grid td {
            width: 25%;
            text-align: center;
            padding: 12px 8px;
            background-color: #e9ecef;
            border: 1px solid #ddd;
        }

        .summary-label {
            font-size: 9px;
            color: #666;
            font-weight: normal;
            display: block;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #080404;
            display: block;
        }

        /* Seção de produtos */
        .products-section {
            margin-bottom: 20px;
        }

        .products-title {
            font-size: 14px;
            color: #080404;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
            border-bottom: 2px solid #080404;
            padding-bottom: 5px;
        }

        /* Tabela de produtos */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 15px;
        }

        .products-table th {
            background-color: #9cf737;
            color: black;
            font-weight: bold;
            padding: 12px 4px;
            text-align: center;
            border: 1px solid #fff;
            font-size: 12px;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .products-table td {
            padding: 10px 4px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 11px;
            vertical-align: top;
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            line-height: 1.4;
            min-height: 30px;
        }

        /* Cores alternadas nas linhas */
        .products-table tr:nth-child(even) td {
            background-color: #f8f9fa;
        }

        .products-table tr:nth-child(odd) td {
            background-color: white;
        }

        /* Colunas específicas */
        .col-planogram { width: 12%; }
        .col-flow { width: 8%; text-align: center; }
        .col-module { width: 8%; text-align: center; }
        .col-shelf { width: 6%; text-align: center; }
        .col-id { width: 10%; font-family: monospace; }
        .col-ean { width: 10%; font-family: monospace; }
        .col-name { width: 24%; }
        .col-fronts { width: 6%; text-align: center; }
        .col-height { width: 6%; text-align: center; }
        .col-depth { width: 6%; text-align: center; }
        .col-total { width: 8%; text-align: center; font-weight: bold; color: #080404; }

        /* Nome do produto com quebra de texto */
        .product-name {
            font-weight: 500;
            line-height: 1.3;
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            overflow-wrap: break-word;
        }

        /* Rodapé */
        .footer {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            text-align: center;
            font-size: 8px;
            color: #666;
        }

        .footer p {
            margin: 3px 0;
        }

        .footer .company {
            font-weight: bold;
            color: #080404;
        }

        /* Quebras de página */
        .page-break {
            page-break-before: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

        /* Ajustes para impressão */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .products-table {
                font-size: 10px;
            }
            
            .products-table th,
            .products-table td {
                padding: 6px 3px;
            }
        }
    </style>
</head>
<body>
    <!-- Cabeçalho -->
    <div class="header no-break">
        <h1>Relatório de Gôndola</h1>
        <div class="subtitle">Análise Detalhada de Planograma</div>
    </div>

    <!-- Resumo Executivo -->
    <div class="summary-section no-break">
        <div class="summary-title">Resumo Executivo</div>
        
        <table class="summary-grid">
            <tr>
                <td>
                    <span class="summary-label">Planograma</span>
                    <span class="summary-value">{{ $gondola_name ?? 'N/A' }}</span>
                </td>
                <td>
                    <span class="summary-label">Total de Módulos</span>
                    <span class="summary-value">{{ $summary['total_sections'] ?? 0 }}</span>
                </td>
                <td>
                    <span class="summary-label">Total de Produtos</span>
                    <span class="summary-value">{{ count($products) }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="summary-label">Fluxo</span>
                    <span class="summary-value">{{ $products[0]['fluxo'] ?? 'N/A' }}</span>
                </td>
                <td>
                    <span class="summary-label">Total de Prateleiras</span>
                    <span class="summary-value">{{ $summary['total_shelves'] ?? 0 }}</span>
                </td>
                <td>
                    <span class="summary-label">Total de Unidades</span>
                    <span class="summary-value">{{ number_format(array_sum(array_column($products, 'total_unidades'))) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tabela de Produtos -->
    <div class="products-section">
        <div class="products-title">Detalhamento dos Produtos</div>
        
        <table class="products-table">
            <thead>
                <tr>
                    <th class="col-module">Módulo</th>
                    <th class="col-shelf">Prat.</th>
                    <th class="col-id">Código ERP</th>
                    <th class="col-ean">EAN</th>
                    <th class="col-name">Nome do Produto</th>
                    <th class="col-fronts">Frentes</th>
                    <th class="col-height">Empilhamento</th>
                    <th class="col-depth">Und. Prof</th>
                    <th class="col-total">Total Und</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // Filtrar apenas produtos da gôndola (não da biblioteca)
                    // array_values reindexa as chaves para que o acesso por índice ([0]) seja seguro
                    $gondolaProducts = array_values(array_filter($products, function($product) {
                        return ($product['source'] ?? 'gondola') === 'gondola';
                    }));
                    
                    // Aplicar lógica de ordenação por fluxo (igual ao Excel)
                    if (!empty($gondolaProducts)) {
                        $flow = $gondolaProducts[0]['fluxo'] ?? 'left_to_right';
                        
                        // Encontrar o número máximo de módulos
                        $maxModule = 0;
                        foreach ($gondolaProducts as $product) {
                            $modulo = $product['modulo'] ?? '';
                            preg_match('/MÓDULO (\d+)/', $modulo, $matches);
                            $moduleNumber = isset($matches[1]) ? (int)$matches[1] : 0;
                            $maxModule = max($maxModule, $moduleNumber);
                        }
                        
                        // Processar cada produto para renumerar módulos se necessário
                        $processedProducts = [];
                        foreach ($gondolaProducts as $product) {
                            $modulo = $product['modulo'] ?? '';
                            preg_match('/MÓDULO (\d+)/', $modulo, $matches);
                            $originalModuleNumber = isset($matches[1]) ? (int)$matches[1] : 0;
                            
                            // Se o fluxo é da direita para esquerda, renumerar os módulos
                            if (strpos($flow, 'right_to_left') !== false || 
                                strpos($flow, 'Direita para Esquerda') !== false ||
                                strpos($flow, 'right') !== false) {
                                
                                // Renumerar: módulo 1 vira o último, módulo 2 vira o penúltimo, etc.
                                $newModuleNumber = $maxModule - $originalModuleNumber + 1;
                                $product['modulo'] = "MÓDULO " . $newModuleNumber;
                            }
                            
                            $processedProducts[] = $product;
                        }
                        
                        // Ordenar por número do módulo e depois por número da prateleira (crescente)
                        usort($processedProducts, function($a, $b) {
                            $moduloA = $a['modulo'] ?? '';
                            $moduloB = $b['modulo'] ?? '';
                            
                            preg_match('/MÓDULO (\d+)/', $moduloA, $matchesA);
                            preg_match('/MÓDULO (\d+)/', $moduloB, $matchesB);
                            
                            $moduleA = isset($matchesA[1]) ? (int)$matchesA[1] : 0;
                            $moduleB = isset($matchesB[1]) ? (int)$matchesB[1] : 0;
                            
                            // Se os módulos são diferentes, ordenar por módulo
                            if ($moduleA !== $moduleB) {
                                return $moduleA - $moduleB;
                            }
                            
                            // Se os módulos são iguais, ordenar por prateleira
                            $prateleiraA = $a['prateleira'] ?? '';
                            $prateleiraB = $b['prateleira'] ?? '';
                            
                            preg_match('/Prateleira (\d+)/', $prateleiraA, $matchesA);
                            preg_match('/Prateleira (\d+)/', $prateleiraB, $matchesB);
                            
                            $shelfA = isset($matchesA[1]) ? (int)$matchesA[1] : 0;
                            $shelfB = isset($matchesB[1]) ? (int)$matchesB[1] : 0;
                            
                            return $shelfA - $shelfB;
                        });
                        
                        $gondolaProducts = $processedProducts;
                    }
                @endphp
                
                @foreach($gondolaProducts as $product)
                <tr>
                    <td class="col-module">{{ $product['modulo'] }}</td>
                    <td class="col-shelf">{{ $product['prateleira'] }}</td>
                    <td class="col-id">{{ $product['codigo_erp'] }}</td>
                    <td class="col-ean">{{ $product['ean'] }}</td>
                    <td class="col-name product-name">{{ $product['nome'] }}</td>
                    <td class="col-fronts">{{ number_format($product['frentes']) }}</td>
                    <td class="col-height">{{ number_format($product['unidades_altura']) }}</td>
                    <td class="col-depth">{{ number_format($product['unidades_profundidade']) }}</td>
                    <td class="col-total">{{ number_format($product['total_unidades']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Rodapé -->
    <div class="footer no-break">
        <p class="company">Plannerate - Sistema de Gestão de Planogramas</p>
        <p>Relatório gerado automaticamente em {{ $generated_at }}</p>
        <p>Total de produtos listados: {{ count($products) }}</p>
    </div>
</body>
</html>