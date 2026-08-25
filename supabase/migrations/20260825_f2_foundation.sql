--
-- PostgreSQL database dump
--

-- Wave F2 foundation schema for the Caldas Gestão application.
--
-- Source: a clean PostgreSQL 18 cluster after `php artisan db:provision-schema`
-- (strict runtime role) and every Laravel migration present on 2026-08-25.
-- Export command: pg_dump --schema-only --schema=app --no-owner --no-privileges.
-- The pg_dump psql-only \restrict guards were removed so this remains portable SQL.
--
-- Apply the snapshot exactly once after db:provision-schema, or to a clean
-- database. CREATE SCHEMA is tolerant of the provisioner already having
-- created app; the remaining DDL is intentionally baseline/one-shot.
-- The baseline ledger below is the sole metadata exception: it prevents
-- Laravel from replaying this snapshot's migrations. There are no domain,
-- catalog, tenant, credential, or managed-Supabase-schema data rows.
-- The PostgreSQL role caldas_runtime must already exist; its login secret is
-- provisioned outside this file. Laravel bootstrap remains responsible for
-- permission-catalog and tenant data.
-- Run as the direct migration role: ALTER DEFAULT PRIVILEGES applies to that
-- role, which must also own future Laravel-migrated objects in this schema.


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: app; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA IF NOT EXISTS app;


--
-- Name: audit_events_append_only_guard(); Type: FUNCTION; Schema: app; Owner: -
--

CREATE FUNCTION app.audit_events_append_only_guard() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RAISE EXCEPTION 'audit_events is append-only; % is not permitted', TG_OP
            USING ERRCODE = '42501';
    END;
    $$;


--
-- Name: roles_system_immutability_guard(); Type: FUNCTION; Schema: app; Owner: -
--

CREATE FUNCTION app.roles_system_immutability_guard() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF TG_OP = 'DELETE' AND OLD.is_system THEN
            RAISE EXCEPTION 'system roles cannot be deleted'
                USING ERRCODE = '42501';
        END IF;

        IF TG_OP = 'UPDATE' AND (OLD.is_system OR OLD.is_system IS DISTINCT FROM NEW.is_system) THEN
            RAISE EXCEPTION 'system roles are immutable'
                USING ERRCODE = '42501';
        END IF;

        IF TG_OP = 'DELETE' THEN
            RETURN OLD;
        END IF;

        RETURN NEW;
    END;
    $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: audit_events; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.audit_events (
    id bigint NOT NULL,
    event_id uuid NOT NULL,
    tenant_id uuid,
    unit_id uuid,
    actor_user_id uuid,
    action character varying(120) NOT NULL,
    resource_type character varying(120) NOT NULL,
    resource_id uuid,
    request_id character varying(100),
    correlation_id character varying(100),
    reason character varying(500),
    metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    ip_address inet,
    user_agent_hash character(64),
    occurred_at timestamp(0) with time zone NOT NULL,
    CONSTRAINT audit_events_scope_check CHECK (((unit_id IS NULL) OR (tenant_id IS NOT NULL)))
);


--
-- Name: audit_events_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.audit_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_events_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.audit_events_id_seq OWNED BY app.audit_events.id;


--
-- Name: cache; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: entitlements; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.entitlements (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    key character varying(100) NOT NULL,
    status character varying(24) DEFAULT 'trial'::character varying NOT NULL,
    quantity bigint,
    starts_at timestamp(0) with time zone NOT NULL,
    ends_at timestamp(0) with time zone,
    source character varying(40) NOT NULL,
    config jsonb DEFAULT '{}'::jsonb NOT NULL,
    lock_version bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT entitlements_dates_check CHECK (((ends_at IS NULL) OR (ends_at > starts_at))),
    CONSTRAINT entitlements_quantity_check CHECK (((quantity IS NULL) OR (quantity >= 0))),
    CONSTRAINT entitlements_source_check CHECK (((source)::text = ANY ((ARRAY['plan'::character varying, 'trial'::character varying, 'manual'::character varying, 'integration'::character varying])::text[]))),
    CONSTRAINT entitlements_status_check CHECK (((status)::text = ANY ((ARRAY['trial'::character varying, 'active'::character varying, 'grace'::character varying, 'suspended'::character varying, 'expired'::character varying, 'revoked'::character varying])::text[])))
);


--
-- Name: failed_jobs; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.failed_jobs_id_seq OWNED BY app.failed_jobs.id;


--
-- Name: idempotency_keys; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.idempotency_keys (
    id bigint NOT NULL,
    tenant_id uuid NOT NULL,
    actor_user_id uuid,
    key character varying(200) NOT NULL,
    request_hash character(64) NOT NULL,
    status character varying(24) DEFAULT 'started'::character varying NOT NULL,
    response_code smallint,
    resource_type character varying(120),
    resource_id uuid,
    response_ref jsonb,
    expires_at timestamp(0) with time zone NOT NULL,
    completed_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT idempotency_keys_completion_check CHECK (((((status)::text = 'started'::text) AND (completed_at IS NULL)) OR (((status)::text = ANY ((ARRAY['succeeded'::character varying, 'failed'::character varying])::text[])) AND (completed_at IS NOT NULL)))),
    CONSTRAINT idempotency_keys_response_check CHECK (((((status)::text = 'started'::text) AND (response_code IS NULL) AND (response_ref IS NULL)) OR (((status)::text = ANY ((ARRAY['succeeded'::character varying, 'failed'::character varying])::text[])) AND (response_code IS NOT NULL) AND (response_ref IS NOT NULL)))),
    CONSTRAINT idempotency_keys_status_check CHECK (((status)::text = ANY ((ARRAY['started'::character varying, 'succeeded'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: idempotency_keys_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.idempotency_keys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: idempotency_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.idempotency_keys_id_seq OWNED BY app.idempotency_keys.id;


--
-- Name: inbox_events; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.inbox_events (
    id bigint NOT NULL,
    tenant_id uuid NOT NULL,
    consumer character varying(120) NOT NULL,
    event_id uuid NOT NULL,
    event_type character varying(160) NOT NULL,
    event_version smallint NOT NULL,
    correlation_id character varying(120),
    causation_id character varying(120),
    payload jsonb DEFAULT '{}'::jsonb NOT NULL,
    status character varying(24) DEFAULT 'received'::character varying NOT NULL,
    attempts integer DEFAULT 0 NOT NULL,
    received_at timestamp(0) with time zone NOT NULL,
    processed_at timestamp(0) with time zone,
    available_at timestamp(0) with time zone NOT NULL,
    last_attempt_at timestamp(0) with time zone,
    dead_at timestamp(0) with time zone,
    locked_at timestamp(0) with time zone,
    lease_until timestamp(0) with time zone,
    locked_by character varying(120),
    last_error text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT inbox_events_dead_check CHECK ((((status)::text = 'dead'::text) = (dead_at IS NOT NULL))),
    CONSTRAINT inbox_events_processed_check CHECK ((((status)::text = 'processed'::text) = (processed_at IS NOT NULL))),
    CONSTRAINT inbox_events_processing_lease_check CHECK ((((status)::text <> 'processing'::text) OR ((locked_by IS NOT NULL) AND (lease_until IS NOT NULL)))),
    CONSTRAINT inbox_events_status_check CHECK (((status)::text = ANY ((ARRAY['received'::character varying, 'processing'::character varying, 'retryable'::character varying, 'processed'::character varying, 'failed'::character varying, 'dead'::character varying])::text[])))
);


--
-- Name: inbox_events_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.inbox_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inbox_events_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.inbox_events_id_seq OWNED BY app.inbox_events.id;


--
-- Name: job_batches; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.job_batches (
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
-- Name: jobs; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.jobs_id_seq OWNED BY app.jobs.id;


--
-- Name: membership_roles; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.membership_roles (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    membership_id uuid NOT NULL,
    role_id uuid NOT NULL,
    scope_kind character varying(16) NOT NULL,
    assignment_scope character varying(120) NOT NULL,
    unit_id uuid,
    lock_version bigint DEFAULT '0'::bigint NOT NULL,
    revoked_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone NOT NULL,
    CONSTRAINT membership_roles_scope_check CHECK (((((scope_kind)::text = 'tenant'::text) AND (unit_id IS NULL) AND ((assignment_scope)::text = 'tenant'::text)) OR (((scope_kind)::text = 'unit'::text) AND (unit_id IS NOT NULL) AND ((assignment_scope)::text = ('unit:'::text || (unit_id)::text))))),
    CONSTRAINT membership_roles_scope_kind_check CHECK (((scope_kind)::text = ANY ((ARRAY['tenant'::character varying, 'unit'::character varying])::text[])))
);


--
-- Name: membership_units; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.membership_units (
    tenant_id uuid NOT NULL,
    membership_id uuid NOT NULL,
    unit_id uuid NOT NULL,
    is_primary boolean DEFAULT false NOT NULL,
    created_at timestamp(0) with time zone NOT NULL
);


--
-- Name: memberships; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.memberships (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    user_id uuid NOT NULL,
    status character varying(24) DEFAULT 'invited'::character varying NOT NULL,
    joined_at timestamp(0) with time zone,
    revoked_at timestamp(0) with time zone,
    lock_version bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT memberships_revoked_at_check CHECK (((((status)::text = 'revoked'::text) AND (revoked_at IS NOT NULL)) OR (((status)::text <> 'revoked'::text) AND (revoked_at IS NULL)))),
    CONSTRAINT memberships_status_check CHECK (((status)::text = ANY ((ARRAY['invited'::character varying, 'active'::character varying, 'suspended'::character varying, 'revoked'::character varying])::text[])))
);


--
-- Name: migrations; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.migrations_id_seq OWNED BY app.migrations.id;


--
-- Name: outbox_events; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.outbox_events (
    id bigint NOT NULL,
    event_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    unit_id uuid,
    actor_user_id uuid,
    aggregate_type character varying(120) NOT NULL,
    aggregate_id uuid NOT NULL,
    aggregate_version bigint NOT NULL,
    event_type character varying(160) NOT NULL,
    event_version smallint NOT NULL,
    correlation_id character varying(120),
    causation_id character varying(120),
    payload jsonb DEFAULT '{}'::jsonb NOT NULL,
    status character varying(24) DEFAULT 'pending'::character varying NOT NULL,
    occurred_at timestamp(0) with time zone NOT NULL,
    available_at timestamp(0) with time zone NOT NULL,
    last_attempt_at timestamp(0) with time zone,
    published_at timestamp(0) with time zone,
    dead_at timestamp(0) with time zone,
    locked_at timestamp(0) with time zone,
    lease_until timestamp(0) with time zone,
    locked_by character varying(120),
    attempts integer DEFAULT 0 NOT NULL,
    last_error text,
    created_at timestamp(0) with time zone NOT NULL,
    CONSTRAINT outbox_events_dead_check CHECK ((((status)::text = 'dead'::text) = (dead_at IS NOT NULL))),
    CONSTRAINT outbox_events_lease_check CHECK ((((status)::text <> 'publishing'::text) OR ((locked_by IS NOT NULL) AND (lease_until IS NOT NULL)))),
    CONSTRAINT outbox_events_published_check CHECK ((((status)::text = 'published'::text) = (published_at IS NOT NULL))),
    CONSTRAINT outbox_events_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'available'::character varying, 'publishing'::character varying, 'retryable'::character varying, 'dead'::character varying, 'published'::character varying])::text[]))),
    CONSTRAINT outbox_events_terminal_check CHECK ((((status)::text = ANY ((ARRAY['published'::character varying, 'dead'::character varying])::text[])) OR ((published_at IS NULL) AND (dead_at IS NULL))))
);


--
-- Name: outbox_events_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.outbox_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: outbox_events_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.outbox_events_id_seq OWNED BY app.outbox_events.id;


--
-- Name: passkeys; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.passkeys (
    id bigint NOT NULL,
    user_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    credential_id character varying(255) NOT NULL,
    credential json NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: passkeys_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.passkeys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: passkeys_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.passkeys_id_seq OWNED BY app.passkeys.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permissions; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.permissions (
    id uuid NOT NULL,
    key character varying(120) NOT NULL,
    description text NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT permissions_key_check CHECK (((key)::text ~ '^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*(\:[a-z][a-z0-9_-]*)?$'::text))
);


--
-- Name: role_permissions; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.role_permissions (
    tenant_id uuid NOT NULL,
    role_id uuid NOT NULL,
    permission_id uuid NOT NULL,
    created_at timestamp(0) with time zone NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.roles (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    key character varying(80) NOT NULL,
    name character varying(120) NOT NULL,
    description text,
    is_system boolean DEFAULT false NOT NULL,
    lock_version bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: sessions; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.sessions (
    id character varying(255) NOT NULL,
    user_id uuid,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: tenants; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tenants (
    id uuid NOT NULL,
    slug character varying(80) NOT NULL,
    name character varying(160) NOT NULL,
    legal_name character varying(200),
    status character varying(24) DEFAULT 'active'::character varying NOT NULL,
    timezone character varying(64) DEFAULT 'UTC'::character varying NOT NULL,
    default_currency character(3) DEFAULT 'BRL'::bpchar NOT NULL,
    lock_version bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT tenants_currency_check CHECK ((default_currency ~ '^[A-Z]{3}$'::text)),
    CONSTRAINT tenants_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'suspended'::character varying, 'closed'::character varying])::text[])))
);


--
-- Name: units; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.units (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    slug character varying(80) NOT NULL,
    name character varying(160) NOT NULL,
    status character varying(24) DEFAULT 'active'::character varying NOT NULL,
    timezone character varying(64),
    address jsonb,
    lock_version bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT units_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying])::text[])))
);


--
-- Name: users; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.users (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(320) NOT NULL,
    email_normalized character varying(320) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    two_factor_secret text,
    two_factor_recovery_codes text,
    two_factor_confirmed_at timestamp(0) without time zone
);


--
-- Name: audit_events id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.audit_events ALTER COLUMN id SET DEFAULT nextval('app.audit_events_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.failed_jobs ALTER COLUMN id SET DEFAULT nextval('app.failed_jobs_id_seq'::regclass);


--
-- Name: idempotency_keys id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.idempotency_keys ALTER COLUMN id SET DEFAULT nextval('app.idempotency_keys_id_seq'::regclass);


--
-- Name: inbox_events id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.inbox_events ALTER COLUMN id SET DEFAULT nextval('app.inbox_events_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.jobs ALTER COLUMN id SET DEFAULT nextval('app.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.migrations ALTER COLUMN id SET DEFAULT nextval('app.migrations_id_seq'::regclass);


--
-- Name: outbox_events id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.outbox_events ALTER COLUMN id SET DEFAULT nextval('app.outbox_events_id_seq'::regclass);


--
-- Name: passkeys id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.passkeys ALTER COLUMN id SET DEFAULT nextval('app.passkeys_id_seq'::regclass);


--
-- Name: audit_events audit_events_event_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.audit_events
    ADD CONSTRAINT audit_events_event_id_unique UNIQUE (event_id);


--
-- Name: audit_events audit_events_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.audit_events
    ADD CONSTRAINT audit_events_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: entitlements entitlements_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.entitlements
    ADD CONSTRAINT entitlements_pkey PRIMARY KEY (id);


--
-- Name: entitlements entitlements_tenant_id_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.entitlements
    ADD CONSTRAINT entitlements_tenant_id_id_unique UNIQUE (tenant_id, id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: idempotency_keys idempotency_keys_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.idempotency_keys
    ADD CONSTRAINT idempotency_keys_pkey PRIMARY KEY (id);


--
-- Name: inbox_events inbox_events_consumer_event_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.inbox_events
    ADD CONSTRAINT inbox_events_consumer_event_id_unique UNIQUE (consumer, event_id);


--
-- Name: inbox_events inbox_events_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.inbox_events
    ADD CONSTRAINT inbox_events_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: membership_roles membership_roles_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.membership_roles
    ADD CONSTRAINT membership_roles_pkey PRIMARY KEY (id);


--
-- Name: membership_roles membership_roles_tenant_id_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.membership_roles
    ADD CONSTRAINT membership_roles_tenant_id_id_unique UNIQUE (tenant_id, id);


--
-- Name: membership_units membership_units_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.membership_units
    ADD CONSTRAINT membership_units_pkey PRIMARY KEY (membership_id, unit_id);


--
-- Name: membership_units membership_units_tenant_id_membership_id_unit_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.membership_units
    ADD CONSTRAINT membership_units_tenant_id_membership_id_unit_id_unique UNIQUE (tenant_id, membership_id, unit_id);


--
-- Name: memberships memberships_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.memberships
    ADD CONSTRAINT memberships_pkey PRIMARY KEY (id);


--
-- Name: memberships memberships_tenant_id_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.memberships
    ADD CONSTRAINT memberships_tenant_id_id_unique UNIQUE (tenant_id, id);


--
-- Name: memberships memberships_tenant_id_user_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.memberships
    ADD CONSTRAINT memberships_tenant_id_user_id_unique UNIQUE (tenant_id, user_id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: outbox_events outbox_events_event_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.outbox_events
    ADD CONSTRAINT outbox_events_event_id_unique UNIQUE (event_id);


--
-- Name: outbox_events outbox_events_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.outbox_events
    ADD CONSTRAINT outbox_events_pkey PRIMARY KEY (id);


--
-- Name: passkeys passkeys_credential_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.passkeys
    ADD CONSTRAINT passkeys_credential_id_unique UNIQUE (credential_id);


--
-- Name: passkeys passkeys_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.passkeys
    ADD CONSTRAINT passkeys_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_key_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.permissions
    ADD CONSTRAINT permissions_key_unique UNIQUE (key);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: role_permissions role_permissions_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.role_permissions
    ADD CONSTRAINT role_permissions_pkey PRIMARY KEY (role_id, permission_id);


--
-- Name: role_permissions role_permissions_tenant_id_role_id_permission_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.role_permissions
    ADD CONSTRAINT role_permissions_tenant_id_role_id_permission_id_unique UNIQUE (tenant_id, role_id, permission_id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_tenant_id_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.roles
    ADD CONSTRAINT roles_tenant_id_id_unique UNIQUE (tenant_id, id);


--
-- Name: roles roles_tenant_id_key_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.roles
    ADD CONSTRAINT roles_tenant_id_key_unique UNIQUE (tenant_id, key);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tenants
    ADD CONSTRAINT tenants_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_slug_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tenants
    ADD CONSTRAINT tenants_slug_unique UNIQUE (slug);


--
-- Name: units units_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.units
    ADD CONSTRAINT units_pkey PRIMARY KEY (id);


--
-- Name: units units_tenant_id_id_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.units
    ADD CONSTRAINT units_tenant_id_id_unique UNIQUE (tenant_id, id);


--
-- Name: units units_tenant_id_slug_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.units
    ADD CONSTRAINT units_tenant_id_slug_unique UNIQUE (tenant_id, slug);


--
-- Name: users users_email_normalized_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.users
    ADD CONSTRAINT users_email_normalized_unique UNIQUE (email_normalized);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: audit_events_actor_user_id_occurred_at_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX audit_events_actor_user_id_occurred_at_index ON app.audit_events USING btree (actor_user_id, occurred_at);


--
-- Name: audit_events_tenant_id_occurred_at_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX audit_events_tenant_id_occurred_at_index ON app.audit_events USING btree (tenant_id, occurred_at);


--
-- Name: audit_events_tenant_id_resource_type_resource_id_occurred_at_in; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX audit_events_tenant_id_resource_type_resource_id_occurred_at_in ON app.audit_events USING btree (tenant_id, resource_type, resource_id, occurred_at);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX cache_expiration_index ON app.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON app.cache_locks USING btree (expiration);


--
-- Name: entitlements_active_unique; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX entitlements_active_unique ON app.entitlements USING btree (tenant_id, key) WHERE ((status)::text = ANY ((ARRAY['trial'::character varying, 'active'::character varying, 'grace'::character varying, 'suspended'::character varying])::text[]));


--
-- Name: entitlements_tenant_id_status_key_starts_at_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX entitlements_tenant_id_status_key_starts_at_index ON app.entitlements USING btree (tenant_id, status, key, starts_at);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON app.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: idempotency_keys_scope_unique; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX idempotency_keys_scope_unique ON app.idempotency_keys USING btree (tenant_id, actor_user_id, key) NULLS NOT DISTINCT;


--
-- Name: idempotency_keys_tenant_id_status_expires_at_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idempotency_keys_tenant_id_status_expires_at_index ON app.idempotency_keys USING btree (tenant_id, status, expires_at);


--
-- Name: inbox_events_tenant_id_status_available_at_lease_until_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX inbox_events_tenant_id_status_available_at_lease_until_index ON app.inbox_events USING btree (tenant_id, status, available_at, lease_until);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX jobs_queue_index ON app.jobs USING btree (queue);


--
-- Name: membership_roles_active_unique; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX membership_roles_active_unique ON app.membership_roles USING btree (tenant_id, membership_id, role_id, unit_id) NULLS NOT DISTINCT WHERE (revoked_at IS NULL);


--
-- Name: membership_roles_membership_id_revoked_at_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX membership_roles_membership_id_revoked_at_index ON app.membership_roles USING btree (membership_id, revoked_at);


--
-- Name: membership_roles_tenant_id_scope_kind_unit_id_membership_id_ind; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX membership_roles_tenant_id_scope_kind_unit_id_membership_id_ind ON app.membership_roles USING btree (tenant_id, scope_kind, unit_id, membership_id);


--
-- Name: membership_units_primary_unique; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX membership_units_primary_unique ON app.membership_units USING btree (membership_id) WHERE (is_primary IS TRUE);


--
-- Name: membership_units_tenant_id_unit_id_membership_id_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX membership_units_tenant_id_unit_id_membership_id_index ON app.membership_units USING btree (tenant_id, unit_id, membership_id);


--
-- Name: memberships_tenant_id_status_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX memberships_tenant_id_status_index ON app.memberships USING btree (tenant_id, status);


--
-- Name: memberships_user_id_status_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX memberships_user_id_status_index ON app.memberships USING btree (user_id, status);


--
-- Name: outbox_events_correlation_id_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX outbox_events_correlation_id_index ON app.outbox_events USING btree (correlation_id);


--
-- Name: outbox_events_status_lease_until_available_at_id_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX outbox_events_status_lease_until_available_at_id_index ON app.outbox_events USING btree (status, lease_until, available_at, id);


--
-- Name: outbox_events_tenant_id_occurred_at_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX outbox_events_tenant_id_occurred_at_index ON app.outbox_events USING btree (tenant_id, occurred_at);


--
-- Name: passkeys_user_id_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX passkeys_user_id_index ON app.passkeys USING btree (user_id);


--
-- Name: role_permissions_tenant_id_permission_id_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX role_permissions_tenant_id_permission_id_index ON app.role_permissions USING btree (tenant_id, permission_id);


--
-- Name: roles_tenant_id_is_system_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX roles_tenant_id_is_system_index ON app.roles USING btree (tenant_id, is_system);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX sessions_last_activity_index ON app.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX sessions_user_id_index ON app.sessions USING btree (user_id);


--
-- Name: units_tenant_id_status_name_index; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX units_tenant_id_status_name_index ON app.units USING btree (tenant_id, status, name);


--
-- Name: audit_events audit_events_append_only_guard; Type: TRIGGER; Schema: app; Owner: -
--

CREATE TRIGGER audit_events_append_only_guard BEFORE DELETE OR UPDATE ON app.audit_events FOR EACH ROW EXECUTE FUNCTION app.audit_events_append_only_guard();


--
-- Name: roles roles_system_immutability_guard; Type: TRIGGER; Schema: app; Owner: -
--

CREATE TRIGGER roles_system_immutability_guard BEFORE DELETE OR UPDATE ON app.roles FOR EACH ROW EXECUTE FUNCTION app.roles_system_immutability_guard();


--
-- Name: audit_events audit_events_actor_user_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.audit_events
    ADD CONSTRAINT audit_events_actor_user_id_foreign FOREIGN KEY (actor_user_id) REFERENCES app.users(id) ON DELETE SET NULL;


--
-- Name: audit_events audit_events_tenant_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.audit_events
    ADD CONSTRAINT audit_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES app.tenants(id) ON DELETE RESTRICT;


--
-- Name: audit_events audit_events_tenant_id_unit_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.audit_events
    ADD CONSTRAINT audit_events_tenant_id_unit_id_foreign FOREIGN KEY (tenant_id, unit_id) REFERENCES app.units(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: entitlements entitlements_tenant_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.entitlements
    ADD CONSTRAINT entitlements_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES app.tenants(id) ON DELETE RESTRICT;


--
-- Name: idempotency_keys idempotency_keys_actor_user_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.idempotency_keys
    ADD CONSTRAINT idempotency_keys_actor_user_id_foreign FOREIGN KEY (actor_user_id) REFERENCES app.users(id) ON DELETE SET NULL;


--
-- Name: idempotency_keys idempotency_keys_tenant_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.idempotency_keys
    ADD CONSTRAINT idempotency_keys_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES app.tenants(id) ON DELETE RESTRICT;


--
-- Name: inbox_events inbox_events_tenant_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.inbox_events
    ADD CONSTRAINT inbox_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES app.tenants(id) ON DELETE RESTRICT;


--
-- Name: membership_roles membership_roles_tenant_id_membership_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.membership_roles
    ADD CONSTRAINT membership_roles_tenant_id_membership_id_foreign FOREIGN KEY (tenant_id, membership_id) REFERENCES app.memberships(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: membership_roles membership_roles_tenant_id_role_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.membership_roles
    ADD CONSTRAINT membership_roles_tenant_id_role_id_foreign FOREIGN KEY (tenant_id, role_id) REFERENCES app.roles(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: membership_roles membership_roles_tenant_id_unit_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.membership_roles
    ADD CONSTRAINT membership_roles_tenant_id_unit_id_foreign FOREIGN KEY (tenant_id, unit_id) REFERENCES app.units(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: membership_units membership_units_tenant_id_membership_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.membership_units
    ADD CONSTRAINT membership_units_tenant_id_membership_id_foreign FOREIGN KEY (tenant_id, membership_id) REFERENCES app.memberships(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: membership_units membership_units_tenant_id_unit_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.membership_units
    ADD CONSTRAINT membership_units_tenant_id_unit_id_foreign FOREIGN KEY (tenant_id, unit_id) REFERENCES app.units(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: memberships memberships_tenant_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.memberships
    ADD CONSTRAINT memberships_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES app.tenants(id) ON DELETE RESTRICT;


--
-- Name: memberships memberships_user_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.memberships
    ADD CONSTRAINT memberships_user_id_foreign FOREIGN KEY (user_id) REFERENCES app.users(id) ON DELETE RESTRICT;


--
-- Name: outbox_events outbox_events_actor_user_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.outbox_events
    ADD CONSTRAINT outbox_events_actor_user_id_foreign FOREIGN KEY (actor_user_id) REFERENCES app.users(id) ON DELETE SET NULL;


--
-- Name: outbox_events outbox_events_tenant_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.outbox_events
    ADD CONSTRAINT outbox_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES app.tenants(id) ON DELETE RESTRICT;


--
-- Name: outbox_events outbox_events_tenant_id_unit_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.outbox_events
    ADD CONSTRAINT outbox_events_tenant_id_unit_id_foreign FOREIGN KEY (tenant_id, unit_id) REFERENCES app.units(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: passkeys passkeys_user_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.passkeys
    ADD CONSTRAINT passkeys_user_id_foreign FOREIGN KEY (user_id) REFERENCES app.users(id) ON DELETE CASCADE;


--
-- Name: role_permissions role_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.role_permissions
    ADD CONSTRAINT role_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES app.permissions(id) ON DELETE RESTRICT;


--
-- Name: role_permissions role_permissions_tenant_id_role_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.role_permissions
    ADD CONSTRAINT role_permissions_tenant_id_role_id_foreign FOREIGN KEY (tenant_id, role_id) REFERENCES app.roles(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: roles roles_tenant_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.roles
    ADD CONSTRAINT roles_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES app.tenants(id) ON DELETE RESTRICT;


--
-- Name: units units_tenant_id_foreign; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.units
    ADD CONSTRAINT units_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES app.tenants(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

-- Snapshot baseline: these Laravel migrations have already been represented by
-- this DDL. Preserve a pre-existing record when an operator imported metadata
-- before this one-time snapshot; no seed, tenant, or permission catalog data is
-- included here.
INSERT INTO app.migrations (migration, batch)
SELECT baseline.migration, baseline.batch
FROM (
    VALUES
        ('0001_01_01_000000_create_users_table', 1),
        ('0001_01_01_000001_create_cache_table', 1),
        ('0001_01_01_000002_create_jobs_table', 1),
        ('2024_01_01_000000_create_passkeys_table', 1),
        ('2025_08_14_170933_add_two_factor_columns_to_users_table', 1),
        ('2026_08_25_013557_create_tenants_table', 1),
        ('2026_08_25_013558_create_units_table', 1),
        ('2026_08_25_013559_create_memberships_table', 1),
        ('2026_08_25_013600_create_membership_units_table', 1),
        ('2026_08_25_013601_create_permissions_table', 1),
        ('2026_08_25_013602_create_roles_table', 1),
        ('2026_08_25_013603_create_role_permissions_table', 1),
        ('2026_08_25_013604_create_membership_roles_table', 1),
        ('2026_08_25_114620_create_entitlements_table', 1),
        ('2026_08_25_114621_create_audit_events_table', 1),
        ('2026_08_25_114622_create_idempotency_keys_table', 1),
        ('2026_08_25_114623_create_outbox_events_table', 1),
        ('2026_08_25_114624_create_inbox_events_table', 1),
        ('2026_08_25_114625_add_governance_guards', 1)
) AS baseline(migration, batch)
WHERE NOT EXISTS (
    SELECT 1
    FROM app.migrations AS recorded
    WHERE recorded.migration = baseline.migration
);

-- The schema-only export above excludes ACLs. Keep these grants equivalent to
-- app:db:provision-schema for the pre-existing least-privilege runtime role.
GRANT USAGE ON SCHEMA app TO caldas_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA app TO caldas_runtime;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA app TO caldas_runtime;
REVOKE UPDATE ON ALL SEQUENCES IN SCHEMA app FROM caldas_runtime;
ALTER DEFAULT PRIVILEGES IN SCHEMA app GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO caldas_runtime;
ALTER DEFAULT PRIVILEGES IN SCHEMA app GRANT USAGE, SELECT ON SEQUENCES TO caldas_runtime;

-- Migration history is owned and written only by the migration connection.
REVOKE ALL PRIVILEGES ON app.migrations FROM caldas_runtime;
REVOKE ALL PRIVILEGES ON SEQUENCE app.migrations_id_seq FROM caldas_runtime;

-- audit_events is append-only for every application connection, including runtime.
REVOKE UPDATE, DELETE, TRIGGER, TRUNCATE ON app.audit_events FROM caldas_runtime;
GRANT SELECT, INSERT ON app.audit_events TO caldas_runtime;
REVOKE TRIGGER, TRUNCATE ON app.audit_events FROM PUBLIC;

-- System-role immutability is guarded by a trigger; runtime cannot bypass it.
REVOKE TRIGGER, TRUNCATE ON app.roles FROM caldas_runtime;
REVOKE TRIGGER, TRUNCATE ON app.roles FROM PUBLIC;
