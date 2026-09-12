<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_a_signed_in_user_can_upload_a_listing_image(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/product-images', [
            'image' => UploadedFile::fake()->image('tomatoes.jpg', 800, 600),
        ]);

        $response->assertCreated()->assertJsonStructure(['path', 'url']);

        $path = $response->json('path');
        $this->assertStringStartsWith('product-images/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_stored_name_is_generated_rather_than_the_client_filename(): void
    {
        $user = User::factory()->create();

        $path = $this->actingAs($user, 'sanctum')->postJson('/api/product-images', [
            'image' => UploadedFile::fake()->image('../../evil name.png'),
        ])->assertCreated()->json('path');

        // Only our own uuid plus the validated extension survives.
        $this->assertMatchesRegularExpression(
            '#^product-images/[0-9a-f-]{36}\.png$#',
            $path
        );
    }

    public function test_uploading_requires_authentication(): void
    {
        $this->postJson('/api/product-images', [
            'image' => UploadedFile::fake()->image('x.jpg'),
        ])->assertUnauthorized();
    }

    public function test_a_non_image_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/product-images', [
                'image' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/product-images', [
                'image' => UploadedFile::fake()->image('huge.jpg')->size(5000),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_an_uploaded_image_can_be_fetched_without_a_token(): void
    {
        $user = User::factory()->create();
        $path = $this->actingAs($user, 'sanctum')->postJson('/api/product-images', [
            'image' => UploadedFile::fake()->image('leek.jpg'),
        ])->json('path');

        // An <img> tag sends no Authorization header, so this must be public.
        $this->getJson('/api/images/'.$path)->assertOk();
    }

    public function test_serving_refuses_paths_outside_the_upload_directory(): void
    {
        Storage::disk('public')->put('secret.txt', 'not yours');

        $this->get('/api/images/secret.txt')->assertNotFound();
        $this->get('/api/images/product-images/../secret.txt')->assertNotFound();
    }

    public function test_a_missing_image_is_a_404_rather_than_an_error(): void
    {
        $this->get('/api/images/product-images/nothing-here.jpg')->assertNotFound();
    }

    public function test_a_product_exposes_its_images_as_full_urls(): void
    {
        $product = Product::factory()->create([
            'images' => ['product-images/abc.jpg'],
        ]);

        $images = $this->getJson("/api/products/{$product->id}")->assertOk()->json('data.images');

        $this->assertCount(1, $images);
        $this->assertStringEndsWith('/api/images/product-images/abc.jpg', $images[0]);
    }

    public function test_a_product_without_images_returns_an_empty_list(): void
    {
        $product = Product::factory()->create(['images' => []]);

        $this->assertSame([], $this->getJson("/api/products/{$product->id}")->json('data.images'));
    }
}
