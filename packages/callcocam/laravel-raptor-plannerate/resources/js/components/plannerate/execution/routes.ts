/**
 * Helpers de URL da camada de Execução em Loja.
 *
 * Escritos manualmente: o gerador do Wayfinder não rastreia o
 * GondolaExecutionLayerController (ver memória do projeto). As rotas vivem sob
 * o prefixo de tenant; caminhos relativos resolvem no host atual (subdomínio).
 */
const base = (executionId: string): string => `/executions/${executionId}`;

export const executionRoutes = {
    /** POST — registra uma evidência. */
    evidenceStore: (executionId: string): string => `${base(executionId)}/evidences`,
    /** DELETE — remove uma evidência. */
    evidenceDestroy: (executionId: string, evidenceId: string): string =>
        `${base(executionId)}/evidences/${evidenceId}`,
    /** POST — registra uma divergência. */
    divergenceStore: (executionId: string): string => `${base(executionId)}/divergences`,
    /** PATCH — atualiza o estado de uma divergência. */
    divergenceUpdate: (executionId: string, divergenceId: string): string =>
        `${base(executionId)}/divergences/${divergenceId}`,
    /** POST — conclui a execução. */
    complete: (executionId: string): string => `${base(executionId)}/complete`,
};
