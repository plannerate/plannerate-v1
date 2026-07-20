<script setup lang="ts">
/** Coluna esquerda: todas as seções de edição da proposta. */
import { ref } from 'vue';
import type {
    ItemCategory,
    ProposalItem,
    UserDiscountType,
} from '@/composables/landlord/proposalCalculations';
import { useInjectedProposalGenerator } from '@/composables/landlord/useProposalGenerator';
import { useT } from '@/composables/useT';
import CollapsibleSection from './CollapsibleSection.vue';

const gen = useInjectedProposalGenerator();

const { t } = useT();
const k = (suffix: string) => t(`app.landlord.proposal_generator.${suffix}`);

const importInput = ref<HTMLInputElement | null>(null);
const draftsSection = ref<InstanceType<typeof CollapsibleSection> | null>(null);

/** Salvou: revela a lista de propostas salvas para o resultado ficar visível. */
function onSaveDraft(): void {
    if (gen.saveDraft()) {
        draftsSection.value?.openSection();
    }
}

const categories: ItemCategory[] = [
    'setup',
    'admin',
    'assistant',
    'store',
    'other',
];
const discountTypes = ['none', 'fixed', 'percent'] as const;

const selectValue = (event: Event): string =>
    (event.target as HTMLSelectElement).value;

const draftDate = (savedAt?: string): string =>
    savedAt ? new Date(savedAt).toLocaleDateString('pt-BR') : '';

function onCategory(item: ProposalItem, event: Event): void {
    gen.onCategoryChange(item, selectValue(event) as ItemCategory);
}

function onBillingType(item: ProposalItem, event: Event): void {
    gen.onBillingTypeChange(item, selectValue(event) as ProposalItem['type']);
}

function onUserDiscountType(item: ProposalItem, event: Event): void {
    gen.onUserDiscountTypeChange(item, selectValue(event) as UserDiscountType);
}
</script>

<template>
    <aside class="editor">
        <div class="head">
            <img src="/img/marca-claro.png" :alt="k('document.company')" />
            <small>{{ k('brand') }}</small>
        </div>

        <div class="scroll">
            <div v-if="gen.warnings.value.length" class="warning">
                <div v-for="message in gen.warnings.value" :key="message">
                    {{ message }}
                </div>
            </div>

            <!-- Proposta -->
            <CollapsibleSection :title="k('sections.proposal')" open>
                <div class="g2">
                    <div>
                        <label>{{ k('fields.number') }}</label>
                        <input v-model="gen.form.num" />
                    </div>
                    <div>
                        <label>{{ k('fields.date') }}</label>
                        <input v-model="gen.form.date" type="date" />
                    </div>
                </div>
                <div class="g2">
                    <div>
                        <label>{{ k('fields.city') }}</label>
                        <input v-model="gen.form.city" />
                    </div>
                    <div>
                        <label>{{ k('fields.validity') }}</label>
                        <input
                            v-model="gen.form.validity"
                            type="number"
                            min="1"
                        />
                    </div>
                </div>
                <div>
                    <label>{{ k('fields.plan') }}</label>
                    <input v-model="gen.form.plan" />
                </div>
                <label>{{ k('fields.intro') }}</label>
                <textarea v-model="gen.form.intro" />
            </CollapsibleSection>

            <!-- Cliente -->
            <CollapsibleSection :title="k('sections.client')" dot="p" open>
                <label>{{ k('fields.client') }}</label>
                <input v-model="gen.form.client" />
                <div class="g2">
                    <div>
                        <label>{{ k('fields.cnpj') }}</label>
                        <input v-model="gen.form.cnpj" />
                    </div>
                    <div>
                        <label>{{ k('fields.client_city') }}</label>
                        <input v-model="gen.form.clientCity" />
                    </div>
                </div>
                <label>{{ k('fields.contact') }}</label>
                <input v-model="gen.form.contact" />
            </CollapsibleSection>

            <!-- Composição comercial -->
            <CollapsibleSection :title="k('sections.commercial')" dot="t" open>
                <p class="small muted" style="margin: 4px 0 10px">
                    {{ k('commercial.hint') }}
                </p>

                <div
                    v-for="(item, index) in gen.items.value"
                    :key="item.id"
                    class="row-card"
                >
                    <div class="row-top">
                        <input
                            v-model="item.name"
                            :placeholder="k('placeholders.item_description')"
                        />
                        <button
                            class="x"
                            :title="k('actions.remove')"
                            @click="gen.removeItem(index)"
                        >
                            ✕
                        </button>
                    </div>

                    <label>{{ k('fields.note') }}</label>
                    <input
                        v-model="item.note"
                        :placeholder="k('placeholders.item_note')"
                    />

                    <div class="g2">
                        <div>
                            <label>{{
                                k('fields.commercial_component')
                            }}</label>
                            <select
                                :value="item.category"
                                @change="onCategory(item, $event)"
                            >
                                <option
                                    v-for="category in categories"
                                    :key="category"
                                    :value="category"
                                >
                                    {{ k(`options.category.${category}`) }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label>{{ k('fields.billing_type') }}</label>
                            <select
                                :value="item.type"
                                @change="onBillingType(item, $event)"
                            >
                                <option value="mensal">
                                    {{ k('options.billing.mensal') }}
                                </option>
                                <option value="unico">
                                    {{ k('options.billing.unico') }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="g3">
                        <div>
                            <label>{{ k('fields.quantity') }}</label>
                            <input
                                v-model.number="item.qty"
                                type="number"
                                min="0"
                                step="1"
                                :disabled="
                                    item.category === 'assistant' &&
                                    item.unlimited
                                "
                            />
                            <label
                                v-if="item.category === 'assistant'"
                                class="inline-option"
                            >
                                <input
                                    type="checkbox"
                                    :checked="item.unlimited"
                                    @change="
                                        gen.onUnlimitedToggle(
                                            item,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                {{ k('fields.unlimited') }}
                            </label>
                        </div>
                        <div>
                            <label>{{ k('fields.billing_base') }}</label>
                            <input
                                v-model="item.measure"
                                :placeholder="k('placeholders.billing_base')"
                            />
                        </div>
                        <div>
                            <label>{{ k('fields.unit_value') }}</label>
                            <input
                                v-model.number="item.unit"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                        </div>
                    </div>

                    <div class="g2">
                        <div>
                            <label>{{ k('fields.installments') }}</label>
                            <input
                                v-model.number="item.installments"
                                type="number"
                                min="1"
                                step="1"
                                :disabled="item.type !== 'unico'"
                            />
                        </div>
                        <div>
                            <label>{{ k('fields.commercial_subtotal') }}</label>
                            <input
                                class="subtotal-field"
                                :value="gen.subtotalText(item)"
                                disabled
                            />
                            <span
                                v-if="gen.itemAdjustLabel(item)"
                                class="item-adjust"
                            >
                                {{ gen.itemAdjustLabel(item) }}
                            </span>
                        </div>
                    </div>
                </div>

                <button class="add" @click="gen.addItem()">
                    {{ k('commercial.add_free_item') }}
                </button>
                <div class="g2" style="margin-top: 8px">
                    <button
                        class="btn ghost"
                        type="button"
                        @click="gen.addStandardItem('setup')"
                    >
                        {{ k('commercial.add_setup') }}
                    </button>
                    <button
                        class="btn ghost"
                        type="button"
                        @click="gen.addStandardItem('admin')"
                    >
                        {{ k('commercial.add_admin') }}
                    </button>
                    <button
                        class="btn ghost"
                        type="button"
                        @click="gen.addStandardItem('assistant')"
                    >
                        {{ k('commercial.add_assistant') }}
                    </button>
                    <button
                        class="btn ghost"
                        type="button"
                        @click="gen.addStandardItem('store')"
                    >
                        {{ k('commercial.add_store') }}
                    </button>
                </div>
            </CollapsibleSection>

            <!-- Módulos incluídos -->
            <CollapsibleSection :title="k('sections.modules')">
                <div
                    v-for="(mod, index) in gen.mods.value"
                    :key="index"
                    class="row-card"
                >
                    <div class="row-top">
                        <input
                            v-model="mod.name"
                            :placeholder="k('placeholders.module_name')"
                        />
                        <button
                            class="x"
                            :title="k('actions.remove')"
                            @click="gen.removeMod(index)"
                        >
                            ✕
                        </button>
                    </div>
                    <label>{{ k('fields.module_description') }}</label>
                    <input v-model="mod.desc" />
                </div>
                <button class="add" @click="gen.addMod()">
                    {{ k('modules.add') }}
                </button>
            </CollapsibleSection>

            <!-- Desconto e pagamento -->
            <CollapsibleSection :title="k('sections.discount')" dot="p">
                <div class="g2">
                    <div>
                        <label>{{ k('fields.setup_discount') }}</label>
                        <select v-model="gen.form.setupDiscountType">
                            <option
                                v-for="type in discountTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ k(`options.discount.${type}`) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label>{{ k('fields.setup_discount_value') }}</label>
                        <input
                            v-model.number="gen.form.setupDiscountValue"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                    </div>
                </div>
                <div class="g2">
                    <div>
                        <label>{{ k('fields.monthly_discount') }}</label>
                        <select v-model="gen.form.monthlyDiscountType">
                            <option
                                v-for="type in discountTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ k(`options.discount.${type}`) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label>{{ k('fields.monthly_discount_value') }}</label>
                        <input
                            v-model.number="gen.form.monthlyDiscountValue"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                    </div>
                </div>
                <div class="g2">
                    <div>
                        <label>{{ k('fields.store_discount') }}</label>
                        <select v-model="gen.form.storeDiscountType">
                            <option
                                v-for="type in discountTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ k(`options.discount.${type}`) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label>{{ k('fields.store_discount_value') }}</label>
                        <input
                            v-model.number="gen.form.storeDiscountValue"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                    </div>
                </div>

                <!-- Descontos e bonificações por usuário -->
                <p
                    v-if="!gen.userDiscountRows.value.length"
                    class="small muted"
                    style="margin: 10px 0"
                >
                    {{ k('commercial.user_discounts_empty') }}
                </p>
                <div v-else class="row-card" style="margin-top: 12px">
                    <div class="row-title" style="margin-bottom: 5px">
                        {{ k('commercial.user_discounts_title') }}
                    </div>
                    <div
                        v-for="row in gen.userDiscountRows.value"
                        :key="row.item.id"
                        style="
                            padding: 8px 0;
                            border-top: 1px solid var(--line);
                        "
                    >
                        <b style="font-size: 11px">{{ row.label }}</b>
                        <div class="g2">
                            <div>
                                <label>{{ k('fields.condition') }}</label>
                                <select
                                    :value="row.item.userDiscountType"
                                    @change="
                                        onUserDiscountType(row.item, $event)
                                    "
                                >
                                    <option value="none">
                                        {{ k('options.user_discount.none') }}
                                    </option>
                                    <option value="percent">
                                        {{ k('options.user_discount.percent') }}
                                    </option>
                                    <option value="fixed">
                                        {{ k('options.user_discount.fixed') }}
                                    </option>
                                    <option v-if="row.allowBonus" value="bonus">
                                        {{ k('options.user_discount.bonus') }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label>{{ row.fieldLabel }}</label>
                                <input
                                    v-model.number="row.item.userDiscountValue"
                                    type="number"
                                    min="0"
                                    :step="
                                        row.item.userDiscountType === 'bonus'
                                            ? '1'
                                            : '0.01'
                                    "
                                    :disabled="
                                        row.item.userDiscountType === 'none'
                                    "
                                />
                            </div>
                        </div>
                        <div class="small muted" style="margin-top: 6px">
                            {{ row.summary }}
                        </div>
                    </div>
                </div>

                <label>{{ k('fields.discount_reason') }}</label>
                <input v-model="gen.form.discountReason" />

                <div class="g2">
                    <div>
                        <label>{{ k('fields.discount_term') }}</label>
                        <select v-model="gen.form.discountTerm">
                            <option value="">
                                {{ k('options.term.none') }}
                            </option>
                            <option value="12">
                                {{ k('options.term.months_12') }}
                            </option>
                            <option value="24">
                                {{ k('options.term.months_24') }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label>{{ k('fields.payment') }}</label>
                        <input v-model="gen.form.payment" />
                    </div>
                </div>
                <div class="g2">
                    <div>
                        <label>{{ k('fields.implementation') }}</label>
                        <input v-model="gen.form.implementation" />
                    </div>
                    <div>
                        <label>{{ k('fields.due') }}</label>
                        <input v-model="gen.form.due" />
                    </div>
                </div>
                <div class="g2">
                    <div>
                        <label>{{ k('fields.adjustment') }}</label>
                        <input v-model="gen.form.adjustment" />
                    </div>
                    <div />
                </div>
            </CollapsibleSection>

            <!-- Condições comerciais -->
            <CollapsibleSection :title="k('sections.conditions')">
                <div
                    v-for="(_, index) in gen.conds.value"
                    :key="index"
                    class="row-top"
                    style="margin-top: 8px"
                >
                    <input v-model="gen.conds.value[index]" />
                    <button
                        class="x"
                        :title="k('actions.remove')"
                        @click="gen.removeCond(index)"
                    >
                        ✕
                    </button>
                </div>
                <button class="add" @click="gen.addCond()">
                    {{ k('conditions.add') }}
                </button>
            </CollapsibleSection>

            <!-- Contato Plannerate -->
            <CollapsibleSection :title="k('sections.contact')" dot="t">
                <div class="g2">
                    <div>
                        <label>{{ k('fields.seller') }}</label>
                        <input v-model="gen.form.seller" />
                    </div>
                    <div>
                        <label>{{ k('fields.seller_role') }}</label>
                        <input v-model="gen.form.sellerRole" />
                    </div>
                </div>
                <div class="g2">
                    <div>
                        <label>{{ k('fields.phone') }}</label>
                        <input v-model="gen.form.phone" />
                    </div>
                    <div>
                        <label>{{ k('fields.email') }}</label>
                        <input v-model="gen.form.email" />
                    </div>
                </div>
                <label>{{ k('fields.site') }}</label>
                <input v-model="gen.form.site" />
            </CollapsibleSection>

            <!-- Modelo padrão -->
            <CollapsibleSection :title="k('sections.template')" dot="t">
                <p class="small muted">{{ k('template.hint') }}</p>
                <div class="g2">
                    <button
                        class="btn ghost"
                        type="button"
                        @click="gen.saveTemplate()"
                    >
                        {{ k('template.save') }}
                    </button>
                    <button
                        class="btn ghost"
                        type="button"
                        @click="gen.restoreFactoryTemplate()"
                    >
                        {{ k('template.restore') }}
                    </button>
                </div>
                <div class="small muted" style="margin-top: 9px">
                    {{ gen.templateStatus.value }}
                </div>
            </CollapsibleSection>

            <!-- Propostas salvas -->
            <CollapsibleSection
                ref="draftsSection"
                :title="k('sections.drafts')"
                dot="p"
            >
                <div v-if="!gen.drafts.value.length" class="small muted">
                    {{ k('drafts.empty') }}
                </div>
                <div
                    v-for="(draft, index) in gen.drafts.value"
                    v-else
                    :key="draft.id"
                    class="draft"
                >
                    <div @click="gen.loadDraft(index)">
                        <b>{{ draft.client || k('drafts.no_client') }}</b>
                        <small
                            >{{ draft.num }} ·
                            {{ draftDate(draft.savedAt) }}</small
                        >
                    </div>
                    <button
                        class="x"
                        :title="k('drafts.remove')"
                        @click="gen.deleteDraft(index)"
                    >
                        ✕
                    </button>
                </div>
            </CollapsibleSection>
        </div>

        <div class="foot">
            <button class="btn ghost" @click="gen.newProposal()">
                {{ k('actions.new') }}
            </button>
            <button class="btn ghost" @click="onSaveDraft()">
                {{ k('actions.save') }}
            </button>
            <button class="btn ghost" @click="gen.exportJSON()">
                {{ k('actions.export') }}
            </button>
            <button class="btn ghost" @click="importInput?.click()">
                {{ k('actions.import') }}
            </button>
            <input
                ref="importInput"
                class="hide"
                type="file"
                accept="application/json"
                @change="gen.importJSON($event.target as HTMLInputElement)"
            />
            <button class="btn primary full" @click="gen.printProposal()">
                {{ k('actions.print') }}
            </button>
        </div>
    </aside>
</template>
