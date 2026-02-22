-- Symfony Messenger tables for failed messages
-- https://symfony.com/doc/current/messenger.html#doctrine-transport

CREATE TABLE IF NOT EXISTS messenger_messages (
    id BIGSERIAL PRIMARY KEY,
    body TEXT NOT NULL,
    headers TEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    available_at TIMESTAMP NOT NULL,
    delivered_at TIMESTAMP DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_queue_name ON messenger_messages (queue_name);
CREATE INDEX IF NOT EXISTS idx_available_at ON messenger_messages (available_at);
CREATE INDEX IF NOT EXISTS idx_delivered_at ON messenger_messages (delivered_at);
