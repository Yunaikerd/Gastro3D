<?php

namespace Tests\Feature;

use App\Models\DishModel;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\MenuProduct;
use App\Services\TenantIsolationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class MultiTenantMenuIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected TenantIsolationService $isolationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolationService = app(TenantIsolationService::class);
    }

    /**
     * Test creating establishments and isolating their menus.
     */
    public function test_establishments_can_be_created_with_custom_branding_and_currency(): void
    {
        $establishment = Establishment::create([
            'name' => 'Cafetería Demo Uno',
            'slug' => 'demo-uno',
            'business_type' => 'cafeteria',
            'tagline' => 'Café de especialidad y arte',
            'currency' => 'COP',
            'theme_config' => [
                'primary_color' => '#8B4513',
                'secondary_color' => '#D2B48C',
                'mode' => 'dark',
            ],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('establishments', [
            'id' => $establishment->id,
            'slug' => 'demo-uno',
            'currency' => 'COP',
        ]);
        $this->assertEquals('#8B4513', $establishment->theme_config['primary_color']);
        $this->assertTrue($establishment->is_active);
    }

    /**
     * Test category slug uniqueness is enforced per establishment (same slug allowed across different establishments).
     */
    public function test_category_slug_uniqueness_is_scoped_to_establishment(): void
    {
        $estA = Establishment::create(['name' => 'Cafetería Alpha', 'slug' => 'alpha']);
        $estB = Establishment::create(['name' => 'Cafetería Beta', 'slug' => 'beta']);

        // Both establishments can have category slug 'cafes'
        $catA = MenuCategory::create([
            'establishment_id' => $estA->id,
            'name' => 'Cafés Alpha',
            'slug' => 'cafes',
        ]);

        $catB = MenuCategory::create([
            'establishment_id' => $estB->id,
            'name' => 'Cafés Beta',
            'slug' => 'cafes',
        ]);

        $this->assertNotEquals($catA->establishment_id, $catB->establishment_id);
        $this->assertEquals('cafes', $catA->slug);
        $this->assertEquals('cafes', $catB->slug);

        // Duplicate slug within the same establishment must throw QueryException
        $this->expectException(QueryException::class);
        MenuCategory::create([
            'establishment_id' => $estA->id,
            'name' => 'Otro Café Alpha',
            'slug' => 'cafes',
        ]);
    }

    /**
     * Test complete data isolation between two distinct establishments.
     */
    public function test_strict_isolation_between_two_establishments(): void
    {
        $estA = Establishment::create(['name' => 'Café Bogotá', 'slug' => 'cafe-bogota']);
        $estB = Establishment::create(['name' => 'Bistro Medellín', 'slug' => 'bistro-medellin']);

        $catA = MenuCategory::create([
            'establishment_id' => $estA->id,
            'name' => 'Bebidas Calientes A',
            'slug' => 'bebidas-calientes-a',
        ]);

        $catB = MenuCategory::create([
            'establishment_id' => $estB->id,
            'name' => 'Bebidas Calientes B',
            'slug' => 'bebidas-calientes-b',
        ]);

        $prodA = MenuProduct::create([
            'establishment_id' => $estA->id,
            'category_id' => $catA->id,
            'name' => 'Tinto Campesino A',
            'price' => 4500,
        ]);

        $prodB = MenuProduct::create([
            'establishment_id' => $estB->id,
            'category_id' => $catB->id,
            'name' => 'Cappuccino Tradicional B',
            'price' => 8000,
        ]);

        // Querying products for Establishment A must only return prodA
        $productsA = MenuProduct::forEstablishment($estA->id)->get();
        $this->assertCount(1, $productsA);
        $this->assertEquals($prodA->id, $productsA->first()->id);
        $this->assertFalse($productsA->contains('id', $prodB->id));

        // Querying products for Establishment B must only return prodB
        $productsB = MenuProduct::forEstablishment($estB->id)->get();
        $this->assertCount(1, $productsB);
        $this->assertEquals($prodB->id, $productsB->first()->id);
        $this->assertFalse($productsB->contains('id', $prodA->id));
    }

    /**
     * Test cross-tenant category contamination is intercepted at model lifecycle.
     */
    public function test_cross_tenant_category_association_is_strictly_forbidden(): void
    {
        $estA = Establishment::create(['name' => 'Café Norte', 'slug' => 'cafe-norte']);
        $estB = Establishment::create(['name' => 'Café Sur', 'slug' => 'cafe-sur']);

        // Category belongs to establishment B
        $catB = MenuCategory::create([
            'establishment_id' => $estB->id,
            'name' => 'Repostería Sur',
            'slug' => 'reposteria-sur',
        ]);

        // Attempting to create a product for Establishment A with Category of B must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Violación de aislamiento multi-tenant');

        MenuProduct::create([
            'establishment_id' => $estA->id,
            'category_id' => $catB->id, // Belongs to B!
            'name' => 'Intento de Producto Contaminado',
            'price' => 12000,
        ]);
    }

    /**
     * Test TenantIsolationService service methods explicitly detect foreign tenants.
     */
    public function test_tenant_isolation_service_validations(): void
    {
        $estA = Establishment::create(['name' => 'Establecimiento A', 'slug' => 'est-a']);
        $estB = Establishment::create(['name' => 'Establecimiento B', 'slug' => 'est-b']);

        $catA = MenuCategory::create([
            'establishment_id' => $estA->id,
            'name' => 'Categoría A',
            'slug' => 'cat-a',
        ]);

        // Validating catA against estA succeeds
        $validated = $this->isolationService->validateCategoryBelongsToEstablishment($catA, $estA);
        $this->assertEquals($catA->id, $validated->id);

        // Validating catA against estB must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->isolationService->validateCategoryBelongsToEstablishment($catA, $estB);
    }

    /**
     * Test optional linking to Studio 3D models without duplicating files.
     */
    public function test_product_can_link_to_studio_3d_dish_model_seamlessly(): void
    {
        $establishment = Establishment::create(['name' => 'Café 3D', 'slug' => 'cafe-3d']);
        $category = MenuCategory::create([
            'establishment_id' => $establishment->id,
            'name' => 'Panadería',
            'slug' => 'panaderia',
        ]);

        // Create a Studio 3D model
        $studioDish = DishModel::create([
            'title' => 'Croissant 3D Studio',
            'image_path' => 'uploads/croissant.jpg',
            'image_url' => 'http://localhost:8000/storage/uploads/croissant.jpg',
            'glb_path' => 'models/croissant_v1.glb',
            'glb_url' => 'http://localhost:8000/storage/models/croissant_v1.glb',
            'status' => 'success',
            'model_version' => 'v3.1-20260211',
        ]);

        // Create product linked to 3D model
        $product = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $category->id,
            'dish_model_id' => $studioDish->id,
            'name' => 'Croissant Artesanal',
            'price' => 7500,
            'ar_enabled' => true,
        ]);

        $this->assertTrue($product->has_3d, 'has_3d should automatically be true when dish_model_id is set');
        $this->assertTrue($product->ar_enabled);
        $this->assertEquals('http://localhost:8000/storage/models/croissant_v1.glb', $product->model_glb_url);

        // Check inverse relation on DishModel
        $this->assertTrue($studioDish->menuProducts->contains('id', $product->id));

        // When product has no 3D model (photo only)
        $product2D = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $category->id,
            'name' => 'Café Latte Tradicional',
            'price' => 6000,
            'photo_url' => 'http://localhost:8000/storage/uploads/latte.jpg',
        ]);

        $this->assertFalse($product2D->has_3d);
        $this->assertFalse($product2D->ar_enabled);
        $this->assertNull($product2D->model_glb_url);
    }

    /**
     * Test deleting an establishment cascades products and categories, but preserves Studio 3D models.
     */
    public function test_establishment_deletion_does_not_destroy_studio_3d_models(): void
    {
        $establishment = Establishment::create(['name' => 'Café Efímero', 'slug' => 'cafe-efimero']);
        $category = MenuCategory::create([
            'establishment_id' => $establishment->id,
            'name' => 'Bebidas',
            'slug' => 'bebidas',
        ]);

        $studioDish = DishModel::create([
            'title' => 'Plato Protegido',
            'image_path' => 'uploads/dish.jpg',
            'image_url' => 'http://localhost:8000/storage/uploads/dish.jpg',
            'glb_path' => 'models/dish_protected.glb',
            'status' => 'success',
        ]);

        $product = MenuProduct::create([
            'establishment_id' => $establishment->id,
            'category_id' => $category->id,
            'dish_model_id' => $studioDish->id,
            'name' => 'Plato del Día',
            'price' => 15000,
        ]);

        $productId = $product->id;
        $categoryId = $category->id;
        $studioDishId = $studioDish->id;

        // Delete establishment
        $establishment->delete();

        // MenuCategory and MenuProduct must be deleted via cascade
        $this->assertDatabaseMissing('establishments', ['id' => $establishment->id]);
        $this->assertDatabaseMissing('menu_categories', ['id' => $categoryId]);
        $this->assertDatabaseMissing('menu_products', ['id' => $productId]);

        // Studio DishModel MUST REMAIN INTACT!
        $this->assertDatabaseHas('dish_models', ['id' => $studioDishId]);
    }
}
