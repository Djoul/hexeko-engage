<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Documentation;

use App\Documentation\ThirdPartyApis\BaseApiDoc;

/**
 * Documentation for Stripe Payment API
 * Based on Stripe API version 2023-10-16
 *
 * @see https://docs.stripe.com/api
 */
class StripeApiDoc extends BaseApiDoc
{
    /**
     * Override parent to look in the correct location for Stripe
     *
     * @return array<string, mixed>
     */
    protected static function loadResponse(string $file): array
    {
        $path = app_path("Integrations/Payments/Stripe/Documentation/responses/{$file}");

        if (! file_exists($path)) {
            // Return empty array to use default response
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function getApiVersion(): string
    {
        return '2023-10-16';
    }

    public static function getLastVerified(): string
    {
        return '2025-08-08';
    }

    public static function getProviderName(): string
    {
        return 'stripe';
    }

    /**
     * Create a Payment Intent
     *
     * @see https://docs.stripe.com/api/payment_intents/create
     *
     * @return array<string, mixed>
     */
    public static function createPaymentIntent(): array
    {
        return [
            'description' => 'Créer une intention de paiement',
            'endpoint' => 'POST /v1/payment_intents',
            'documentation_url' => 'https://docs.stripe.com/api/payment_intents/create',
            'parameters' => [
                'amount' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Montant en centimes (ex: 1000 pour 10.00 EUR)',
                ],
                'currency' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Code devise ISO (eur, usd, etc.)',
                    'default' => 'eur',
                ],
                'automatic_payment_methods' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Configuration des méthodes de paiement automatiques',
                    'default' => ['enabled' => true],
                ],
                'metadata' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Métadonnées personnalisées (max 50 clés)',
                ],
                'description' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Description du paiement (visible dans le dashboard)',
                ],
                'statement_descriptor' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Libellé sur le relevé bancaire (max 22 caractères)',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {STRIPE_SECRET_KEY}',
                'Stripe-Version' => '2023-10-16',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'responses' => [
                '200' => self::loadResponse('payment-intent-create-success.json') !== [] ? self::loadResponse('payment-intent-create-success.json') : [
                    'id' => 'pi_3MtwBwLkdIwHu7ix28a3tqPa',
                    'object' => 'payment_intent',
                    'amount' => 2000,
                    'amount_capturable' => 0,
                    'amount_details' => [
                        'tip' => [],
                    ],
                    'amount_received' => 0,
                    'application' => null,
                    'application_fee_amount' => null,
                    'automatic_payment_methods' => [
                        'enabled' => true,
                    ],
                    'canceled_at' => null,
                    'cancellation_reason' => null,
                    'capture_method' => 'automatic',
                    'client_secret' => 'pi_3MtwBwLkdIwHu7ix28a3tqPa_secret_oMbz4JfCvjdxdPZ4bj31A2wt2',
                    'confirmation_method' => 'automatic',
                    'created' => 1680800504,
                    'currency' => 'eur',
                    'customer' => null,
                    'description' => null,
                    'invoice' => null,
                    'last_payment_error' => null,
                    'latest_charge' => null,
                    'livemode' => false,
                    'metadata' => [],
                    'next_action' => null,
                    'on_behalf_of' => null,
                    'payment_method' => null,
                    'payment_method_options' => [],
                    'payment_method_types' => ['card', 'sepa_debit'],
                    'processing' => null,
                    'receipt_email' => null,
                    'review' => null,
                    'setup_future_usage' => null,
                    'shipping' => null,
                    'source' => null,
                    'statement_descriptor' => null,
                    'statement_descriptor_suffix' => null,
                    'status' => 'requires_payment_method',
                    'transfer_data' => null,
                    'transfer_group' => null,
                ],
                '400' => self::loadResponse('payment-intent-create-error-invalid.json') !== [] ? self::loadResponse('payment-intent-create-error-invalid.json') : [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'Invalid amount parameter',
                        'param' => 'amount',
                    ],
                ],
                '402' => self::loadResponse('payment-intent-create-error-card-declined.json') !== [] ? self::loadResponse('payment-intent-create-error-card-declined.json') : [
                    'error' => [
                        'type' => 'card_error',
                        'code' => 'card_declined',
                        'decline_code' => 'generic_decline',
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
            'example_call' => [
                'amount' => 2000,
                'currency' => 'eur',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'order_id' => '12345',
                    'user_id' => 'usr_123',
                ],
            ],
            'notes' => [
                'Le montant est toujours en centimes (100 = 1.00 EUR)',
                'Le client_secret est utilisé côté frontend avec Stripe.js',
                'Un webhook payment_intent.succeeded est envoyé après le paiement',
                'Les métadonnées sont limitées à 50 paires clé-valeur',
                'Maximum 500 caractères par valeur de métadonnée',
            ],
        ];
    }

    /**
     * Retrieve a Payment Intent
     *
     * @see https://docs.stripe.com/api/payment_intents/retrieve
     */
    /**
     * @return array<string, mixed>
     */
    public static function retrievePaymentIntent(): array
    {
        return [
            'description' => 'Récupérer une intention de paiement existante',
            'endpoint' => 'GET /v1/payment_intents/{id}',
            'documentation_url' => 'https://docs.stripe.com/api/payment_intents/retrieve',
            'parameters' => [
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Identifiant du Payment Intent (pi_xxx)',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {STRIPE_SECRET_KEY}',
                'Stripe-Version' => '2023-10-16',
            ],
            'responses' => [
                '200' => self::loadResponse('payment-intent-retrieve-success.json'),
                '404' => [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'No such payment_intent: pi_xxx',
                    ],
                ],
            ],
            'example_call' => [
                'id' => 'pi_3MtwBwLkdIwHu7ix28a3tqPa',
            ],
            'notes' => [
                'L\'ID commence toujours par "pi_"',
                'Inclut l\'historique des tentatives de paiement',
            ],
        ];
    }

    /**
     * Confirm a Payment Intent
     *
     * @see https://docs.stripe.com/api/payment_intents/confirm
     */
    /**
     * @return array<string, mixed>
     */
    public static function confirmPaymentIntent(): array
    {
        return [
            'description' => 'Confirmer une intention de paiement',
            'endpoint' => 'POST /v1/payment_intents/{id}/confirm',
            'documentation_url' => 'https://docs.stripe.com/api/payment_intents/confirm',
            'parameters' => [
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Identifiant du Payment Intent',
                ],
                'payment_method' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'ID de la méthode de paiement (pm_xxx)',
                ],
                'return_url' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'URL de retour après authentification 3D Secure',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {STRIPE_SECRET_KEY}',
                'Stripe-Version' => '2023-10-16',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'responses' => [
                '200' => self::loadResponse('payment-intent-confirm-success.json'),
                '400' => [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'This PaymentIntent cannot be confirmed',
                    ],
                ],
            ],
            'example_call' => [
                'payment_method' => 'pm_card_visa',
                'return_url' => 'https://example.com/return',
            ],
            'notes' => [
                'Peut déclencher une authentification 3D Secure',
                'Status passe à "processing" puis "succeeded"',
            ],
        ];
    }

    /**
     * Create a Checkout Session
     *
     * @see https://docs.stripe.com/api/checkout/sessions/create
     */
    /**
     * @return array<string, mixed>
     */
    public static function createCheckoutSession(): array
    {
        return [
            'description' => 'Créer une session de paiement Checkout',
            'endpoint' => 'POST /v1/checkout/sessions',
            'documentation_url' => 'https://docs.stripe.com/api/checkout/sessions/create',
            'parameters' => [
                'payment_method_types' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'Types de méthodes de paiement acceptés',
                    'default' => ['card'],
                ],
                'line_items' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'Articles à acheter',
                ],
                'mode' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Mode de paiement (payment, setup, subscription)',
                    'default' => 'payment',
                ],
                'success_url' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'URL de redirection après succès',
                ],
                'cancel_url' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'URL de redirection après annulation',
                ],
                'expires_at' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Timestamp Unix d\'expiration (max 24h)',
                ],
                'metadata' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Métadonnées personnalisées',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {STRIPE_SECRET_KEY}',
                'Stripe-Version' => '2023-10-16',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'responses' => [
                '200' => self::loadResponse('checkout-session-create-success.json') !== [] ? self::loadResponse('checkout-session-create-success.json') : [
                    'id' => 'cs_test_a1b2c3d4e5f6g7h8i9j0',
                    'object' => 'checkout.session',
                    'after_expiration' => null,
                    'allow_promotion_codes' => null,
                    'amount_subtotal' => 2000,
                    'amount_total' => 2000,
                    'automatic_tax' => [
                        'enabled' => false,
                        'status' => null,
                    ],
                    'billing_address_collection' => null,
                    'cancel_url' => 'https://example.com/cancel',
                    'client_reference_id' => null,
                    'consent' => null,
                    'consent_collection' => null,
                    'created' => 1680800504,
                    'currency' => 'eur',
                    'customer' => null,
                    'customer_creation' => 'if_required',
                    'customer_details' => null,
                    'customer_email' => null,
                    'expires_at' => 1680886904,
                    'invoice' => null,
                    'invoice_creation' => null,
                    'livemode' => false,
                    'locale' => null,
                    'metadata' => [
                        'user_id' => 'usr_123',
                        'credit_type' => 'standard',
                        'credit_amount' => '20',
                    ],
                    'mode' => 'payment',
                    'payment_intent' => 'pi_3MtwBwLkdIwHu7ix28a3tqPa',
                    'payment_link' => null,
                    'payment_method_collection' => 'always',
                    'payment_method_options' => [],
                    'payment_method_types' => ['card'],
                    'payment_status' => 'unpaid',
                    'phone_number_collection' => [
                        'enabled' => false,
                    ],
                    'recovered_from' => null,
                    'setup_intent' => null,
                    'shipping' => null,
                    'shipping_address_collection' => null,
                    'shipping_options' => [],
                    'shipping_rate' => null,
                    'status' => 'open',
                    'submit_type' => null,
                    'subscription' => null,
                    'success_url' => 'https://example.com/success?session_id={CHECKOUT_SESSION_ID}',
                    'total_details' => [
                        'amount_discount' => 0,
                        'amount_shipping' => 0,
                        'amount_tax' => 0,
                    ],
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_a1b2c3d4e5f6g7h8i9j0',
                ],
                '400' => self::loadResponse('checkout-session-create-error.json') !== [] ? self::loadResponse('checkout-session-create-error.json') : [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'Invalid line_items',
                    ],
                ],
            ],
            'example_call' => [
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => [
                                'name' => 'Crédit Premium',
                            ],
                            'unit_amount' => 2000,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => 'https://example.com/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'https://example.com/cancel',
                'expires_at' => time() + (30 * 60),
                'metadata' => [
                    'user_id' => 'usr_123',
                    'credit_type' => 'premium',
                    'credit_amount' => '20',
                ],
            ],
            'notes' => [
                'La session expire par défaut après 24 heures',
                'L\'URL retournée redirige vers la page de paiement Stripe',
                '{CHECKOUT_SESSION_ID} est automatiquement remplacé dans success_url',
                'Un webhook checkout.session.completed est envoyé après paiement',
            ],
        ];
    }

    /**
     * Retrieve a Checkout Session
     *
     * @see https://docs.stripe.com/api/checkout/sessions/retrieve
     */
    /**
     * @return array<string, mixed>
     */
    public static function retrieveCheckoutSession(): array
    {
        return [
            'description' => 'Récupérer une session Checkout existante',
            'endpoint' => 'GET /v1/checkout/sessions/{id}',
            'documentation_url' => 'https://docs.stripe.com/api/checkout/sessions/retrieve',
            'parameters' => [
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Identifiant de la session (cs_xxx)',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {STRIPE_SECRET_KEY}',
                'Stripe-Version' => '2023-10-16',
            ],
            'responses' => [
                '200' => self::loadResponse('checkout-session-retrieve-success.json'),
                '404' => [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'No such checkout session: cs_xxx',
                    ],
                ],
            ],
            'example_call' => [
                'id' => 'cs_test_a1b2c3d4e5f6g7h8i9j0',
            ],
            'notes' => [
                'L\'ID commence toujours par "cs_"',
                'Contient le payment_intent associé si le paiement est effectué',
            ],
        ];
    }

    /**
     * Create a Customer
     *
     * @see https://docs.stripe.com/api/customers/create
     */
    /**
     * @return array<string, mixed>
     */
    public static function createCustomer(): array
    {
        return [
            'description' => 'Créer un client dans Stripe',
            'endpoint' => 'POST /v1/customers',
            'documentation_url' => 'https://docs.stripe.com/api/customers/create',
            'parameters' => [
                'email' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Email du client',
                ],
                'name' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Nom complet du client',
                ],
                'description' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Description arbitraire',
                ],
                'metadata' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Métadonnées personnalisées',
                ],
                'phone' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Numéro de téléphone',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {STRIPE_SECRET_KEY}',
                'Stripe-Version' => '2023-10-16',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'responses' => [
                '200' => self::loadResponse('customer-create-success.json') !== [] ? self::loadResponse('customer-create-success.json') : [
                    'id' => 'cus_NffrFeUfNV2Hib',
                    'object' => 'customer',
                    'address' => null,
                    'balance' => 0,
                    'created' => 1680893993,
                    'currency' => null,
                    'default_source' => null,
                    'delinquent' => false,
                    'description' => null,
                    'discount' => null,
                    'email' => 'user@example.com',
                    'invoice_prefix' => 'B1F8A8F',
                    'invoice_settings' => [
                        'custom_fields' => null,
                        'default_payment_method' => null,
                        'footer' => null,
                        'rendering_options' => null,
                    ],
                    'livemode' => false,
                    'metadata' => [
                        'user_id' => 'usr_123',
                    ],
                    'name' => 'Jean Dupont',
                    'next_invoice_sequence' => 1,
                    'phone' => null,
                    'preferred_locales' => [],
                    'shipping' => null,
                    'tax_exempt' => 'none',
                    'test_clock' => null,
                ],
                '400' => [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'Invalid email address',
                        'param' => 'email',
                    ],
                ],
            ],
            'example_call' => [
                'email' => 'user@example.com',
                'name' => 'Jean Dupont',
                'metadata' => [
                    'user_id' => 'usr_123',
                ],
            ],
            'notes' => [
                'L\'ID commence toujours par "cus_"',
                'Un client peut avoir plusieurs méthodes de paiement',
                'Permet de sauvegarder les méthodes de paiement pour usage futur',
            ],
        ];
    }

    /**
     * Create a Refund
     *
     * @see https://docs.stripe.com/api/refunds/create
     */
    /**
     * @return array<string, mixed>
     */
    public static function createRefund(): array
    {
        return [
            'description' => 'Créer un remboursement',
            'endpoint' => 'POST /v1/refunds',
            'documentation_url' => 'https://docs.stripe.com/api/refunds/create',
            'parameters' => [
                'payment_intent' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'ID du Payment Intent à rembourser',
                ],
                'charge' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'ID du Charge à rembourser (legacy)',
                ],
                'amount' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Montant en centimes (partiel si inférieur au total)',
                ],
                'reason' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Raison du remboursement',
                    'enum' => ['duplicate', 'fraudulent', 'requested_by_customer'],
                ],
                'metadata' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Métadonnées personnalisées',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {STRIPE_SECRET_KEY}',
                'Stripe-Version' => '2023-10-16',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'responses' => [
                '200' => self::loadResponse('refund-create-success.json') !== [] ? self::loadResponse('refund-create-success.json') : [
                    'id' => 're_3MtwBwLkdIwHu7ix0rDrWaZa',
                    'object' => 'refund',
                    'amount' => 1000,
                    'balance_transaction' => 'txn_3MtwBwLkdIwHu7ix0oJh8npR',
                    'charge' => 'ch_3MtwBwLkdIwHu7ix0snN2oJt',
                    'created' => 1680893993,
                    'currency' => 'eur',
                    'metadata' => [],
                    'payment_intent' => 'pi_3MtwBwLkdIwHu7ix28a3tqPa',
                    'reason' => 'requested_by_customer',
                    'receipt_number' => null,
                    'source_transfer_reversal' => null,
                    'status' => 'succeeded',
                    'transfer_reversal' => null,
                ],
                '400' => [
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'This charge has already been refunded',
                    ],
                ],
            ],
            'example_call' => [
                'payment_intent' => 'pi_3MtwBwLkdIwHu7ix28a3tqPa',
                'amount' => 1000,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'reason_details' => 'Produit non conforme',
                ],
            ],
            'notes' => [
                'L\'ID commence toujours par "re_"',
                'Sans montant spécifié, remboursement total',
                'Le remboursement peut prendre 5-10 jours ouvrés',
                'Un webhook charge.refunded est envoyé',
            ],
        ];
    }

    /**
     * Webhook Events
     *
     * @see https://docs.stripe.com/webhooks
     *
     * @return array<string, mixed>
     */
    public static function webhookEvents(): array
    {
        return [
            'description' => 'Événements webhook envoyés par Stripe',
            'endpoint' => 'POST {YOUR_WEBHOOK_ENDPOINT}',
            'documentation_url' => 'https://docs.stripe.com/webhooks',
            'events' => [
                'payment_intent.succeeded' => [
                    'description' => 'Paiement réussi',
                    'payload' => self::loadResponse('webhook-payment-intent-succeeded.json') !== [] ? self::loadResponse('webhook-payment-intent-succeeded.json') : [
                        'id' => 'evt_1MtwBwLkdIwHu7ixfTbhRPNX',
                        'object' => 'event',
                        'api_version' => '2023-10-16',
                        'created' => 1680893993,
                        'data' => [
                            'object' => [
                                'id' => 'pi_3MtwBwLkdIwHu7ix28a3tqPa',
                                'object' => 'payment_intent',
                                'amount' => 2000,
                                'currency' => 'eur',
                                'status' => 'succeeded',
                                // ... payment intent complet
                            ],
                        ],
                        'livemode' => false,
                        'pending_webhooks' => 1,
                        'request' => [
                            'id' => null,
                            'idempotency_key' => null,
                        ],
                        'type' => 'payment_intent.succeeded',
                    ],
                ],
                'payment_intent.payment_failed' => [
                    'description' => 'Échec du paiement',
                    'payload' => self::loadResponse('webhook-payment-intent-failed.json'),
                ],
                'checkout.session.completed' => [
                    'description' => 'Session Checkout complétée',
                    'payload' => self::loadResponse('webhook-checkout-session-completed.json') !== [] ? self::loadResponse('webhook-checkout-session-completed.json') : [
                        'id' => 'evt_1MtwBwLkdIwHu7ixfTbhRPNX',
                        'object' => 'event',
                        'api_version' => '2023-10-16',
                        'created' => 1680893993,
                        'data' => [
                            'object' => [
                                'id' => 'cs_test_a1b2c3d4e5f6g7h8i9j0',
                                'object' => 'checkout.session',
                                'payment_intent' => 'pi_3MtwBwLkdIwHu7ix28a3tqPa',
                                'payment_status' => 'paid',
                                'status' => 'complete',
                                // ... session complète
                            ],
                        ],
                        'livemode' => false,
                        'pending_webhooks' => 1,
                        'request' => [
                            'id' => null,
                            'idempotency_key' => null,
                        ],
                        'type' => 'checkout.session.completed',
                    ],
                ],
                'charge.refunded' => [
                    'description' => 'Remboursement effectué',
                    'payload' => self::loadResponse('webhook-charge-refunded.json'),
                ],
            ],
            'headers' => [
                'Stripe-Signature' => 'Signature HMAC pour vérification',
            ],
            'notes' => [
                'Vérifier la signature avec le secret webhook',
                'Implémenter l\'idempotence pour éviter les doublons',
                'Répondre avec HTTP 200 dans les 20 secondes',
                'Stripe réessaye jusqu\'à 3 fois en cas d\'échec',
            ],
        ];
    }

    /**
     * Get all documented endpoints
     *
     * @return array<int, string>
     */
    public static function getStripeEndpoints(): array
    {
        return [
            'createPaymentIntent',
            'retrievePaymentIntent',
            'confirmPaymentIntent',
            'createCheckoutSession',
            'retrieveCheckoutSession',
            'createCustomer',
            'createRefund',
            'webhookEvents',
        ];
    }
}
