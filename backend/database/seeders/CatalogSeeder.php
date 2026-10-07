<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Specialization;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::updateOrCreate(
            ['slug' => 'languages'],
            ['name' => 'Languages', 'status' => 'active', 'sort_order' => 1],
        );

        $subcategory = Subcategory::updateOrCreate(
            ['category_id' => $category->id, 'slug' => 'english'],
            ['name' => 'English', 'status' => 'active'],
        );

        Specialization::updateOrCreate(
            ['subcategory_id' => $subcategory->id, 'slug' => 'conversation-english'],
            ['name' => 'Conversation English', 'status' => 'active'],
        );
    }
}
