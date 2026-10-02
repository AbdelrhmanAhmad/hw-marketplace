<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\User;

/**
 * بوابة المقالات — Authorization مستقل عن الأهلية للتأليف نفسها (تلك تُفحَص
 * بـArticleService::createDraft عبر ArticleAuthor::isApproved()، فحص حالة
 * لا Authorization بمعنى Gate). هذي الـPolicy فقط لملكية المقال — أي عضو
 * غير مالك المقال لا يقدر يعدّله أو يرسله للمراجعة (IDOR).
 */
class ArticlePolicy
{
    /** إنشاء مقال جديد — يتطلب ملف مؤلف مُوافَق عليه (لا مقال بعينه بعد، فحص على مستوى الفئة). */
    public function create(User $user): bool
    {
        return ArticleAuthor::where('user_id', $user->id)->where('status', 'approved')->exists();
    }

    public function update(User $user, Article $article): bool
    {
        return $this->owns($user, $article);
    }

    public function submit(User $user, Article $article): bool
    {
        return $this->owns($user, $article);
    }

    private function owns(User $user, Article $article): bool
    {
        $author = ArticleAuthor::where('user_id', $user->id)->first();

        return $author !== null && $article->article_author_id === $author->id;
    }
}
