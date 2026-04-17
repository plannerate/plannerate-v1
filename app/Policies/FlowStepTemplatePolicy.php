<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */
 
namespace App\Policies;

use App\Models\FlowStepTemplate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FlowStepTemplatePolicy
{

     protected $permission = "flowStepTemplates";
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(sprintf('%s.%s.view', request()->getContext(), $this->permission));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FlowStepTemplate $flowStepTemplate): bool
    {
        return $user->can(sprintf('%s.%s.view', request()->getContext(), $this->permission));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
         return $user->can(sprintf('%s.%s.create', request()->getContext(), $this->permission));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FlowStepTemplate $flowStepTemplate): bool
    {
        return $user->can(sprintf('%s.%s.update', request()->getContext(), $this->permission));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FlowStepTemplate $flowStepTemplate): bool
    {
       return $user->can(sprintf('%s.%s.delete', request()->getContext(), $this->permission));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FlowStepTemplate $flowStepTemplate): bool
    {
        return $user->can(sprintf('%s.%s.restore', request()->getContext(), $this->permission));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FlowStepTemplate $flowStepTemplate): bool
    {
       return $user->can(sprintf('%s.%s.force-delete', request()->getContext(), $this->permission));
    }
}
