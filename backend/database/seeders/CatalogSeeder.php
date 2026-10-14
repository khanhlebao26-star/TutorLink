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
        $catalog = [
            [
                'name' => 'Languages',
                'slug' => 'languages',
                'sort_order' => 1,
                'subcategories' => [
                    [
                        'name' => 'English',
                        'slug' => 'english',
                        'specializations' => [
                            ['name' => 'Conversation English', 'slug' => 'conversation-english'],
                            ['name' => 'Business English', 'slug' => 'business-english'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Education',
                'slug' => 'education',
                'sort_order' => 2,
                'subcategories' => [
                    [
                        'name' => 'Mathematics',
                        'slug' => 'mathematics',
                        'specializations' => [
                            ['name' => 'High School Math', 'slug' => 'high-school-math'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Fitness',
                'slug' => 'fitness',
                'sort_order' => 3,
                'subcategories' => [
                    [
                        'name' => 'Personal Training',
                        'slug' => 'personal-training',
                        'specializations' => [
                            ['name' => 'Strength Training', 'slug' => 'strength-training'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'sort_order' => 4,
                'subcategories' => [
                    [
                        'name' => 'Racket Sports',
                        'slug' => 'racket-sports',
                        'specializations' => [
                            ['name' => 'Badminton', 'slug' => 'badminton'],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($catalog as $categoryData) {
            $category = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['name'],
                    'status' => 'active',
                    'sort_order' => $categoryData['sort_order'],
                ],
            );

            foreach ($categoryData['subcategories'] as $subcategoryData) {
                $subcategory = Subcategory::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'slug' => $subcategoryData['slug'],
                    ],
                    [
                        'name' => $subcategoryData['name'],
                        'status' => 'active',
                    ],
                );

                foreach ($subcategoryData['specializations'] as $specializationData) {
                    Specialization::updateOrCreate(
                        [
                            'subcategory_id' => $subcategory->id,
                            'slug' => $specializationData['slug'],
                        ],
                        [
                            'name' => $specializationData['name'],
                            'status' => 'active',
                        ],
                    );
                }
            }
        }
    }
}
