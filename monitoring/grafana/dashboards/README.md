# Grafana Dashboards Recomendados

## Como importar dashboards da comunidade

1. Acesse Grafana: https://grafana.plannerate.com.br
2. Login: admin / plannerate2026
3. Vá em "+" → "Import Dashboard"
4. Cole o ID do dashboard e clique "Load"
5. Selecione o datasource "Prometheus"
6. Clique "Import"

## Dashboards Essenciais

### 1. Node Exporter Full (ID: 1860)
**Descrição**: Dashboard completo para métricas de sistema (CPU, memória, disco, rede)  
**Para**: Ambos servidores (Docker VM + PostgreSQL Server)  
**URL**: https://grafana.com/grafana/dashboards/1860

**Métricas incluídas**:
- CPU usage (user, system, iowait)
- Memory usage (RAM, swap)
- Disk I/O, space usage
- Network traffic (RX/TX)
- Load average
- Uptime

---

### 2. PostgreSQL Database (ID: 9628)
**Descrição**: Dashboard avançado para PostgreSQL  
**Para**: Servidor PostgreSQL (72.62.139.43)  
**URL**: https://grafana.com/grafana/dashboards/9628

**Métricas incluídas**:
- Active connections
- Queries per second
- Transaction rate
- Cache hit ratio
- Slow queries
- Locks and deadlocks
- Database size
- Replication lag

---

### 3. PgBouncer Stats (ID: 16396)
**Descrição**: Dashboard para PgBouncer connection pooling  
**Para**: Servidor PostgreSQL (72.62.139.43)  
**URL**: https://grafana.com/grafana/dashboards/16396

**Métricas incluídas**:
- Pool connections (active, idle, waiting)
- Client connections
- Query throughput
- Average query time
- Connection errors

---

### 4. Redis Dashboard (ID: 763)
**Descrição**: Dashboard para Redis  
**Para**: Servidor Docker (148.230.78.184)  
**URL**: https://grafana.com/grafana/dashboards/763

**Métricas incluídas**:
- Memory usage
- Keyspace hits/misses
- Commands processed
- Connected clients
- Network I/O
- Evicted keys

---

### 5. Docker Container Metrics (ID: 193)
**Descrição**: Dashboard para containers Docker via cAdvisor  
**Para**: Servidor Docker (148.230.78.184)  
**URL**: https://grafana.com/grafana/dashboards/193

**Métricas incluídas**:
- Container CPU usage
- Container memory usage
- Container network I/O
- Container disk I/O
- Container count

---

## Instalação Rápida (via API)

Você também pode importar todos de uma vez via script:

```bash
# No servidor Docker, depois de subir o Grafana
GRAFANA_URL="http://localhost:3000"
GRAFANA_USER="admin"
GRAFANA_PASSWORD="plannerate2026"

dashboards=(1860 9628 16396 763 193)

for id in "${dashboards[@]}"; do
    curl -X POST \
        -H "Content-Type: application/json" \
        -u "$GRAFANA_USER:$GRAFANA_PASSWORD" \
        -d "{\"dashboard\":{\"id\":$id},\"overwrite\":true,\"inputs\":[{\"name\":\"DS_PROMETHEUS\",\"type\":\"datasource\",\"pluginId\":\"prometheus\",\"value\":\"Prometheus\"}]}" \
        "$GRAFANA_URL/api/dashboards/import"
    echo "Dashboard $id importado"
done
```

---

## Dashboards Customizados (Futuro)

Você pode criar dashboards customizados para:
- Métricas da aplicação Laravel (Horizon, filas, jobs)
- Métricas de negócio (planogramas criados, usuários ativos)
- SLA e uptime tracking
- Cost tracking (AWS/DO usage)
