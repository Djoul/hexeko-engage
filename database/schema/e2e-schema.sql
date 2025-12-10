--
-- PostgreSQL database dump
--

\restrict xInddNEfnWpmjlooUgCAWNgbDsEx7V5w83OccUgZaQ9JVPKa05uABLWbDeBBVpb

-- Dumped from database version 15.14 (Debian 15.14-1.pgdg13+1)
-- Dumped by pg_dump version 15.14 (Debian 15.14-0+deb12u1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_log (
    id bigint NOT NULL,
    log_name character varying(255),
    description text NOT NULL,
    subject_id uuid,
    subject_type character varying(255),
    causer_id uuid,
    causer_type character varying(255),
    properties json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    event character varying(255),
    batch_uuid uuid
);


--
-- Name: activity_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_log_id_seq OWNED BY public.activity_log.id;


--
-- Name: admin_audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.admin_audit_logs (
    id bigint NOT NULL,
    user_id uuid,
    action character varying(50) NOT NULL,
    entity_type character varying(100) NOT NULL,
    entity_id character varying(255),
    old_values json,
    new_values json,
    ip_address character varying(45),
    user_agent text,
    "timestamp" timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: admin_audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.admin_audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: admin_audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.admin_audit_logs_id_seq OWNED BY public.admin_audit_logs.id;


--
-- Name: audits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audits (
    id bigint NOT NULL,
    user_type character varying(255),
    user_id character varying(36),
    event character varying(255) NOT NULL,
    auditable_type character varying(255) NOT NULL,
    auditable_id character varying(36) NOT NULL,
    old_values text,
    new_values text,
    url text,
    ip_address inet,
    user_agent character varying(1023),
    tags character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    financer_id uuid
);


--
-- Name: audits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audits_id_seq OWNED BY public.audits.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: credit_balances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.credit_balances (
    id bigint NOT NULL,
    owner_type character varying(255) NOT NULL,
    owner_id uuid NOT NULL,
    type character varying(255) NOT NULL,
    balance integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    context json
);


--
-- Name: credit_balances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.credit_balances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: credit_balances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.credit_balances_id_seq OWNED BY public.credit_balances.id;


--
-- Name: credits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.credits (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: credits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.credits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: credits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.credits_id_seq OWNED BY public.credits.id;


--
-- Name: demo_entities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.demo_entities (
    id uuid NOT NULL,
    entity_type character varying(191) NOT NULL,
    entity_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: division_balances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.division_balances (
    id uuid NOT NULL,
    division_id uuid NOT NULL,
    balance integer DEFAULT 0 NOT NULL,
    last_invoice_at timestamp(0) without time zone,
    last_payment_at timestamp(0) without time zone,
    last_credit_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: division_integration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.division_integration (
    id uuid NOT NULL,
    division_id uuid NOT NULL,
    integration_id uuid NOT NULL,
    active boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: division_module; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.division_module (
    id uuid NOT NULL,
    division_id uuid NOT NULL,
    module_id uuid NOT NULL,
    active boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    price_per_beneficiary integer
);


--
-- Name: COLUMN division_module.price_per_beneficiary; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.division_module.price_per_beneficiary IS 'Price in euro cents per beneficiary for this module';


--
-- Name: divisions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.divisions (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    remarks character varying(255),
    country character varying(255) NOT NULL,
    currency character varying(3) DEFAULT 'EUR'::character varying NOT NULL,
    timezone character varying(255) DEFAULT 'Europe/Paris'::character varying NOT NULL,
    language character varying(5) DEFAULT 'fr-FR'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    core_package_price integer,
    vat_rate numeric(5,2),
    contract_start_date date,
    CONSTRAINT divisions_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'pending'::character varying, 'archived'::character varying])::text[])))
);


--
-- Name: COLUMN divisions.core_package_price; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.divisions.core_package_price IS 'Price in euro cents for core modules package';


--
-- Name: engagement_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.engagement_logs (
    id uuid NOT NULL,
    user_id uuid,
    type character varying(255) NOT NULL,
    target character varying(255),
    metadata json,
    logged_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: engagement_metrics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.engagement_metrics (
    id uuid NOT NULL,
    date_from date NOT NULL,
    metric character varying(255) NOT NULL,
    financer_id character varying(255),
    data json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    date_to date NOT NULL,
    period character varying(255) NOT NULL
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: financer_integration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.financer_integration (
    id uuid NOT NULL,
    financer_id uuid NOT NULL,
    integration_id uuid NOT NULL,
    active boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: financer_module; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.financer_module (
    id uuid NOT NULL,
    financer_id uuid NOT NULL,
    module_id uuid NOT NULL,
    active boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    promoted boolean DEFAULT false NOT NULL,
    price_per_beneficiary integer
);


--
-- Name: COLUMN financer_module.price_per_beneficiary; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.financer_module.price_per_beneficiary IS 'Price in euro cents per beneficiary for this module (overrides division price)';


--
-- Name: financer_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.financer_user (
    id uuid NOT NULL,
    financer_id uuid NOT NULL,
    user_id uuid NOT NULL,
    active boolean DEFAULT false NOT NULL,
    sirh_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    "from" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "to" timestamp(0) without time zone,
    roles json,
    language character varying(5)
);


--
-- Name: COLUMN financer_user.language; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.financer_user.language IS 'User language preference for this financer context';


--
-- Name: financers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.financers (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    external_id jsonb,
    timezone character varying(255) DEFAULT 'Europe/Paris'::character varying NOT NULL,
    registration_number character varying(255),
    registration_country character varying(255),
    website character varying(255),
    iban character varying(255),
    vat_number character varying(255),
    representative_id uuid,
    division_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    active boolean DEFAULT true NOT NULL,
    available_languages json NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    bic character varying(11),
    company_number character varying(255) NOT NULL,
    core_package_price integer,
    contract_start_date date,
    CONSTRAINT financers_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'pending'::character varying, 'archived'::character varying])::text[])))
);


--
-- Name: COLUMN financers.core_package_price; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.financers.core_package_price IS 'Price in euro cents for core modules package (overrides division price)';


--
-- Name: int_amilon_processed_webhook_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_amilon_processed_webhook_events (
    id bigint NOT NULL,
    event_id character varying(255) NOT NULL,
    event_type character varying(255) NOT NULL,
    processed_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: int_amilon_processed_webhook_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.int_amilon_processed_webhook_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: int_amilon_processed_webhook_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.int_amilon_processed_webhook_events_id_seq OWNED BY public.int_amilon_processed_webhook_events.id;


--
-- Name: int_communication_rh_article_interactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_communication_rh_article_interactions (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    article_id uuid NOT NULL,
    reaction character varying(255),
    is_favorite boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    article_translation_id uuid
);


--
-- Name: int_communication_rh_article_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_communication_rh_article_tag (
    article_id uuid NOT NULL,
    tag_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: int_communication_rh_article_translations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_communication_rh_article_translations (
    id uuid NOT NULL,
    article_id uuid NOT NULL,
    language character varying(5) NOT NULL,
    title character varying(255) NOT NULL,
    content text NOT NULL,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    published_at timestamp(0) without time zone
);


--
-- Name: int_communication_rh_article_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_communication_rh_article_versions (
    id uuid NOT NULL,
    article_id uuid NOT NULL,
    content jsonb NOT NULL,
    prompt text,
    llm_response text,
    version_number integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    article_translation_id uuid,
    language character varying(5),
    title character varying(255),
    llm_request_id uuid,
    author_id uuid,
    illustration_id bigint
);


--
-- Name: int_communication_rh_articles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_communication_rh_articles (
    id uuid NOT NULL,
    financer_id uuid NOT NULL,
    author_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: int_communication_rh_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_communication_rh_tags (
    id uuid NOT NULL,
    financer_id uuid NOT NULL,
    label jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: int_outils_rh_link_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_outils_rh_link_user (
    id bigint NOT NULL,
    link_id uuid NOT NULL,
    user_id uuid NOT NULL,
    pinned boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: int_outils_rh_link_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.int_outils_rh_link_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: int_outils_rh_link_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.int_outils_rh_link_user_id_seq OWNED BY public.int_outils_rh_link_user.id;


--
-- Name: int_outils_rh_links; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_outils_rh_links (
    id uuid NOT NULL,
    name json NOT NULL,
    description json,
    url json NOT NULL,
    logo_url character varying(255),
    financer_id uuid NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    api_endpoint character varying(255),
    front_endpoint character varying(255),
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: int_stripe_payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_stripe_payments (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    stripe_payment_id character varying(255),
    stripe_checkout_id character varying(255),
    status character varying(255) NOT NULL,
    amount integer NOT NULL,
    currency character varying(3) NOT NULL,
    credit_amount integer NOT NULL,
    credit_type character varying(255) NOT NULL,
    metadata json,
    processed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    error_message text,
    cancelled_at timestamp(0) without time zone,
    CONSTRAINT int_stripe_payments_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'completed'::character varying, 'failed'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: int_vouchers_amilon_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_vouchers_amilon_categories (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: int_vouchers_amilon_merchant_category; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_vouchers_amilon_merchant_category (
    merchant_id uuid NOT NULL,
    category_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: int_vouchers_amilon_merchants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_vouchers_amilon_merchants (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    country character varying(255),
    merchant_id character varying(255) NOT NULL,
    description text,
    image_url character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    average_discount double precision
);


--
-- Name: int_vouchers_amilon_order_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_vouchers_amilon_order_items (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    product_id uuid NOT NULL,
    quantity integer NOT NULL,
    price numeric(10,2),
    vouchers jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: int_vouchers_amilon_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_vouchers_amilon_orders (
    id uuid NOT NULL,
    merchant_id character varying(255) NOT NULL,
    amount integer NOT NULL,
    external_order_id character varying(255) NOT NULL,
    order_id character varying(255),
    status character varying(255),
    price_paid integer,
    voucher_url text,
    user_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    payment_id character varying(255),
    order_date timestamp(0) without time zone,
    order_status character varying(255),
    gross_amount integer,
    net_amount integer,
    total_requested_codes integer,
    metadata json,
    voucher_code character varying(255),
    product_id uuid,
    total_amount integer,
    payment_method character varying(255),
    recovery_attempts integer DEFAULT 0 NOT NULL,
    last_error text,
    last_recovery_attempt timestamp(0) without time zone,
    stripe_payment_id character varying(255),
    balance_amount_used integer,
    voucher_pin character varying(255),
    product_name character varying(255),
    currency character varying(3) DEFAULT 'EUR'::character varying NOT NULL,
    order_recovered_id uuid
);


--
-- Name: COLUMN int_vouchers_amilon_orders.stripe_payment_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.int_vouchers_amilon_orders.stripe_payment_id IS 'Reference to Stripe payment intent ID';


--
-- Name: COLUMN int_vouchers_amilon_orders.voucher_pin; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.int_vouchers_amilon_orders.voucher_pin IS 'PIN code for the voucher from API response';


--
-- Name: COLUMN int_vouchers_amilon_orders.product_name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.int_vouchers_amilon_orders.product_name IS 'Product/Retailer name from API response';


--
-- Name: COLUMN int_vouchers_amilon_orders.currency; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.int_vouchers_amilon_orders.currency IS 'Currency code for the order';


--
-- Name: int_vouchers_amilon_products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.int_vouchers_amilon_products (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    category_id uuid,
    merchant_id character varying(255) NOT NULL,
    product_code character varying(255),
    price integer,
    currency character varying(255),
    country character varying(255),
    description text,
    image_url character varying(255),
    is_available boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    net_price integer,
    discount integer
);


--
-- Name: COLUMN int_vouchers_amilon_products.price; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.int_vouchers_amilon_products.price IS 'Price in cents (1 euro = 100 cents)';


--
-- Name: COLUMN int_vouchers_amilon_products.net_price; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.int_vouchers_amilon_products.net_price IS 'Net price in cents (1 euro = 100 cents)';


--
-- Name: COLUMN int_vouchers_amilon_products.discount; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.int_vouchers_amilon_products.discount IS 'Discount amount in cents (1 euro = 100 cents)';


--
-- Name: integrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.integrations (
    id uuid NOT NULL,
    module_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    description text,
    active boolean DEFAULT true NOT NULL,
    settings json,
    api_endpoint character varying(255),
    front_endpoint character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    resources_count_query text
);


--
-- Name: invited_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invited_users (
    id uuid NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    financer_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    external_id character varying(255),
    phone character varying(255),
    sirh_id character varying(255),
    extra_data jsonb
);


--
-- Name: invoice_generation_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invoice_generation_batches (
    id uuid NOT NULL,
    batch_id uuid NOT NULL,
    month_year character varying(255) NOT NULL,
    total_invoices integer NOT NULL,
    completed_count integer DEFAULT 0 NOT NULL,
    failed_count integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'in_progress'::character varying NOT NULL,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    last_error text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: invoice_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invoice_items (
    id uuid NOT NULL,
    invoice_id uuid NOT NULL,
    item_type character varying(255) NOT NULL,
    module_id uuid,
    label jsonb NOT NULL,
    description jsonb,
    beneficiaries_count integer,
    unit_price_htva integer NOT NULL,
    quantity integer NOT NULL,
    subtotal_htva integer NOT NULL,
    vat_rate numeric(5,2),
    vat_amount integer,
    total_ttc integer,
    prorata_percentage numeric(5,2),
    prorata_days integer,
    total_days integer,
    metadata jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT invoice_items_quantity_positive CHECK ((quantity > 0)),
    CONSTRAINT invoice_items_subtotal_htva_positive CHECK ((subtotal_htva >= 0))
);


--
-- Name: invoice_sequences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invoice_sequences (
    id bigint NOT NULL,
    invoice_type character varying(255) NOT NULL,
    year character varying(4) NOT NULL,
    sequence bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: invoice_sequences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.invoice_sequences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: invoice_sequences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.invoice_sequences_id_seq OWNED BY public.invoice_sequences.id;


--
-- Name: invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invoices (
    id uuid NOT NULL,
    invoice_number character varying(255) NOT NULL,
    invoice_type character varying(255) NOT NULL,
    issuer_type character varying(255) NOT NULL,
    issuer_id uuid,
    recipient_type character varying(255) NOT NULL,
    recipient_id uuid NOT NULL,
    billing_period_start date NOT NULL,
    billing_period_end date NOT NULL,
    subtotal_htva integer NOT NULL,
    vat_rate numeric(5,2) NOT NULL,
    vat_amount integer NOT NULL,
    total_ttc integer NOT NULL,
    currency character varying(3) DEFAULT 'EUR'::character varying NOT NULL,
    status character varying(255) NOT NULL,
    confirmed_at timestamp(0) without time zone,
    sent_at timestamp(0) without time zone,
    paid_at timestamp(0) without time zone,
    due_date date,
    notes text,
    metadata jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT invoices_subtotal_htva_positive CHECK ((subtotal_htva >= 0)),
    CONSTRAINT invoices_total_ttc_positive CHECK ((total_ttc >= 0))
);


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: llm_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.llm_requests (
    id uuid NOT NULL,
    prompt text NOT NULL,
    response text NOT NULL,
    tokens_used integer DEFAULT 0 NOT NULL,
    engine_used character varying(50) NOT NULL,
    financer_id uuid NOT NULL,
    requestable_id uuid NOT NULL,
    requestable_type character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    prompt_system text
);


--
-- Name: media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media (
    id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id uuid NOT NULL,
    uuid uuid,
    collection_name character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    file_name character varying(255) NOT NULL,
    mime_type character varying(255),
    disk character varying(255) NOT NULL,
    conversions_disk character varying(255),
    size bigint NOT NULL,
    manipulations json NOT NULL,
    custom_properties json NOT NULL,
    generated_conversions json NOT NULL,
    responsive_images json NOT NULL,
    order_column integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: media_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.media_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: media_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.media_id_seq OWNED BY public.media.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id uuid NOT NULL,
    model_type character varying(255) NOT NULL,
    model_uuid uuid NOT NULL,
    team_id uuid NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id uuid NOT NULL,
    model_type character varying(255) NOT NULL,
    model_uuid uuid NOT NULL,
    team_id uuid NOT NULL
);


--
-- Name: module_pricing_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.module_pricing_history (
    id uuid NOT NULL,
    module_id uuid NOT NULL,
    entity_id uuid NOT NULL,
    entity_type character varying(255) NOT NULL,
    old_price integer,
    new_price integer,
    price_type character varying(255) NOT NULL,
    changed_by uuid,
    reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    valid_from date,
    valid_until date
);


--
-- Name: COLUMN module_pricing_history.entity_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.module_pricing_history.entity_id IS 'Division or Financer ID';


--
-- Name: COLUMN module_pricing_history.entity_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.module_pricing_history.entity_type IS 'division or financer';


--
-- Name: COLUMN module_pricing_history.old_price; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.module_pricing_history.old_price IS 'Previous price in euro cents';


--
-- Name: COLUMN module_pricing_history.new_price; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.module_pricing_history.new_price IS 'New price in euro cents';


--
-- Name: COLUMN module_pricing_history.price_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.module_pricing_history.price_type IS 'core_package or module_price';


--
-- Name: COLUMN module_pricing_history.changed_by; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.module_pricing_history.changed_by IS 'User ID who made the change';


--
-- Name: COLUMN module_pricing_history.reason; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.module_pricing_history.reason IS 'Reason for price change';


--
-- Name: modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modules (
    id uuid NOT NULL,
    name json NOT NULL,
    description json,
    active boolean DEFAULT true NOT NULL,
    settings json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    category character varying(255) DEFAULT 'enterprise_life'::character varying NOT NULL,
    is_core boolean DEFAULT false NOT NULL
);


--
-- Name: notification_topic_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_topic_subscriptions (
    notification_topic_id uuid NOT NULL,
    push_subscription_id uuid NOT NULL,
    subscribed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: notification_topics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_topics (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    display_name character varying(255) NOT NULL,
    description text,
    financer_id uuid,
    is_active boolean DEFAULT true NOT NULL,
    subscriber_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    is_protected boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: pulse_aggregates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pulse_aggregates (
    id bigint NOT NULL,
    bucket integer NOT NULL,
    period integer NOT NULL,
    type character varying(255) NOT NULL,
    key text NOT NULL,
    key_hash uuid GENERATED ALWAYS AS ((md5(key))::uuid) STORED NOT NULL,
    aggregate character varying(255) NOT NULL,
    value numeric(20,2) NOT NULL,
    count integer
);


--
-- Name: pulse_aggregates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pulse_aggregates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pulse_aggregates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pulse_aggregates_id_seq OWNED BY public.pulse_aggregates.id;


--
-- Name: pulse_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pulse_entries (
    id bigint NOT NULL,
    "timestamp" integer NOT NULL,
    type character varying(255) NOT NULL,
    key text NOT NULL,
    key_hash uuid GENERATED ALWAYS AS ((md5(key))::uuid) STORED NOT NULL,
    value bigint
);


--
-- Name: pulse_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pulse_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pulse_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pulse_entries_id_seq OWNED BY public.pulse_entries.id;


--
-- Name: pulse_values; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pulse_values (
    id bigint NOT NULL,
    "timestamp" integer NOT NULL,
    type character varying(255) NOT NULL,
    key text NOT NULL,
    key_hash uuid GENERATED ALWAYS AS ((md5(key))::uuid) STORED NOT NULL,
    value text NOT NULL
);


--
-- Name: pulse_values_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pulse_values_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pulse_values_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pulse_values_id_seq OWNED BY public.pulse_values.id;


--
-- Name: push_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.push_events (
    id uuid NOT NULL,
    push_notification_id uuid NOT NULL,
    push_subscription_id uuid,
    event_type character varying(255) NOT NULL,
    event_id character varying(255),
    event_data jsonb DEFAULT '{}'::jsonb NOT NULL,
    ip_address character varying(255),
    user_agent character varying(255),
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT push_events_event_type_check CHECK (((event_type)::text = ANY ((ARRAY['sent'::character varying, 'delivered'::character varying, 'opened'::character varying, 'clicked'::character varying, 'dismissed'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: push_notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.push_notifications (
    id uuid NOT NULL,
    notification_id character varying(255) NOT NULL,
    external_id character varying(255),
    delivery_type character varying(255) DEFAULT 'targeted'::character varying NOT NULL,
    device_count integer DEFAULT 0 NOT NULL,
    type character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    body text NOT NULL,
    url character varying(255),
    image character varying(255),
    icon character varying(255),
    data jsonb DEFAULT '{}'::jsonb NOT NULL,
    buttons jsonb DEFAULT '[]'::jsonb NOT NULL,
    priority character varying(255) DEFAULT 'normal'::character varying NOT NULL,
    ttl integer DEFAULT 86400 NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    recipient_count integer DEFAULT 0 NOT NULL,
    delivered_count integer DEFAULT 0 NOT NULL,
    opened_count integer DEFAULT 0 NOT NULL,
    clicked_count integer DEFAULT 0 NOT NULL,
    scheduled_at timestamp(0) without time zone,
    sent_at timestamp(0) without time zone,
    author_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT push_notifications_delivery_type_check CHECK (((delivery_type)::text = ANY ((ARRAY['targeted'::character varying, 'broadcast'::character varying])::text[]))),
    CONSTRAINT push_notifications_priority_check CHECK (((priority)::text = ANY ((ARRAY['low'::character varying, 'normal'::character varying, 'high'::character varying, 'urgent'::character varying])::text[]))),
    CONSTRAINT push_notifications_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'scheduled'::character varying, 'sending'::character varying, 'sent'::character varying, 'failed'::character varying, 'cancelled'::character varying])::text[]))),
    CONSTRAINT push_notifications_type_check CHECK (((type)::text = ANY ((ARRAY['transaction'::character varying, 'marketing'::character varying, 'system'::character varying, 'reminder'::character varying, 'alert'::character varying])::text[])))
);


--
-- Name: push_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.push_subscriptions (
    id uuid NOT NULL,
    user_id uuid,
    subscription_id character varying(255) NOT NULL,
    device_type character varying(255) NOT NULL,
    device_model character varying(255),
    device_os character varying(255),
    app_version character varying(255),
    timezone character varying(255),
    language character varying(255),
    notification_preferences jsonb DEFAULT '{}'::jsonb NOT NULL,
    push_enabled boolean DEFAULT true NOT NULL,
    sound_enabled boolean DEFAULT true NOT NULL,
    vibration_enabled boolean DEFAULT true NOT NULL,
    tags jsonb DEFAULT '{}'::jsonb NOT NULL,
    metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    last_active_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT push_subscriptions_device_type_check CHECK (((device_type)::text = ANY ((ARRAY['ios'::character varying, 'android'::character varying, 'web'::character varying, 'desktop'::character varying])::text[])))
);


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id uuid NOT NULL,
    role_id uuid NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id uuid NOT NULL,
    team_id uuid,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    is_protected boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL,
    user_id character varying(36)
);


--
-- Name: snapshots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.snapshots (
    id bigint NOT NULL,
    aggregate_uuid uuid NOT NULL,
    aggregate_version bigint NOT NULL,
    state jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: snapshots_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.snapshots_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: snapshots_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.snapshots_id_seq OWNED BY public.snapshots.id;


--
-- Name: stored_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stored_events (
    id bigint NOT NULL,
    aggregate_uuid uuid,
    aggregate_version bigint,
    event_version smallint DEFAULT '1'::smallint NOT NULL,
    event_class character varying(255) NOT NULL,
    event_properties jsonb NOT NULL,
    meta_data jsonb NOT NULL,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: stored_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stored_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stored_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stored_events_id_seq OWNED BY public.stored_events.id;


--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.teams (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    type character varying(3),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: telescope_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telescope_entries (
    sequence bigint NOT NULL,
    uuid uuid NOT NULL,
    batch_id uuid NOT NULL,
    family_hash character varying(255),
    should_display_on_index boolean DEFAULT true NOT NULL,
    type character varying(20) NOT NULL,
    content text NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: telescope_entries_sequence_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.telescope_entries_sequence_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: telescope_entries_sequence_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.telescope_entries_sequence_seq OWNED BY public.telescope_entries.sequence;


--
-- Name: telescope_entries_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telescope_entries_tags (
    entry_uuid uuid NOT NULL,
    tag character varying(255) NOT NULL
);


--
-- Name: telescope_monitoring; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telescope_monitoring (
    tag character varying(255) NOT NULL
);


--
-- Name: test_audit_models; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.test_audit_models (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: test_audit_models_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.test_audit_models_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: test_audit_models_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.test_audit_models_id_seq OWNED BY public.test_audit_models.id;


--
-- Name: translation_activity_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.translation_activity_logs (
    id bigint NOT NULL,
    user_id uuid,
    action character varying(255) NOT NULL,
    target_type character varying(255) NOT NULL,
    target_id bigint NOT NULL,
    locale character varying(10),
    before json,
    after json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT translation_activity_logs_action_check CHECK (((action)::text = ANY ((ARRAY['created'::character varying, 'updated'::character varying, 'deleted'::character varying])::text[]))),
    CONSTRAINT translation_activity_logs_target_type_check CHECK (((target_type)::text = ANY ((ARRAY['key'::character varying, 'value'::character varying])::text[])))
);


--
-- Name: translation_activity_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.translation_activity_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: translation_activity_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.translation_activity_logs_id_seq OWNED BY public.translation_activity_logs.id;


--
-- Name: translation_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.translation_keys (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    "group" character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    interface_origin character varying(50) DEFAULT 'web'::character varying NOT NULL
);


--
-- Name: translation_keys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.translation_keys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: translation_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.translation_keys_id_seq OWNED BY public.translation_keys.id;


--
-- Name: translation_migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.translation_migrations (
    id bigint NOT NULL,
    filename character varying(255) NOT NULL,
    interface_origin character varying(255) NOT NULL,
    version character varying(255) NOT NULL,
    checksum character varying(64) NOT NULL,
    metadata json NOT NULL,
    status character varying(255) NOT NULL,
    batch_number integer,
    executed_at timestamp(0) without time zone,
    rolled_back_at timestamp(0) without time zone,
    error_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT translation_migrations_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'processing'::character varying, 'completed'::character varying, 'failed'::character varying, 'rolled_back'::character varying])::text[])))
);


--
-- Name: translation_migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.translation_migrations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: translation_migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.translation_migrations_id_seq OWNED BY public.translation_migrations.id;


--
-- Name: translation_values; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.translation_values (
    id bigint NOT NULL,
    translation_key_id bigint NOT NULL,
    locale character varying(5) NOT NULL,
    value text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: translation_values_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.translation_values_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: translation_values_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.translation_values_id_seq OWNED BY public.translation_values.id;


--
-- Name: user_pinned_modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_pinned_modules (
    user_id uuid NOT NULL,
    module_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id uuid NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    temp_password character varying(255),
    cognito_id character varying(255),
    first_name character varying(255),
    last_name character varying(255),
    force_change_email boolean DEFAULT false NOT NULL,
    birthdate date,
    terms_confirmed boolean DEFAULT false NOT NULL,
    enabled boolean DEFAULT true NOT NULL,
    locale character varying(5) DEFAULT 'fr-FR'::character varying NOT NULL,
    currency character varying(3) DEFAULT 'EUR'::character varying NOT NULL,
    timezone character varying(255),
    stripe_id character varying(255),
    sirh_id character varying(255),
    last_login timestamp(0) without time zone,
    opt_in boolean DEFAULT false NOT NULL,
    phone character varying(255),
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    description text,
    team_id uuid
);


--
-- Name: activity_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log ALTER COLUMN id SET DEFAULT nextval('public.activity_log_id_seq'::regclass);


--
-- Name: admin_audit_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admin_audit_logs ALTER COLUMN id SET DEFAULT nextval('public.admin_audit_logs_id_seq'::regclass);


--
-- Name: audits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audits ALTER COLUMN id SET DEFAULT nextval('public.audits_id_seq'::regclass);


--
-- Name: credit_balances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_balances ALTER COLUMN id SET DEFAULT nextval('public.credit_balances_id_seq'::regclass);


--
-- Name: credits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credits ALTER COLUMN id SET DEFAULT nextval('public.credits_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: int_amilon_processed_webhook_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_amilon_processed_webhook_events ALTER COLUMN id SET DEFAULT nextval('public.int_amilon_processed_webhook_events_id_seq'::regclass);


--
-- Name: int_outils_rh_link_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_outils_rh_link_user ALTER COLUMN id SET DEFAULT nextval('public.int_outils_rh_link_user_id_seq'::regclass);


--
-- Name: invoice_sequences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoice_sequences ALTER COLUMN id SET DEFAULT nextval('public.invoice_sequences_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: media id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media ALTER COLUMN id SET DEFAULT nextval('public.media_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: pulse_aggregates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pulse_aggregates ALTER COLUMN id SET DEFAULT nextval('public.pulse_aggregates_id_seq'::regclass);


--
-- Name: pulse_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pulse_entries ALTER COLUMN id SET DEFAULT nextval('public.pulse_entries_id_seq'::regclass);


--
-- Name: pulse_values id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pulse_values ALTER COLUMN id SET DEFAULT nextval('public.pulse_values_id_seq'::regclass);


--
-- Name: snapshots id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.snapshots ALTER COLUMN id SET DEFAULT nextval('public.snapshots_id_seq'::regclass);


--
-- Name: stored_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stored_events ALTER COLUMN id SET DEFAULT nextval('public.stored_events_id_seq'::regclass);


--
-- Name: telescope_entries sequence; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries ALTER COLUMN sequence SET DEFAULT nextval('public.telescope_entries_sequence_seq'::regclass);


--
-- Name: test_audit_models id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.test_audit_models ALTER COLUMN id SET DEFAULT nextval('public.test_audit_models_id_seq'::regclass);


--
-- Name: translation_activity_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_activity_logs ALTER COLUMN id SET DEFAULT nextval('public.translation_activity_logs_id_seq'::regclass);


--
-- Name: translation_keys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_keys ALTER COLUMN id SET DEFAULT nextval('public.translation_keys_id_seq'::regclass);


--
-- Name: translation_migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_migrations ALTER COLUMN id SET DEFAULT nextval('public.translation_migrations_id_seq'::regclass);


--
-- Name: translation_values id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_values ALTER COLUMN id SET DEFAULT nextval('public.translation_values_id_seq'::regclass);


--
-- Name: activity_log activity_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log
    ADD CONSTRAINT activity_log_pkey PRIMARY KEY (id);


--
-- Name: admin_audit_logs admin_audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admin_audit_logs
    ADD CONSTRAINT admin_audit_logs_pkey PRIMARY KEY (id);


--
-- Name: audits audits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audits
    ADD CONSTRAINT audits_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: credit_balances credit_balances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_balances
    ADD CONSTRAINT credit_balances_pkey PRIMARY KEY (id);


--
-- Name: credits credits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credits
    ADD CONSTRAINT credits_pkey PRIMARY KEY (id);


--
-- Name: demo_entities demo_entities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demo_entities
    ADD CONSTRAINT demo_entities_pkey PRIMARY KEY (id);


--
-- Name: demo_entities demo_entity_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demo_entities
    ADD CONSTRAINT demo_entity_unique UNIQUE (entity_type, entity_id);


--
-- Name: division_balances division_balances_division_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.division_balances
    ADD CONSTRAINT division_balances_division_id_unique UNIQUE (division_id);


--
-- Name: division_balances division_balances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.division_balances
    ADD CONSTRAINT division_balances_pkey PRIMARY KEY (id);


--
-- Name: division_integration division_integration_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.division_integration
    ADD CONSTRAINT division_integration_pkey PRIMARY KEY (id);


--
-- Name: division_module division_module_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.division_module
    ADD CONSTRAINT division_module_pkey PRIMARY KEY (id);


--
-- Name: divisions divisions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.divisions
    ADD CONSTRAINT divisions_pkey PRIMARY KEY (id);


--
-- Name: engagement_logs engagement_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.engagement_logs
    ADD CONSTRAINT engagement_logs_pkey PRIMARY KEY (id);


--
-- Name: engagement_metrics engagement_metrics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.engagement_metrics
    ADD CONSTRAINT engagement_metrics_pkey PRIMARY KEY (id);


--
-- Name: engagement_metrics engagement_metrics_unique_period; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.engagement_metrics
    ADD CONSTRAINT engagement_metrics_unique_period UNIQUE (date_from, date_to, metric, financer_id, period);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: financer_integration financer_integration_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.financer_integration
    ADD CONSTRAINT financer_integration_pkey PRIMARY KEY (id);


--
-- Name: financer_module financer_module_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.financer_module
    ADD CONSTRAINT financer_module_pkey PRIMARY KEY (id);


--
-- Name: financer_user financer_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.financer_user
    ADD CONSTRAINT financer_user_pkey PRIMARY KEY (id);


--
-- Name: financers financers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.financers
    ADD CONSTRAINT financers_pkey PRIMARY KEY (id);


--
-- Name: int_amilon_processed_webhook_events int_amilon_processed_webhook_events_event_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_amilon_processed_webhook_events
    ADD CONSTRAINT int_amilon_processed_webhook_events_event_id_unique UNIQUE (event_id);


--
-- Name: int_amilon_processed_webhook_events int_amilon_processed_webhook_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_amilon_processed_webhook_events
    ADD CONSTRAINT int_amilon_processed_webhook_events_pkey PRIMARY KEY (id);


--
-- Name: int_communication_rh_article_interactions int_communication_rh_article_interactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_interactions
    ADD CONSTRAINT int_communication_rh_article_interactions_pkey PRIMARY KEY (id);


--
-- Name: int_communication_rh_article_interactions int_communication_rh_article_interactions_user_id_article_id_un; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_interactions
    ADD CONSTRAINT int_communication_rh_article_interactions_user_id_article_id_un UNIQUE (user_id, article_id);


--
-- Name: int_communication_rh_article_tag int_communication_rh_article_tag_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_tag
    ADD CONSTRAINT int_communication_rh_article_tag_pkey PRIMARY KEY (article_id, tag_id);


--
-- Name: int_communication_rh_article_translations int_communication_rh_article_translations_article_id_language_u; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_translations
    ADD CONSTRAINT int_communication_rh_article_translations_article_id_language_u UNIQUE (article_id, language);


--
-- Name: int_communication_rh_article_translations int_communication_rh_article_translations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_translations
    ADD CONSTRAINT int_communication_rh_article_translations_pkey PRIMARY KEY (id);


--
-- Name: int_communication_rh_article_versions int_communication_rh_article_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_versions
    ADD CONSTRAINT int_communication_rh_article_versions_pkey PRIMARY KEY (id);


--
-- Name: int_communication_rh_articles int_communication_rh_articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_articles
    ADD CONSTRAINT int_communication_rh_articles_pkey PRIMARY KEY (id);


--
-- Name: int_communication_rh_tags int_communication_rh_tags_financer_label_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_tags
    ADD CONSTRAINT int_communication_rh_tags_financer_label_unique UNIQUE (financer_id, label);


--
-- Name: int_communication_rh_tags int_communication_rh_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_tags
    ADD CONSTRAINT int_communication_rh_tags_pkey PRIMARY KEY (id);


--
-- Name: int_outils_rh_link_user int_outils_rh_link_user_link_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_outils_rh_link_user
    ADD CONSTRAINT int_outils_rh_link_user_link_id_user_id_unique UNIQUE (link_id, user_id);


--
-- Name: int_outils_rh_link_user int_outils_rh_link_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_outils_rh_link_user
    ADD CONSTRAINT int_outils_rh_link_user_pkey PRIMARY KEY (id);


--
-- Name: int_outils_rh_links int_outils_rh_links_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_outils_rh_links
    ADD CONSTRAINT int_outils_rh_links_pkey PRIMARY KEY (id);


--
-- Name: int_stripe_payments int_stripe_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_stripe_payments
    ADD CONSTRAINT int_stripe_payments_pkey PRIMARY KEY (id);


--
-- Name: int_stripe_payments int_stripe_payments_stripe_checkout_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_stripe_payments
    ADD CONSTRAINT int_stripe_payments_stripe_checkout_id_unique UNIQUE (stripe_checkout_id);


--
-- Name: int_stripe_payments int_stripe_payments_stripe_payment_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_stripe_payments
    ADD CONSTRAINT int_stripe_payments_stripe_payment_id_unique UNIQUE (stripe_payment_id);


--
-- Name: int_vouchers_amilon_categories int_vouchers_amilon_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_categories
    ADD CONSTRAINT int_vouchers_amilon_categories_pkey PRIMARY KEY (id);


--
-- Name: int_vouchers_amilon_merchant_category int_vouchers_amilon_merchant_category_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_merchant_category
    ADD CONSTRAINT int_vouchers_amilon_merchant_category_pkey PRIMARY KEY (merchant_id, category_id);


--
-- Name: int_vouchers_amilon_merchants int_vouchers_amilon_merchants_merchant_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_merchants
    ADD CONSTRAINT int_vouchers_amilon_merchants_merchant_id_unique UNIQUE (merchant_id);


--
-- Name: int_vouchers_amilon_merchants int_vouchers_amilon_merchants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_merchants
    ADD CONSTRAINT int_vouchers_amilon_merchants_pkey PRIMARY KEY (id);


--
-- Name: int_vouchers_amilon_order_items int_vouchers_amilon_order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_order_items
    ADD CONSTRAINT int_vouchers_amilon_order_items_pkey PRIMARY KEY (id);


--
-- Name: int_vouchers_amilon_orders int_vouchers_amilon_orders_external_order_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_orders
    ADD CONSTRAINT int_vouchers_amilon_orders_external_order_id_unique UNIQUE (external_order_id);


--
-- Name: int_vouchers_amilon_orders int_vouchers_amilon_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_orders
    ADD CONSTRAINT int_vouchers_amilon_orders_pkey PRIMARY KEY (id);


--
-- Name: int_vouchers_amilon_products int_vouchers_amilon_products_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_products
    ADD CONSTRAINT int_vouchers_amilon_products_pkey PRIMARY KEY (id);


--
-- Name: integrations integrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integrations
    ADD CONSTRAINT integrations_pkey PRIMARY KEY (id);


--
-- Name: invited_users invited_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invited_users
    ADD CONSTRAINT invited_users_pkey PRIMARY KEY (id);


--
-- Name: invoice_generation_batches invoice_generation_batches_batch_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoice_generation_batches
    ADD CONSTRAINT invoice_generation_batches_batch_id_unique UNIQUE (batch_id);


--
-- Name: invoice_generation_batches invoice_generation_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoice_generation_batches
    ADD CONSTRAINT invoice_generation_batches_pkey PRIMARY KEY (id);


--
-- Name: invoice_items invoice_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoice_items
    ADD CONSTRAINT invoice_items_pkey PRIMARY KEY (id);


--
-- Name: invoice_sequences invoice_sequences_invoice_type_year_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoice_sequences
    ADD CONSTRAINT invoice_sequences_invoice_type_year_unique UNIQUE (invoice_type, year);


--
-- Name: invoice_sequences invoice_sequences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoice_sequences
    ADD CONSTRAINT invoice_sequences_pkey PRIMARY KEY (id);


--
-- Name: invoices invoices_invoice_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_invoice_number_unique UNIQUE (invoice_number);


--
-- Name: invoices invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: llm_requests llm_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.llm_requests
    ADD CONSTRAINT llm_requests_pkey PRIMARY KEY (id);


--
-- Name: media media_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_pkey PRIMARY KEY (id);


--
-- Name: media media_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_uuid_unique UNIQUE (uuid);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (team_id, permission_id, model_uuid, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (team_id, role_id, model_uuid, model_type);


--
-- Name: module_pricing_history module_pricing_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_pricing_history
    ADD CONSTRAINT module_pricing_history_pkey PRIMARY KEY (id);


--
-- Name: modules modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_pkey PRIMARY KEY (id);


--
-- Name: notification_topics notification_topics_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_topics
    ADD CONSTRAINT notification_topics_name_unique UNIQUE (name);


--
-- Name: notification_topics notification_topics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_topics
    ADD CONSTRAINT notification_topics_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: pulse_aggregates pulse_aggregates_bucket_period_type_aggregate_key_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pulse_aggregates
    ADD CONSTRAINT pulse_aggregates_bucket_period_type_aggregate_key_hash_unique UNIQUE (bucket, period, type, aggregate, key_hash);


--
-- Name: pulse_aggregates pulse_aggregates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pulse_aggregates
    ADD CONSTRAINT pulse_aggregates_pkey PRIMARY KEY (id);


--
-- Name: pulse_entries pulse_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pulse_entries
    ADD CONSTRAINT pulse_entries_pkey PRIMARY KEY (id);


--
-- Name: pulse_values pulse_values_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pulse_values
    ADD CONSTRAINT pulse_values_pkey PRIMARY KEY (id);


--
-- Name: pulse_values pulse_values_type_key_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pulse_values
    ADD CONSTRAINT pulse_values_type_key_hash_unique UNIQUE (type, key_hash);


--
-- Name: push_events push_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_events
    ADD CONSTRAINT push_events_pkey PRIMARY KEY (id);


--
-- Name: push_events push_events_push_notification_id_push_subscription_id_event_typ; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_events
    ADD CONSTRAINT push_events_push_notification_id_push_subscription_id_event_typ UNIQUE (push_notification_id, push_subscription_id, event_type, event_id);


--
-- Name: push_notifications push_notifications_notification_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_notifications
    ADD CONSTRAINT push_notifications_notification_id_unique UNIQUE (notification_id);


--
-- Name: push_notifications push_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_notifications
    ADD CONSTRAINT push_notifications_pkey PRIMARY KEY (id);


--
-- Name: push_subscriptions push_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: push_subscriptions push_subscriptions_subscription_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_subscription_id_unique UNIQUE (subscription_id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_team_id_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_team_id_name_guard_name_unique UNIQUE (team_id, name, guard_name);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: snapshots snapshots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.snapshots
    ADD CONSTRAINT snapshots_pkey PRIMARY KEY (id);


--
-- Name: stored_events stored_events_aggregate_uuid_aggregate_version_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stored_events
    ADD CONSTRAINT stored_events_aggregate_uuid_aggregate_version_unique UNIQUE (aggregate_uuid, aggregate_version);


--
-- Name: stored_events stored_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stored_events
    ADD CONSTRAINT stored_events_pkey PRIMARY KEY (id);


--
-- Name: teams teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_pkey PRIMARY KEY (id);


--
-- Name: teams teams_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_slug_unique UNIQUE (slug);


--
-- Name: telescope_entries telescope_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries
    ADD CONSTRAINT telescope_entries_pkey PRIMARY KEY (sequence);


--
-- Name: telescope_entries_tags telescope_entries_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries_tags
    ADD CONSTRAINT telescope_entries_tags_pkey PRIMARY KEY (entry_uuid, tag);


--
-- Name: telescope_entries telescope_entries_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries
    ADD CONSTRAINT telescope_entries_uuid_unique UNIQUE (uuid);


--
-- Name: telescope_monitoring telescope_monitoring_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_monitoring
    ADD CONSTRAINT telescope_monitoring_pkey PRIMARY KEY (tag);


--
-- Name: test_audit_models test_audit_models_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.test_audit_models
    ADD CONSTRAINT test_audit_models_pkey PRIMARY KEY (id);


--
-- Name: notification_topic_subscriptions topic_subscription_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_topic_subscriptions
    ADD CONSTRAINT topic_subscription_unique UNIQUE (notification_topic_id, push_subscription_id);


--
-- Name: translation_activity_logs translation_activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_activity_logs
    ADD CONSTRAINT translation_activity_logs_pkey PRIMARY KEY (id);


--
-- Name: translation_keys translation_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_keys
    ADD CONSTRAINT translation_keys_pkey PRIMARY KEY (id);


--
-- Name: translation_keys translation_keys_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_keys
    ADD CONSTRAINT translation_keys_unique UNIQUE (key, "group", interface_origin);


--
-- Name: translation_migrations translation_migrations_filename_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_migrations
    ADD CONSTRAINT translation_migrations_filename_unique UNIQUE (filename);


--
-- Name: translation_migrations translation_migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_migrations
    ADD CONSTRAINT translation_migrations_pkey PRIMARY KEY (id);


--
-- Name: translation_values translation_values_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_values
    ADD CONSTRAINT translation_values_pkey PRIMARY KEY (id);


--
-- Name: credit_balances unique_credit_balance; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_balances
    ADD CONSTRAINT unique_credit_balance UNIQUE (owner_type, owner_id, type);


--
-- Name: user_pinned_modules user_pinned_modules_user_id_module_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_pinned_modules
    ADD CONSTRAINT user_pinned_modules_user_id_module_id_unique UNIQUE (user_id, module_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: activity_log_log_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_log_name_index ON public.activity_log USING btree (log_name);


--
-- Name: admin_audit_logs_action_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admin_audit_logs_action_index ON public.admin_audit_logs USING btree (action);


--
-- Name: admin_audit_logs_entity_type_entity_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admin_audit_logs_entity_type_entity_id_index ON public.admin_audit_logs USING btree (entity_type, entity_id);


--
-- Name: admin_audit_logs_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admin_audit_logs_timestamp_index ON public.admin_audit_logs USING btree ("timestamp");


--
-- Name: admin_audit_logs_user_id_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admin_audit_logs_user_id_timestamp_index ON public.admin_audit_logs USING btree (user_id, "timestamp");


--
-- Name: audits_auditable_id_auditable_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audits_auditable_id_auditable_type_index ON public.audits USING btree (auditable_id, auditable_type);


--
-- Name: audits_user_id_user_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audits_user_id_user_type_index ON public.audits USING btree (user_id, user_type);


--
-- Name: companies_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_created_at_index ON public.financers USING btree (created_at);


--
-- Name: companies_deleted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_deleted_at_index ON public.financers USING btree (deleted_at);


--
-- Name: companies_division_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_division_id_foreign ON public.financers USING btree (division_id);


--
-- Name: companies_representative_id_foreign; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_representative_id_foreign ON public.financers USING btree (representative_id);


--
-- Name: demo_entities_entity_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demo_entities_entity_id_index ON public.demo_entities USING btree (entity_id);


--
-- Name: demo_entities_entity_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demo_entities_entity_type_index ON public.demo_entities USING btree (entity_type);


--
-- Name: division_balances_division_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX division_balances_division_id_index ON public.division_balances USING btree (division_id);


--
-- Name: engagement_logs_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX engagement_logs_user_id_index ON public.engagement_logs USING btree (user_id);


--
-- Name: financer_user_financer_id_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX financer_user_financer_id_user_id_index ON public.financer_user USING btree (financer_id, user_id);


--
-- Name: idx_financer_user_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_financer_user_active ON public.financer_user USING btree (financer_id, active);


--
-- Name: idx_invoice_items_invoice; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_invoice_items_invoice ON public.invoice_items USING btree (invoice_id);


--
-- Name: idx_invoice_items_module; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_invoice_items_module ON public.invoice_items USING btree (module_id);


--
-- Name: idx_invoice_items_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_invoice_items_type ON public.invoice_items USING btree (item_type);


--
-- Name: idx_invoices_issuer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_invoices_issuer ON public.invoices USING btree (issuer_type, issuer_id);


--
-- Name: idx_invoices_number; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_invoices_number ON public.invoices USING btree (invoice_number);


--
-- Name: idx_invoices_period; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_invoices_period ON public.invoices USING btree (billing_period_start, billing_period_end);


--
-- Name: idx_invoices_recipient; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_invoices_recipient ON public.invoices USING btree (recipient_type, recipient_id);


--
-- Name: idx_invoices_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_invoices_status ON public.invoices USING btree (status);


--
-- Name: idx_translation_keys_interface; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_translation_keys_interface ON public.translation_keys USING btree (interface_origin);


--
-- Name: idx_users_email_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_users_email_trgm ON public.users USING gin (email public.gin_trgm_ops);


--
-- Name: idx_users_enabled_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_users_enabled_created_at ON public.users USING btree (enabled, created_at DESC) WHERE (deleted_at IS NULL);


--
-- Name: idx_users_first_name_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_users_first_name_trgm ON public.users USING gin (first_name public.gin_trgm_ops);


--
-- Name: idx_users_last_name_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_users_last_name_trgm ON public.users USING gin (last_name public.gin_trgm_ops);


--
-- Name: int_amilon_processed_webhook_events_event_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_amilon_processed_webhook_events_event_type_index ON public.int_amilon_processed_webhook_events USING btree (event_type);


--
-- Name: int_amilon_processed_webhook_events_processed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_amilon_processed_webhook_events_processed_at_index ON public.int_amilon_processed_webhook_events USING btree (processed_at);


--
-- Name: int_communication_rh_article_interactions_article_reaction_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_interactions_article_reaction_idx ON public.int_communication_rh_article_interactions USING btree (article_id, reaction);


--
-- Name: int_communication_rh_article_interactions_is_favorite_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_interactions_is_favorite_idx ON public.int_communication_rh_article_interactions USING btree (is_favorite);


--
-- Name: int_communication_rh_article_interactions_reaction_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_interactions_reaction_idx ON public.int_communication_rh_article_interactions USING btree (reaction);


--
-- Name: int_communication_rh_article_interactions_translation_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_interactions_translation_idx ON public.int_communication_rh_article_interactions USING btree (article_translation_id);


--
-- Name: int_communication_rh_article_interactions_user_favorite_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_interactions_user_favorite_idx ON public.int_communication_rh_article_interactions USING btree (user_id, is_favorite);


--
-- Name: int_communication_rh_article_tag_created_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_tag_created_at_idx ON public.int_communication_rh_article_tag USING btree (created_at);


--
-- Name: int_communication_rh_article_tag_tag_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_tag_tag_id_idx ON public.int_communication_rh_article_tag USING btree (tag_id);


--
-- Name: int_communication_rh_article_translations_lang_published_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_translations_lang_published_idx ON public.int_communication_rh_article_translations USING btree (language, published_at);


--
-- Name: int_communication_rh_article_translations_language_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_translations_language_idx ON public.int_communication_rh_article_translations USING btree (language);


--
-- Name: int_communication_rh_article_translations_published_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_translations_published_at_idx ON public.int_communication_rh_article_translations USING btree (published_at);


--
-- Name: int_communication_rh_article_translations_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_translations_status_idx ON public.int_communication_rh_article_translations USING btree (status);


--
-- Name: int_communication_rh_article_translations_status_language_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_translations_status_language_idx ON public.int_communication_rh_article_translations USING btree (status, language);


--
-- Name: int_communication_rh_article_translations_status_published_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_translations_status_published_idx ON public.int_communication_rh_article_translations USING btree (status, published_at);


--
-- Name: int_communication_rh_article_versions_article_version_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_versions_article_version_idx ON public.int_communication_rh_article_versions USING btree (article_id, version_number);


--
-- Name: int_communication_rh_article_versions_author_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_versions_author_idx ON public.int_communication_rh_article_versions USING btree (author_id);


--
-- Name: int_communication_rh_article_versions_language_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_versions_language_idx ON public.int_communication_rh_article_versions USING btree (language);


--
-- Name: int_communication_rh_article_versions_llm_request_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_versions_llm_request_idx ON public.int_communication_rh_article_versions USING btree (llm_request_id);


--
-- Name: int_communication_rh_article_versions_translation_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_versions_translation_idx ON public.int_communication_rh_article_versions USING btree (article_translation_id);


--
-- Name: int_communication_rh_article_versions_version_number_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_article_versions_version_number_idx ON public.int_communication_rh_article_versions USING btree (version_number);


--
-- Name: int_communication_rh_articles_created_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_articles_created_at_idx ON public.int_communication_rh_articles USING btree (created_at);


--
-- Name: int_communication_rh_articles_deleted_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_articles_deleted_at_idx ON public.int_communication_rh_articles USING btree (deleted_at);


--
-- Name: int_communication_rh_articles_financer_author_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_articles_financer_author_idx ON public.int_communication_rh_articles USING btree (financer_id, author_id);


--
-- Name: int_communication_rh_articles_updated_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_articles_updated_at_idx ON public.int_communication_rh_articles USING btree (updated_at);


--
-- Name: int_communication_rh_tags_created_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_tags_created_at_idx ON public.int_communication_rh_tags USING btree (created_at);


--
-- Name: int_communication_rh_tags_deleted_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_communication_rh_tags_deleted_at_idx ON public.int_communication_rh_tags USING btree (deleted_at);


--
-- Name: int_stripe_payments_stripe_payment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_stripe_payments_stripe_payment_id_index ON public.int_stripe_payments USING btree (stripe_payment_id);


--
-- Name: int_stripe_payments_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_stripe_payments_user_id_status_index ON public.int_stripe_payments USING btree (user_id, status);


--
-- Name: int_vouchers_amilon_merchant_category_category_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_merchant_category_category_id_index ON public.int_vouchers_amilon_merchant_category USING btree (category_id);


--
-- Name: int_vouchers_amilon_merchant_category_merchant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_merchant_category_merchant_id_index ON public.int_vouchers_amilon_merchant_category USING btree (merchant_id);


--
-- Name: int_vouchers_amilon_order_items_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_order_items_order_id_index ON public.int_vouchers_amilon_order_items USING btree (order_id);


--
-- Name: int_vouchers_amilon_order_items_product_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_order_items_product_id_index ON public.int_vouchers_amilon_order_items USING btree (product_id);


--
-- Name: int_vouchers_amilon_orders_currency_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_currency_index ON public.int_vouchers_amilon_orders USING btree (currency);


--
-- Name: int_vouchers_amilon_orders_external_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_external_order_id_index ON public.int_vouchers_amilon_orders USING btree (external_order_id);


--
-- Name: int_vouchers_amilon_orders_merchant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_merchant_id_index ON public.int_vouchers_amilon_orders USING btree (merchant_id);


--
-- Name: int_vouchers_amilon_orders_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_order_id_index ON public.int_vouchers_amilon_orders USING btree (order_id);


--
-- Name: int_vouchers_amilon_orders_order_recovered_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_order_recovered_id_index ON public.int_vouchers_amilon_orders USING btree (order_recovered_id);


--
-- Name: int_vouchers_amilon_orders_payment_method_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_payment_method_index ON public.int_vouchers_amilon_orders USING btree (payment_method);


--
-- Name: int_vouchers_amilon_orders_product_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_product_id_index ON public.int_vouchers_amilon_orders USING btree (product_id);


--
-- Name: int_vouchers_amilon_orders_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_status_index ON public.int_vouchers_amilon_orders USING btree (status);


--
-- Name: int_vouchers_amilon_orders_stripe_payment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_stripe_payment_id_index ON public.int_vouchers_amilon_orders USING btree (stripe_payment_id);


--
-- Name: int_vouchers_amilon_orders_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_orders_user_id_index ON public.int_vouchers_amilon_orders USING btree (user_id);


--
-- Name: int_vouchers_amilon_products_category_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_products_category_id_index ON public.int_vouchers_amilon_products USING btree (category_id);


--
-- Name: int_vouchers_amilon_products_country_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_products_country_index ON public.int_vouchers_amilon_products USING btree (country);


--
-- Name: int_vouchers_amilon_products_merchant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_products_merchant_id_index ON public.int_vouchers_amilon_products USING btree (merchant_id);


--
-- Name: int_vouchers_amilon_products_product_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX int_vouchers_amilon_products_product_code_index ON public.int_vouchers_amilon_products USING btree (product_code);


--
-- Name: invoice_generation_batches_batch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invoice_generation_batches_batch_id_index ON public.invoice_generation_batches USING btree (batch_id);


--
-- Name: invoice_generation_batches_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invoice_generation_batches_status_index ON public.invoice_generation_batches USING btree (status);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: llm_requests_requestable_type_requestable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_requests_requestable_type_requestable_id_index ON public.llm_requests USING btree (requestable_type, requestable_id);


--
-- Name: media_model_type_model_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_model_type_model_id_index ON public.media USING btree (model_type, model_id);


--
-- Name: media_order_column_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_order_column_index ON public.media USING btree (order_column);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_uuid, model_type);


--
-- Name: model_has_permissions_team_foreign_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_team_foreign_key_index ON public.model_has_permissions USING btree (team_id);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_uuid, model_type);


--
-- Name: model_has_roles_team_foreign_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_team_foreign_key_index ON public.model_has_roles USING btree (team_id);


--
-- Name: module_pricing_history_changed_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX module_pricing_history_changed_by_index ON public.module_pricing_history USING btree (changed_by);


--
-- Name: module_pricing_history_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX module_pricing_history_created_at_index ON public.module_pricing_history USING btree (created_at);


--
-- Name: module_pricing_history_entity_id_entity_type_valid_from_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX module_pricing_history_entity_id_entity_type_valid_from_index ON public.module_pricing_history USING btree (entity_id, entity_type, valid_from);


--
-- Name: module_pricing_history_module_id_entity_id_entity_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX module_pricing_history_module_id_entity_id_entity_type_index ON public.module_pricing_history USING btree (module_id, entity_id, entity_type);


--
-- Name: module_pricing_history_module_id_valid_from_valid_until_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX module_pricing_history_module_id_valid_from_valid_until_index ON public.module_pricing_history USING btree (module_id, valid_from, valid_until);


--
-- Name: modules_is_core_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modules_is_core_index ON public.modules USING btree (is_core);


--
-- Name: notification_topic_subscriptions_notification_topic_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_topic_subscriptions_notification_topic_id_index ON public.notification_topic_subscriptions USING btree (notification_topic_id);


--
-- Name: notification_topic_subscriptions_push_subscription_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_topic_subscriptions_push_subscription_id_index ON public.notification_topic_subscriptions USING btree (push_subscription_id);


--
-- Name: notification_topics_financer_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_topics_financer_id_is_active_index ON public.notification_topics USING btree (financer_id, is_active);


--
-- Name: notification_topics_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_topics_is_active_index ON public.notification_topics USING btree (is_active);


--
-- Name: pulse_aggregates_period_bucket_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pulse_aggregates_period_bucket_index ON public.pulse_aggregates USING btree (period, bucket);


--
-- Name: pulse_aggregates_period_type_aggregate_bucket_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pulse_aggregates_period_type_aggregate_bucket_index ON public.pulse_aggregates USING btree (period, type, aggregate, bucket);


--
-- Name: pulse_aggregates_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pulse_aggregates_type_index ON public.pulse_aggregates USING btree (type);


--
-- Name: pulse_entries_key_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pulse_entries_key_hash_index ON public.pulse_entries USING btree (key_hash);


--
-- Name: pulse_entries_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pulse_entries_timestamp_index ON public.pulse_entries USING btree ("timestamp");


--
-- Name: pulse_entries_timestamp_type_key_hash_value_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pulse_entries_timestamp_type_key_hash_value_index ON public.pulse_entries USING btree ("timestamp", type, key_hash, value);


--
-- Name: pulse_entries_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pulse_entries_type_index ON public.pulse_entries USING btree (type);


--
-- Name: pulse_values_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pulse_values_timestamp_index ON public.pulse_values USING btree ("timestamp");


--
-- Name: pulse_values_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pulse_values_type_index ON public.pulse_values USING btree (type);


--
-- Name: push_events_event_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_events_event_type_index ON public.push_events USING btree (event_type);


--
-- Name: push_events_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_events_occurred_at_index ON public.push_events USING btree (occurred_at);


--
-- Name: push_events_push_notification_id_event_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_events_push_notification_id_event_type_index ON public.push_events USING btree (push_notification_id, event_type);


--
-- Name: push_events_push_subscription_id_event_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_events_push_subscription_id_event_type_index ON public.push_events USING btree (push_subscription_id, event_type);


--
-- Name: push_notifications_author_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_notifications_author_id_index ON public.push_notifications USING btree (author_id);


--
-- Name: push_notifications_sent_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_notifications_sent_at_index ON public.push_notifications USING btree (sent_at);


--
-- Name: push_notifications_status_scheduled_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_notifications_status_scheduled_at_index ON public.push_notifications USING btree (status, scheduled_at);


--
-- Name: push_notifications_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_notifications_type_index ON public.push_notifications USING btree (type);


--
-- Name: push_subscriptions_device_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_subscriptions_device_type_index ON public.push_subscriptions USING btree (device_type);


--
-- Name: push_subscriptions_last_active_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_subscriptions_last_active_at_index ON public.push_subscriptions USING btree (last_active_at);


--
-- Name: push_subscriptions_user_id_push_enabled_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_subscriptions_user_id_push_enabled_index ON public.push_subscriptions USING btree (user_id, push_enabled);


--
-- Name: roles_team_foreign_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX roles_team_foreign_key_index ON public.roles USING btree (team_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: snapshots_aggregate_uuid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX snapshots_aggregate_uuid_index ON public.snapshots USING btree (aggregate_uuid);


--
-- Name: stored_events_aggregate_uuid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stored_events_aggregate_uuid_index ON public.stored_events USING btree (aggregate_uuid);


--
-- Name: stored_events_event_class_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stored_events_event_class_index ON public.stored_events USING btree (event_class);


--
-- Name: telescope_entries_batch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_batch_id_index ON public.telescope_entries USING btree (batch_id);


--
-- Name: telescope_entries_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_created_at_index ON public.telescope_entries USING btree (created_at);


--
-- Name: telescope_entries_family_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_family_hash_index ON public.telescope_entries USING btree (family_hash);


--
-- Name: telescope_entries_tags_tag_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_tags_tag_index ON public.telescope_entries_tags USING btree (tag);


--
-- Name: telescope_entries_type_should_display_on_index_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_type_should_display_on_index_index ON public.telescope_entries USING btree (type, should_display_on_index);


--
-- Name: translation_migrations_interface_origin_version_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX translation_migrations_interface_origin_version_index ON public.translation_migrations USING btree (interface_origin, version);


--
-- Name: translation_migrations_status_batch_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX translation_migrations_status_batch_number_index ON public.translation_migrations USING btree (status, batch_number);


--
-- Name: translation_migrations_version_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX translation_migrations_version_index ON public.translation_migrations USING btree (version);


--
-- Name: users_cognito_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_cognito_id_index ON public.users USING btree (cognito_id);


--
-- Name: users_phone_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_phone_index ON public.users USING btree (phone);


--
-- Name: users_stripe_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_stripe_id_index ON public.users USING btree (stripe_id);


--
-- Name: admin_audit_logs admin_audit_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admin_audit_logs
    ADD CONSTRAINT admin_audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: division_balances division_balances_division_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.division_balances
    ADD CONSTRAINT division_balances_division_id_foreign FOREIGN KEY (division_id) REFERENCES public.divisions(id);


--
-- Name: financer_user financer_user_financer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.financer_user
    ADD CONSTRAINT financer_user_financer_id_foreign FOREIGN KEY (financer_id) REFERENCES public.financers(id) ON DELETE CASCADE;


--
-- Name: financer_user financer_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.financer_user
    ADD CONSTRAINT financer_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: financers financers_division_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.financers
    ADD CONSTRAINT financers_division_id_foreign FOREIGN KEY (division_id) REFERENCES public.divisions(id);


--
-- Name: financers financers_representative_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.financers
    ADD CONSTRAINT financers_representative_id_foreign FOREIGN KEY (representative_id) REFERENCES public.users(id);


--
-- Name: int_communication_rh_article_interactions int_communication_rh_article_interactions_article_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_interactions
    ADD CONSTRAINT int_communication_rh_article_interactions_article_id_foreign FOREIGN KEY (article_id) REFERENCES public.int_communication_rh_articles(id) ON DELETE CASCADE;


--
-- Name: int_communication_rh_article_interactions int_communication_rh_article_interactions_article_translation_i; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_interactions
    ADD CONSTRAINT int_communication_rh_article_interactions_article_translation_i FOREIGN KEY (article_translation_id) REFERENCES public.int_communication_rh_article_translations(id) ON DELETE CASCADE;


--
-- Name: int_communication_rh_article_interactions int_communication_rh_article_interactions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_interactions
    ADD CONSTRAINT int_communication_rh_article_interactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: int_communication_rh_article_tag int_communication_rh_article_tag_article_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_tag
    ADD CONSTRAINT int_communication_rh_article_tag_article_id_foreign FOREIGN KEY (article_id) REFERENCES public.int_communication_rh_articles(id) ON DELETE CASCADE;


--
-- Name: int_communication_rh_article_tag int_communication_rh_article_tag_tag_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_tag
    ADD CONSTRAINT int_communication_rh_article_tag_tag_id_foreign FOREIGN KEY (tag_id) REFERENCES public.int_communication_rh_tags(id) ON DELETE CASCADE;


--
-- Name: int_communication_rh_article_translations int_communication_rh_article_translations_article_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_translations
    ADD CONSTRAINT int_communication_rh_article_translations_article_id_foreign FOREIGN KEY (article_id) REFERENCES public.int_communication_rh_articles(id) ON DELETE CASCADE;


--
-- Name: int_communication_rh_article_versions int_communication_rh_article_versions_article_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_versions
    ADD CONSTRAINT int_communication_rh_article_versions_article_id_foreign FOREIGN KEY (article_id) REFERENCES public.int_communication_rh_articles(id) ON DELETE CASCADE;


--
-- Name: int_communication_rh_article_versions int_communication_rh_article_versions_article_translation_id_fo; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_versions
    ADD CONSTRAINT int_communication_rh_article_versions_article_translation_id_fo FOREIGN KEY (article_translation_id) REFERENCES public.int_communication_rh_article_translations(id) ON DELETE CASCADE;


--
-- Name: int_communication_rh_article_versions int_communication_rh_article_versions_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_versions
    ADD CONSTRAINT int_communication_rh_article_versions_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id);


--
-- Name: int_communication_rh_article_versions int_communication_rh_article_versions_illustration_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_article_versions
    ADD CONSTRAINT int_communication_rh_article_versions_illustration_id_foreign FOREIGN KEY (illustration_id) REFERENCES public.media(id) ON DELETE SET NULL;


--
-- Name: int_communication_rh_articles int_communication_rh_articles_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_articles
    ADD CONSTRAINT int_communication_rh_articles_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id);


--
-- Name: int_communication_rh_articles int_communication_rh_articles_financer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_articles
    ADD CONSTRAINT int_communication_rh_articles_financer_id_foreign FOREIGN KEY (financer_id) REFERENCES public.financers(id);


--
-- Name: int_communication_rh_tags int_communication_rh_tags_financer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_communication_rh_tags
    ADD CONSTRAINT int_communication_rh_tags_financer_id_foreign FOREIGN KEY (financer_id) REFERENCES public.financers(id);


--
-- Name: int_outils_rh_link_user int_outils_rh_link_user_link_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_outils_rh_link_user
    ADD CONSTRAINT int_outils_rh_link_user_link_id_foreign FOREIGN KEY (link_id) REFERENCES public.int_outils_rh_links(id);


--
-- Name: int_outils_rh_link_user int_outils_rh_link_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_outils_rh_link_user
    ADD CONSTRAINT int_outils_rh_link_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: int_outils_rh_links int_outils_rh_links_financer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_outils_rh_links
    ADD CONSTRAINT int_outils_rh_links_financer_id_foreign FOREIGN KEY (financer_id) REFERENCES public.financers(id);


--
-- Name: int_stripe_payments int_stripe_payments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_stripe_payments
    ADD CONSTRAINT int_stripe_payments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: int_vouchers_amilon_merchant_category int_vouchers_amilon_merchant_category_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_merchant_category
    ADD CONSTRAINT int_vouchers_amilon_merchant_category_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.int_vouchers_amilon_categories(id);


--
-- Name: int_vouchers_amilon_merchant_category int_vouchers_amilon_merchant_category_merchant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_merchant_category
    ADD CONSTRAINT int_vouchers_amilon_merchant_category_merchant_id_foreign FOREIGN KEY (merchant_id) REFERENCES public.int_vouchers_amilon_merchants(id);


--
-- Name: int_vouchers_amilon_order_items int_vouchers_amilon_order_items_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_order_items
    ADD CONSTRAINT int_vouchers_amilon_order_items_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.int_vouchers_amilon_orders(id) ON DELETE CASCADE;


--
-- Name: int_vouchers_amilon_order_items int_vouchers_amilon_order_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_order_items
    ADD CONSTRAINT int_vouchers_amilon_order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.int_vouchers_amilon_products(id) ON DELETE CASCADE;


--
-- Name: int_vouchers_amilon_orders int_vouchers_amilon_orders_order_recovered_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_orders
    ADD CONSTRAINT int_vouchers_amilon_orders_order_recovered_id_foreign FOREIGN KEY (order_recovered_id) REFERENCES public.int_vouchers_amilon_orders(id) ON DELETE SET NULL;


--
-- Name: int_vouchers_amilon_orders int_vouchers_amilon_orders_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_orders
    ADD CONSTRAINT int_vouchers_amilon_orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: int_vouchers_amilon_products int_vouchers_amilon_products_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_products
    ADD CONSTRAINT int_vouchers_amilon_products_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.int_vouchers_amilon_categories(id) ON DELETE SET NULL;


--
-- Name: int_vouchers_amilon_products int_vouchers_amilon_products_merchant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.int_vouchers_amilon_products
    ADD CONSTRAINT int_vouchers_amilon_products_merchant_id_foreign FOREIGN KEY (merchant_id) REFERENCES public.int_vouchers_amilon_merchants(merchant_id);


--
-- Name: invited_users invited_users_financer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invited_users
    ADD CONSTRAINT invited_users_financer_id_foreign FOREIGN KEY (financer_id) REFERENCES public.financers(id);


--
-- Name: invoice_items invoice_items_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoice_items
    ADD CONSTRAINT invoice_items_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES public.invoices(id) ON DELETE CASCADE;


--
-- Name: invoice_items invoice_items_module_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoice_items
    ADD CONSTRAINT invoice_items_module_id_foreign FOREIGN KEY (module_id) REFERENCES public.modules(id) ON DELETE SET NULL;


--
-- Name: llm_requests llm_requests_financer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.llm_requests
    ADD CONSTRAINT llm_requests_financer_id_foreign FOREIGN KEY (financer_id) REFERENCES public.financers(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id);


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id);


--
-- Name: module_pricing_history module_pricing_history_changed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_pricing_history
    ADD CONSTRAINT module_pricing_history_changed_by_foreign FOREIGN KEY (changed_by) REFERENCES public.users(id);


--
-- Name: module_pricing_history module_pricing_history_module_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_pricing_history
    ADD CONSTRAINT module_pricing_history_module_id_foreign FOREIGN KEY (module_id) REFERENCES public.modules(id);


--
-- Name: notification_topics notification_topics_financer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_topics
    ADD CONSTRAINT notification_topics_financer_id_foreign FOREIGN KEY (financer_id) REFERENCES public.financers(id);


--
-- Name: push_events push_events_push_notification_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_events
    ADD CONSTRAINT push_events_push_notification_id_foreign FOREIGN KEY (push_notification_id) REFERENCES public.push_notifications(id);


--
-- Name: push_events push_events_push_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_events
    ADD CONSTRAINT push_events_push_subscription_id_foreign FOREIGN KEY (push_subscription_id) REFERENCES public.push_subscriptions(id);


--
-- Name: push_notifications push_notifications_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_notifications
    ADD CONSTRAINT push_notifications_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id);


--
-- Name: push_subscriptions push_subscriptions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id);


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id);


--
-- Name: telescope_entries_tags telescope_entries_tags_entry_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries_tags
    ADD CONSTRAINT telescope_entries_tags_entry_uuid_foreign FOREIGN KEY (entry_uuid) REFERENCES public.telescope_entries(uuid);


--
-- Name: translation_values translation_values_translation_key_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_values
    ADD CONSTRAINT translation_values_translation_key_id_foreign FOREIGN KEY (translation_key_id) REFERENCES public.translation_keys(id);


--
-- Name: user_pinned_modules user_pinned_modules_module_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_pinned_modules
    ADD CONSTRAINT user_pinned_modules_module_id_foreign FOREIGN KEY (module_id) REFERENCES public.modules(id);


--
-- Name: user_pinned_modules user_pinned_modules_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_pinned_modules
    ADD CONSTRAINT user_pinned_modules_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- PostgreSQL database dump complete
--

\unrestrict xInddNEfnWpmjlooUgCAWNgbDsEx7V5w83OccUgZaQ9JVPKa05uABLWbDeBBVpb

--
-- PostgreSQL database dump
--

\restrict yUKklUc24jwP36PCAGjitBcdtR4YY4PNwdfWkSt68SZos0fEpQMf3WAY4lDjUw3

-- Dumped from database version 15.14 (Debian 15.14-1.pgdg13+1)
-- Dumped by pg_dump version 15.14 (Debian 15.14-0+deb12u1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2025_06_10_000000_create_amilon_merchants_table	1
2	2025_06_11_000000_create_amilon_products_table	1
3	2025_06_15_000000_create_amilon_orders_table	1
4	2025_06_16_000000_add_payment_id_to_amilon_orders_table	1
5	2025_06_16_000001_create_amilon_order_items_table	1
6	2025_06_16_120245_create_activity_log_table	1
7	2025_06_16_120245_create_cache_locks_table	1
8	2025_06_16_120245_create_cache_table	1
9	2025_06_16_120245_create_credit_balances_table	1
10	2025_06_16_120245_create_credits_table	1
11	2025_06_16_120245_create_division_integration_table	1
12	2025_06_16_120245_create_division_module_table	1
13	2025_06_16_120245_create_divisions_table	1
14	2025_06_16_120245_create_engagement_logs_table	1
15	2025_06_16_120245_create_engagement_metrics_table	1
16	2025_06_16_120245_create_failed_jobs_table	1
17	2025_06_16_120245_create_financer_integration_table	1
18	2025_06_16_120245_create_financer_module_table	1
19	2025_06_16_120245_create_financer_user_table	1
20	2025_06_16_120245_create_financers_table	1
21	2025_06_16_120245_create_int_communication_rh_article_interactions_table	1
22	2025_06_16_120245_create_int_communication_rh_article_tag_table	1
23	2025_06_16_120245_create_int_communication_rh_article_translations_table	1
24	2025_06_16_120245_create_int_communication_rh_article_versions_table	1
25	2025_06_16_120245_create_int_communication_rh_articles_table	1
26	2025_06_16_120245_create_int_communication_rh_tags_table	1
27	2025_06_16_120245_create_int_outils_rh_link_user_table	1
28	2025_06_16_120245_create_int_outils_rh_links_table	1
29	2025_06_16_120245_create_integrations_table	1
30	2025_06_16_120245_create_invited_users_table	1
31	2025_06_16_120245_create_job_batches_table	1
32	2025_06_16_120245_create_jobs_table	1
33	2025_06_16_120245_create_llm_requests_table	1
34	2025_06_16_120245_create_media_table	1
35	2025_06_16_120245_create_model_has_permissions_table	1
36	2025_06_16_120245_create_model_has_roles_table	1
37	2025_06_16_120245_create_modules_table	1
38	2025_06_16_120245_create_password_reset_tokens_table	1
39	2025_06_16_120245_create_permissions_table	1
40	2025_06_16_120245_create_role_has_permissions_table	1
41	2025_06_16_120245_create_roles_table	1
42	2025_06_16_120245_create_sessions_table	1
43	2025_06_16_120245_create_snapshots_table	1
44	2025_06_16_120245_create_stored_events_table	1
45	2025_06_16_120245_create_teams_table	1
46	2025_06_16_120245_create_telescope_entries_table	1
47	2025_06_16_120245_create_telescope_entries_tags_table	1
48	2025_06_16_120245_create_telescope_monitoring_table	1
49	2025_06_16_120245_create_translation_activity_logs_table	1
50	2025_06_16_120245_create_translation_keys_table	1
51	2025_06_16_120245_create_translation_values_table	1
52	2025_06_16_120245_create_user_pinned_modules_table	1
53	2025_06_16_120245_create_users_table	1
54	2025_06_16_120248_add_foreign_keys_to_financer_user_table	1
55	2025_06_16_120248_add_foreign_keys_to_financers_table	1
56	2025_06_16_120248_add_foreign_keys_to_int_communication_rh_article_interactions_table	1
57	2025_06_16_120248_add_foreign_keys_to_int_communication_rh_article_tag_table	1
58	2025_06_16_120248_add_foreign_keys_to_int_communication_rh_article_translations_table	1
59	2025_06_16_120248_add_foreign_keys_to_int_communication_rh_article_versions_table	1
60	2025_06_16_120248_add_foreign_keys_to_int_communication_rh_articles_table	1
61	2025_06_16_120248_add_foreign_keys_to_int_communication_rh_tags_table	1
62	2025_06_16_120248_add_foreign_keys_to_int_outils_rh_link_user_table	1
63	2025_06_16_120248_add_foreign_keys_to_int_outils_rh_links_table	1
64	2025_06_16_120248_add_foreign_keys_to_invited_users_table	1
65	2025_06_16_120248_add_foreign_keys_to_llm_requests_table	1
66	2025_06_16_120248_add_foreign_keys_to_model_has_permissions_table	1
67	2025_06_16_120248_add_foreign_keys_to_model_has_roles_table	1
68	2025_06_16_120248_add_foreign_keys_to_role_has_permissions_table	1
69	2025_06_16_120248_add_foreign_keys_to_telescope_entries_tags_table	1
70	2025_06_16_120248_add_foreign_keys_to_translation_values_table	1
71	2025_06_16_120248_add_foreign_keys_to_user_pinned_modules_table	1
72	2025_06_16_120250_add_performance_indexes_to_article_translations	1
73	2025_06_16_120251_add_performance_indexes_to_article_interactions	1
74	2025_06_16_120252_add_performance_indexes_to_article_versions	1
75	2025_06_16_120253_add_performance_indexes_to_articles	1
76	2025_06_16_120254_add_performance_indexes_to_tags	1
77	2025_06_16_120255_add_performance_indexes_to_article_tag_pivot	1
78	2025_06_16_124015_create_audits_table	1
79	2025_06_16_124016_modify_audits_table_for_uuids	1
80	2025_06_16_161746_create_test_models_table	1
81	2025_06_17_000000_add_foreign_keys_to_int_vouchers_amilon_orders_table	1
82	2025_06_17_000001_create_int_vouchers_amilon_categories_table	1
83	2025_06_17_000001_modify_user_id_in_engagement_logs_table	1
84	2025_06_17_000003_add_foreign_keys_to_int_vouchers_amilon_products_table	1
85	2025_06_19_131238_create_int_stripe_payments_table	1
86	2025_06_19_131243_add_foreign_keys_to_int_stripe_payments_table	1
87	2025_06_26_104319_add_sirh_fields_to_invited_users_table	1
88	2025_06_26_104351_rename_external_id_to_sirh_id_in_users_table	1
89	2025_06_26_104818_remove_external_id_from_financer_user_table	1
90	2025_06_26_130616_change_sirh_id_to_string_in_users_table	1
91	2025_07_02_145142_create_int_amilon_processed_webhook_events_table	1
92	2025_07_02_150608_add_error_message_to_int_stripe_payments_table	1
93	2025_07_02_153108_add_metadata_and_voucher_code_to_int_vouchers_amilon_orders_table	1
94	2025_07_04_153408_remove_category_from_amilon_products_and_merchants_tables	1
95	2025_07_04_161028_create_merchant_category_pivot_table	1
96	2025_07_04_161055_add_foreign_keys_to_merchant_category_table	1
97	2025_07_08_163825_add_balance_payment_fields_to_amilon_orders_table	1
98	2025_07_11_110255_add_recovery_fields_to_int_vouchers_orders_table	1
99	2025_07_22_140434_install_reverb_broadcasting	1
100	2025_07_23_133305_add_status_to_financers_table	1
101	2025_07_23_133339_add_bic_to_financers_table	1
102	2025_07_23_133408_add_company_number_to_financers_table	1
103	2025_07_26_061925_add_roles_to_financer_user_table	1
104	2025_07_29_000001_add_status_to_divisions_table	1
105	2025_07_30_134743_update_engagement_metrics_table_for_periods	1
106	2025_07_30_151806_create_pulse_tables	1
107	2025_08_01_064643_add_net_price_to_int_vouchers_amilon_products_table	1
108	2025_08_01_130733_add_financer_id_to_audits_table	1
109	2025_08_01_144314_create_test_audit_models_table	1
110	2025_08_02_101107_add_discount_to_int_vouchers_amilon_products_table	1
111	2025_08_02_102539_add_average_discount_to_int_vouchers_amilon_merchants_table	1
112	2025_08_03_002816_fix_sessions_table_user_id_to_uuid	1
113	2025_08_03_011218_add_interface_origin_to_translation_keys_table	1
114	2025_08_03_232853_fix_translation_keys_unique_constraint	1
115	2025_08_05_add_payment_tracking_to_amilon_orders_table	1
116	2025_08_05_remove_deprecated_payment_columns_from_amilon_orders_table	1
117	2025_08_07_233230_add_missing_fields_to_amilon_orders_table	1
118	2025_08_08_000103_rename_download_url_to_voucher_url_in_amilon_orders_table	1
119	2025_08_08_071321_remove_category_column_from_amilon_products_table	1
120	2025_08_08_084305_convert_amilon_product_prices_to_cents	1
121	2025_08_08_101752_add_cancelled_at_to_stripe_payments_table	1
122	2025_08_18_095049_convert_amilon_orders_amounts_to_cents	1
123	2025_08_21_123706_change_amount_to_integer_in_int_stripe_payments_table	1
124	2025_08_22_070343_increase_illustration_field_size_in_article_versions_table	1
125	2025_08_26_071436_change_illustration_to_illustration_id_in_article_versions_table	1
126	2025_08_26_072620_add_foreign_key_to_article_versions_table	1
127	2025_08_26_add_resources_count_query_to_integrations_table	1
128	2025_08_27_060030_create_translation_migrations_table	1
129	2025_08_29_080129_remove_next_retry_at_from_int_vouchers_orders_table	1
130	2025_08_29_081504_add_order_recovered_id_to_int_vouchers_orders_table	1
131	2025_08_29_133250_create_demo_entities_table	1
132	2025_09_16_121020_add_is_core_to_modules_table	1
133	2025_09_18_085745_add_core_package_price_to_divisions_table	1
134	2025_09_18_085751_add_core_package_price_to_financers_table	1
135	2025_09_18_085756_create_module_pricing_history_table	1
136	2025_09_18_085920_add_price_to_division_module_table	1
137	2025_09_18_085926_add_price_to_financer_module_table	1
138	2025_09_18_134031_add_temporal_fields_to_module_pricing_history_table	1
139	2025_09_19_110147_add_language_to_financer_user_table	1
140	2025_09_20_000001_create_push_subscriptions_table	1
141	2025_09_20_000002_create_push_notifications_table	1
142	2025_09_20_000003_create_push_events_table	1
143	2025_09_20_000004_create_notification_topics_table	1
144	2025_09_20_000005_create_notification_topic_subscriptions_table	1
145	2025_09_27_061922_create_admin_audit_logs_table	1
146	2025_10_01_053133_add_user_index_performance_indexes	1
147	2025_10_05_130000_create_invoice_sequences_table	1
148	2025_10_05_130000_create_invoices_table	1
149	2025_10_05_130100_create_invoice_items_table	1
150	2025_10_05_130200_add_contract_start_date_to_financers_table	1
151	2025_10_05_130300_add_vat_rate_to_divisions_table	1
152	2025_10_05_130300_create_division_balances_table	1
153	2025_10_05_130400_add_contract_start_date_to_divisions_table	1
154	2025_10_05_130400_create_invoice_generation_batches_table	1
155	2025_10_05_130500_add_foreign_keys_to_invoices_table	1
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 155, true);


--
-- PostgreSQL database dump complete
--

\unrestrict yUKklUc24jwP36PCAGjitBcdtR4YY4PNwdfWkSt68SZos0fEpQMf3WAY4lDjUw3

