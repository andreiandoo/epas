<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class ShopMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'shop'],
            [
                'name' => [
                    'en' => 'Shop & Merchandise',
                    'ro' => 'Magazin & Merchandise',
                ],
                'description' => [
                    'en' => 'Sell merchandise, products, and add-ons alongside your event tickets. Includes product catalog, shopping cart, inventory management, and seamless checkout integration.',
                    'ro' => 'Vinde merchandise, produse si add-on-uri alaturi de biletele tale. Include catalog de produse, cos de cumparaturi, gestionare inventar si integrare checkout fara intreruperi.',
                ],
                'short_description' => [
                    'en' => 'E-commerce module for merchandise & products',
                    'ro' => 'Modul e-commerce pentru merchandise si produse',
                ],
                'price' => 20.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'category' => 'sales',
                'status' => 'active',
                'icon' => 'shopping-bag',
                'sort_order' => 50,
                'features' => [
                    'en' => [
                        'Product catalog with categories',
                        'Product variants (size, color, etc.)',
                        'Inventory/stock management',
                        'Product images gallery',
                        'Shopping cart integration',
                        'Checkout with tickets',
                        'Order management',
                        'Customer reviews & ratings',
                        'Product wishlists',
                        'Stock alerts for low inventory',
                        'Event-specific products',
                        'Shipping zones & rates',
                        'Digital product delivery',
                    ],
                    'ro' => [
                        'Catalog produse cu categorii',
                        'Variante produse (marime, culoare, etc.)',
                        'Gestionare inventar/stoc',
                        'Galerie imagini produse',
                        'Integrare cos cumparaturi',
                        'Checkout impreuna cu biletele',
                        'Gestionare comenzi',
                        'Recenzii & evaluari clienti',
                        'Liste de dorinte produse',
                        'Alerte stoc pentru inventar scazut',
                        'Produse specifice evenimentelor',
                        'Zone si tarife livrare',
                        'Livrare produse digitale',
                    ],
                ],
                'metadata' => [
                    'endpoints' => [
                        '/shop/products',
                        '/shop/categories',
                        '/shop/cart',
                        '/shop/orders',
                        '/shop/reviews',
                        '/shop/wishlist',
                    ],
                    'database_tables' => [
                        'shop_products',
                        'shop_categories',
                        'shop_product_variants',
                        'shop_orders',
                        'shop_order_items',
                        'shop_reviews',
                        'shop_wishlists',
                        'shop_shipping_zones',
                    ],
                    'integrations' => [
                        'checkout',
                        'orders',
                        'customers',
                        'inventory',
                    ],
                    'limits' => [
                        'max_products' => 500,
                        'max_categories' => 50,
                        'max_variants_per_product' => 20,
                    ],
                ],
            ]
        );
    }
}
