<?php

use App\Models\BakeryCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table): void {
            $table->foreignId('linked_category_id')->nullable()->after('parent_id')
                ->constrained('bakery_categories')->nullOnDelete();
            $table->string('placement', 20)->default('all')->after('linked_category_id')->index();
            $table->boolean('open_in_new_tab')->default(false)->after('is_active');
            $table->boolean('hide_when_empty')->default(false)->after('open_in_new_tab');
            $table->string('image_path')->nullable()->after('description');
        });

        if (DB::table('navigation_items')->exists()) {
            return;
        }

        $now = now();
        foreach ([
            ['label' => 'خانه', 'href' => '/', 'sort_order' => 10],
            ['label' => 'فروشگاه', 'href' => '/products', 'sort_order' => 20],
            ['label' => 'راهنماها', 'href' => '/blog', 'sort_order' => 30],
            ['label' => 'تماس با ما', 'href' => '/contact', 'sort_order' => 40],
            ['label' => 'درباره ما', 'href' => '/about', 'sort_order' => 50],
        ] as $item) {
            DB::table('navigation_items')->insert([...$item, 'placement' => 'all', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $shopId = DB::table('navigation_items')->where('href', '/products')->value('id');
        DB::table('navigation_items')->insert([
            'label' => 'مشاهده همه محصولات',
            'href' => '/products',
            'parent_id' => $shopId,
            'placement' => 'all',
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        BakeryCategory::query()->active()->ordered()->get()->each(function (BakeryCategory $category) use ($shopId, $now): void {
            DB::table('navigation_items')->insert([
                'label' => $category->name,
                'href' => '/products/category/'.$category->slug,
                'parent_id' => $shopId,
                'linked_category_id' => $category->getKey(),
                'placement' => 'all',
                'sort_order' => 10 + (int) $category->sort_order,
                'is_active' => true,
                'hide_when_empty' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('linked_category_id');
            $table->dropIndex(['placement']);
            $table->dropColumn(['placement', 'open_in_new_tab', 'hide_when_empty', 'image_path']);
        });
    }
};
