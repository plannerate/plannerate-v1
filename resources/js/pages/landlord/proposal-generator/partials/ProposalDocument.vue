<script setup lang="ts">
/** Coluna direita: o documento A4 da proposta (o que vai para o PDF). */
import { computed } from 'vue';
import { useInjectedProposalGenerator } from '@/composables/landlord/useProposalGenerator';
import { useT } from '@/composables/useT';

const gen = useInjectedProposalGenerator();
const doc = computed(() => gen.documentModel.value);

const { t } = useT();
const k = (suffix: string) => t(`app.landlord.proposal_generator.${suffix}`);
</script>

<template>
    <div class="doc proposal-doc">
        <div class="doc-inner">
            <header class="doc-header">
                <!-- marca-claro = versão para fundo claro (texto preto); a "dark" é
                     branca e sumiria no papel. -->
                <img src="/img/marca-claro.png" :alt="k('document.company')" />
                <div class="title">
                    <h1>{{ k('document.title') }}</h1>
                    <div class="proposal-code-wrap">
                        <span class="proposal-code-badge">{{ doc.num }}</span>
                    </div>
                    <div class="proposal-date">
                        {{ doc.city }}, {{ doc.dateText }}
                    </div>
                </div>
            </header>

            <section class="client">
                <div class="client-main">
                    <div class="eyebrow">{{ k('document.prepared_for') }}</div>
                    <div class="client-details">
                        <template v-if="doc.clientLines.length">
                            <div
                                v-for="line in doc.clientLines"
                                :key="line.label"
                                class="client-line"
                            >
                                <span class="label">{{ line.label }}:</span>
                                <span class="value">{{ line.value }}</span>
                            </div>
                        </template>
                        <div v-else class="client-line">
                            <span class="label"
                                >{{ k('document.client_label') }}:</span
                            >
                            <span class="value">{{
                                k('document.client_fallback')
                            }}</span>
                        </div>
                    </div>
                </div>
                <div class="right">
                    <div class="eyebrow">{{ k('document.plan') }}</div>
                    <div class="plan-wrap">
                        <span class="chip">{{ doc.planLabel }}</span>
                    </div>
                </div>
            </section>

            <p v-if="doc.intro" class="intro">{{ doc.intro }}</p>

            <section v-if="doc.modules.length" class="sec">
                <h3>{{ k('document.modules') }}</h3>
                <div class="mods">
                    <div v-for="mod in doc.modules" :key="mod.name" class="mod">
                        <div class="check">✓</div>
                        <div>
                            {{ mod.name }}
                            <small v-if="mod.desc">{{ mod.desc }}</small>
                        </div>
                    </div>
                </div>
            </section>

            <section class="sec">
                <h3 class="p">{{ k('document.investment') }}</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ k('document.col_description') }}</th>
                            <th class="qty-col">
                                {{ k('document.col_quantity') }}
                            </th>
                            <th class="unit-col">
                                {{ k('document.col_unit') }}
                            </th>
                            <th class="type-col">
                                {{ k('document.col_type') }}
                            </th>
                            <th class="total-col">
                                {{ k('document.col_value') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in doc.rows" :key="row.key">
                            <td class="desc-cell">
                                <b>{{ row.name }}</b>
                                <small v-if="row.note">{{ row.note }}</small>
                                <span
                                    v-if="row.adjustLabel"
                                    class="item-adjust"
                                    >{{ row.adjustLabel }}</span
                                >
                            </td>
                            <td class="qty-col qty-cell">
                                {{ row.qtyDisplay }}
                            </td>
                            <td class="unit-col unit-cell">
                                {{ row.unitText }}
                            </td>
                            <td class="type-col">
                                <span class="badge" :class="row.typeClass">{{
                                    row.typeLabel
                                }}</span>
                            </td>
                            <td class="total-col total-cell">
                                <span
                                    v-if="row.originalText"
                                    class="original-value"
                                    >{{ row.originalText }}</span
                                >
                                <small v-if="row.parcelText">{{
                                    row.parcelText
                                }}</small>
                                <strong>{{ row.totalText }}</strong>
                            </td>
                        </tr>
                        <tr v-if="!doc.rows.length">
                            <td colspan="5" class="muted">
                                {{ k('document.empty_items') }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="summary">
                    <div
                        class="sum-card setup-card"
                        :class="{ obsolete: doc.setupChanged }"
                    >
                        <div class="eyebrow">
                            {{ k('document.card_setup') }}
                        </div>
                        <strong>{{ doc.setupOriginal }}</strong>
                        <small>{{ doc.installmentsNote }}</small>
                        <span v-if="doc.setupChanged" class="status">{{
                            k('document.status_original')
                        }}</span>
                    </div>

                    <div
                        class="sum-card monthly-card"
                        :class="{ obsolete: doc.monthlyChanged }"
                    >
                        <div class="eyebrow">
                            {{ k('document.card_monthly') }}
                        </div>
                        <strong>{{ doc.monthlyOriginal }}</strong>
                        <small>{{ doc.planLabel }}</small>
                        <span v-if="doc.monthlyChanged" class="status">{{
                            k('document.status_original')
                        }}</span>
                    </div>

                    <template v-if="doc.hasAnyDiscount">
                        <div
                            v-if="doc.setupChanged"
                            class="sum-card setup-card discounted"
                        >
                            <div class="eyebrow">
                                {{ k('document.card_setup_discounted') }}
                            </div>
                            <strong>{{ doc.setupFinal }}</strong>
                            <small>{{ doc.installmentsNote }}</small>
                            <span class="status">{{
                                k('document.status_new')
                            }}</span>
                        </div>
                        <div
                            v-if="doc.monthlyChanged"
                            class="sum-card monthly-card discounted"
                        >
                            <div class="eyebrow">
                                {{ k('document.card_monthly_discounted') }}
                            </div>
                            <strong>{{ doc.monthlyFinal }}</strong>
                            <small>{{ doc.planLabel }}</small>
                            <span class="status">{{
                                k('document.status_new')
                            }}</span>
                        </div>
                    </template>
                </div>

                <div v-if="doc.discountConditionMonths" class="discount-note">
                    <b>{{ k('document.discount_condition_label') }}</b>
                    {{
                        t(
                            'app.landlord.proposal_generator.document.discount_condition',
                            { months: doc.discountConditionMonths },
                        )
                    }}
                </div>
            </section>

            <section v-if="doc.conditions.length" class="sec cond-page-break">
                <h3>{{ k('document.conditions') }}</h3>
                <div class="conditions">
                    <div
                        v-for="condition in doc.conditions"
                        :key="condition"
                        class="condition"
                    >
                        {{ condition }}
                    </div>
                </div>
            </section>

            <section class="accept">
                <div class="eyebrow">{{ k('document.accept') }}</div>
                <p>{{ k('document.accept_text') }}</p>
                <div class="signs">
                    <div class="sign">
                        <b>{{ doc.signClient }}</b>
                        <template v-if="doc.signContact"
                            ><br />{{ doc.signContact }}</template
                        >
                    </div>
                    <div class="sign">
                        {{ k('document.company') }}<br />{{ doc.sellerLine }}
                    </div>
                </div>
            </section>

            <footer class="doc-footer">
                <div>
                    <h4>{{ k('document.footer_thanks') }}</h4>
                    <div class="small muted">
                        {{
                            t(
                                'app.landlord.proposal_generator.document.footer_validity',
                                { days: String(doc.validityDays) },
                            )
                        }}
                    </div>
                </div>
                <div class="contact">
                    <template
                        v-for="(entry, index) in doc.contacts"
                        :key="entry"
                    >
                        <br v-if="index" />{{ entry }}
                    </template>
                </div>
            </footer>
        </div>
    </div>
</template>
