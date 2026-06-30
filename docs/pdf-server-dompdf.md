# PDF da gôndola server-side (dompdf)

> Geração do PDF do planograma movida do frontend (html2canvas/jsPDF) para o
> **servidor com `barryvdh/laravel-dompdf`**. Implementado, mergeado na `main` e
> **em produção**. Esta doc registra o que foi feito e o que falta, para
> continuar em outras sessões.

Branch de desenvolvimento: `feat/pdf-server-dompdf` (já mergeada na `main`,
fast-forward). Backup do pré-merge: branch `backup/main-pre-pdf-merge`.

---

## 1. Motivação

O PDF era 100% frontend (`html2canvas-pro` + `jsPDF`, com _tiling_ por módulo) e
dava problemas recorrentes: produtos cortados no topo, módulos virando "filete
vertical", imagens sumindo, e estado de DOM inconsistente entre gerações
(exigindo reload da página). Causa raiz: fragilidade do html2canvas para
rasterizar layouts grandes com muitas imagens e posicionamento absoluto.

Decisão: renderizar o mesmo visual em Blade/HTML e gerar o PDF no servidor com
dompdf (já instalado, `^3.1`, e já usado no relatório de tabela).

**Escopo entregue:** os dois modos — "em linha" (A4 landscape, todos os módulos
lado a lado) e "por módulo" (A4 portrait, 1 página por módulo). A tela de preview
interativa (zoom, toggle) foi **mantida**; só o botão "Baixar PDF" passou a
chamar a rota server-side. O pipeline html2canvas continua no código como
fallback, apenas desconectado do botão.

---

## 2. Arquitetura

dompdf **não suporta** flexbox, `column-gap` nem `transform`. Suporta bem
`position:absolute` (top/left/bottom/width/height) e `<img>`. Por isso toda a
geometria que no frontend é feita com flexbox/scale é resolvida em PHP e
convertida para **posição absoluta em pixels**.

Fluxo:

```
Rota  → GondolaReportController
      → GondolaPrintService::prepareGondolaData()   (payload + imagens base64)
      → PlanogramPdfLayoutService::build*Layout()    (geometria TS→PHP, px absolutos, fit-to-page)
      → Pdf::loadView('plannerate::pdf.*', ...)       (Blade dompdf-friendly)
      → stream() ou download()
```

### Arquivos (no pacote `packages/callcocam/laravel-raptor-plannerate`)

| Arquivo | Papel |
|---|---|
| `src/Services/Export/PlanogramPdfLayoutService.php` | **Núcleo.** Porta a geometria do TS para PHP e devolve estrutura "burra" pronta pro Blade (cada módulo/prateleira/barra/célula em px absolutos). Fit-to-page determinístico. |
| `resources/views/pdf/planogram-row.blade.php` | Página "em linha" (landscape). |
| `resources/views/pdf/planogram-modules.blade.php` | Páginas "por módulo" (portrait, `page-break-after`). |
| `resources/views/pdf/partials/_module-section.blade.php` | Render de UM módulo (cremalheira + furos + prateleiras + barras + produtos). Compartilhado pelos dois modos. |
| `src/Http/Controllers/Export/GondolaReportController.php` | Métodos `generatePlanogramRowPdf` / `generatePlanogramModulesPdf` + helpers (`planogramViewData`, `pdfOptions`, `brandLogo`, `currentTenantName`). |
| `routes/export.php` | Rotas (ver abaixo). |
| `resources/js/components/plannerate/print/PdfPreview.vue` | `handleDownloadPdf` / `handleGenerateFromSelector` agora abrem a rota server-side. |
| `resources/js/components/plannerate/print/pdfRoutes.ts` | Helper de URL manual (sem Wayfinder). |
| `tests/Unit/PlanogramPdfLayoutServiceTest.php` | 6 testes da geometria (pura, sem DB). |
| `lang/pt_BR/plannerate/print.php` | Chave nova `preview.shelf_short` = "Prat". |

### Rotas

Grupo `export/gondola-report` (middleware `auth` + `tenant.client.redirect`):

- `GET export/gondola-report/{gondola}/planogram-pdf` → `generatePlanogramRowPdf` (name `export.gondola-report.planogram`)
- `GET export/gondola-report/{gondola}/planogram-modules-pdf` → `generatePlanogramModulesPdf` (name `export.gondola-report.planogram-modules`)

Query params: `?download=1` baixa (senão abre inline/stream); `?sectionIds=a,b,c`
filtra módulos no modo "por módulo".

---

## 3. Geometria portada (TS → PHP)

Portado **verbatim** dos composables/componentes Vue para garantir o mesmo
visual da tela de preview:

- `calculateHolePositions(section)` ← `useSectionHoles.ts` (usa
  `calculateUsableHeight` e `DEFAULT_SECTION_FIELDS` de `useSectionFields.ts`).
- `calculateShelfArea(shelf, previousShelf)` ← `useShelfAreaCalculation.ts`
  (`minSpacing=2`, `minAreaHeight=50`).
- `shelfBasePosition` (centragem no furo mais próximo) e
  `justifyGap`/alinhamento ← `PdfShelf.vue` / `useShelfLayout.ts`.
- Ordenação: módulos por `ordering` asc, invertido se `flow=right_to_left`
  (`PdfPreview.vue`); prateleiras por `shelf_position` asc (`PdfSection.vue`).
- `product_type==='hook'` (gancheira): produtos ancoram pelo TOPO e penduram
  para baixo (demais ancoram pela BASE e crescem para cima).

### Fit-to-page

`PlanogramPdfLayoutService` calcula um `pxPerCm` que faz o conteúdo caber
exatamente na caixa útil do A4 — **independente do zoom de preview**. Isso
elimina por construção a classe de bugs "cortado/filete". Constantes ajustáveis
no serviço:

- `ROW_CONTENT_WIDTH = 1080`, `ROW_BAND_HEIGHT = 470` (modo linha)
- `COL_CONTENT_WIDTH = 740`, `COL_CONTENT_HEIGHT = 820` (modo por módulo)
- `TOP_HEADROOM_CM = 50` (folga no topo p/ produtos altos da prateleira de cima)

---

## 4. Gotchas resolvidos (não regredir)

1. **Setas/estrelas viravam "?"** — `font-family: sans-serif` no dompdf = Helvetica,
   sem glifos `→ ← ★ ☆`. Fix: `defaultFont => 'DejaVu Sans'` **E**
   `body{font-family:'DejaVu Sans'}` nos dois Blades.
2. **`trans('plannerate.print...')` RESOLVE no servidor** (testado) apesar do
   split em subpastas `lang/pt_BR/plannerate/` — pode usar `__()` no Blade.
3. **Cremalheira do meio sumia (modo colunas)** — `showLeftCremalheira` era só no
   1º módulo (correto no modo linha, onde a rail é compartilhada). No modo "por
   módulo" cada página é isolada → `buildModulesLayout` força
   `showLeftCremalheira = true`.
4. **Logo** — `marca-claro.png` embutida em base64 (`brandLogo()`), não por path
   (evita depender de acesso a arquivo do dompdf).
5. **Furos da cremalheira deslocados para a direita** — `*{box-sizing:border-box}`
   não é aplicado de forma confiável em divs com style inline no dompdf; a borda
   de 1px do trilho deslocava a origem e a borda do furo o expandia. Fix:
   `box-sizing:border-box` **inline** no trilho e no furo + `left` calculado
   `(cremalheiraWidth - holeWidth)/2 - 1` (o -1 compensa a borda do trilho).
6. **Teste** — helpers prefixados `pdfFake*` p/ evitar colisão global com
   `AutoPlanogramRegressionTest::fakeSection`.

---

## 5. Deploy / Infra

O build de produção (`vps-v2-build-push`, `Dockerfile.prod`) falhou **por
flakiness do PECL** (download corrompido de `pecl.php.net` — `unable to unpack`,
quebrava em pacote diferente a cada run: msgpack, depois redis), **não por
código nosso** (o mesmo commit buildou na `dev`). Corrigido com retry:

- `Dockerfile.prod`: função `_pecl_retry` (5 tentativas, limpando
  `/tmp/pear/download` entre elas) + `pecl channel-update pecl.php.net` antes dos
  installs. Commit `fix(docker): retry pecl installs...`. Build e deploy de
  produção passaram depois disso.
- **Pendência menor:** aplicar o mesmo retry no `docker/php/Dockerfile` (dev).

---

## 6. TODO / Próximos passos

### 6.1. Overflow → encolher para caber na largura

**Problema:** no editor, segurando **Shift** é possível adicionar mais frentes do
que cabem na largura da seção. No overflow (soma das larguras dos produtos
> largura da seção), os produtos transbordam / ficam "forçados".

**PDF — FEITO** (encolhimento **só horizontal**, decidido com o usuário em
2026-06-30): em `PlanogramPdfLayoutService::buildCells`, quando
`totalProductsWidthCm > sectionWidthCm`, calcula
`fit = sectionWidthCm / totalProductsWidthCm` e aplica `fit` **só** à largura
desenhada de cada produto (`drawWidthCm`) e ao avanço de x — a **altura física é
preservada** (só "achata" na horizontal). Sem overflow, `fit == 1` e nada muda.
Coberto por 2 testes em `PlanogramPdfLayoutServiceTest` (overflow encaixa em
`areaWidth`; sem overflow mantém largura física).

**Editor — PENDENTE (follow-up):** aplicar a mesma lógica no editor
(`useShelfLayout.ts` → `justifyGap` retorna `null` em `freeSpacePx <= 0` e cai no
`justify-evenly`; flex não encolhe itens de largura fixa → transborda) e nos
componentes `Shelf`/`Segment`/`Layer`, para tela e PDF baterem. Cuidado com a
reatividade do drag (ver memória de performance). Mantém o mesmo modo: só
horizontal.

### 6.2. Outras pendências
- Validação manual em produção: fluxo `right_to_left` e módulo hook (vassouras).
- `docker/php/Dockerfile` (dev): mesmo retry do PECL.
- Eventual limpeza do pipeline html2canvas (`usePdfGenerator`, `useCanvasCapture`,
  `generatePDF` em `PdfPreview.vue`) se o server-side se firmar — hoje mantido
  como fallback.

---

## 7. Como verificar

```bash
# Testes da geometria (rápidos, isolados)
docker compose exec php php artisan test --compact --filter=PlanogramPdfLayoutService

# Pint
docker compose exec php vendor/bin/pint --dirty --format agent

# No browser (tenant alberti): abrir gôndola → preview PDF → "Baixar PDF" nos 2 modos.
# Rotas diretas:
#   /export/gondola-report/{gondola}/planogram-pdf
#   /export/gondola-report/{gondola}/planogram-modules-pdf?sectionIds=...
```

Smoke test server-side (gera PDF sem browser, útil em dev): instanciar
`GondolaPrintService` + `PlanogramPdfLayoutService`, chamar `prepareGondolaData`
→ `buildRowLayout`/`buildModulesLayout` → `Pdf::loadView(...)->output()`, e
converter a 1ª página com ImageMagick (`convert -density 110 x.pdf[0] x.png`)
para inspeção visual. Lembrar de `Tenant::makeCurrent()` antes.
