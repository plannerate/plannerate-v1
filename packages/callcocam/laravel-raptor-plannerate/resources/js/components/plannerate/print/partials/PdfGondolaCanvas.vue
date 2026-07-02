<script setup lang="ts">
import { computed } from 'vue'
import type { Section } from '@/types/planogram'
import PdfSection from './PdfSection.vue'

interface Props {
    sections: Section[]
    localScale: number
    alignment: string
}

const props = defineProps<Props>()

/**
 * Folga (em cm) adicionada ao topo de cada módulo. No PDF em linha cada módulo
 * é capturado isoladamente pelo seletor `[data-module-section]`, e o
 * html2canvas recorta tudo que ultrapassa a caixa do elemento. Produtos da
 * prateleira de cima crescem para cima e podem passar do topo da seção
 * (`section.height`), sendo cortados na captura. O `extraHeight` aumenta a
 * caixa da seção e empurra as prateleiras para baixo, criando o espaço livre
 * no topo — o mesmo efeito do `paddingTop` usado no modo coluna
 * (PdfModulePage).
 *
 * Mantido pequeno de propósito: o PDF final é gerado no servidor (dompdf), então
 * essa folga serve apenas para o preview em tela e para o fallback html2canvas.
 * Valores altos deixavam um vão enorme e vazio acima dos produtos.
 */
const TOP_HEADROOM_CM = 12

const footHeight = computed(() => {
    const baseHeight = props.sections[0]?.base_height ?? 20
    return Math.round(baseHeight * props.localScale)
})
</script>

<template>
    <div data-pdf-scroll class="flex-1 overflow-auto bg-slate-50 dark:bg-slate-800/50">
        <div
            class="w-max min-w-full"
            :style="{
                paddingTop: `${Math.ceil(localScale * 24)}px`,
                paddingBottom: `${Math.ceil(localScale * 40)}px`,
                paddingLeft: `${Math.ceil(localScale * 40)}px`,
                paddingRight: `${Math.ceil(localScale * 40)}px`,
            }"
        >
            <!--
                Módulos da gôndola lado a lado.

                Usa inline-block (NÃO flexbox) de propósito: na captura para PDF
                o html2canvas não aplica `flex-direction:row` de forma confiável
                e empilha os módulos verticalmente, virando um "filete" vertical.
                `inline-block` + `vertical-align: bottom` reproduz exatamente o
                mesmo visual (módulos em linha, alinhados ao chão da gôndola) e é
                rasterizado corretamente. `white-space: nowrap` impede a quebra
                de linha; `font-size: 0` no container elimina os espaços em
                branco que o inline-block insere entre os itens (os rótulos têm
                tamanho de fonte próprio, então não são afetados).
            -->
            <div class="w-max" style="white-space: nowrap; font-size: 0">
                <div
                    v-for="(section, index) in sections"
                    :key="section.id"
                    class="inline-block align-bottom"
                >
                    <PdfSection
                        :section="section"
                        :scale-factor="localScale"
                        :alignment="alignment"
                        :index="index"
                        layout-direction="row"
                        :extra-height="TOP_HEADROOM_CM"
                        :data-section-id="section.id"
                    />
                </div>
            </div>


        </div>
    </div>
</template>
