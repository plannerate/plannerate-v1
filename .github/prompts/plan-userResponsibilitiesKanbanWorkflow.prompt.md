# Plan: Adicionar Sistema de Responsabilidade por Users ao Kanban

## Context

Atualmente, o sistema Kanban usa **Roles** para atribuição de responsabilidades (`assigned_to` aponta para Role, `responsible_role_id` em configs). O objetivo é migrar para usar **Users individuais** através do relacionamento many-to-many `users()` que já existe em `PlanogramWorkflowConfig`.

## Requirements (confirmed with user)

1. **Users em paralelo com Roles**: Manter sistema de Roles atual, adicionar Users como responsáveis individuais
2. **Múltiplos users por execution**: Many-to-many via nova pivot table
3. **PlanogramWorkflowConfig.users()** define os responsáveis disponíveis para cada step
4. **Permissões híbridas**: Pode mover card se tiver a Role OU se for um dos Users atribuídos
5. **Dois filtros separados**: Um para Role, outro para User
6. **Exibir users nos cards**: Mostrar avatares/nomes dos users responsáveis

## TL;DR

Criar relacionamento many-to-many entre `GondolaWorkflowExecution` e `User`, carregar users das configs no Kanban, exibir nos cards, filtrar por user, e permitir permissões híbridas (Role OU User).

## Implementation Steps

### Phase 1: Database & Models (Backend Foundation)

**1. Create migration for user-execution pivot** (*parallel with step 2*)
- Create `user_gondola_workflow_execution` table with columns: `user_id`, `gondola_workflow_execution_id`, `timestamps`
- Add composite primary key on both IDs
- Add indexes for common queries

**2. Update GondolaWorkflowExecution model** (*parallel with step 1*)
- Add `users(): BelongsToMany` relationship using pivot table
- Add helper method `isAssignedToUser(string $userId): bool`
- Add helper method `canBeMovedByUser(User $user): bool` - checks Role OR User assignment

**3. Create/Update seed data** (*depends on 1, 2*)
- Seed example users to existing `PlanogramWorkflowConfig` via `users()` relationship
- Create sample user-execution assignments for testing

### Phase 2: Backend Service & API Updates

**4. Update KanbanService to load users** (*depends on 2*)
- In `getExecutions()`: eager load `'config.users'` and `'users'` relationships
- Fix `getUsers()` method: change from `Role::select()` to `User::select('id', 'name', 'email')`
- Add filter support for `user_id` alongside existing `assigned_to` (role) filter
- In `enrichExecutionsWithTenantData()`: include `users` array in execution data

**5. Update GondolaWorkflowController** (*depends on 2*)
- In `move()` method: validate user permissions using `canBeMovedByUser()`
- Allow move if user has required role OR is assigned to execution

**6. Create user assignment endpoints** (*depends on 2, 4*)
- `POST /workflow/executions/{id}/users` - Assign users to execution (accepts array of user_ids)
- `DELETE /workflow/executions/{id}/users/{userId}` - Remove user from execution
- Validate against available users from `config.users()`

### Phase 3: Frontend Types & State

**7. Update TypeScript types** (*parallel with steps 8-9*)
- In `workflow.ts`: Add `users: Array<{ id: string; name: string; email?: string }>` to `GondolaWorkflowExecution`
- Add `users` to `PlanogramWorkflowConfig` type
- Update `KanbanFilters` to include `user_id: string | null` alongside `assigned_to`

**8. Update KanbanHeader component** (*depends on 7*)
- Add second "Responsável (User)" select dropdown  
- Keep existing "Responsável (Role)" filter as-is
- Update filter state to handle both `assigned_to` (role) and `user_id` filters
- Both filters work independently (AND logic)

**9. Update KanbanBoard/Column permissions** (*depends on 7*)
- Update `canDrag` computed in GondolaCard to check: `userHasRole OR userIsAssigned`
- Pass current user ID to components for permission checks

### Phase 4: Card Display & Interaction

**10. Update GondolaCard to display users** (*depends on 7*)
- Add "Users" section below "Assigned Role" showing avatars/names of assigned users
- Use avatar component or initials badges for each user
- Limit display to 3 users + "+N more" indicator if more exist

**11. Update GondolaDetailsModal** (*depends on 6, 7*)
- Add "Responsáveis" tab/section showing full list of assigned users
- Add UI to assign/unassign users (multiselect from config.users)
- Call new assignment endpoints on change
- Show success/error toasts on assignment changes

**12. Add user assignment to GondolaCreateStepper** (*depends on 6, 7*)
- Optional step to pre-assign users when creating gondola workflow
- Show available users from first step's config
- Store assignments to be created after workflow execution creation

### Phase 5: Verification & Polish

**13. Write feature tests** (*depends on all previous*)
- Test user assignment/unassignment via API
- Test filtering by user_id in KanbanService
- Test permission logic (role OR user)
- Test eager loading doesn't cause N+1

**14. Write browser test** (*depends on 13*)
- Create execution, assign users, verify display in card
- Test filtering by user vs by role
- Test drag permission with user assignment (no role)

**15. Update documentation** (*depends on all*)
- Document user assignment feature in KANBAM.md or similar
- Update API endpoints documentation
- Add examples of permission checks

## Relevant Files

### Backend
- `database/migrations/` - New migration for pivot table
- `app/Models/Workflow/GondolaWorkflowExecution.php` - Add `users()` relationship, permission helpers
- `app/Services/Workflow/KanbanService.php` - Fix `getUsers()`, add user filtering, eager load users
- `app/Http/Controllers/Workflow/GondolaWorkflowController.php` - Update `move()` permissions
- New: `app/Http/Controllers/Workflow/GondolaWorkflowExecutionUserController.php` - User assignment endpoints

### Frontend
- `resources/js/types/workflow.ts` - Add users to types
- `resources/js/components/kanban/KanbanHeader.vue` - Add user_id filter dropdown
- `resources/js/components/kanban/GondolaCard.vue` - Display users, update canDrag logic
- `resources/js/components/kanban/GondolaDetailsModal.vue` - User assignment UI
- `resources/js/pages/admin/tenant/plannerates/kanbans/index.vue` - Pass user context for permissions

## Verification Steps

1. Run migration: `php artisan migrate` - verify pivot table created
2. Seed users to configs: `php artisan db:seed` or tinker to test relationships
3. Test KanbanService: `php artisan tinker` - verify users loaded, filters work
4. Run feature tests: `php artisan test --filter=Workflow` - verify all user assignment logic
5. Browser test: Open Kanban, assign users to execution, verify display and filtering
6. Permission test: Assign user without role, verify can still drag card
7. Check N+1: Use Debugbar or Telescope to verify no extra queries with users loaded

## Key Decisions

- **Pivot table approach**: Using many-to-many allows multiple users per execution, more flexible than single user_id column
- **Config.users() as source**: Users in `PlanogramWorkflowConfig.users()` define who *can* be assigned to executions of that step - provides governance
- **Hybrid permissions**: Role OR User assignment allows gradual migration and flexibility (role-based teams + individual assignments)
- **Parallel filters**: Separate filters for role/user maintains backwards compatibility and allows complex queries
- **Left assigned_to as-is**: Not changing existing `assigned_to` field/relationship to minimize breaking changes - new user system is additive

## Open Questions

1. **assigned_to field clarity**: The `assigned_to` field has conflicting documentation (migration says users, model says roles). Should we:
   - A) Create technical debt ticket to investigate and align later (recommended)
   - B) Fix now by creating new `assigned_role_id` field and migrating data
   - C) Document current state and leave as role_id

2. **User assignment UI location**: Where should users be able to assign/unassign users?
   - A) Only in GondolaDetailsModal (recommended for v1)
   - B) Also inline in card (dropdown or popover)
   - C) Bulk assignment tool (separate page/modal)

3. **Notification on assignment**: Should assigned users receive notifications?
   - A) Yes, send notification when user is assigned to execution (recommended)
   - B) No, manual assignment is informational only
   - C) Optional per-tenant setting

## Current System Issues Found

1. **KanbanService.php**:
   - Método `getUsers()` retorna Roles ao invés de Users (BUG!)
   - Filtro `assigned_to` filtra executions por Role
   - Não carrega users do relacionamento many-to-many

2. **Frontend**:
   - KanbanHeader filtra por "user" mas recebe Roles
   - Types indicam "User" mas backend envia Roles

3. **Migration vs Model inconsistency**:
   - Migration comment: `assigned_to` → 'FK to users'
   - Model relationship: `assignedRole()` → Role
   - Needs investigation and alignment
