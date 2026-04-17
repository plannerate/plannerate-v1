Sub CalcularEstoqueAlvo()

    Dim wsDados As Worksheet
    Dim wsResultado As Worksheet
    Dim wsNS As Worksheet
    Dim wsReposicao As Worksheet

    Dim ultimaLinha As Long, i As Long, j As Long
    Dim linhaResultado As Long
    Dim ean As String, produto As String, classificacao As String
    Dim somaVendas As Double, contagemDias As Long
    Dim media As Double, desvioPadrao As Double, variabilidade As Double
    Dim nivelServico As Double, zScore As Double, coberturaDias As Long
    Dim estoqueSeguranca As Double, estoqueMinimo As Double, estoqueAlvo As Double
    Dim estoqueAtual As Double, permiteFrentes As String
    Dim chaves() As String, chave As String
    Dim chaveEncontrada As Boolean

    Set wsDados = ThisWorkbook.Sheets("Dados produtos")
    Set wsResultado = ThisWorkbook.Sheets("Resultado calculo")
    Set wsNS = ThisWorkbook.Sheets("Parametros nivel de serviço ABC")
    Set wsReposicao = ThisWorkbook.Sheets("Parametros modelo de reposição")

    ' Corrigir nome da coluna de estoque atual, se necessário
    wsDados.Cells(1, 5).Value = "Estoque Atual"

    ' Limpa resultados anteriores
    wsResultado.Range("A2:L10000").ClearContents
    wsResultado.Range("A2:K10000").Interior.ColorIndex = xlNone ' Limpa cores anteriores

    ' Coleta produtos únicos
    ultimaLinha = wsDados.Cells(wsDados.Rows.Count, "A").End(xlUp).Row
    ReDim chaves(1 To 1)
    chaves(1) = ""

    linhaResultado = 2

    For i = 2 To ultimaLinha
        ean = wsDados.Cells(i, 1).Value
        produto = wsDados.Cells(i, 2).Value
        classificacao = wsDados.Cells(i, 6).Value

        chave = ean & "|" & produto & "|" & classificacao

        chaveEncontrada = False
        For j = 1 To UBound(chaves)
            If chaves(j) = chave Then
                chaveEncontrada = True
                Exit For
            End If
        Next j

        If Not chaveEncontrada Then
            ReDim Preserve chaves(1 To UBound(chaves) + 1)
            chaves(UBound(chaves)) = chave

            ' Calcular demanda média e capturar último estoque atual
            somaVendas = 0
            contagemDias = 0
            estoqueAtual = 0
            Dim intervalo As Range
            Dim primeiraLinha As Long: primeiraLinha = -1
            Dim ultimaLinhaProduto As Long: ultimaLinhaProduto = -1

            For j = 2 To ultimaLinha
                If wsDados.Cells(j, 1).Value = ean And wsDados.Cells(j, 2).Value = produto Then
                    somaVendas = somaVendas + wsDados.Cells(j, 4).Value
                    contagemDias = contagemDias + 1
                    estoqueAtual = wsDados.Cells(j, 5).Value
                    If primeiraLinha = -1 Then primeiraLinha = j
                    ultimaLinhaProduto = j
                End If
            Next j

            If contagemDias > 0 Then
                media = somaVendas / contagemDias
                Set intervalo = wsDados.Range("D" & primeiraLinha & ":D" & ultimaLinhaProduto)
                desvioPadrao = WorksheetFunction.StDev_P(intervalo)

                ' Alerta e destaque se variabilidade for alta
                If media > 0 Then
                    variabilidade = desvioPadrao / media
                    If variabilidade > 1 Then
                        MsgBox "?? ALERTA DE VARIABILIDADE" & vbCrLf & vbCrLf & _
                               "Produto: " & produto & vbCrLf & _
                               "EAN: " & ean & vbCrLf & _
                               "Desvio padrão está " & Format(variabilidade, "0.00%") & " acima da média." & vbCrLf & _
                               "Recomenda-se revisar esse comportamento antes de usar o cálculo de estoque.", vbExclamation, "Desvio Padrão Anormal"

                        ' Pintar a linha de amarelo na aba Resultado
                        wsResultado.Range("A" & linhaResultado & ":K" & linhaResultado).Interior.Color = RGB(255, 255, 153)
                    End If
                End If
            Else
                media = 0
                desvioPadrao = 0
            End If

            ' Obter o nível de serviço da aba de parâmetros
            nivelServico = WorksheetFunction.VLookup(classificacao, wsNS.Range("A2:B10"), 2, False)

            ' Validação do nível de serviço
            If nivelServico < 0.5 Or nivelServico >= 1 Then
                MsgBox "Nível de serviço inválido para o produto: " & produto & vbCrLf & _
                       "Classificação: " & classificacao & vbCrLf & _
                       "Valor informado: " & nivelServico & vbCrLf & vbCrLf & _
                       "Por favor, corrija o valor na aba 'Parametros nivel de serviço ABC'.", vbCritical, "Erro de Nível de Serviço"
                Exit Sub
            End If

            ' Calcular o z-score com base no nível de serviço
            zScore = Application.NormSInv(nivelServico)

            ' Obter cobertura de estoque em dias
            coberturaDias = WorksheetFunction.VLookup(classificacao, wsReposicao.Range("A2:B10"), 2, False)

            ' Calcular estoques
            estoqueSeguranca = zScore * desvioPadrao
            estoqueMinimo = media * coberturaDias
            estoqueAlvo = estoqueMinimo + estoqueSeguranca

            If estoqueAtual >= estoqueAlvo Then
                permiteFrentes = "Sim"
            Else
                permiteFrentes = "Não"
            End If

            ' Preencher resultado com arredondamento
            With wsResultado
                .Cells(linhaResultado, 1).Value = ean
                .Cells(linhaResultado, 2).Value = produto
                .Cells(linhaResultado, 3).Value = Round(media, 2)
                .Cells(linhaResultado, 4).Value = Round(desvioPadrao, 2)
                .Cells(linhaResultado, 5).Value = coberturaDias
                .Cells(linhaResultado, 6).Value = nivelServico
                .Cells(linhaResultado, 7).Value = Round(zScore, 3)
                .Cells(linhaResultado, 8).Value = Round(estoqueSeguranca, 0)
                .Cells(linhaResultado, 9).Value = Round(estoqueMinimo, 0)
                .Cells(linhaResultado, 10).Value = Round(estoqueAlvo, 0)
                .Cells(linhaResultado, 11).Value = permiteFrentes
            End With

            linhaResultado = linhaResultado + 1
        End If
    Next i

    MsgBox "Cálculo de estoque alvo finalizado com sucesso!", vbInformation

End Sub