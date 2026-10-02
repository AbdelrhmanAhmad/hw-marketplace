<?php

namespace App\Filament\Resources\ArticleAuthorResource\Pages;

use App\Filament\Resources\ArticleAuthorResource;
use Filament\Resources\Pages\ListRecords;

/** لا CreateAction — طلبات التأليف تُنشأ من الواجهة العامة فقط (المستخدم نفسه)، لا من لوحة الإدارة. */
class ListArticleAuthors extends ListRecords
{
    protected static string $resource = ArticleAuthorResource::class;
}
