<?php

namespace Tests\Feature;

use App\Models\DishModel;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\MenuProduct;
use Database\Seeders\CoffeliaPilotSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EstablishmentMenuApiTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 1. Test full digital menu endpoint returns correct structure and HTTP 200.
     */
    public function test_menu_endpoint_returns_complete_menu_structure(): void
    {
        $establishment = Establishment::create([
            'name' => 'Bistro Gourmet',
            'slug' => 'bistro-gourmet',
            'business_type' => 'restaurante',
            'tagline' => 'Cocina de autor en 3D',
            'currency' => 'COP',
            'theme_config' => [
                'primary_color' => '#112233',
                'accent_color' => '#FF8800',
            ],
            'is_active' => true,
        ]);

        $category = MenuCategory::create([
            'establishment_id' => $establishment->id,
            'name' => 'Entradas',
            'slug' => 'entradas',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $category->id,
            'name' => 'Bruschetta Mediterránea',
            'price' => 18000,
            'photo_url' => 'https://storage.local/bruschetta.jpg',
            'is_available' => true,
            'sort_order' => 1,
            'meta' => ['slug' => 'bruschetta-mediterranea'],
        ]);

        $response = $this->getJson("/api/v1/establishments/{$establishment->slug}/menu");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $establishment->id,
                    'name' => 'Bistro Gourmet',
                    'slug' => 'bistro-gourmet',
                    'currency' => 'COP',
                    'theme_config' => [
                        'primary_color' => '#112233',
                        'accent_color' => '#FF8800',
                    ],
                    'summary' => [
                        'total_categories' => 1,
                        'total_products' => 1,
                        'total_3d_products' => 0,
                    ],
                    'categories' => [
                        [
                            'id' => $category->id,
                            'name' => 'Entradas',
                            'slug' => 'entradas',
                            'products' => [
                                [
                                    'id' => $product->id,
                                    'name' => 'Bruschetta Mediterránea',
                                    'slug' => 'bruschetta-mediterranea',
                                    'price' => 18000.0,
                                    'has_3d' => false,
                                    'ar_enabled' => false,
                                    'model_glb_url' => null,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * 2. Test categories and products are ordered by sort_order ascending.
     */
    public function test_menu_endpoint_orders_categories_and_products_by_sort_order(): void
    {
        $establishment = Establishment::create([
            'name' => 'Sort Test Café',
            'slug' => 'sort-test-cafe',
            'is_active' => true,
        ]);

        $cat3 = MenuCategory::create(['establishment_id' => $establishment->id, 'name' => 'Postres', 'slug' => 'postres', 'sort_order' => 3, 'is_active' => true]);
        $cat1 = MenuCategory::create(['establishment_id' => $establishment->id, 'name' => 'Bebidas', 'slug' => 'bebidas', 'sort_order' => 1, 'is_active' => true]);
        $cat2 = MenuCategory::create(['establishment_id' => $establishment->id, 'name' => 'Platos Fuertes', 'slug' => 'platos-fuertes', 'sort_order' => 2, 'is_active' => true]);

        $prodB = MenuProduct::create(['establishment_id' => $establishment->id, 'category_id' => $cat1->id, 'name' => 'Agua con Gas', 'price' => 4000, 'sort_order' => 2, 'is_available' => true]);
        $prodA = MenuProduct::create(['establishment_id' => $establishment->id, 'category_id' => $cat1->id, 'name' => 'Café Americano', 'price' => 5000, 'sort_order' => 1, 'is_available' => true]);

        $response = $this->getJson("/api/v1/establishments/{$establishment->slug}/menu");

        $response->assertStatus(200);
        $categories = $response->json('data.categories');

        $this->assertCount(3, $categories);
        $this->assertEquals('Bebidas', $categories[0]['name']);
        $this->assertEquals('Platos Fuertes', $categories[1]['name']);
        $this->assertEquals('Postres', $categories[2]['name']);

        // Check products inside Bebidas
        $drinks = $categories[0]['products'];
        $this->assertCount(2, $drinks);
        $this->assertEquals('Café Americano', $drinks[0]['name']);
        $this->assertEquals('Agua con Gas', $drinks[1]['name']);
    }

    /**
     * 3. Test inactive categories and unavailable products are filtered out.
     */
    public function test_menu_endpoint_filters_inactive_categories_and_unavailable_products(): void
    {
        $establishment = Establishment::create([
            'name' => 'Filter Test Café',
            'slug' => 'filter-test-cafe',
            'is_active' => true,
        ]);

        $activeCat = MenuCategory::create(['establishment_id' => $establishment->id, 'name' => 'Visible Cat', 'slug' => 'visible-cat', 'is_active' => true]);
        $inactiveCat = MenuCategory::create(['establishment_id' => $establishment->id, 'name' => 'Hidden Cat', 'slug' => 'hidden-cat', 'is_active' => false]);

        // Products in visible category
        $availableProd = MenuProduct::create(['establishment_id' => $establishment->id, 'category_id' => $activeCat->id, 'name' => 'Disponible', 'price' => 10000, 'is_available' => true]);
        $unavailableProd = MenuProduct::create(['establishment_id' => $establishment->id, 'category_id' => $activeCat->id, 'name' => 'Agotado', 'price' => 10000, 'is_available' => false]);

        // Product in hidden category
        MenuProduct::create(['establishment_id' => $establishment->id, 'category_id' => $inactiveCat->id, 'name' => 'Producto Oculto', 'price' => 15000, 'is_available' => true]);

        $response = $this->getJson("/api/v1/establishments/{$establishment->slug}/menu");

        $response->assertStatus(200);
        $categories = $response->json('data.categories');

        $this->assertCount(1, $categories);
        $this->assertEquals('Visible Cat', $categories[0]['name']);

        $products = $categories[0]['products'];
        $this->assertCount(1, $products);
        $this->assertEquals('Disponible', $products[0]['name']);
    }

    /**
     * 4. Test 404 for non-existent or suspended establishments.
     */
    public function test_menu_endpoint_returns_404_for_unknown_or_inactive_establishment(): void
    {
        // Unknown slug
        $this->getJson('/api/v1/establishments/non-existent-restaurant/menu')
            ->assertStatus(404)
            ->assertJson(['success' => false]);

        // Inactive establishment
        $suspended = Establishment::create([
            'name' => 'Suspended Café',
            'slug' => 'suspended-cafe',
            'is_active' => false,
        ]);

        $this->getJson("/api/v1/establishments/{$suspended->slug}/menu")
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    /**
     * 5. Test single product detail by slug and UUID.
     */
    public function test_product_detail_endpoint_resolves_by_slug_and_uuid(): void
    {
        $establishment = Establishment::create([
            'name' => 'Detail Test Bistro',
            'slug' => 'detail-test-bistro',
            'is_active' => true,
        ]);

        $category = MenuCategory::create([
            'establishment_id' => $establishment->id,
            'name' => 'Cafetería',
            'slug' => 'cafeteria',
            'is_active' => true,
        ]);

        $product = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $category->id,
            'name' => 'Mocha Chocolate',
            'price' => 9000,
            'is_available' => true,
            'meta' => ['slug' => 'mocha-chocolate'],
        ]);

        // Query by slug
        $responseSlug = $this->getJson("/api/v1/establishments/{$establishment->slug}/products/mocha-chocolate");
        $responseSlug->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => 'Mocha Chocolate',
                    'slug' => 'mocha-chocolate',
                    'price' => 9000.0,
                    'category' => [
                        'name' => 'Cafetería',
                        'slug' => 'cafeteria',
                    ],
                    'establishment' => [
                        'name' => 'Detail Test Bistro',
                        'slug' => 'detail-test-bistro',
                    ],
                ],
            ]);

        // Query by UUID
        $responseUuid = $this->getJson("/api/v1/establishments/{$establishment->slug}/products/{$product->id}");
        $responseUuid->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => 'Mocha Chocolate',
                ],
            ]);
    }

    /**
     * 6. Test product detail with and without 3D models.
     */
    public function test_product_detail_endpoint_exposes_3d_and_ar_metadata(): void
    {
        $establishment = Establishment::create([
            'name' => '3D Test Café',
            'slug' => '3d-test-cafe',
            'is_active' => true,
        ]);

        $category = MenuCategory::create([
            'establishment_id' => $establishment->id,
            'name' => 'Especiales 3D',
            'slug' => 'especiales-3d',
            'is_active' => true,
        ]);

        // Simulated dish model in Studio
        $dishModel = DishModel::create([
            'title' => 'Plato Prueba 3D',
            'image_path' => 'uploads/test_dish.jpg',
            'image_url' => 'http://localhost:8000/storage/uploads/test_dish.jpg',
            'status' => 'success',
            'glb_path' => 'models/test_dish.glb',
            'glb_url' => 'http://localhost:8000/storage/models/test_dish.glb',
            'thumbnail_url' => 'http://localhost:8000/storage/models/test_dish_thumb.webp',
        ]);

        $productWith3D = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $category->id,
            'dish_model_id' => $dishModel->id,
            'name' => 'Croissant 3D Pro',
            'price' => 12000,
            'is_available' => true,
            'ar_enabled' => true,
            'meta' => ['slug' => 'croissant-3d-pro'],
        ]);

        $productWithout3D = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $category->id,
            'dish_model_id' => null,
            'name' => 'Café Regular 2D',
            'price' => 5000,
            'is_available' => true,
            'meta' => ['slug' => 'cafe-regular-2d'],
        ]);

        // Verify product WITH 3D
        $res3D = $this->getJson("/api/v1/establishments/{$establishment->slug}/products/croissant-3d-pro");
        $res3D->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_3d' => true,
                    'ar_enabled' => true,
                    'model_3d' => [
                        'glb_url' => 'http://localhost:8000/storage/models/test_dish.glb',
                        'thumbnail_url' => 'http://localhost:8000/storage/models/test_dish_thumb.webp',
                    ],
                ],
            ]);

        // Verify product WITHOUT 3D
        $res2D = $this->getJson("/api/v1/establishments/{$establishment->slug}/products/cafe-regular-2d");
        $res2D->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_3d' => false,
                    'ar_enabled' => false,
                    'model_3d' => null,
                ],
            ]);
    }

    /**
     * 7. Test multi-tenant isolation: cannot query product from another establishment.
     */
    public function test_product_detail_prevents_cross_tenant_access(): void
    {
        $estA = Establishment::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'is_active' => true]);
        $estB = Establishment::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'is_active' => true]);

        $catA = MenuCategory::create(['establishment_id' => $estA->id, 'name' => 'Cat A', 'slug' => 'cat-a', 'is_active' => true]);
        $catB = MenuCategory::create(['establishment_id' => $estB->id, 'name' => 'Cat B', 'slug' => 'cat-b', 'is_active' => true]);

        $prodB = MenuProduct::create([
            'establishment_id' => $estB->id,
            'category_id' => $catB->id,
            'name' => 'Secreto de Tenant B',
            'price' => 50000,
            'is_available' => true,
            'meta' => ['slug' => 'secreto-tenant-b'],
        ]);

        // Attempt to request Tenant B's product under Tenant A's endpoint
        $this->getJson("/api/v1/establishments/{$estA->slug}/products/secreto-tenant-b")
            ->assertStatus(404)
            ->assertJson(['success' => false]);

        $this->getJson("/api/v1/establishments/{$estA->slug}/products/{$prodB->id}")
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    /**
     * 8. Test product detail returns 404 for unavailable products or inactive categories.
     */
    public function test_product_detail_returns_404_for_unavailable_product_or_inactive_category(): void
    {
        $establishment = Establishment::create(['name' => 'Status Check', 'slug' => 'status-check', 'is_active' => true]);
        $activeCat = MenuCategory::create(['establishment_id' => $establishment->id, 'name' => 'Active Cat', 'slug' => 'active-cat', 'is_active' => true]);
        $inactiveCat = MenuCategory::create(['establishment_id' => $establishment->id, 'name' => 'Inactive Cat', 'slug' => 'inactive-cat', 'is_active' => false]);

        // Unavailable product
        $soldOut = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $activeCat->id,
            'name' => 'Tarta Agotada',
            'price' => 12000,
            'is_available' => false,
            'meta' => ['slug' => 'tarta-agotada'],
        ]);

        $this->getJson("/api/v1/establishments/{$establishment->slug}/products/tarta-agotada")
            ->assertStatus(404);

        // Product in inactive category
        $hiddenCatProd = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $inactiveCat->id,
            'name' => 'Producto en Cat Oculta',
            'price' => 12000,
            'is_available' => true,
            'meta' => ['slug' => 'producto-cat-oculta'],
        ]);

        $this->getJson("/api/v1/establishments/{$establishment->slug}/products/producto-cat-oculta")
            ->assertStatus(404);
    }

    /**
     * 9. Test JSON responses do not leak private studio tokens, task IDs, or internal file paths.
     */
    public function test_json_responses_do_not_leak_internal_credentials_or_server_paths(): void
    {
        $establishment = Establishment::create(['name' => 'Security Audit', 'slug' => 'security-audit', 'is_active' => true]);
        $cat = MenuCategory::create(['establishment_id' => $establishment->id, 'name' => 'Cat Security', 'slug' => 'cat-sec', 'is_active' => true]);

        $dishModel = DishModel::create([
            'title' => 'Plato Seguro',
            'tripo_task_id' => 'secret-tripo-task-999888',
            'tripo_file_token' => 'super-secret-tripo-token-12345',
            'image_path' => 'private/server/storage/dish_upload_xyz.jpg',
            'image_url' => 'http://localhost:8000/storage/uploads/dish_upload_xyz.jpg',
            'glb_path' => 'private/server/storage/models/model_xyz.glb',
            'glb_url' => 'http://localhost:8000/storage/models/model_xyz.glb',
            'thumbnail_url' => 'http://localhost:8000/storage/models/model_xyz_thumb.webp',
            'status' => 'success',
            'meta' => ['internal_server_ip' => '10.0.0.99', 'tripo_credit_cost' => 40],
        ]);

        $product = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $cat->id,
            'dish_model_id' => $dishModel->id,
            'name' => 'Seguridad Pro',
            'price' => 25000,
            'is_available' => true,
            'meta' => ['slug' => 'seguridad-pro'],
        ]);

        $menuResponse = $this->getJson("/api/v1/establishments/{$establishment->slug}/menu");
        $menuContent = $menuResponse->getContent();

        $this->assertStringNotContainsString('secret-tripo-task-999888', $menuContent);
        $this->assertStringNotContainsString('super-secret-tripo-token-12345', $menuContent);
        $this->assertStringNotContainsString('private/server/storage', $menuContent);
        $this->assertStringNotContainsString('internal_server_ip', $menuContent);

        $detailResponse = $this->getJson("/api/v1/establishments/{$establishment->slug}/products/seguridad-pro");
        $detailContent = $detailResponse->getContent();

        $this->assertStringNotContainsString('secret-tripo-task-999888', $detailContent);
        $this->assertStringNotContainsString('super-secret-tripo-token-12345', $detailContent);
        $this->assertStringNotContainsString('private/server/storage', $detailContent);
        $this->assertStringNotContainsString('internal_server_ip', $detailContent);
    }

    /**
     * 10. Test CoffeliaPilotSeeder is idempotent, creates valid catalog with 2D photography only and demo prices.
     */
    public function test_coffelia_pilot_seeder_is_idempotent_and_creates_valid_catalog(): void
    {
        $seeder = new CoffeliaPilotSeeder();

        // 1st run
        $seeder->run();

        $est = Establishment::where('slug', 'coffelia')->first();
        $this->assertNotNull($est);
        $this->assertEquals('Coffelia Coffee Roasters', $est->name);
        $this->assertEquals('COP', $est->currency);
        $this->assertTrue($est->is_active);

        $catCountFirstRun = MenuCategory::where('establishment_id', $est->id)->count();
        $prodCountFirstRun = MenuProduct::where('establishment_id', $est->id)->count();

        $this->assertEquals(4, $catCountFirstRun);
        $this->assertEquals(9, $prodCountFirstRun);

        // 2nd run: Idempotency check
        $seeder->run();

        $catCountSecondRun = MenuCategory::where('establishment_id', $est->id)->count();
        $prodCountSecondRun = MenuProduct::where('establishment_id', $est->id)->count();

        $this->assertEquals(4, $catCountSecondRun, 'Seeder must not duplicate categories on repeated execution.');
        $this->assertEquals(9, $prodCountSecondRun, 'Seeder must not duplicate products on repeated execution.');

        // Verify products have NO fake 3D models linked and have demo price notice
        $coffeliaProducts = MenuProduct::where('establishment_id', $est->id)->get();
        foreach ($coffeliaProducts as $product) {
            $this->assertNull($product->dish_model_id, "Product [{$product->name}] must not link to inappropriate 3D models.");
            $this->assertFalse($product->has_3d);
            $this->assertFalse($product->ar_enabled);
            $this->assertNotEmpty($product->photo_url);
            $this->assertTrue($product->meta['is_demo_price'] ?? false, "Product [{$product->name}] must flag demo price.");
        }

        // Test HTTP endpoint against seeded Coffelia menu
        $response = $this->getJson('/api/v1/establishments/coffelia/menu');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'coffelia',
                    'name' => 'Coffelia Coffee Roasters',
                    'currency' => 'COP',
                    'summary' => [
                        'total_categories' => 4,
                        'total_products' => 9,
                        'total_3d_products' => 0,
                    ],
                ],
            ]);

        // Test HTTP endpoint for Cappuccino detail
        $cappuccinoResponse = $this->getJson('/api/v1/establishments/coffelia/products/cappuccino-tradicional');
        $cappuccinoResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Cappuccino Tradicional',
                    'slug' => 'cappuccino-tradicional',
                    'price' => 8500.0,
                    'has_3d' => false,
                    'ar_enabled' => false,
                    'model_3d' => null,
                ],
            ]);
    }

    /**
     * 11. Test slug stability upon renaming, collision avoidance, and cross-tenant scoped uniqueness.
     */
    public function test_product_slug_stability_and_collision_prevention(): void
    {
        $estA = Establishment::create(['name' => 'Slug Est A', 'slug' => 'slug-est-a', 'is_active' => true]);
        $estB = Establishment::create(['name' => 'Slug Est B', 'slug' => 'slug-est-b', 'is_active' => true]);

        $catA = MenuCategory::create(['establishment_id' => $estA->id, 'name' => 'Cat A', 'slug' => 'cat-a', 'is_active' => true]);
        $catB = MenuCategory::create(['establishment_id' => $estB->id, 'name' => 'Cat B', 'slug' => 'cat-b', 'is_active' => true]);

        // First product
        $prod1 = MenuProduct::create([
            'establishment_id' => $estA->id,
            'category_id' => $catA->id,
            'name' => 'Café Latte Especial',
            'price' => 7000,
            'is_available' => true,
        ]);
        $this->assertEquals('cafe-latte-especial', $prod1->slug);

        // Rename product name: Slug MUST remain stable!
        $prod1->name = 'Café Latte Especial Seleccionado';
        $prod1->save();
        $this->assertEquals('cafe-latte-especial', $prod1->fresh()->slug, 'Product slug must not change when dish name is modified.');

        // Second product in same establishment with same original name: Collision avoidance!
        $prod2 = MenuProduct::create([
            'establishment_id' => $estA->id,
            'category_id' => $catA->id,
            'name' => 'Café Latte Especial',
            'price' => 7500,
            'is_available' => true,
        ]);
        $this->assertEquals('cafe-latte-especial-2', $prod2->slug, 'Collision should automatically append counter suffix.');

        // Third product in DIFFERENT establishment with exact same name: Allowed!
        $prodB = MenuProduct::create([
            'establishment_id' => $estB->id,
            'category_id' => $catB->id,
            'name' => 'Café Latte Especial',
            'price' => 8000,
            'is_available' => true,
        ]);
        $this->assertEquals('cafe-latte-especial', $prodB->slug, 'Different establishments can share the same product slug.');
    }
}

