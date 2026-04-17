<div class="gondola-visual">
    <div class="sections-container">
        @php
        $scale = 3; // Mesma escala do editor V3
        @endphp

        @foreach($sections as $idx => $sec)
        @php
        $sectionHeight = $sec->height * $scale;
        $sectionWidth = $sec->width * $scale;
        $cremalheiraWidth = ($sec->cremalheira_width ?? 4) * $scale;
        $totalWidth = $sectionWidth + (2 * $cremalheiraWidth);
        @endphp

        <div class="section-visual-box" style="width: {{ $totalWidth }}px; height: {{ $sectionHeight + 20 }}px;">
            {{-- Cremalheira esquerda --}}
            <div class="cremalheira cremalheira-left" style="width: {{ $cremalheiraWidth }}px;"></div>

            {{-- Área das prateleiras --}}
            @foreach($sec->shelves->sortBy('position') as $shelfIdx => $shelf)
            @php
            $shelfHeight = ($shelf->height ?? 2) * $scale;
            $nextShelf = $sec->shelves->where('position', '>', $shelf->position)->sortBy('position')->first();
            $shelfTop = $shelf->position * $scale;
            $shelfAreaHeight = $nextShelf
            ? ($nextShelf->position - $shelf->position) * $scale
            : ($sectionHeight - $shelfTop);
            @endphp

            <div class="shelf-area" style="bottom: {{ $shelfTop }}px; height: {{ $shelfAreaHeight }}px;">
                {{-- Base da prateleira --}}
                <div class="shelf-base" style="height: {{ $shelfHeight }}px; background: rgba(30, 41, 59, 0.95);"></div>

                {{-- Produtos --}}
                <div class="products-row" style="padding-bottom: {{ $shelfHeight }}px;">
                    @foreach($shelf->segments->sortBy('position') as $segment)
                    @if($segment->layer && $segment->layer->product)
                    @php
                    $product = $segment->layer->product;
                    $dimension = $product->dimension;
                    $productWidth = ($dimension->width ?? 10) * $scale;
                    $productHeight = ($dimension->height ?? 10) * $scale;
                    $quantity = $segment->layer->quantity ?? 1;
                    $colorClass = 'color-' . ((crc32($product->id) % 10) + 1);
                    @endphp

                    @for($q = 0; $q < $quantity; $q++)
                        <div class="product-box" style="width: {{ $productWidth }}px; height: {{ $productHeight }}px;">
                        @if($product->image_url && !str_contains($product->image_url, 'fallback'))
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="product-img">
                        @else
                        <div class="product-placeholder {{ $colorClass }}">
                            {{ substr($product->name, 0, 8) }}
                        </div>
                        @endif
                </div>
                @endfor
                @endif
                @endforeach
            </div>
        </div>
        @endforeach

        {{-- Cremalheira direita --}}
        <div class="cremalheira cremalheira-right" style="width: {{ $cremalheiraWidth }}px;"></div>

        {{-- Label do módulo --}}
        <div class="section-label">Módulo #{{ $sec->ordering }}</div>
    </div>
    @endforeach
</div>
</div>

<div class="page-break"></div>