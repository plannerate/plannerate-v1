<div class="header">
    <h1>{{ $gondola->name }}</h1>
    <div class="header-info">
        <div class="info-group">
            <div class="info-item">
                <span class="info-label">Cliente:</span>
                <span class="info-value">{{ $gondola->planogram?->client?->name ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Loja:</span>
                <span class="info-value">{{ $gondola->planogram?->store?->name ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Localização:</span>
                <span class="info-value">{{ $gondola->location ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Lado:</span>
                <span class="info-value">{{ $gondola->side ?? 'N/A' }}</span>
            </div>
        </div>
        <div class="info-group">
            <div class="info-item">
                <span class="info-label">Módulos:</span>
                <span class="info-value">{{ $sections->count() }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Largura Total:</span>
                <span class="info-value">{{ $sections->sum('width') }} cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">Altura:</span>
                <span class="info-value">{{ $sections->first()->height ?? 'N/A' }} cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">Data:</span>
                <span class="info-value">{{ date('d/m/Y H:i') }}</span>
            </div>
        </div>
    </div>
</div>