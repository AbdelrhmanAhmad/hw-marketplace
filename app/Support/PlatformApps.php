<?php

namespace App\Support;

class PlatformApps
{
    /**
     * تصنيفات المستخدمين المستهدفين لعرض التطبيقات حسب من يستفيد منها.
     */
    public static function audiences(): array
    {
        return [
            'lawyer' => 'للمحامي المستقل',
            'firm' => 'لمكتب المحاماة',
            'finance' => 'للمحاسب والمالي',
            'student' => 'للطالب',
        ];
    }

    /**
     * القائمة التفصيلية الكاملة لتطبيقات وبوابات متجر حكم ورقم.
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'marefa',
                'name' => 'بوابة معرفة',
                'tagline' => 'الخدمات القانونية',
                'description' => 'الأنظمة السعودية وتحديثاتها التشريعية، وحاسبات قانونية عملية.',
                'status' => 'available',
                'href' => route('marefa.home'),
                'icon' => 'legal',
                'audiences' => ['lawyer', 'firm'],
                'free' => true,
            ],
            [
                'key' => 'bankruptcy-tech',
                'name' => 'إفلاس تك',
                'tagline' => 'خدمات الإفلاس',
                'description' => 'حلول متخصصة بإجراءات الإفلاس وإعادة الهيكلة المالية.',
                'status' => 'soon',
                'icon' => 'bankruptcy',
                'audiences' => ['lawyer', 'firm', 'finance'],
            ],
            [
                'key' => 'articles',
                'name' => 'بوابة المقالات',
                'tagline' => 'محتوى معرفي',
                'description' => 'مقالات وتحليلات قانونية ومالية من مختصين.',
                'status' => 'soon',
                'icon' => 'articles',
                'audiences' => ['lawyer', 'firm', 'finance', 'student'],
                'free' => true,
            ],
            [
                'key' => 'community',
                'name' => 'مجتمع الخدمات',
                'tagline' => 'شبكة تبادل الخدمات',
                'description' => 'مساحة للمهنيين لتبادل الخدمات والفرص فيما بينهم.',
                'status' => 'soon',
                'icon' => 'community',
                'audiences' => ['lawyer', 'firm', 'finance'],
                'free' => true,
            ],
            [
                'key' => 'tech-portal',
                'name' => 'بوابة التقنية',
                'tagline' => 'حلول واستشارات تقنية',
                'description' => 'خدمات واستشارات تقنية تدعم المهنيين والمكاتب.',
                'status' => 'soon',
                'icon' => 'tech',
                'audiences' => ['firm'],
            ],
            [
                'key' => 'network',
                'name' => 'الشبكة المهنية',
                'tagline' => 'تواصل مهني',
                'description' => 'بناء ملف مهني والتواصل مع المحامين والمختصين، على غرار LinkedIn.',
                'status' => 'soon',
                'icon' => 'network',
                'audiences' => ['lawyer', 'firm', 'finance', 'student'],
            ],
            [
                'key' => 'internships',
                'name' => 'بوابة التدريب الميداني',
                'tagline' => 'فرص للطلاب',
                'description' => 'ربط طلاب القانون والمحاسبة بفرص التدريب الميداني لدى المكاتب.',
                'status' => 'soon',
                'icon' => 'students',
                'audiences' => ['student', 'firm'],
                'free' => true,
            ],
            [
                'key' => 'ai-case-draft',
                'name' => 'محرك مسودة القضية الذكي',
                'tagline' => 'مدعوم بالذكاء الاصطناعي',
                'description' => 'إنشاء مسودة أولية للقضية تلقائيًا بالاستناد لمعطياتك.',
                'status' => 'soon',
                'icon' => 'ai',
                'audiences' => ['lawyer', 'firm'],
            ],
        ];
    }

    /**
     * تجميع كل التطبيقات حسب فئة المستخدم المستهدفة، بترتيب audiences().
     *
     * @return array<string, array{label: string, apps: array}>
     */
    public static function groupedByAudience(): array
    {
        $apps = static::all();
        $groups = [];

        foreach (static::audiences() as $key => $label) {
            $groups[$key] = [
                'label' => $label,
                'apps' => array_values(array_filter($apps, fn ($app) => in_array($key, $app['audiences'], true))),
            ];
        }

        return $groups;
    }
}
