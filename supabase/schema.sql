create table if not exists public.app_state (
    key text primary key,
    payload jsonb not null default '{}'::jsonb,
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.cart_sessions (
    session_key text primary key,
    customer_id text null,
    payload jsonb not null default '{"items":[],"coupon_code":""}'::jsonb,
    updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.media_assets (
    id bigserial primary key,
    public_url text not null unique,
    file_path text not null,
    file_name text not null,
    mime_type text not null,
    media_type text not null default 'file',
    file_size bigint not null default 0,
    source text not null default 'hosting',
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create or replace function public.touch_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists touch_updated_at_app_state on public.app_state;
create trigger touch_updated_at_app_state
before update on public.app_state
for each row
execute function public.touch_updated_at();

drop trigger if exists touch_updated_at_cart_sessions on public.cart_sessions;
create trigger touch_updated_at_cart_sessions
before update on public.cart_sessions
for each row
execute function public.touch_updated_at();

drop trigger if exists touch_updated_at_media_assets on public.media_assets;
create trigger touch_updated_at_media_assets
before update on public.media_assets
for each row
execute function public.touch_updated_at();

alter table public.app_state enable row level security;
alter table public.cart_sessions enable row level security;
alter table public.media_assets enable row level security;

comment on table public.app_state is 'Application state records such as site_content and admin_login_attempts.';
comment on table public.cart_sessions is 'Persistent guest and customer cart snapshots keyed by a server-issued cart token.';
comment on table public.media_assets is 'Hosting-stored media metadata. Files stay on hosting disk; only URLs and metadata live here.';
