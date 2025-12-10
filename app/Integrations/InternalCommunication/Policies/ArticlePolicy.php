<?php

namespace App\Integrations\InternalCommunication\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\InternalCommunication\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    /**
     * Determine whether the user can view any articles.
     *
     * Note: financer_id filtering is handled automatically by HasFinancer global scope.
     */
    public function viewAny(User $user): bool
    {

        return $user->hasPermissionTo(PermissionDefaults::READ_ARTICLE);

    }

    /**
     * Determine whether the user can view the article.
     *
     * Note: financer_id filtering is handled automatically by HasFinancer global scope.
     * Draft/Published status filtering is handled in StatusFilter pipeline.
     */
    public function view(User $user, Article $article): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_ARTICLE)) {
            return $user->current_financer_id === $article->financer_id;
        }

        return false;

    }

    /**
     * Determine whether the user can create articles.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_ARTICLE);
    }

    /**
     * Determine whether the user can update the article.
     *
     * Note: financer_id filtering is handled automatically by HasFinancer global scope.
     */
    public function update(User $user, Article $article): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_ARTICLE)) {
            return $user->current_financer_id === $article->financer_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the article.
     *
     * Note: financer_id filtering is handled automatically by HasFinancer global scope.
     */
    public function delete(User $user, Article $article): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_ARTICLE)) {
            return $user->current_financer_id === $article->financer_id;
        }

        return false;
    }

    public function restore(User $user, Article $article): bool
    {
        return $this->delete($user, $article);
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
}
