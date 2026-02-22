 -- Broadway Event Store table for PostgreSQL
-- Stores all domain events for Event Sourcing

CREATE TABLE IF NOT EXISTS events (
    id SERIAL PRIMARY KEY,
    uuid UUID NOT NULL,
    playhead INTEGER NOT NULL,
    metadata JSON NOT NULL,
    payload JSON NOT NULL,
    recorded_on TIMESTAMP NOT NULL,
    type VARCHAR(255) NOT NULL,
    CONSTRAINT unique_uuid_playhead UNIQUE (uuid, playhead)
);
