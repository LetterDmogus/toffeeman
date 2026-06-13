<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\IngredientCategory;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Position;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPositionSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@restaurant.com'],
            [
                'name' => 'Admin Restaurant',
                'password' => bcrypt('password'),
                'position_id' => null,
            ]
        );
        $admin->assignRole('superadmin');

        // Create tables
        $tables = [
            ['number' => 'T1', 'name' => 'Rose Garden Alcove', 'capacity' => 2, 'location' => 'indoor', 'status' => 'available', 'qr_code' => 'tbl-t1-rose'],
            ['number' => 'T2', 'name' => 'Royal Parlour', 'capacity' => 4, 'location' => 'indoor', 'status' => 'available', 'qr_code' => 'tbl-t2-royal'],
            ['number' => 'T3', 'name' => 'Chelsea Patio', 'capacity' => 4, 'location' => 'outdoor', 'status' => 'available', 'qr_code' => 'tbl-t3-chelsea'],
            ['number' => 'T4', 'name' => 'Victoria Balcony', 'capacity' => 2, 'location' => 'rooftop', 'status' => 'available', 'qr_code' => 'tbl-t4-victoria'],
            ['number' => 'T5', 'name' => 'The Cozy Nook', 'capacity' => 2, 'location' => 'indoor', 'status' => 'available', 'qr_code' => 'tbl-t5-nook'],
        ];

        foreach ($tables as $t) {
            Table::create($t);
        }

        // Create Categories
        $categoriesData = [
            ['name' => 'Scones', 'slug' => 'scones', 'description' => 'British traditional scones served with clotted cream'],
            ['name' => 'Cakes', 'slug' => 'cakes', 'description' => 'Light, layered sponge cakes and classics'],
            ['name' => 'Puddings', 'slug' => 'puddings', 'description' => 'Traditional warm, sweet British puddings'],
            ['name' => 'Tarts', 'slug' => 'tarts', 'description' => 'Crisp shortcrust pastry tarts and pastries'],
            ['name' => 'Tea & Drinks', 'slug' => 'tea-drinks', 'description' => 'Premium hot teas, coffee, and beverages'],
        ];

        $categoriesMap = [];
        foreach ($categoriesData as $cat) {
            $categoriesMap[$cat['name']] = Category::create($cat)->id;
        }

        // Create menu items with nano banana placeholder images
        $menuItems = [
            [
                'name' => 'Classic Warm Scone with Clotted Cream',
                'slug' => 'classic-scone-clotted-cream',
                'description' => 'A beautifully baked, traditional English scone served warm with rich Devonshire clotted cream and strawberry jam.',
                'price' => 45000.00,
                'category_name' => 'Scones',
                'status' => 'available',
                'image_url' => 'https://images.unsplash.com/photo-1587314168485-3236d6710814?auto=format&fit=crop&q=80&w=600', // Unsplash scone image
                'allergens' => ['dairy', 'gluten'],
                'calories' => 420,
                'preparation_time' => 5,
                'tags' => ['bestseller', 'signature'],
            ],
            [
                'name' => 'Sticky Toffee Pudding',
                'slug' => 'sticky-toffee-pudding',
                'description' => 'A moist sponge cake made with finely chopped dates, covered in a rich toffee sauce and served with warm custard.',
                'price' => 65000.00,
                'category_name' => 'Puddings',
                'status' => 'available',
                'image_url' => 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&q=80&w=600', // Unsplash dessert image
                'allergens' => ['dairy', 'gluten', 'eggs'],
                'calories' => 580,
                'preparation_time' => 8,
                'tags' => ['signature', 'warm'],
            ],
            [
                'name' => 'Victoria Sponge Cake Slice',
                'slug' => 'victoria-sponge-slice',
                'description' => 'Two layers of light sponge filled with raspberry jam and vanilla buttercream, lightly dusted with powdered sugar.',
                'price' => 55000.00,
                'category_name' => 'Cakes',
                'status' => 'available',
                'image_url' => 'https://images.unsplash.com/photo-1535141192574-5d4897c13636?auto=format&fit=crop&q=80&w=600', // Cake slice
                'allergens' => ['dairy', 'gluten', 'eggs'],
                'calories' => 390,
                'preparation_time' => 3,
                'tags' => ['classic'],
            ],
            [
                'name' => 'Bakeewell Tart Slice',
                'slug' => 'bakewell-tart-slice',
                'description' => 'A shortcrust pastry shell beneath layers of jam, frangipane (almond filling), topped with flaked almonds.',
                'price' => 48000.00,
                'category_name' => 'Tarts',
                'status' => 'available',
                'image_url' => 'https://images.unsplash.com/photo-1519869325930-281384150729?auto=format&fit=crop&q=80&w=600', // Tart
                'allergens' => ['nuts', 'dairy', 'gluten', 'eggs'],
                'calories' => 410,
                'preparation_time' => 3,
                'tags' => ['nuts'],
            ],
            [
                'name' => 'Imperial Earl Grey Tea',
                'slug' => 'imperial-earl-grey',
                'description' => 'Fragrant black tea scented with oil of bergamot, served in a fine china teapot with optional milk or lemon.',
                'price' => 35000.00,
                'category_name' => 'Tea & Drinks',
                'status' => 'available',
                'image_url' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&q=80&w=600', // Tea
                'allergens' => [],
                'calories' => 5,
                'preparation_time' => 4,
                'tags' => ['classic', 'beverage'],
            ],
            [
                'name' => 'Traditional English Breakfast Tea',
                'slug' => 'traditional-breakfast-tea',
                'description' => 'A robust and full-bodied blend of Assam, Ceylon, and Kenyan black teas. Perfectly refreshing.',
                'price' => 32000.00,
                'category_name' => 'Tea & Drinks',
                'status' => 'available',
                'image_url' => 'https://images.unsplash.com/photo-1594631252845-29fc4cc8cde9?auto=format&fit=crop&q=80&w=600', // Breakfast tea
                'allergens' => [],
                'calories' => 5,
                'preparation_time' => 4,
                'tags' => ['classic', 'beverage'],
            ],
        ];

        $createdMenuItems = [];
        foreach ($menuItems as $m) {
            $catName = $m['category_name'];
            unset($m['category_name']);
            $m['category_id'] = $categoriesMap[$catName];
            $createdMenuItems[$m['slug']] = MenuItem::create($m);
        }

        // Create Packages (Bundles)
        $royalAfternoonTea = Package::create([
            'name' => 'Royal Afternoon Tea Experience',
            'slug' => 'royal-afternoon-tea-experience',
            'description' => 'The ultimate British tradition: a choice of warm scone, a slice of Victoria Sponge, and a pot of Imperial Earl Grey Tea.',
            'price' => 120000.00, // Bundled discount price (individual is 45k + 55k + 35k = 135k)
            'status' => 'active',
            'image_url' => 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&q=80&w=600', // Afternoon tea spread
        ]);

        PackageItem::create([
            'package_id' => $royalAfternoonTea->id,
            'menu_item_id' => $createdMenuItems['classic-scone-clotted-cream']->id,
            'qty' => 1,
            'notes' => 'Served with strawberry jam and clotted cream',
        ]);
        PackageItem::create([
            'package_id' => $royalAfternoonTea->id,
            'menu_item_id' => $createdMenuItems['victoria-sponge-slice']->id,
            'qty' => 1,
            'notes' => 'Victoria sponge slice',
        ]);
        PackageItem::create([
            'package_id' => $royalAfternoonTea->id,
            'menu_item_id' => $createdMenuItems['imperial-earl-grey']->id,
            'qty' => 1,
            'notes' => 'A pot of Earl Grey',
        ]);

        $sweetTreatDuo = Package::create([
            'name' => 'Sweet Treat Duo',
            'slug' => 'sweet-treat-duo',
            'description' => 'A delightful pairing of our signature Sticky Toffee Pudding and a warm cup of English Breakfast Tea.',
            'price' => 85000.00, // Individual: 65k + 32k = 97k
            'status' => 'active',
            'image_url' => 'https://images.unsplash.com/photo-1551024601-bec78aea704b?auto=format&fit=crop&q=80&w=600', // Dessert and drink
        ]);

        PackageItem::create([
            'package_id' => $sweetTreatDuo->id,
            'menu_item_id' => $createdMenuItems['sticky-toffee-pudding']->id,
            'qty' => 1,
            'notes' => 'Served with warm custard',
        ]);
        PackageItem::create([
            'package_id' => $sweetTreatDuo->id,
            'menu_item_id' => $createdMenuItems['traditional-breakfast-tea']->id,
            'qty' => 1,
            'notes' => 'Served with milk on the side',
        ]);

        // Seed Ingredient Categories (Bahan Baku)
        $ingCategories = [
            ['name' => 'Bahan Kering', 'slug' => 'bahan-kering', 'description' => 'Tepung, gula, dan bahan kering lainnya'],
            ['name' => 'Produk Susu & Telur', 'slug' => 'produk-susu-telur', 'description' => 'Susu, mentega, keju, dan telur segar'],
            ['name' => 'Buah & Sayuran', 'slug' => 'buah-sayuran', 'description' => 'Buah-buahan segar dan sayur-sayuran dapur'],
            ['name' => 'Teh & Kopi', 'slug' => 'teh-kopi', 'description' => 'Daun teh premium, biji kopi, dan sirup'],
        ];

        $ingCatMap = [];
        foreach ($ingCategories as $ic) {
            $ingCatMap[$ic['name']] = IngredientCategory::create($ic)->id;
        }

        // Seed Ingredients (Bahan Baku)
        $ingredientsList = [
            [
                'name' => 'Tepung Terigu Segitiga Biru',
                'slug' => 'tepung-terigu-segitiga-biru',
                'sku' => 'ING-DRY-FLOUR-01',
                'ingredient_category_id' => $ingCatMap['Bahan Kering'],
                'qty' => 0.00, // calculated from batches
                'unit' => 'kg',
                'small_unit' => 'gram',
                'conversion_factor' => 1000.00,
                'min_qty' => 10.00,
                'price' => 12500.00,
                'storage_temperature' => 'Suhu Ruang (25°C)',
                'small_unit_qty' => 50000.00, // 50.000 gram
                'description' => 'Tepung terigu protein sedang untuk scone dan kue.',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Mentega Anchor Unsalted',
                'slug' => 'mentega-anchor-unsalted',
                'sku' => 'ING-DAI-BUTTER-01',
                'ingredient_category_id' => $ingCatMap['Produk Susu & Telur'],
                'qty' => 0.00, // calculated from batches
                'unit' => 'kg',
                'small_unit' => 'gram',
                'conversion_factor' => 1000.00,
                'min_qty' => 5.00,
                'price' => 140000.00,
                'storage_temperature' => 'Dingin (4°C)',
                'small_unit_qty' => 15500.00,
                'description' => 'Mentega premium import dari Selandia Baru.',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Strawberry Lokal Fresh',
                'slug' => 'strawberry-lokal-fresh',
                'sku' => 'ING-FRU-STRAW-01',
                'ingredient_category_id' => $ingCatMap['Buah & Sayuran'],
                'qty' => 0.00, // calculated from batches
                'unit' => 'kg',
                'small_unit' => 'gram',
                'conversion_factor' => 1000.00,
                'min_qty' => 3.00,
                'price' => 45000.00,
                'storage_temperature' => 'Dingin (4°C)',
                'small_unit_qty' => 25000.00,
                'description' => 'Strawberry segar untuk jam scone dan garnish kue.',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Imperial Earl Grey Tea Leaves',
                'slug' => 'imperial-earl-grey-tea-leaves',
                'sku' => 'ING-TEA-EG-01',
                'ingredient_category_id' => $ingCatMap['Teh & Kopi'],
                'qty' => 0.00, // calculated from batches
                'unit' => 'pack',
                'small_unit' => 'pcs',
                'conversion_factor' => 1.00,
                'min_qty' => 2.00,
                'price' => 110000.00,
                'storage_temperature' => 'Kering & Sejuk',
                'small_unit_qty' => 8.00,
                'description' => 'Daun teh Earl Grey premium impor.',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Gula Pasir Gulaku',
                'slug' => 'gula-pasir-gulaku',
                'sku' => 'ING-DRY-SUGAR-01',
                'ingredient_category_id' => $ingCatMap['Bahan Kering'],
                'qty' => 0.00, // calculated from batches
                'unit' => 'kg',
                'small_unit' => 'gram',
                'conversion_factor' => 1000.00,
                'min_qty' => 5.00,
                'price' => 16000.00,
                'storage_temperature' => 'Suhu Ruang',
                'small_unit_qty' => 0.00,
                'description' => 'Gula pasir kristal putih murni.',
                'created_by' => $admin->id,
            ],
        ];

        $createdIngredients = [];
        foreach ($ingredientsList as $ing) {
            $createdIngredients[$ing['slug']] = Ingredient::create($ing);
        }

        // Seed Ingredient Batches
        IngredientBatch::create([
            'ingredient_id' => $createdIngredients['tepung-terigu-segitiga-biru']->id,
            'batch_number' => 'BCH-FLR-001',
            'qty' => 30.00,
            'expiration_date' => '2026-12-31',
            'created_by' => $admin->id,
        ]);
        IngredientBatch::create([
            'ingredient_id' => $createdIngredients['tepung-terigu-segitiga-biru']->id,
            'batch_number' => 'BCH-FLR-002',
            'qty' => 20.00,
            'expiration_date' => '2027-02-28',
            'created_by' => $admin->id,
        ]);
        IngredientBatch::create([
            'ingredient_id' => $createdIngredients['mentega-anchor-unsalted']->id,
            'batch_number' => 'BCH-BTR-092',
            'qty' => 15.50,
            'expiration_date' => '2026-09-15',
            'created_by' => $admin->id,
        ]);
        IngredientBatch::create([
            'ingredient_id' => $createdIngredients['strawberry-lokal-fresh']->id,
            'batch_number' => 'BCH-STR-010',
            'qty' => 2.50,
            'expiration_date' => '2026-06-10',
            'created_by' => $admin->id,
        ]);
        IngredientBatch::create([
            'ingredient_id' => $createdIngredients['imperial-earl-grey-tea-leaves']->id,
            'batch_number' => 'BCH-TEA-992',
            'qty' => 8.00,
            'expiration_date' => '2027-06-01',
            'created_by' => $admin->id,
        ]);

        // Recalculate quantities after seeding batches (since WithoutModelEvents is used)
        $ingredients = Ingredient::all();
        foreach ($ingredients as $ing) {
            $ing->recalculateQty();
        }

        // Seed Inventory Categories (Barang / Peralatan)
        $invCategories = [
            ['name' => 'Peralatan Makan', 'slug' => 'peralatan-makan', 'description' => 'Piring, cangkir, sendok, garpu, dll.'],
            ['name' => 'Peralatan POS / Elektronik', 'slug' => 'peralatan-pos-elektronik', 'description' => 'Tablet kasir, printer thermal, scanner.'],
            ['name' => 'Perlengkapan Kebersihan', 'slug' => 'perlengkapan-kebersihan', 'description' => 'Sapu, kain pel, cairan pembersih.'],
        ];

        $invCatMap = [];
        foreach ($invCategories as $ic) {
            $invCatMap[$ic['name']] = InventoryCategory::create($ic)->id;
        }

        // Seed Inventory Items (Barang / Peralatan)
        $invItems = [
            [
                'name' => 'Piring Keramik Putih',
                'slug' => 'piring-keramik-putih',
                'sku' => 'INV-PLT-001',
                'inventory_category_id' => $invCatMap['Peralatan Makan'],
                'qty_good' => 45.00,
                'qty_fair' => 5.00,
                'qty_damaged' => 0.00,
                'unit' => 'pcs',
                'min_qty' => 10.00,
                'price' => 25000.00,
                'purchase_date' => '2026-01-10',
                'storage_location' => 'Rak Dapur Utama',
                'description' => 'Piring keramik saji diameter 20cm.',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Cangkir Teh Keramik',
                'slug' => 'cangkir-teh-keramik',
                'sku' => 'INV-CUP-001',
                'inventory_category_id' => $invCatMap['Peralatan Makan'],
                'qty_good' => 25.00,
                'qty_fair' => 3.00,
                'qty_damaged' => 2.00,
                'unit' => 'pcs',
                'min_qty' => 15.00,
                'price' => 18000.00,
                'purchase_date' => '2026-02-15',
                'storage_location' => 'Lemari Depan Kasir',
                'description' => 'Cangkir teh bermotif bunga ala British.',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Tablet POS Samsung A7',
                'slug' => 'tablet-pos-samsung-a7',
                'sku' => 'INV-POS-TBL-01',
                'inventory_category_id' => $invCatMap['Peralatan POS / Elektronik'],
                'qty_good' => 2.00,
                'qty_fair' => 0.00,
                'qty_damaged' => 0.00,
                'unit' => 'unit',
                'min_qty' => 1.00,
                'price' => 2400000.00,
                'purchase_date' => '2026-03-01',
                'storage_location' => 'Meja Kasir',
                'description' => 'Tablet untuk aplikasi POS Kasir.',
                'created_by' => $admin->id,
            ],
        ];

        foreach ($invItems as $ii) {
            InventoryItem::create($ii);
        }

        // Seed Employees (Karyawan)
        $employees = [
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@restaurant.com',
                'phone' => '081234567890',
                'password' => bcrypt('password'),
                'salary' => 4500000.00,
                'position_slug' => 'cashier',
                'role' => 'admin',
                'status' => 'active',
            ],
            [
                'name' => 'Siti Aminah',
                'email' => 'siti@restaurant.com',
                'phone' => '082345678901',
                'password' => bcrypt('password'),
                'salary' => 6000000.00,
                'position_slug' => 'chef',
                'role' => 'admin',
                'status' => 'active',
            ],
            [
                'name' => 'Andi Wijaya',
                'email' => 'andi@restaurant.com',
                'phone' => '083456789012',
                'password' => bcrypt('password'),
                'salary' => 3800000.00,
                'position_slug' => 'waiter',
                'role' => 'user',
                'status' => 'active',
            ],
            [
                'name' => 'Eko Prasetyo',
                'email' => 'eko@restaurant.com',
                'phone' => '084567890123',
                'password' => bcrypt('password'),
                'salary' => 7500000.00,
                'position_slug' => 'manager',
                'role' => 'admin',
                'status' => 'active',
            ],
        ];

        foreach ($employees as $empData) {
            $position = Position::where('slug', $empData['position_slug'])->first();

            $user = User::create([
                'name' => $empData['name'],
                'email' => $empData['email'],
                'phone' => $empData['phone'],
                'password' => $empData['password'],
                'status' => $empData['status'],
                'position_id' => $position ? $position->id : null,
            ]);

            $user->assignRole($empData['role']);

            Employee::create([
                'user_id' => $user->id,
                'salary' => $empData['salary'],
                'status' => $empData['status'],
                'hired_at' => now(),
            ]);
        }

        $this->call([
            OrderSampleSeeder::class,
            TransactionSampleSeeder::class,
        ]);

        // Seed App Settings for Restaurant location & IP
        AppSetting::firstOrCreate(
            ['id' => 1],
            [
                'restaurant_ip' => '127.0.0.1',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'radius_meters' => 100,
                'website_name' => 'Toffee Manor',
                'address' => 'Jl. M.H. Thamrin No. 1, Jakarta Pusat',
                'contact' => '+62 812-3456-7890',
            ]
        );
    }
}
