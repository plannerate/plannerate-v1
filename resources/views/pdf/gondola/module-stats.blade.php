<div class="module-stats">
    <div class="stats-title">Estatísticas do Módulo</div>
    <div class="stats-grid">
        <div class="stat-item">
            <span class="stat-label">Total de Prateleiras</span>
            <span class="stat-value">{{ $shelves->count() }}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Total de Produtos</span>
            <span class="stat-value">
                {{ $shelves->sum(function($shelf) {
                                return $shelf->segments->filter(function($segment) {
                                    return $segment->layer && $segment->layer->product;
                                })->count();
                            }) }}
            </span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Total de Facings</span>
            <span class="stat-value">
                {{ $shelves->sum(function($shelf) {
                                return $shelf->segments->sum(function($segment) {
                                    return ($segment->layer && $segment->layer->product) 
                                        ? ($segment->layer->quantity ?? 1) 
                                        : 0;
                                });
                            }) }}
            </span>
        </div>
    </div>
</div>