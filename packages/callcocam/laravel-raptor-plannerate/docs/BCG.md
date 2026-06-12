<!--
NOTA DE IMPLEMENTAÇÃO (2026-06-12)

Este arquivo é a referência VBA original da planilha (matriz BCG por categoria).
A Análise de Papel implementada em src/Services/Analysis/PaperAnalysisService.php
usa share × crescimento por produto com as seguintes regras (diferentes do VBA):

- Limiar de crescimento RELATIVO: mediana dos growth_rate da categoria
  (produtos novos ficam fora do cálculo). Um limiar fixo pode ser definido
  via setGrowthThreshold().
- Produto novo (sem venda no período anterior): is_new = true,
  growth_rate = null e papel 'rising' (item em introdução) — não recebe
  +100% de crescimento artificial.
- Produto sem venda nos dois períodos: 'lagging' (candidato à revisão de mix).
- Papéis: leader / anchor / rising / lagging (share ≥ mediana × crescimento ≥ mediana).

Testes: tests/Unit/Services/Analysis/PaperAnalysisServiceTest.php
-->

Dim wsDados As Worksheet
Dim wsConfig As Worksheet
Dim wsResultado As Worksheet

Set wsDados = ThisWorkbook.Sheets("Dados")
Set wsConfig = ThisWorkbook.Sheets("Configurações")
Set wsResultado = ThisWorkbook.Sheets("Resultado")

' Limpar aba Resultado
wsResultado.Cells.ClearContents

' Obter nomes dos eixos
Dim eixoYNome As String, eixoXNome As String
eixoYNome = Trim(wsConfig.Cells(2, 1).Value)
eixoXNome = Trim(wsConfig.Cells(2, 2).Value)

If eixoYNome = "" Or eixoXNome = "" Then
    MsgBox "Nomes dos eixos não definidos corretamente na aba 'Configurações'.", vbCritical
    Exit Sub
End If

' Identificar colunas
Dim colEAN As Long, colDescricao As Long, colCategoria As Long, colY As Long, colX As Long
Dim col As Long, ultimaColuna As Long
ultimaColuna = wsDados.Cells(1, wsDados.Columns.Count).End(xlToLeft).Column

For col = 1 To ultimaColuna
    Select Case Trim(wsDados.Cells(1, col).Value)
        Case "EAN": colEAN = col
        Case "DESCRIÇÃO": colDescricao = col
        Case "CATEGORIA": colCategoria = col
        Case eixoYNome: colY = col
        Case eixoXNome: colX = col
    End Select
Next col

If colEAN = 0 Or colDescricao = 0 Or colCategoria = 0 Or colY = 0 Or colX = 0 Then
    MsgBox "Colunas obrigatórias não foram encontradas na aba 'Dados'.", vbCritical
    Exit Sub
End If

Dim lastRow As Long
lastRow = wsDados.Cells(wsDados.Rows.Count, 1).End(xlUp).Row

' Preparar estrutura para armazenar médias por categoria
Dim categorias() As String
Dim somaY() As Double, somaX() As Double, contagem() As Long
Dim numCategorias As Long: numCategorias = 0
Dim i As Long, j As Long
Dim catAtual As String
Dim existe As Boolean

' Primeira passagem: identificar categorias e acumular soma e contagem
For i = 2 To lastRow
    catAtual = Trim(wsDados.Cells(i, colCategoria).Value)
    existe = False

    ' Verifica se a categoria já existe
    For j = 1 To numCategorias
        If categorias(j) = catAtual Then
            existe = True
            Exit For
        End If
    Next j

    ' Se não existe, adicionar
    If Not existe Then
        numCategorias = numCategorias + 1
        ReDim Preserve categorias(1 To numCategorias)
        ReDim Preserve somaY(1 To numCategorias)
        ReDim Preserve somaX(1 To numCategorias)
        ReDim Preserve contagem(1 To numCategorias)

        categorias(numCategorias) = catAtual
    End If

    ' Atualizar soma e contagem
    For j = 1 To numCategorias
        If categorias(j) = catAtual Then
            If IsNumeric(wsDados.Cells(i, colY).Value) And IsNumeric(wsDados.Cells(i, colX).Value) Then
                somaY(j) = somaY(j) + wsDados.Cells(i, colY).Value
                somaX(j) = somaX(j) + wsDados.Cells(i, colX).Value
                contagem(j) = contagem(j) + 1
            End If
            Exit For
        End If
    Next j
Next i

' Cabeçalhos da aba Resultado
With wsResultado
    .Cells(1, 1).Value = "EAN"
    .Cells(1, 2).Value = "DESCRIÇÃO"
    .Cells(1, 3).Value = "CATEGORIA"
    .Cells(1, 4).Value = eixoYNome
    .Cells(1, 5).Value = eixoXNome
    .Cells(1, 6).Value = "CLASSIFICAÇÃO BCG"
End With

' Segunda passagem: classificar e escrever na aba Resultado
Dim linhaRes As Long: linhaRes = 2
Dim mediaY As Double, mediaX As Double
Dim valY As Double, valX As Double
Dim classificacao As String
Dim corLinha As Long

For i = 2 To lastRow
    catAtual = Trim(wsDados.Cells(i, colCategoria).Value)
    valY = wsDados.Cells(i, colY).Value
    valX = wsDados.Cells(i, colX).Value

    If IsNumeric(valY) And IsNumeric(valX) Then
        ' Buscar médias da categoria
        For j = 1 To numCategorias
            If categorias(j) = catAtual And contagem(j) > 0 Then
                mediaY = somaY(j) / contagem(j)
                mediaX = somaX(j) / contagem(j)
                Exit For
            End If
        Next j

        ' Aplicar classificação
        If valX >= mediaX And valY >= mediaY Then
            classificacao = "Alto valor – manutenção"
            corLinha = RGB(0, 176, 80) ' Verde
        ElseIf valX >= mediaX And valY < mediaY Then
            classificacao = "Incentivo – volume"
            corLinha = RGB(0, 176, 240) ' Azul claro
        ElseIf valX < mediaX And valY >= mediaY Then
            classificacao = "Incentivo – lucro"
            corLinha = RGB(191, 144, 255) ' Roxo claro
        Else
            classificacao = "Baixo valor – descontinuar"
            corLinha = RGB(255, 99, 71) ' Vermelho claro
        End If

        ' Preencher aba Resultado
        With wsResultado
            .Cells(linhaRes, 1).Value = wsDados.Cells(i, colEAN).Value
            .Cells(linhaRes, 2).Value = wsDados.Cells(i, colDescricao).Value
            .Cells(linhaRes, 3).Value = catAtual
            .Cells(linhaRes, 4).Value = valY
            .Cells(linhaRes, 5).Value = valX
            .Cells(linhaRes, 6).Value = classificacao
            .Cells(linhaRes, 6).Interior.Color = corLinha
        End With

        linhaRes = linhaRes + 1
    End If
Next i

MsgBox "Resultado da Matriz BCG por categoria gerado com sucesso na aba 'Resultado'.", vbInformation