import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useEcho } from '@laravel/echo-vue'

export interface SyncNotification {
    id: string
    client_id: string
    client_name: string
    store_name?: string
    type: 'started' | 'progress' | 'completed' | 'failed'
    context: 'sales' | 'products' | 'categories' | 'dimensions'
    date?: string
    total_items?: number
    processed_items?: number
    message?: string
    timestamp: string
}

export function useSyncNotifications() {
    const page = usePage()
    const notifications = ref<SyncNotification[]>([])
    const isConnected = ref(false)
    const activeSync = ref<SyncNotification | null>(null)

    // Adiciona notificação à lista
    const addNotification = (notification: SyncNotification) => {
         
        // Remove notificações antigas (mantém últimas 50)
        if (notifications.value.length > 50) {
            notifications.value = notifications.value.slice(-50)
        }

        notifications.value.push(notification) 

        // Se é progress ou started, atualiza activeSync
        if (notification.type === 'started' || notification.type === 'progress') {
            activeSync.value = notification 
        }

        // Se completou ou falhou, limpa activeSync após 3s
        if (notification.type === 'completed' || notification.type === 'failed') {
            setTimeout(() => {
                activeSync.value = null 
            }, 3000)
        }
    }

    // Calcula progresso em %
    const getProgress = (notification: SyncNotification) => {
        if (!notification.total_items || !notification.processed_items) return 0
        return Math.round((notification.processed_items / notification.total_items) * 100)
    }

    // Formata mensagem
    const formatMessage = (notification: SyncNotification) => {
        const context = notification.context === 'sales' ? 'Vendas' 
            : notification.context === 'products' ? 'Produtos'
            : notification.context === 'categories' ? 'Categorias'
            : notification.context === 'dimensions' ? 'Dimensões'
            : 'Importação'
        
        const action = notification.type === 'started' ? 'Iniciando' 
            : notification.type === 'progress' ? 'Processando'
            : notification.type === 'completed' ? 'Concluído'
            : 'Falhou'

        let msg = `${action} ${context}`
        
        if (notification.store_name) {
            msg += ` - ${notification.store_name}`
        }
        
        if (notification.date) {
            msg += ` (${notification.date})`
        }

        if (notification.processed_items && notification.total_items) {
            msg += ` - ${notification.processed_items}/${notification.total_items} itens`
        }

        return msg
    }

    // Limpa notificações
    const clearNotifications = () => {
        notifications.value = []
        activeSync.value = null
    }

    // Função auxiliar para processar eventos
    const handleSyncEvent = (event: any) => {
        isConnected.value = true
        
        const notification: SyncNotification = {
            id: `${event.timestamp}-${Math.random()}`,
            client_id: event.client_id,
            client_name: event.client_name,
            store_name: event.store_name,
            type: event.type,
            context: event.context,
            date: event.date,
            total_items: event.total_items,
            processed_items: event.processed_items,
            message: event.message,
            timestamp: event.timestamp,
        }

        addNotification(notification)
    }

    // Obtém userId e clientId
    const userId = (page.props as any).auth?.user_id
    const clientId = (page.props as any).auth?.user?.client?.id || (page.props as any).client_id
    // Conecta ao canal privado do usuário (se userId estiver disponível)
    let listenUser: (() => void) | null = null
    let listenUserImport: (() => void) | null = null
    if (userId) {
         
        // Canal de sync (vendas/produtos da API)
        const userEcho = useEcho(
            `sync.user.${userId}`,
            '.sync.progress',
            (event: any) => { 
                handleSyncEvent(event)
            },
            [userId],
            'private'
        )
        listenUser = userEcho.listen
        listenUser()
         

        // Canal de import (importação de arquivos Excel)
        const userImportEcho = useEcho(
            `import.user.${userId}`,
            '.import.progress',
            (event: any) => {
                 
                // Adapta o evento de importação para o formato de sync
                const adaptedEvent = {
                    client_id: event.client_id,
                    client_name: event.client_name || 'Cliente',
                    store_name: event.sheet_name, // Usa sheet_name como store_name
                    type: event.type,
                    context: event.sheet_name?.includes('Tabela mercadológico') ? 'categories' 
                        : event.sheet_name?.includes('dimensão') ? 'dimensions' 
                        : 'products',
                    total_items: event.total_rows,
                    processed_items: event.processed_rows,
                    message: event.message,
                    timestamp: event.timestamp,
                }
                 
                handleSyncEvent(adaptedEvent)
            },
            [userId],
            'private'
        )
        listenUserImport = userImportEcho.listen
        listenUserImport()
         
    }

    // Conecta ao canal privado do cliente (se clientId estiver disponível)
    let listenClient: (() => void) | null = null
    if (clientId) {
        const clientEcho = useEcho(
            `sync.client.${clientId}`,
            '.sync.progress',
            (event: any) => {
                handleSyncEvent(event)
            },
            [clientId],
            'private'
        )
        listenClient = clientEcho.listen
        listenClient()
    }

    return {
        notifications,
        activeSync,
        isConnected,
        addNotification,
        getProgress,
        formatMessage,
        clearNotifications,
    }
}
