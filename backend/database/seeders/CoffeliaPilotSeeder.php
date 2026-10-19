<?php

namespace Database\Seeders;

use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\MenuProduct;
use Illuminate\Database\Seeder;

class CoffeliaPilotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Idempotent seeder for Coffelia pilot establishment.
     * Note: Uses strictly demonstrative prices, local vector placeholders,
     * no fake 3D associations, and mock contact info clearly flagged as non-official.
     */
    public function run(): void
    {
        // 1. Establishment: Coffelia Coffee Roasters (Demo Pilot)
        $establishment = Establishment::updateOrCreate(
            ['slug' => 'coffelia'],
            [
                'name' => 'Coffelia Coffee Roasters',
                'business_type' => 'cafeteria',
                'tagline' => 'Café de especialidad y repostería artesanal (Demostración)',
                'description' => 'Piloto comercial demostrativo en Gastro3D. Menú digital interactivo para café de especialidad y panadería fresca.',
                'logo_url' => '/images/placeholders/coffelia-logo-demo.svg',
                'cover_url' => '/images/placeholders/coffelia-cover-demo.svg',
                'contact_whatsapp' => '+573000000000',
                'contact_email' => 'piloto-demo@coffelia.local',
                'contact_phone' => '+576010000000',
                'address' => 'Zona Gastronómica (Ubicación Demostrativa de Prueba)',
                'city' => 'Bogotá',
                'country' => 'CO',
                'currency' => 'COP',
                'theme_config' => [
                    'primary_color' => '#2C1810',
                    'accent_color' => '#C89666',
                    'background_color' => '#FAF7F2',
                    'card_color' => '#FFFFFF',
                    'font_family' => 'Outfit',
                ],
                'is_active' => true,
            ]
        );

        // 2. Categories
        $categoriesData = [
            [
                'slug' => 'cafes-especialidad',
                'name' => 'Cafés de Especialidad',
                'description' => 'Cafés filtrados y espressos con granos de origen colombiano tostados artesanalmente.',
                'icon' => 'coffee',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'slug' => 'bebidas-frias',
                'name' => 'Bebidas Frías',
                'description' => 'Extracciones en frío, lattes helados y tónicos refrescantes.',
                'icon' => 'glass-water',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'slug' => 'reposteria-artesanal',
                'name' => 'Repostería Artesanal',
                'description' => 'Tortas, cheesecakes y pastelería fina horneada diariamente.',
                'icon' => 'cake',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'slug' => 'panaderia-salada',
                'name' => 'Panadería & Salados',
                'description' => 'Croissants de mantequilla, focaccias y tostadas artesanales.',
                'icon' => 'bread-slice',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $catData) {
            $categories[$catData['slug']] = MenuCategory::updateOrCreate(
                [
                    'establishment_id' => $establishment->id,
                    'slug' => $catData['slug'],
                ],
                [
                    'name' => $catData['name'],
                    'description' => $catData['description'],
                    'icon' => $catData['icon'],
                    'sort_order' => $catData['sort_order'],
                    'is_active' => $catData['is_active'],
                ]
            );
        }

        // 3. Representative Products (Strictly Demonstrative Prices, Safe Local Placeholders, No Fake 3D Models)
        $placeholderPhoto = '/images/placeholders/food-placeholder.svg';

        $productsData = [
            // Cafés de Especialidad
            [
                'category_slug' => 'cafes-especialidad',
                'name' => 'Espresso Doble Especial',
                'slug' => 'espresso-doble-especial',
                'description' => 'Doble extracción de café variedad Geisha / Castillo, notas a frutos rojos y cacao amargo.',
                'price' => 6500.00,
                'photo_url' => $placeholderPhoto,
                'is_featured' => true,
                'is_available' => true,
                'dietary_tags' => ['vegano', 'sin-gluten'],
                'allergens' => [],
                'prep_time_minutes' => 4,
                'sort_order' => 1,
            ],
            [
                'category_slug' => 'cafes-especialidad',
                'name' => 'Cappuccino Tradicional',
                'slug' => 'cappuccino-tradicional',
                'description' => 'Espresso balanceado con leche texturizada sedosa y microespuma brillante.',
                'price' => 8500.00,
                'photo_url' => $placeholderPhoto,
                'is_featured' => true,
                'is_available' => true,
                'dietary_tags' => ['vegetariano'],
                'allergens' => ['lacteos'],
                'prep_time_minutes' => 5,
                'sort_order' => 2,
            ],
            [
                'category_slug' => 'cafes-especialidad',
                'name' => 'Filtrado V60 de Origen',
                'slug' => 'filtrado-v60-origen',
                'description' => 'Método por goteo que resalta acidez cítrica brillante y cuerpo sedoso. Café del Huila.',
                'price' => 9500.00,
                'photo_url' => $placeholderPhoto,
                'is_featured' => false,
                'is_available' => true,
                'dietary_tags' => ['vegano', 'sin-gluten'],
                'allergens' => [],
                'prep_time_minutes' => 7,
                'sort_order' => 3,
            ],

            // Bebidas Frías
            [
                'category_slug' => 'bebidas-frias',
                'name' => 'Cold Brew Nitro 16h',
                'slug' => 'cold-brew-nitro-16h',
                'description' => 'Extracción en frío durante 16 horas con infusión de nitrógeno para textura cremosa natural.',
                'price' => 11000.00,
                'photo_url' => $placeholderPhoto,
                'is_featured' => true,
                'is_available' => true,
                'dietary_tags' => ['vegano', 'sin-azucar'],
                'allergens' => [],
                'prep_time_minutes' => 3,
                'sort_order' => 1,
            ],
            [
                'category_slug' => 'bebidas-frias',
                'name' => 'Iced Matcha Latte',
                'slug' => 'iced-matcha-latte',
                'description' => 'Matcha ceremonial japonés batido con leche vegetal y jarabe ligero de vainilla natural.',
                'price' => 12500.00,
                'photo_url' => $placeholderPhoto,
                'is_featured' => false,
                'is_available' => true,
                'dietary_tags' => ['vegetariano'],
                'allergens' => ['lacteos'],
                'prep_time_minutes' => 5,
                'sort_order' => 2,
            ],

            // Repostería Artesanal
            [
                'category_slug' => 'reposteria-artesanal',
                'name' => 'Torta de Zanahoria & Nuez',
                'slug' => 'torta-zanahoria-nuez',
                'description' => 'Esponjosa torta especiada con nueces tostadas y glaseado ligero de queso crema.',
                'price' => 12000.00,
                'photo_url' => $placeholderPhoto,
                'is_featured' => true,
                'is_available' => true,
                'dietary_tags' => ['vegetariano'],
                'allergens' => ['gluten', 'lacteos', 'frutos-secos'],
                'prep_time_minutes' => 2,
                'sort_order' => 1,
            ],
            [
                'category_slug' => 'reposteria-artesanal',
                'name' => 'Cheesecake Frutos del Bosque',
                'slug' => 'cheesecake-frutos-bosque',
                'description' => 'Cheesecake horneado con base crocante de galleta y coulis casero de mora y arándanos.',
                'price' => 13500.00,
                'photo_url' => $placeholderPhoto,
                'is_featured' => false,
                'is_available' => true,
                'dietary_tags' => ['vegetariano'],
                'allergens' => ['gluten', 'lacteos'],
                'prep_time_minutes' => 2,
                'sort_order' => 2,
            ],

            // Panadería Salada
            [
                'category_slug' => 'panaderia-salada',
                'name' => 'Croissant de Mantequilla Artesanal',
                'slug' => 'croissant-mantequilla-artesanal',
                'description' => 'Clásico hojaldrado francés preparado con 100% mantequilla pura de granja.',
                'price' => 8000.00,
                'photo_url' => $placeholderPhoto,
                'is_featured' => true,
                'is_available' => true,
                'dietary_tags' => ['vegetariano'],
                'allergens' => ['gluten', 'lacteos'],
                'prep_time_minutes' => 3,
                'sort_order' => 1,
            ],
            [
                'category_slug' => 'panaderia-salada',
                'name' => 'Focaccia Romero & Tomates Cherry',
                'slug' => 'focaccia-romero-tomates-cherry',
                'description' => 'Masa madre de larga fermentación con aceite de oliva virgen extra y sal marina.',
                'price' => 10500.00,
                'photo_url' => $placeholderPhoto,
                'is_featured' => false,
                'is_available' => true,
                'dietary_tags' => ['vegano'],
                'allergens' => ['gluten'],
                'prep_time_minutes' => 4,
                'sort_order' => 2,
            ],
        ];

        foreach ($productsData as $prodData) {
            $cat = $categories[$prodData['category_slug']];

            MenuProduct::updateOrCreate(
                [
                    'establishment_id' => $establishment->id,
                    'slug' => $prodData['slug'],
                ],
                [
                    'category_id' => $cat->id,
                    'name' => $prodData['name'],
                    'dish_model_id' => null, // No inappropriate links to 3D dishes from Studio
                    'description' => $prodData['description'],
                    'price' => $prodData['price'],
                    'photo_url' => $prodData['photo_url'],
                    'is_featured' => $prodData['is_featured'],
                    'is_available' => $prodData['is_available'],
                    'has_3d' => false, // 2D photography only until real coffee 3D model is generated
                    'ar_enabled' => false,
                    'dietary_tags' => $prodData['dietary_tags'],
                    'allergens' => $prodData['allergens'],
                    'prep_time_minutes' => $prodData['prep_time_minutes'],
                    'sort_order' => $prodData['sort_order'],
                    'meta' => [
                        'slug' => $prodData['slug'],
                        'is_demo_price' => true,
                        'price_notice' => 'Precio demostrativo para pruebas técnicas. No corresponde a la carta oficial de Coffelia.',
                        'is_demo_catalog' => true,
                    ],
                ]
            );
        }
    }
}
