Sub AnaliseCompletaSortimentoEStatus()

    Dim ws As Worksheet
    Dim configWs As Worksheet
    Dim ultimaLinha As Long
    Dim i As Long, j As Long
    Dim categoriaAtual As String
    Dim totalPonderado As Double
    Dim acumulado As Double
    Dim menorPercentualB As Double
    Dim percentualIndividual As Double
    Dim ranking As Long

    Dim qtde As Double
    Dim valor As Double
    Dim margem As Double
    Dim pesoQtde As Double
    Dim pesoValor As Double
    Dim pesoMargem As Double
    Dim somaPesos As Double
    Dim mediaPonderada As Double

    Dim corteA As Double
    Dim corteB As Double
    
    Dim dataHoje As Date
    Dim ultimaCompra As Date
    Dim ultimaVenda As Date
    Dim estoqueAtual As Double

    Set ws = ThisWorkbook.Sheets("Dados")
    Set configWs = ThisWorkbook.Sheets("Configurações")
    
    dataHoje = Date

    ' Ler pesos e cortes
    pesoQtde = CDbl(configWs.Range("B2").Value)
    pesoValor = CDbl(configWs.Range("B3").Value)
    pesoMargem = CDbl(configWs.Range("B4").Value)

    corteA = CDbl(configWs.Range("E2").Value)
    corteB = CDbl(configWs.Range("E3").Value)

    ultimaLinha = ws.Cells(ws.Rows.Count, "A").End(xlUp).Row

    ' >>> 1. Média Ponderada
    For i = 2 To ultimaLinha

        If IsNumeric(ws.Cells(i, "D").Value) Then
            qtde = CDbl(ws.Cells(i, "D").Value)
        Else
            qtde = 0
        End If

        If IsNumeric(ws.Cells(i, "E").Value) Then
            valor = CDbl(ws.Cells(i, "E").Value)
        Else
            valor = 0
        End If

        If IsNumeric(ws.Cells(i, "F").Value) Then
            margem = CDbl(ws.Cells(i, "F").Value)
        Else
            margem = 0
        End If

        somaPesos = 0
        mediaPonderada = 0

        If qtde <> 0 Then somaPesos = somaPesos + pesoQtde: mediaPonderada = mediaPonderada + (qtde * pesoQtde)
        If valor <> 0 Then somaPesos = somaPesos + pesoValor: mediaPonderada = mediaPonderada + (valor * pesoValor)
        If margem <> 0 Then somaPesos = somaPesos + pesoMargem: mediaPonderada = mediaPonderada + (margem * pesoMargem)

        If somaPesos <> 0 Then
            ws.Cells(i, "K").Value = WorksheetFunction.Round(mediaPonderada / somaPesos, 6)
        Else
            ws.Cells(i, "K").Value = 0
        End If
    Next i

    ' >>> Ordenar Categoria + Média Ponderada
    ws.Sort.SortFields.Clear
    ws.Sort.SortFields.Add Key:=ws.Range("C2:C" & ultimaLinha), Order:=xlAscending
    ws.Sort.SortFields.Add Key:=ws.Range("K2:K" & ultimaLinha), Order:=xlDescending
    With ws.Sort
        .SetRange ws.Range("A1:R" & ultimaLinha)
        .Header = xlYes
        .Apply
    End With

    ' >>> Porcentagem Individual e Acumulada
    categoriaAtual = ""
    For i = 2 To ultimaLinha
        If ws.Cells(i, "C").Value <> categoriaAtual Then
            categoriaAtual = ws.Cells(i, "C").Value
            totalPonderado = 0
            For j = 2 To ultimaLinha
                If ws.Cells(j, "C").Value = categoriaAtual Then totalPonderado = totalPonderado + ws.Cells(j, "K").Value
            Next j
            acumulado = 0
        End If

        percentualIndividual = ws.Cells(i, "K").Value / totalPonderado
        acumulado = acumulado + percentualIndividual

        ws.Cells(i, "L").Value = percentualIndividual
        ws.Cells(i, "M").Value = acumulado
    Next i

    ' >>> Classificação ABC com corteA e corteB dinâmicos
    For i = 2 To ultimaLinha
        If ws.Cells(i, "M").Value <= corteA Then
            ws.Cells(i, "N").Value = "A"
        ElseIf ws.Cells(i, "M").Value <= corteB Then
            ws.Cells(i, "N").Value = "B"
        Else
            ws.Cells(i, "N").Value = "C"
        End If
    Next i

    ' >>> Ranking
    categoriaAtual = ""
    ranking = 1
    For i = 2 To ultimaLinha
        If ws.Cells(i, "C").Value <> categoriaAtual Then
            categoriaAtual = ws.Cells(i, "C").Value
            ranking = 1
        End If
        ws.Cells(i, "O").Value = ranking
        ranking = ranking + 1
    Next i

    ' >>> Class + Rank
    For i = 2 To ultimaLinha
        ws.Cells(i, "P").Value = ws.Cells(i, "N").Value & ws.Cells(i, "O").Value
    Next i

    ' >>> Retirar do Mix
    categoriaAtual = ""
    For i = 2 To ultimaLinha
        If ws.Cells(i, "C").Value <> categoriaAtual Then
            categoriaAtual = ws.Cells(i, "C").Value
            menorPercentualB = 1
            For j = 2 To ultimaLinha
                If ws.Cells(j, "C").Value = categoriaAtual And ws.Cells(j, "N").Value = "B" Then
                    If ws.Cells(j, "L").Value < menorPercentualB Then menorPercentualB = ws.Cells(j, "L").Value
                End If
            Next j
        End If

        If ws.Cells(i, "N").Value = "C" And ws.Cells(i, "L").Value < menorPercentualB / 2 Then
            ws.Cells(i, "R").Value = "Sim"
        Else
            ws.Cells(i, "R").Value = "Não"
        End If
    Next i

    ' >>> Status do Produto com regra do estoque
    For i = 2 To ultimaLinha

        If IsNumeric(ws.Cells(i, "I").Value) Then
            estoqueAtual = ws.Cells(i, "I").Value
        Else
            estoqueAtual = 0
        End If

        If IsDate(ws.Cells(i, "G")) Then
            ultimaCompra = ws.Cells(i, "G").Value
        Else
            ultimaCompra = 0
        End If

        If IsDate(ws.Cells(i, "H")) Then
            ultimaVenda = ws.Cells(i, "H").Value
        Else
            ultimaVenda = 0
        End If

        If ultimaVenda <> 0 And dataHoje - ultimaVenda <= 120 And ultimaCompra <> 0 And dataHoje - ultimaCompra <= 180 Then
            ws.Cells(i, "Q").Value = "Ativo"
            ws.Cells(i, "S").Value = "Venda e compra recentes"
        ElseIf ultimaVenda <> 0 And dataHoje - ultimaVenda <= 120 And (ultimaCompra = 0 Or dataHoje - ultimaCompra > 180) Then
            ws.Cells(i, "Q").Value = "Ativo"
            ws.Cells(i, "S").Value = "Venda recente, sem compra"
        ElseIf ultimaCompra <> 0 And dataHoje - ultimaCompra <= 180 And (ultimaVenda = 0 Or dataHoje - ultimaVenda > 120) Then
            ws.Cells(i, "Q").Value = "Ativo"
            ws.Cells(i, "S").Value = "Compra recente, sem venda"
        Else
            ws.Cells(i, "S").Value = "Sem venda e sem compra"
            If estoqueAtual = 0 Then
                ws.Cells(i, "Q").Value = "Inativo"
            Else
                ws.Cells(i, "Q").Value = "Ativo"
            End If
        End If
    Next i

    ' >>> Formatando porcentagens
    ws.Columns("L").NumberFormat = "0.00%"
    ws.Columns("M").NumberFormat = "0.00%"

    MsgBox "Processo concluído com sucesso!"

End Sub