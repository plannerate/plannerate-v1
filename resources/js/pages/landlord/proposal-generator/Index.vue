<script setup lang="ts">
/**
 * Gerador de Propostas — porte da ferramenta standalone docs/preview.html.
 *
 * Editor à esquerda, documento A4 ao vivo à direita. Estado, rascunhos e modelo padrão
 * ficam no localStorage do navegador (mesmas chaves da ferramenta original).
 */
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { onMounted, provide } from 'vue';
import ProposalGeneratorController from '@/actions/App/Http/Controllers/Landlord/ProposalGeneratorController';
import {
    PROPOSAL_GENERATOR_KEY,
    useProposalGenerator,
} from '@/composables/landlord/useProposalGenerator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import ProposalDocument from './partials/ProposalDocument.vue';
import ProposalEditor from './partials/ProposalEditor.vue';

const { t } = useT();
const k = (suffix: string) => t(`app.landlord.proposal_generator.${suffix}`);

const gen = useProposalGenerator();

provide(PROPOSAL_GENERATOR_KEY, gen);

setLayoutProps({
    breadcrumbs: [
        {
            title: k('navigation'),
            href: ProposalGeneratorController.index
                .url()
                .replace(/^\/\/[^/]+/, ''),
        },
    ],
});

// localStorage só existe no cliente — inicializar aqui evita divergência com o SSR.
onMounted(() => gen.init());
</script>

<template>
    <AppLayout>
        <Head :title="k('title')" />

        <div class="proposal-generator">
            <div class="app">
                <ProposalEditor />

                <main class="preview">
                    <div class="toolbar">
                        <button class="btn ghost" @click="gen.saveDraft()">
                            {{ k('actions.save') }}
                        </button>
                        <button
                            class="btn primary"
                            @click="gen.printProposal()"
                        >
                            {{ k('actions.print') }}
                        </button>
                    </div>
                    <ProposalDocument />
                </main>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/*
 * CSS portado de docs/preview.html, aninhado em .proposal-generator para não vazar
 * para o resto do app (a ferramenta tem paleta e tipografia próprias).
 */
.proposal-generator {
    --bg: #f3f4f3;
    --panel: #ffffff;
    --card: #fafafa;
    --cyan: #96ff26;
    --purple: #111111;
    --teal: #96ff26;
    --light: #111111;
    --muted: #747a76;
    --line: #d9ddda;
    --danger: #d92d20;
    --ok: #5abf18;
    --r: 12px;
    --font: Poppins, 'Segoe UI', Arial, sans-serif;
    /* Altura do cabeçalho do AppLayout (barra h-12 + margem). */
    --pg-offset: 4.5rem;

    font-family: var(--font);
    color: var(--light);
    background: var(--bg);
}

.proposal-generator * {
    box-sizing: border-box;
}

.proposal-generator button,
.proposal-generator input,
.proposal-generator select,
.proposal-generator textarea {
    font: inherit;
}

.proposal-generator .app {
    display: grid;
    grid-template-columns: 450px 1fr;
    align-items: start;
    min-height: calc(100vh - var(--pg-offset));
}

.proposal-generator .editor {
    position: sticky;
    top: 0;
    height: calc(100vh - var(--pg-offset));
    background: var(--panel);
    border-right: 1px solid var(--line);
    display: flex;
    flex-direction: column;
}

.proposal-generator .head {
    padding: 17px 20px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.proposal-generator .head small {
    font-size: 10px;
    color: var(--muted);
}

.proposal-generator .scroll {
    padding: 14px 18px 30px;
    overflow: auto;
    flex: 1;
}

.proposal-generator .section {
    border: 1px solid var(--line);
    border-radius: var(--r);
    background: var(--card);
    margin-bottom: 10px;
    overflow: hidden;
}

.proposal-generator .section h3 {
    font-size: 13px;
    margin: 0;
    padding: 12px 14px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.proposal-generator .section h3 span:first-child {
    display: flex;
    align-items: center;
    gap: 8px;
}

.proposal-generator .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--cyan);
}

.proposal-generator .dot.p {
    background: var(--purple);
}

.proposal-generator .dot.t {
    background: var(--teal);
}

.proposal-generator .body {
    display: none;
    border-top: 1px solid var(--line);
    padding: 5px 14px 15px;
}

.proposal-generator .section.open .body {
    display: block;
}

.proposal-generator .section.open .chev {
    transform: rotate(90deg);
}

.proposal-generator label {
    display: block;
    font-size: 10.5px;
    color: var(--muted);
    margin: 10px 0 5px;
}

.proposal-generator input,
.proposal-generator select,
.proposal-generator textarea {
    width: 100%;
    background: var(--bg);
    color: var(--light);
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 12px;
    outline: 0;
}

.proposal-generator input:focus,
.proposal-generator select:focus,
.proposal-generator textarea:focus {
    border-color: var(--cyan);
}

.proposal-generator textarea {
    resize: vertical;
    min-height: 60px;
}

.proposal-generator .g2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
}

.proposal-generator .g3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
}

.proposal-generator .inline-option {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 7px 0 0;
    font-size: 10.5px;
    color: var(--muted);
    cursor: pointer;
}

.proposal-generator .inline-option input {
    width: auto;
    margin: 0;
    accent-color: var(--cyan);
}

.proposal-generator .row-card {
    border: 1px solid var(--line);
    background: var(--bg);
    border-radius: 10px;
    padding: 10px;
    margin-top: 9px;
}

.proposal-generator .row-title {
    font-size: 11px;
    font-weight: 700;
}

.proposal-generator .row-top {
    display: flex;
    gap: 7px;
    align-items: center;
}

.proposal-generator .row-top input {
    flex: 1;
}

.proposal-generator .x {
    width: 30px;
    height: 30px;
    flex: 0 0 auto;
    background: transparent;
    color: var(--muted);
    border: 1px solid var(--line);
    border-radius: 8px;
    cursor: pointer;
}

.proposal-generator .x:hover {
    color: var(--danger);
    border-color: var(--danger);
}

.proposal-generator .add {
    width: 100%;
    margin-top: 9px;
    padding: 8px;
    background: transparent;
    color: #111111;
    border: 1px dashed var(--line);
    border-radius: 8px;
    cursor: pointer;
}

.proposal-generator .foot {
    padding: 13px 18px;
    border-top: 1px solid var(--line);
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.proposal-generator .btn {
    padding: 10px 12px;
    border-radius: 9px;
    border: 1px solid var(--line);
    cursor: pointer;
    font-weight: 600;
    font-size: 12px;
}

.proposal-generator .primary {
    background: #111111;
    color: #ffffff;
    border-color: #111111;
}

.proposal-generator .ghost {
    background: var(--card);
    color: var(--light);
}

.proposal-generator .full {
    grid-column: 1 / -1;
}

.proposal-generator .preview {
    background: #e8eae8;
    padding: 28px 24px 70px;
    overflow: auto;
}

.proposal-generator .toolbar {
    display: flex;
    justify-content: center;
    gap: 9px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.proposal-generator .doc {
    width: 794px;
    margin: auto;
    background: var(--bg);
    box-shadow: 0 20px 60px #0003;
    color: var(--light);
    min-height: 1123px;
}

.proposal-generator .doc-inner {
    padding: 48px 56px 42px;
}

.proposal-generator .doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.proposal-generator .head img,
.proposal-generator .doc-header img {
    display: block;
    width: auto;
    object-fit: contain;
    object-position: left center;
    background: transparent;
    border: 0;
    box-shadow: none;
}

.proposal-generator .head img {
    height: 34px;
    max-width: 245px;
}

.proposal-generator .doc-header img {
    height: 48px;
    max-width: 270px;
}

.proposal-generator .title {
    text-align: right;
}

.proposal-generator .title h1 {
    margin: 0;
    color: #111111;
    font-size: 33px;
    line-height: 1.05;
    font-weight: 800;
}

.proposal-generator .title div {
    color: var(--muted);
    font-size: 11px;
    margin-top: 5px;
}

.proposal-generator .proposal-code-wrap {
    margin-top: 10px;
    display: flex;
    justify-content: flex-end;
}

.proposal-generator .proposal-code-badge {
    display: inline-block;
    background: var(--cyan);
    color: #111111;
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.8px;
    line-height: 1;
    text-transform: uppercase;
}

.proposal-generator .proposal-date {
    margin-top: 7px;
    color: var(--muted);
    font-size: 11px;
}

.proposal-generator .client {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 210px;
    gap: 28px;
    align-items: stretch;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 18px 20px 20px;
    margin-top: 30px;
}

.proposal-generator .client-main {
    min-width: 0;
}

.proposal-generator .eyebrow {
    font-size: 9.5px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #5f9900;
    font-weight: 700;
}

.proposal-generator .client-details {
    margin-top: 16px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 30px;
}

.proposal-generator .client-line {
    display: grid;
    gap: 5px;
    align-content: start;
    min-width: 0;
}

.proposal-generator .client-line .label {
    color: var(--light);
    font-weight: 600;
    font-size: 11px;
    line-height: 1.2;
}

.proposal-generator .client-line .value {
    color: var(--muted);
    font-size: 11.5px;
    line-height: 1.45;
    overflow-wrap: anywhere;
    word-break: normal;
}

.proposal-generator .muted {
    color: var(--muted);
}

.proposal-generator .small {
    font-size: 11px;
    line-height: 1.55;
}

.proposal-generator .right {
    min-width: 0;
    position: relative;
    padding-left: 24px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-end;
    text-align: right;
}

.proposal-generator .right:before {
    content: '';
    position: absolute;
    left: 0;
    top: 2px;
    bottom: 2px;
    width: 1px;
    background: var(--line);
}

.proposal-generator .plan-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

.proposal-generator .chip {
    display: inline-block;
    background: #111111;
    color: #ffffff;
    border-radius: 20px;
    padding: 6px 15px;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.proposal-generator .intro {
    margin: 25px 0 0;
    font-size: 12px;
    line-height: 1.65;
    color: #3f4741;
}

.proposal-generator .sec {
    margin-top: 27px;
}

.proposal-generator .sec h3 {
    font-size: 10.5px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #111111;
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 0 0 16px;
}

.proposal-generator .sec h3:after {
    content: '';
    height: 1px;
    background: var(--line);
    flex: 1;
}

.proposal-generator .sec h3.p {
    color: var(--purple);
}

.proposal-generator .mods {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 34px;
}

.proposal-generator .mod {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    font-size: 13px;
    line-height: 1.35;
}

.proposal-generator .mod > div:last-child {
    font-weight: 600;
    color: var(--light);
}

.proposal-generator .check {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: var(--cyan);
    color: #111111;
    display: grid;
    place-items: center;
    flex: none;
    font-size: 10px;
    font-weight: 800;
    margin-top: 1px;
}

.proposal-generator .mod small {
    display: block;
    color: var(--muted);
    font-size: 10.5px;
    font-weight: 400;
    line-height: 1.4;
    margin-top: 3px;
}

.proposal-generator .table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 11.5px;
}

.proposal-generator .table thead th {
    background: #111111;
    color: #ffffff;
    text-align: left;
    padding: 10px 14px;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 3px;
}

.proposal-generator .table thead th:first-child {
    border-radius: 12px 0 0 12px;
}

.proposal-generator .table thead th:last-child {
    border-radius: 0 12px 12px 0;
    text-align: right;
}

.proposal-generator .table th.qty-col,
.proposal-generator .table td.qty-col {
    width: 118px;
    text-align: center;
}

.proposal-generator .table th.unit-col,
.proposal-generator .table td.unit-col {
    width: 120px;
    text-align: right;
}

.proposal-generator .table th.type-col,
.proposal-generator .table td.type-col {
    width: 110px;
    text-align: center;
}

.proposal-generator .table th.total-col,
.proposal-generator .table td.total-col {
    width: 140px;
    text-align: right;
}

.proposal-generator .table tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
    vertical-align: middle;
}

.proposal-generator .table tbody tr:last-child td {
    border-bottom: 0;
}

.proposal-generator .desc-cell b {
    display: block;
    font-size: 13px;
    line-height: 1.35;
    color: var(--light);
}

.proposal-generator .desc-cell small {
    display: block;
    color: var(--muted);
    margin-top: 2px;
    font-size: 10.5px;
    line-height: 1.35;
}

.proposal-generator .qty-cell,
.proposal-generator .unit-cell,
.proposal-generator .total-cell {
    font-size: 12px;
    color: var(--light);
    white-space: nowrap;
}

.proposal-generator .total-cell small {
    display: block;
    color: var(--muted);
    font-size: 10px;
    line-height: 1.2;
    margin-bottom: 3px;
}

.proposal-generator .total-cell strong {
    font-size: 14px;
    color: var(--light);
    font-weight: 700;
    white-space: nowrap;
}

.proposal-generator .badge {
    display: inline-block;
    border: 1px solid var(--line);
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1.7px;
    line-height: 1;
}

.proposal-generator .badge.mensal {
    color: #111111;
    border-color: #111111;
}

.proposal-generator .badge.unico {
    color: #4f8f00;
    border-color: var(--cyan);
}

.proposal-generator .summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 18px;
}

.proposal-generator .sum-card {
    position: relative;
    border: 1px solid var(--line);
    background: var(--card);
    border-radius: 14px;
    padding: 14px 16px;
    overflow: hidden;
}

.proposal-generator .sum-card strong {
    display: block;
    font-size: 18px;
    margin-top: 5px;
}

.proposal-generator .sum-card small {
    display: block;
    font-size: 9.5px;
    color: var(--muted);
    margin-top: 6px;
    line-height: 1.45;
}

.proposal-generator .sum-card .status {
    display: block;
    margin-top: 10px;
    font-size: 9px;
    letter-spacing: 1.3px;
    text-transform: uppercase;
    color: var(--cyan);
    font-weight: 700;
    line-height: 1;
    max-width: max-content;
}

.proposal-generator .sum-card.setup-card {
    background: #111111;
    color: #ffffff;
    border-color: #111111;
    box-shadow: 0 12px 26px rgba(0, 0, 0, 0.12);
}

.proposal-generator .sum-card.setup-card .eyebrow,
.proposal-generator .sum-card.setup-card .status {
    color: var(--cyan);
}

.proposal-generator .sum-card.setup-card small {
    color: #d5d8d6;
}

.proposal-generator .sum-card.monthly-card {
    background: linear-gradient(135deg, var(--cyan) 0%, #b8ff63 100%);
    color: #111111;
    border-color: #a9ed4c;
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.12);
}

.proposal-generator .sum-card.monthly-card:before {
    content: '';
    position: absolute;
    right: -18px;
    top: -18px;
    width: 92px;
    height: 92px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.22);
}

.proposal-generator .sum-card.monthly-card .eyebrow,
.proposal-generator .sum-card.monthly-card small {
    color: #111111;
}

.proposal-generator .sum-card.monthly-card strong {
    font-size: 20px;
}

.proposal-generator .sum-card.monthly-card .status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 10px;
    padding: 5px 10px;
    border-radius: 999px;
    background: #111111;
    color: var(--cyan);
    font-size: 8.5px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    font-weight: 800;
    line-height: 1;
    max-width: max-content;
}

.proposal-generator .sum-card.obsolete {
    opacity: 0.82;
}

.proposal-generator .sum-card.obsolete strong {
    text-decoration: line-through;
    text-decoration-thickness: 2px;
}

.proposal-generator .sum-card.obsolete:after {
    content: '';
    position: absolute;
    left: -8%;
    right: -8%;
    top: 50%;
    height: 2px;
    transform: rotate(-7deg);
}

.proposal-generator .sum-card.setup-card.obsolete:after {
    background: rgba(255, 255, 255, 0.45);
}

.proposal-generator .sum-card.monthly-card.obsolete:after {
    background: rgba(17, 17, 17, 0.28);
}

.proposal-generator .sum-card.discounted {
    box-shadow:
        0 0 0 1px rgba(150, 255, 38, 0.18) inset,
        0 14px 30px rgba(0, 0, 0, 0.1);
}

.proposal-generator .discount-note {
    margin-top: 10px;
    font-size: 10.5px;
    line-height: 1.55;
    color: var(--muted);
}

.proposal-generator .discount-note b {
    color: var(--light);
    font-weight: 600;
}

.proposal-generator .item-adjust {
    display: block;
    margin-top: 4px;
    font-size: 10px;
    color: #4f8f00;
    font-weight: 700;
}

.proposal-generator .original-value {
    text-decoration: line-through;
    color: var(--muted);
    font-size: 10px;
    display: block;
    margin-bottom: 3px;
}

.proposal-generator .conditions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 22px;
}

.proposal-generator .condition {
    font-size: 11px;
    color: var(--muted);
    line-height: 1.5;
    display: flex;
    gap: 8px;
}

.proposal-generator .condition:before {
    content: '—';
    color: var(--purple);
    font-weight: 700;
}

.proposal-generator .accept {
    margin-top: 26px;
    border: 1px solid var(--line);
    border-radius: 13px;
    padding: 17px;
}

.proposal-generator .accept p {
    font-size: 10.5px;
    color: var(--muted);
    line-height: 1.55;
    margin: 0 0 23px;
}

.proposal-generator .signs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.proposal-generator .sign {
    border-top: 1px solid var(--muted);
    padding-top: 6px;
    font-size: 10px;
    color: var(--muted);
}

.proposal-generator .doc-footer {
    margin-top: 28px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 24px;
}

.proposal-generator .doc-footer h4 {
    margin: 0;
    color: #111111;
    font-size: 14px;
}

.proposal-generator .contact {
    text-align: right;
    font-size: 10px;
    color: var(--muted);
    line-height: 1.7;
}

/* Alertas de validação: o original usava cor de tema escuro e ficava ilegível no claro. */
.proposal-generator .warning {
    background: #fdecea;
    border: 1px solid var(--danger);
    color: var(--danger);
    padding: 10px;
    border-radius: 9px;
    font-size: 11px;
    margin-bottom: 10px;
}

.proposal-generator .draft {
    padding: 8px 9px;
    border: 1px solid var(--line);
    border-radius: 8px;
    margin-top: 7px;
    font-size: 11px;
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.proposal-generator .draft div {
    cursor: pointer;
    min-width: 0;
}

.proposal-generator .draft b {
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.proposal-generator .draft small {
    color: var(--muted);
}

.proposal-generator .hide {
    display: none !important;
}

/*
 * `screen and` é obrigatório: na impressão a largura da mídia é a da folha (A4 ≈ 688px
 * úteis), então sem isso estes breakpoints disparavam no PDF e empilhavam o bloco do
 * cliente em uma coluna só.
 */
@media screen and (max-width: 1180px) {
    .proposal-generator .app {
        display: block;
    }

    .proposal-generator .editor {
        position: relative;
        width: 100%;
        height: auto;
    }

    .proposal-generator .scroll {
        max-height: 65vh;
    }

    .proposal-generator .preview {
        padding: 15px 8px 50px;
    }

    .proposal-generator .doc {
        transform: scale(0.7);
        transform-origin: top center;
        margin-bottom: -320px;
    }
}

@media screen and (max-width: 760px) {
    .proposal-generator .client {
        grid-template-columns: 1fr;
    }

    .proposal-generator .right {
        border-left: 0;
        padding-left: 0;
        padding-top: 12px;
        border-top: 1px solid var(--line);
        align-items: flex-start;
        text-align: left;
    }

    .proposal-generator .client-details {
        grid-template-columns: 1fr;
    }
}

/*
 * Impressão / PDF.
 *
 * A página vive dentro do AppLayout (sidebar + cabeçalho), então é preciso esconder o
 * chrome do app sem tirar o documento do fluxo normal — elemento posicionado/absoluto
 * NÃO quebra em várias páginas, era o que fazia o PDF sair cortado numa página só.
 *
 * A estratégia: `:has()` acha a cadeia de ancestrais que contém o documento, neutraliza
 * cada um (bloco simples, sem altura/overflow/transform) e esconde todo irmão que não
 * contém o documento. Assim o .doc fica em fluxo direto e o navegador pagina sozinho.
 */
@media print {
    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
        overflow: visible !important;
        background: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Ancestrais do documento viram contêineres transparentes para o layout. */
    body *:has(.proposal-doc) {
        display: block !important;
        position: static !important;
        overflow: visible !important;
        width: auto !important;
        max-width: none !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        background: #ffffff !important;
        box-shadow: none !important;
        transform: none !important;
    }

    /* Some com sidebar, cabeçalho, editor, toolbar, toasts — tudo que não é o documento. */
    body > *:not(:has(.proposal-doc)) {
        display: none !important;
    }

    body *:has(.proposal-doc) > *:not(:has(.proposal-doc)):not(.proposal-doc) {
        display: none !important;
    }

    /* Fallback para navegador sem :has() — pelo menos o editor e a toolbar somem. */
    .proposal-generator .editor,
    .proposal-generator .toolbar {
        display: none !important;
    }

    /* O documento em fluxo normal, ocupando a folha inteira (margem vem do padding). */
    .proposal-doc {
        display: block !important;
        position: static !important;
        width: auto !important;
        max-width: none !important;
        min-height: 0 !important;
        margin: 0 !important;
        transform: none !important;
        box-shadow: none !important;
        filter: none !important;
        background: #ffffff !important;
        color: #111111 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /*
     * Margem da folha via padding, e não via `@page { margin }`, porque o Chrome desenha
     * data / URL / "1 de 3" dentro da margem do @page — com margem 0 não sobra espaço e
     * ele simplesmente não imprime esse cabeçalho. `box-decoration-break: clone` repete o
     * padding em cada fragmento, então a margem vale também da 2ª página em diante.
     */
    .proposal-doc .doc-inner {
        min-height: 0 !important;
        padding: 14mm 15mm !important;
        background: #ffffff !important;
        -webkit-box-decoration-break: clone !important;
        box-decoration-break: clone !important;
    }

    /* Preserva os fundos escuros/lima ao imprimir. */
    .proposal-doc .client,
    .proposal-doc .sum-card,
    .proposal-doc .accept,
    .proposal-doc .table thead th,
    .proposal-doc .chip,
    .proposal-doc .check,
    .proposal-doc .proposal-code-badge {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /*
     * Blocos que não podem ser partidos no meio por uma quebra de página.
     *
     * `.summary` fica de fora de propósito: proteger o grid inteiro fazia os dois cards
     * pularem juntos para a página seguinte, deixando um vão enorme. Protegendo só cada
     * `.sum-card`, eles podem se separar sem nenhum card ser cortado ao meio.
     */
    .proposal-doc .client,
    .proposal-doc .sum-card,
    .proposal-doc .accept,
    .proposal-doc .doc-footer,
    .proposal-doc .condition,
    .proposal-doc .mod,
    .proposal-doc .table tr {
        break-inside: avoid;
    }

    .proposal-doc .sec,
    .proposal-doc .summary {
        break-inside: auto;
    }

    /* Título de seção nunca fica órfão no pé da página. */
    .proposal-doc .sec h3 {
        break-after: avoid-page;
        page-break-after: avoid;
    }

    /* Cabeçalho da tabela se repete quando ela atravessa páginas. */
    .proposal-doc .table {
        table-layout: fixed !important;
    }

    .proposal-doc .table thead {
        display: table-header-group;
    }

    .proposal-doc .table thead th {
        font-size: 8px !important;
        letter-spacing: 2px !important;
        line-height: 1.15 !important;
        padding: 9px 10px !important;
        white-space: normal !important;
        vertical-align: middle !important;
    }

    .proposal-doc .table th.qty-col,
    .proposal-doc .table td.qty-col {
        width: 108px !important;
    }

    .proposal-doc .table th.unit-col,
    .proposal-doc .table td.unit-col {
        width: 116px !important;
    }

    .proposal-doc .table th.type-col,
    .proposal-doc .table td.type-col {
        width: 98px !important;
    }

    .proposal-doc .table th.total-col,
    .proposal-doc .table td.total-col {
        width: 126px !important;
    }

    /*
     * A quebra de página forçada antes de "Condições comerciais" vinha do HTML original,
     * onde a impressão nunca paginou de verdade e a regra jamais surtiu efeito. Com a
     * paginação funcionando ela deixava uma página quase vazia — agora o conteúdo flui.
     */
    .proposal-doc .cond-page-break {
        break-before: auto !important;
        page-break-before: auto !important;
    }

    .proposal-doc .summary {
        background: transparent !important;
    }

    .proposal-doc .sum-card.monthly-card:before,
    .proposal-doc .sum-card.obsolete:after {
        display: none !important;
    }

    /* margin: 0 é o que suprime data/URL/numeração do navegador. Ver .doc-inner acima. */
    @page {
        size: A4 portrait;
        margin: 0;
    }
}
</style>
