-- Seedle schema, ported from the Laravel/SQLite original.
--
-- Differences from the source schema, all deliberate:
--   * Laravel's own tables (cache, sessions, jobs, migrations,
--     password_reset_tokens, personal_access_tokens) are gone. Flyway owns
--     migration history and the queue and cache layers are Spring's problem now.
--   * Columns that held JSON as text are real jsonb here.
--   * tinyint(1) booleans are real booleans, datetimes are timestamptz.
--   * products carries a generated tsvector, which is what replaces the
--     Elasticsearch index for the swap shelf search.

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE TABLE users (
    id                bigserial PRIMARY KEY,
    name              varchar(255) NOT NULL,
    email             varchar(255) NOT NULL,
    email_verified_at timestamptz,
    password          varchar(255) NOT NULL,
    username          varchar(255) NOT NULL,
    bio               text,
    avatar_path       varchar(255),
    location          varchar(255),
    hardiness_zone    varchar(255),
    postcode          varchar(255),
    -- Rounded to roughly a kilometre before storage, and only ever exposed as
    -- a distance, so a listing never reveals where somebody actually lives.
    latitude          numeric(9, 6),
    longitude         numeric(9, 6),
    created_at        timestamptz,
    updated_at        timestamptz
);
CREATE UNIQUE INDEX users_email_unique ON users (email);
CREATE UNIQUE INDEX users_username_unique ON users (username);
CREATE INDEX users_latitude_longitude_index ON users (latitude, longitude);

CREATE TABLE plants (
    id               bigserial PRIMARY KEY,
    name             varchar(255) NOT NULL,
    scientific_name  varchar(255),
    slug             varchar(255) NOT NULL,
    type             varchar(32) NOT NULL
                     CHECK (type IN ('vegetable', 'fruit', 'herb', 'flower', 'tree', 'shrub')),
    sun_requirement  varchar(32) NOT NULL
                     CHECK (sun_requirement IN ('full_sun', 'partial_sun', 'shade')),
    water_needs      varchar(32) NOT NULL
                     CHECK (water_needs IN ('low', 'medium', 'high')),
    soil_type        varchar(255),
    min_zone         integer NOT NULL,
    max_zone         integer NOT NULL,
    days_to_maturity integer,
    spacing_cm       integer,
    planting_months  jsonb NOT NULL DEFAULT '[]'::jsonb,
    description      text,
    created_at       timestamptz,
    updated_at       timestamptz
);
CREATE UNIQUE INDEX plants_slug_unique ON plants (slug);
CREATE INDEX plants_type_index ON plants (type);
CREATE INDEX plants_min_zone_max_zone_index ON plants (min_zone, max_zone);

CREATE TABLE plant_companions (
    id                 bigserial PRIMARY KEY,
    plant_id           bigint NOT NULL REFERENCES plants (id) ON DELETE CASCADE,
    companion_plant_id bigint NOT NULL REFERENCES plants (id) ON DELETE CASCADE,
    relationship       varchar(16) NOT NULL CHECK (relationship IN ('good', 'bad')),
    note               varchar(255),
    created_at         timestamptz,
    updated_at         timestamptz
);
CREATE UNIQUE INDEX plant_companions_plant_id_companion_plant_id_unique
    ON plant_companions (plant_id, companion_plant_id);

CREATE TABLE products (
    id            bigserial PRIMARY KEY,
    seller_id     bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    plant_id      bigint REFERENCES plants (id) ON DELETE SET NULL,
    title         varchar(255) NOT NULL,
    slug          varchar(255) NOT NULL,
    description   text,
    category      varchar(32) NOT NULL
                  CHECK (category IN ('seed', 'live_plant', 'tool', 'fertilizer', 'other')),
    stock         integer NOT NULL DEFAULT 0,
    images        jsonb NOT NULL DEFAULT '[]'::jsonb,
    is_active     boolean NOT NULL DEFAULT true,
    created_at    timestamptz,
    updated_at    timestamptz,
    search_vector tsvector GENERATED ALWAYS AS (
        setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
        setweight(to_tsvector('english', coalesce(description, '')), 'B')
    ) STORED
);
CREATE UNIQUE INDEX products_slug_unique ON products (slug);
CREATE INDEX products_seller_id_index ON products (seller_id);
CREATE INDEX products_category_index ON products (category);
CREATE INDEX products_is_active_index ON products (is_active);
CREATE INDEX products_search_vector_index ON products USING GIN (search_vector);
CREATE INDEX products_title_trgm_index ON products USING GIN (title gin_trgm_ops);

CREATE TABLE garden_beds (
    id             bigserial PRIMARY KEY,
    user_id        bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    name           varchar(255) NOT NULL,
    hardiness_zone varchar(255),
    width_cm       integer,
    length_cm      integer,
    created_at     timestamptz,
    updated_at     timestamptz
);
CREATE INDEX garden_beds_user_id_index ON garden_beds (user_id);

CREATE TABLE garden_bed_plants (
    id            bigserial PRIMARY KEY,
    garden_bed_id bigint NOT NULL REFERENCES garden_beds (id) ON DELETE CASCADE,
    plant_id      bigint NOT NULL REFERENCES plants (id) ON DELETE CASCADE,
    planted_at    date,
    notes         text,
    -- Null until the entry is placed on the plot. Measured from the bed's
    -- top-left corner in centimetres, the same units as the bed's dimensions.
    x_cm          integer,
    y_cm          integer,
    created_at    timestamptz,
    updated_at    timestamptz
);
CREATE INDEX garden_bed_plants_garden_bed_id_index ON garden_bed_plants (garden_bed_id);

CREATE TABLE harvests (
    id                  bigserial PRIMARY KEY,
    garden_bed_plant_id bigint NOT NULL REFERENCES garden_bed_plants (id) ON DELETE CASCADE,
    user_id             bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    harvested_at        date NOT NULL,
    quantity            numeric(10, 2),
    unit                varchar(32),
    notes               text,
    created_at          timestamptz,
    updated_at          timestamptz
);
CREATE INDEX harvests_user_id_harvested_at_index ON harvests (user_id, harvested_at);

CREATE TABLE posts (
    id         bigserial PRIMARY KEY,
    user_id    bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    plant_id   bigint REFERENCES plants (id) ON DELETE SET NULL,
    type       varchar(16) NOT NULL DEFAULT 'update'
               CHECK (type IN ('update', 'question', 'tip')),
    body       text NOT NULL,
    image_path varchar(255),
    pinned_at  timestamptz,
    created_at timestamptz,
    updated_at timestamptz
);
CREATE INDEX posts_user_id_created_at_index ON posts (user_id, created_at);
CREATE INDEX posts_type_index ON posts (type);

CREATE TABLE comments (
    id         bigserial PRIMARY KEY,
    post_id    bigint NOT NULL REFERENCES posts (id) ON DELETE CASCADE,
    user_id    bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    body       text NOT NULL,
    created_at timestamptz,
    updated_at timestamptz
);
CREATE INDEX comments_post_id_created_at_index ON comments (post_id, created_at);

CREATE TABLE likes (
    id         bigserial PRIMARY KEY,
    post_id    bigint NOT NULL REFERENCES posts (id) ON DELETE CASCADE,
    user_id    bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    created_at timestamptz,
    updated_at timestamptz
);
CREATE UNIQUE INDEX likes_post_id_user_id_unique ON likes (post_id, user_id);

CREATE TABLE follows (
    id          bigserial PRIMARY KEY,
    follower_id bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    followed_id bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    created_at  timestamptz,
    updated_at  timestamptz
);
CREATE UNIQUE INDEX follows_follower_id_followed_id_unique ON follows (follower_id, followed_id);

CREATE TABLE orders (
    id         bigserial PRIMARY KEY,
    buyer_id   bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    status     varchar(16) NOT NULL DEFAULT 'pending'
               CHECK (status IN ('pending', 'processing', 'completed', 'cancelled')),
    created_at timestamptz,
    updated_at timestamptz
);
CREATE INDEX orders_buyer_id_created_at_index ON orders (buyer_id, created_at);
CREATE INDEX orders_status_index ON orders (status);

CREATE TABLE order_items (
    id         bigserial PRIMARY KEY,
    order_id   bigint NOT NULL REFERENCES orders (id) ON DELETE CASCADE,
    product_id bigint NOT NULL REFERENCES products (id) ON DELETE RESTRICT,
    seller_id  bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    quantity   integer NOT NULL,
    created_at timestamptz,
    updated_at timestamptz
);
CREATE INDEX order_items_order_id_index ON order_items (order_id);
CREATE INDEX order_items_seller_id_index ON order_items (seller_id);

CREATE TABLE guides (
    id           bigserial PRIMARY KEY,
    title        varchar(255) NOT NULL,
    slug         varchar(255) NOT NULL,
    excerpt      varchar(255) NOT NULL,
    body         text NOT NULL,
    category     varchar(64) NOT NULL,
    plant_id     bigint REFERENCES plants (id) ON DELETE SET NULL,
    read_minutes integer NOT NULL DEFAULT 3,
    published_at timestamptz,
    created_at   timestamptz,
    updated_at   timestamptz
);
CREATE UNIQUE INDEX guides_slug_unique ON guides (slug);
CREATE INDEX guides_category_index ON guides (category);

CREATE TABLE reviews (
    id         bigserial PRIMARY KEY,
    product_id bigint NOT NULL REFERENCES products (id) ON DELETE CASCADE,
    user_id    bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    -- The original left this unconstrained and enforced the range in the form
    -- request only. The database is the right place for it.
    rating     integer NOT NULL CHECK (rating BETWEEN 1 AND 5),
    body       text,
    created_at timestamptz,
    updated_at timestamptz
);
CREATE UNIQUE INDEX reviews_product_id_user_id_unique ON reviews (product_id, user_id);
CREATE INDEX reviews_product_id_rating_index ON reviews (product_id, rating);

CREATE TABLE conversations (
    id              bigserial PRIMARY KEY,
    product_id      bigint REFERENCES products (id) ON DELETE SET NULL,
    buyer_id        bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    seller_id       bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    last_message_at timestamptz,
    created_at      timestamptz,
    updated_at      timestamptz
);
CREATE UNIQUE INDEX conversations_buyer_id_seller_id_product_id_unique
    ON conversations (buyer_id, seller_id, product_id);
CREATE INDEX conversations_buyer_id_last_message_at_index
    ON conversations (buyer_id, last_message_at);
CREATE INDEX conversations_seller_id_last_message_at_index
    ON conversations (seller_id, last_message_at);

CREATE TABLE messages (
    id              bigserial PRIMARY KEY,
    conversation_id bigint NOT NULL REFERENCES conversations (id) ON DELETE CASCADE,
    sender_id       bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    body            text NOT NULL,
    read_at         timestamptz,
    created_at      timestamptz,
    updated_at      timestamptz
);
CREATE INDEX messages_conversation_id_created_at_index
    ON messages (conversation_id, created_at);
CREATE INDEX messages_conversation_id_sender_id_read_at_index
    ON messages (conversation_id, sender_id, read_at);

CREATE TABLE saves (
    id           bigserial PRIMARY KEY,
    user_id      bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    -- Polymorphic, as in the original: a save points at either a product or a
    -- plant. Kept rather than split so the saved items page stays one query.
    savable_type varchar(64) NOT NULL,
    savable_id   bigint NOT NULL,
    created_at   timestamptz,
    updated_at   timestamptz
);
CREATE UNIQUE INDEX saves_user_id_savable_type_savable_id_unique
    ON saves (user_id, savable_type, savable_id);
CREATE INDEX saves_savable_type_savable_id_index ON saves (savable_type, savable_id);

CREATE TABLE wants (
    id          bigserial PRIMARY KEY,
    user_id     bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    plant_id    bigint REFERENCES plants (id) ON DELETE SET NULL,
    title       varchar(255) NOT NULL,
    description text,
    is_open     boolean NOT NULL DEFAULT true,
    created_at  timestamptz,
    updated_at  timestamptz
);
CREATE INDEX wants_is_open_created_at_index ON wants (is_open, created_at);
CREATE INDEX wants_plant_id_is_open_index ON wants (plant_id, is_open);

CREATE TABLE garden_reminders (
    id         bigserial PRIMARY KEY,
    user_id    bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    kind       varchar(32) NOT NULL,
    subject_id bigint NOT NULL,
    -- The unique key below is what makes the daily job idempotent: running it
    -- twice in the same period cannot tell anyone twice.
    period     varchar(32) NOT NULL,
    created_at timestamptz,
    updated_at timestamptz
);
CREATE UNIQUE INDEX garden_reminders_user_id_kind_subject_id_period_unique
    ON garden_reminders (user_id, kind, subject_id, period);

CREATE TABLE notifications (
    id              uuid PRIMARY KEY,
    type            varchar(255) NOT NULL,
    notifiable_type varchar(64) NOT NULL,
    notifiable_id   bigint NOT NULL,
    data            jsonb NOT NULL,
    read_at         timestamptz,
    created_at      timestamptz,
    updated_at      timestamptz
);
CREATE INDEX notifications_notifiable_type_notifiable_id_index
    ON notifications (notifiable_type, notifiable_id);

-- Sanctum stored one row per issued token and logout deleted it. Access tokens
-- here are stateless JWTs, so logout instead records the token's jti until it
-- would have expired anyway. Keeps logout meaning what it meant before.
CREATE TABLE revoked_tokens (
    jti        uuid PRIMARY KEY,
    user_id    bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    expires_at timestamptz NOT NULL,
    revoked_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX revoked_tokens_expires_at_index ON revoked_tokens (expires_at);
