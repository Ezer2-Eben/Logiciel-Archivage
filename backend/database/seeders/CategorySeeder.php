<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Affaires Civiles',
                'description' => 'Documents relatifs aux litiges civils, contrats, et droits de propriété'
            ],
            [
                'name' => 'Affaires Pénales',
                'description' => 'Documents relatifs aux infractions, délits et crimes'
            ],
            [
                'name' => 'Affaires Commerciales',
                'description' => 'Documents relatifs aux litiges commerciaux et entreprise'
            ],
            [
                'name' => 'Affaires Familiales',
                'description' => 'Documents relatifs au divorce, garde d\'enfants, succession'
            ],
            [
                'name' => 'Affaires Administratives',
                'description' => 'Documents relatifs aux litiges avec l\'administration'
            ],
            [
                'name' => 'Affaires Sociales',
                'description' => 'Documents relatifs à la sécurité sociale, chômage, retraite'
            ],
            [
                'name' => 'Affaires Fiscales',
                'description' => 'Documents relatifs aux litiges fiscaux et impôts'
            ],
            [
                'name' => 'Autres',
                'description' => 'Autres types de documents juridiques'
            ]
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
} 