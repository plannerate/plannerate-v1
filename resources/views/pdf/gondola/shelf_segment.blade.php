<div style="position: relative; display: flex; flex-direction: column; align-items: flex-start; pointer-events: auto;">
    @for($i = 0; $i < $segment->quantity; $i++)
        <div style="display: flex; flex-direction: column;">
            <div style="display: flex; align-items: center;">
                @if ($layer = $segment->layer)
                @if ($product = $layer->product)
                @for ($j = 0; $j < $layer->quantity; $j++)
                    @php
                    $productWidth = ($product->width ?? 10) * $gondola->scale_factor;
                    $productHeight = ($product->height ?? 15) * $gondola->scale_factor;
                    @endphp
                    <img src="{{ $product->image_url_encoded ?? $product->image_url }}" alt="{{ $product->name }}" style="width: {{ $productWidth }}px; height: {{ $productHeight }}px; z-index: 20; object-fit: cover;">
                    @endfor
                    @endif
                    @endif
            </div>
        </div>
        @endfor
</div>