<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Documentation;

use App\Documentation\ThirdPartyApis\BaseApiDoc;

/**
 * Documentation for Amilon Voucher API
 * Based on actual API responses logged on 2025-08-07
 *
 * @see https://b2bstg-sso.amilon.eu
 */
class AmilonApiDoc extends BaseApiDoc
{
    public static function getApiVersion(): string
    {
        return 'v1';
    }

    public static function getLastVerified(): string
    {
        return '2025-08-07';
    }

    public static function getProviderName(): string
    {
        return 'amilon';
    }

    /**
     * OAuth 2.0 Token Endpoint - Password Grant
     *
     * @return array<string, mixed>
     */
    public static function authenticate(): array
    {
        return [
            'description' => 'Authenticate with Amilon OAuth 2.0 server',
            'endpoint' => 'POST /connect/token',
            'documentation_url' => 'https://b2bstg-sso.amilon.eu/docs',
            'parameters' => [
                'grant_type' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'OAuth grant type',
                    'default' => 'password',
                ],
                'client_id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Client ID (e.g., b2bwsuserwebapi)',
                ],
                'client_secret' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Client secret provided by Amilon',
                ],
                'username' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Username for password grant',
                ],
                'password' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Password for password grant',
                ],
                'scope' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'OAuth scopes',
                    'default' => 'b2b.webapi openid profile offline_access',
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'responses' => [
                '200' => [
                    'access_token' => 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjBBRDNBMDE0OTQ5MTdEOTIxOTEwMkQzODhFQ0Q2NjczRTc3QTVDMzlSUzI1NiIsInR5cCI6ImF0K2p3dCJ9.eyJpc3MiOiJodHRwczovL2IyYnN0Zy1zc28uYW1pbG9uLmV1IiwibmJmIjoxNzU0NTY1MDI4LCJpYXQiOjE3NTQ1NjUwMjgsImV4cCI6MTc1NDU2NTMyOCwiYXVkIjpbImIyYi53ZWJhcGkiLCJodHRwczovL2IyYnN0Zy1zc28uYW1pbG9uLmV1L3Jlc291cmNlcyJdLCJzY29wZSI6WyJiMmIud2ViYXBpIiwib3BlbmlkIiwicHJvZmlsZSIsIm9mZmxpbmVfYWNjZXNzIl0sImFtciI6WyJwd2QiXSwiY2xpZW50X2lkIjoiYjJid3N1c2Vyd2ViYXBpIiwic3ViIjoiOTg5YTZhNzEtNjg0MC1mMDExLWFhMDktMDA1MDU2ODQxY2IzIiwiYXV0aF90aW1lIjoxNzU0NTY1MDI4LCJpZHAiOiJsb2NhbCIsImxvY2FsZSI6ImZyLUZSIiwiYjJiX2N1c3RvbWVyX2lkIjoiYWFkN2U3YjQtNzFhNC00NDk4LWI0YzEtOGQwMmNhMWI0OTVkIiwiZW1haWwiOiJleGFtcGxlQGNvbXBhbnkuY29tIiwiZW1haWxfdmVyaWZpZWQiOnRydWUsInJvbGUiOiJXUyBVc2VyIn0.example_signature',
                    'expires_in' => 300,
                    'token_type' => 'Bearer',
                ],
                '400' => [
                    'error' => 'invalid_request',
                    'error_description' => 'The request is missing a required parameter',
                ],
                '401' => [
                    'error' => 'invalid_grant',
                    'error_description' => 'The username or password is incorrect',
                ],
            ],
            'example_call' => [
                'grant_type' => 'password',
                'client_id' => 'b2bwsuserwebapi',
                'client_secret' => 'your-client-secret',
                'username' => 'api-user@company.com',
                'password' => 'secure-password',
            ],
            'notes' => [
                'Token expires after 5 minutes (300 seconds)',
                'JWT contains user claims: email, role, b2b_customer_id, locale',
                'Use refresh token for long-lived sessions',
                'Cache token for 4 minutes to avoid expiration issues',
            ],
        ];
    }

    /**
     * List Product Categories
     */
    /**
     * @return array<string, mixed>
     */
    public static function listCategories(): array
    {
        return [
            'description' => 'Get list of retailer categories',
            'endpoint' => 'GET /retailers/categories',
            'documentation_url' => 'https://api.amilon.com/docs/categories',
            'parameters' => [],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
            ],
            'responses' => [
                '200' => [
                    [
                        'CategoryId' => 'aa15f17b-4c5f-ed11-aa01-005056841cb3',
                        'CategoryName' => 'Sport & Loisirs',
                    ],
                    [
                        'CategoryId' => '2d90e5ac-1560-ed11-aa01-005056841cb3',
                        'CategoryName' => 'Mode & Beauté',
                    ],
                    [
                        'CategoryId' => '72b7c5cc-1560-ed11-aa01-005056841cb3',
                        'CategoryName' => 'High-Tech',
                    ],
                ],
                '401' => [
                    'error' => 'Unauthorized',
                    'message' => 'Invalid or expired token',
                ],
            ],
            'notes' => [
                'Categories are used to filter retailers and products',
                'CategoryId is a GUID format',
            ],
        ];
    }

    /**
     * List Retailers/Merchants
     */
    /**
     * @return array<string, mixed>
     */
    public static function listRetailers(): array
    {
        return [
            'description' => 'Get list of retailers for a specific contract and culture',
            'endpoint' => 'GET /contracts/{contractId}/{culture}/retailers',
            'documentation_url' => 'https://api.amilon.com/docs/retailers',
            'parameters' => [
                'contractId' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Contract UUID (e.g., fac5336f-8a34-4a0a-b3a3-aed4790ae808)',
                ],
                'culture' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Culture code (e.g., pt-PT, fr-FR, it-IT)',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
            ],
            'responses' => [
                '200' => [
                    [
                        'RetailerId' => 'd7f5ff6b-0d83-4697-918e-03d3bc6485d3',
                        'Name' => 'Decathlon',
                        'Country' => 'Italy',
                        'CountryISOAlpha3' => 'ITA',
                        'Region' => null,
                        'County' => null,
                        'City' => null,
                        'Address' => null,
                        'ZipCode' => null,
                        'Phone' => null,
                        'Email' => null,
                        'ShortDescription' => 'Leader européen dans la création et distribution de produits sportifs.',
                        'LongDescription' => '<p>La <strong>Carte Cadeau</strong> est utilisable dans les magasins participants et en ligne.</p>',
                        'CodeValidityMonths' => 6,
                        'ImageUrl' => 'https://b2bstg-web.amilon.eu/b2bfiles/retailers/logo.jpg',
                        'RetailerShopShowDetails' => true,
                        'RetailerShopDetailsText' => 'Trouvez les magasins où utiliser votre carte cadeau.',
                        'IsCombinable' => true,
                        'IsFractionable' => true,
                        'ValiditySaleDays' => 180,
                        'Slug' => 'decathlon-ita',
                        'SaleViewTimeUnitId' => 30,
                        'RetailerSaleType' => 'FixedPrice',
                        'VatValue' => 0.0,
                        'VatValueName' => 'IVA FORA DE CAMPO Art. 6-quater',
                        'TermsAndConditions' => 'Conditions générales d\'utilisation.',
                    ],
                    [
                        'RetailerId' => '8fc2a7d0-3355-48b9-be70-0ff54c538f0a',
                        'Name' => 'Trony',
                        'Country' => 'Italy',
                        'CountryISOAlpha3' => 'ITA',
                        'ShortDescription' => 'La grande chaîne d\'électrodomestiques et d\'électronique.',
                        'LongDescription' => '<p>Gift Cards valables <strong>12 mois</strong>.</p>',
                        'CodeValidityMonths' => 12,
                        'ImageUrl' => 'https://b2bstg-web.amilon.eu/B2BFiles/retailers/logo.png',
                        'IsCombinable' => true,
                        'IsFractionable' => false,
                        'ValiditySaleDays' => 360,
                        'Slug' => 'trony-ita',
                    ],
                    [
                        'RetailerId' => '0cf9bfdf-ab4e-4226-b868-1886b097db02',
                        'Name' => 'Amazon',
                        'Country' => 'France',
                        'CountryISOAlpha3' => 'FRA',
                        'ShortDescription' => 'Amazon',
                        'CodeValidityMonths' => 60,
                        'ValiditySaleDays' => 1825,
                        'Slug' => 'amazon-fra',
                    ],
                ],
                '401' => [
                    'error' => 'Unauthorized',
                    'message' => 'Invalid or expired token',
                ],
                '404' => [
                    'error' => 'Not Found',
                    'message' => 'Contract not found',
                ],
            ],
            'notes' => [
                'Retailers vary by contract and culture',
                'IsCombinable: Can be combined with other payment methods',
                'IsFractionable: Can be used in multiple transactions',
                'ValiditySaleDays: How long the voucher is valid',
            ],
        ];
    }

    /**
     * List Products (Complete)
     */
    /**
     * @return array<string, mixed>
     */
    public static function listProducts(): array
    {
        return [
            'description' => 'Get complete list of products for a contract and culture',
            'endpoint' => 'GET /contracts/{contractId}/{culture}/products/complete',
            'documentation_url' => 'https://api.amilon.com/docs/products',
            'parameters' => [
                'contractId' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Contract UUID',
                ],
                'culture' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Culture code (e.g., pt-PT)',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
            ],
            'responses' => [
                '200' => [
                    [
                        'ProductCode' => '4b3cb6ad-eebb-49a9-973c-003821c39d1d',
                        'Currency' => 'Euro',
                        'ProductType' => 'Voucher',
                        'MerchantCode' => 'd7f5ff6b-0d83-4697-918e-03d3bc6485d3',
                        'MerchantCountry' => 'Italy',
                        'MerchantCountryISOAlpha3' => 'ITA',
                        'MerchantName' => 'Decathlon',
                        'MerchantImageUrl' => 'https://b2bstg-web.amilon.eu/b2bfiles/retailers/logo.jpg',
                        'MerchantShortDescription' => 'Leader européen dans les produits sportifs.',
                        'MerchantLongDescription' => '<p>Description détaillée HTML</p>',
                        'Name' => 'Decathlon - Gift Card 70 €',
                        'Price' => 70.0,
                        'ImageUrl' => 'https://b2bstg-web.amilon.eu/B2BFiles/products/logo.png',
                        'Active' => true,
                        'Visible' => true,
                        'Art100' => false,
                        'RebateTypeName' => 'Sconto fisso per Retailer',
                        'NetPrice' => 69.3,
                        'ShortDescription' => null,
                        'LongDescription' => null,
                        'MerchantImage100x50' => 'https://b2bstg-web.amilon.eu/B2BFiles/retailers/100x50.png',
                        'MerchantImage150x150' => 'https://b2bstg-web.amilon.eu/B2BFiles/retailers/150x150.png',
                        'MerchantImage180x70' => 'https://b2bstg-web.amilon.eu/B2BFiles/retailers/180x70.png',
                        'MerchantExtraShortDescription' => 'Pour le sport et les loisirs.',
                        'MerchantTermsAndConditions' => 'Conditions générales du marchand.',
                        'MerchantFacebookFanPage' => 'https://www.facebook.com/decathlon',
                        'MerchantCategory1' => 'FD208FE2-A945-44E9-B894-CBEC6FA58278',
                        'MerchantCategory2' => null,
                        'MerchantCategory3' => null,
                        'MerchantSlug' => 'decathlon-ita',
                        'Image136x86' => null,
                        'Image461x292' => null,
                        'Image200x200' => null,
                        'Image300x190' => null,
                        'Image560x292' => null,
                        'VatValue' => 0.0,
                        'VatValueName' => 'IVA FORA DE CAMPO Art. 6-quater',
                    ],
                    [
                        'ProductCode' => 'a14c9d26-b5a7-41a2-b23e-007ea91d6c55',
                        'Name' => 'Decathlon - Gift Card 865 €',
                        'Price' => 865.0,
                        'NetPrice' => 856.35,
                    ],
                    [
                        'ProductCode' => 'cbd520cb-e357-401a-9d1f-00cfc71896e5',
                        'Name' => 'Decathlon - Gift Card 965 €',
                        'Price' => 965.0,
                        'NetPrice' => 955.35,
                    ],
                ],
            ],
            'notes' => [
                'Products include complete merchant information',
                'Multiple image sizes available for display',
                'NetPrice is the cost after rebate',
                'Art100 indicates special product status',
            ],
        ];
    }

    /**
     * Create Order
     */
    /**
     * @return array<string, mixed>
     */
    public static function createOrder(): array
    {
        return [
            'description' => 'Create a new voucher order',
            'endpoint' => 'POST /Orders/create/{contractId}',
            'documentation_url' => 'https://api.amilon.com/docs/orders',
            'parameters' => [
                'contractId' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Contract UUID',
                ],
            ],
            'body' => [
                'externalOrderId' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'External order reference (e.g., ENGAGE-2025-{UUID})',
                ],
                'orderRows' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'Array of order items',
                    'items' => [
                        'productId' => 'Product code UUID',
                        'quantity' => 'Number of vouchers',
                    ],
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'Vouchers' => [
                        [
                            'VoucherLink' => 'https://stg-web.my-gate.eu/v?c=ABC123DEF456',
                            'ValidityStartDate' => '2025-08-07T00:00:00',
                            'ValidityEndDate' => '2026-02-07T00:00:00',
                            'ProductId' => '6af60678-6cdf-4909-a785-07fa421d9239',
                            'CardCode' => 'ABC123DEF456789',
                            'Pin' => 'PIN123456',
                            'RetailerId' => '875196f7-5e79-4e6d-8f8f-5e27f8fa2146',
                            'RetailerName' => 'IdeaShopping',
                            'RetailerCountry' => 'Italy',
                            'RetailerCountryISOAlpha3' => 'ITA',
                            'Name' => null,
                            'Surname' => null,
                            'Email' => null,
                            'Dedication' => null,
                            'OrderFrom' => null,
                            'OrderTo' => null,
                            'Amount' => 20.0,
                            'Deleted' => false,
                        ],
                    ],
                    'ExternalOrderId' => 'ENGAGE-2025-fab4a83e-4744-4fe6-9dfc-3741d3554b9d',
                    'OrderDate' => '2025-08-07T17:47:55.517',
                ],
                '400' => [
                    'error' => 'Invalid Request',
                    'message' => 'Product not found or invalid quantity',
                ],
                '402' => [
                    'error' => 'Insufficient Funds',
                    'message' => 'Not enough credit in contract',
                ],
            ],
            'example_call' => [
                'externalOrderId' => 'ENGAGE-2025-abc123def',
                'orderRows' => [
                    [
                        'productId' => '4b3cb6ad-eebb-49a9-973c-003821c39d1d',
                        'quantity' => 2,
                    ],
                ],
            ],
            'notes' => [
                'Order returns immediately with vouchers',
                'VoucherLink is the direct redemption URL',
                'CardCode and Pin are the voucher credentials',
                'ValidityEndDate shows voucher expiration',
                'Amount shows the voucher value',
                'Use ExternalOrderId for tracking',
            ],
        ];
    }

    /**
     * Get Order Status (Complete)
     */
    /**
     * @return array<string, mixed>
     */
    public static function getOrderStatus(): array
    {
        return [
            'description' => 'Get complete order information including vouchers',
            'endpoint' => 'GET /Orders/{externalOrderId}/complete',
            'documentation_url' => 'https://api.amilon.com/docs/orders',
            'parameters' => [
                'externalOrderId' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'External order ID provided during creation',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
            ],
            'responses' => [
                '200' => [
                    'orderId' => '12345678-90ab-cdef-1234-567890abcdef',
                    'externalOrderId' => 'ENGAGE-2025-abc123',
                    'status' => 'Completed',
                    'orderStatus' => 'Delivered',
                    'orderRows' => [
                        [
                            'productId' => '4b3cb6ad-eebb-49a9-973c-003821c39d1d',
                            'quantity' => 1,
                            'vouchers' => [
                                [
                                    'code' => 'VOUCHER-ABC-123-DEF',
                                    'pin' => '1234',
                                    'url' => 'https://vouchers.amilon.eu/redeem/VOUCHER-ABC-123-DEF',
                                    'expiresAt' => '2026-02-07T23:59:59Z',
                                    'value' => 70.0,
                                    'currency' => 'EUR',
                                    'status' => 'active',
                                ],
                            ],
                        ],
                    ],
                    'totalAmount' => 70.0,
                    'currency' => 'EUR',
                    'createdAt' => '2025-08-07T13:10:30Z',
                    'completedAt' => '2025-08-07T13:10:35Z',
                ],
                '404' => [
                    'error' => 'Not Found',
                    'message' => 'Order not found',
                ],
            ],
            'notes' => [
                'Poll this endpoint to check order completion',
                'Vouchers appear when status is Completed',
                'Each voucher has unique code and optional PIN',
                'Voucher URL can be shared with beneficiary',
            ],
        ];
    }

    /**
     * Get Contract Information
     */
    /**
     * @return array<string, mixed>
     */
    public static function getContract(): array
    {
        return [
            'description' => 'Get contract details and available balance',
            'endpoint' => 'GET /contracts/{contractId}',
            'documentation_url' => 'https://api.amilon.com/docs/contracts',
            'parameters' => [
                'contractId' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Contract UUID',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
            ],
            'responses' => [
                '200' => [
                    'contractId' => 'fac5336f-8a34-4a0a-b3a3-aed4790ae808',
                    'name' => 'Example Contract',
                    'status' => 'Active',
                    'currency' => 'EUR',
                    'availableBalance' => 10000.0,
                    'creditLimit' => 50000.0,
                    'usedCredit' => 40000.0,
                    'startDate' => '2024-01-01T00:00:00Z',
                    'endDate' => '2025-12-31T23:59:59Z',
                    'supportedCultures' => ['pt-PT', 'fr-FR', 'it-IT', 'es-ES'],
                    'defaultCulture' => 'pt-PT',
                ],
                '404' => [
                    'error' => 'Not Found',
                    'message' => 'Contract not found',
                ],
            ],
            'notes' => [
                'Contract defines credit limits and validity',
                'Supported cultures determine available products',
                'Balance is updated in real-time',
            ],
        ];
    }
}
