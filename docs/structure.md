┌─────────────────────────────────────────────────────────────┐
│                         VPS                                  │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │         Traefik Global (Porta 80/443)              │    │
│  │  - Gerencia TODOS os domínios e SSL                │    │
│  └────────────────────────────────────────────────────┘    │
│                           │                                  │
│           ┌───────────────┴───────────────┐                │
│           │                               │                │
│  ┌────────▼─────────┐          ┌─────────▼────────┐       │
│  │   STAGING        │          │   PRODUCTION     │       │
│  │ plannerate.dev.br│          │ plannerate.com.br│       │
│  │                  │          │                  │       │
│  │ - app            │          │ - app            │       │
│  │ - postgres       │          │ - postgres       │       │
│  │ - redis          │          │ - redis          │       │
│  │ - reverb         │          │ - reverb         │       │
│  │                  │          │                  │       │
│  │ Subdomínios:     │          │ Subdomínios:     │       │
│  │ reverb.dev.br    │          │ reverb.com.br    │       │
│  │ api.dev.br       │          │ api.com.br       │       │
│  └──────────────────┘          └──────────────────┘       │
└─────────────────────────────────────────────────────────────┘

Git Flow:
branch staging → Push → GitHub Actions → Deploy Staging
branch staging → Merge to main → GitHub Actions → Deploy Production


/opt/plannerate/
├── traefik/                      # Traefik global
│   ├── docker-compose.yml
│   ├── .env
│   └── letsencrypt/
│       └── acme.json
│
├── staging/                      # Branch: staging
│   ├── .git/
│   ├── .env                      # plannerate.dev.br
│   ├── docker-compose.staging.yml
│   ├── Dockerfile.prod
│   ├── storage/
│   └── ...
│
└── production/                   # Branch: main
    ├── .git/
    ├── .env                      # plannerate.com.br
    ├── docker-compose.prod.yml
    ├── Dockerfile.prod
    ├── storage/
    └── ...