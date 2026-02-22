-- Read Model table for Gift Cards (CQRS Query side)
-- This is a denormalized projection updated by Event Subscribers

CREATE TABLE gift_cards_read (
    id UUID PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    balance_amount INT NOT NULL,
    balance_currency VARCHAR(3) NOT NULL,
    initial_amount INT NOT NULL,
    initial_currency VARCHAR(3) NOT NULL,
    status VARCHAR(20) NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL,
    activated_at TIMESTAMP WITH TIME ZONE,
    suspended_at TIMESTAMP WITH TIME ZONE,
    cancelled_at TIMESTAMP WITH TIME ZONE,
    expired_at TIMESTAMP WITH TIME ZONE,
    depleted_at TIMESTAMP WITH TIME ZONE,
    suspension_duration INT DEFAULT 0,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW()
);

-- Basic index on status for filtering
CREATE INDEX idx_gift_cards_read_status ON gift_cards_read(status);

-- Index for tenant-based queries (multi-tenancy)
CREATE INDEX idx_gift_cards_read_tenant_id ON gift_cards_read(tenant_id);

-- Index for finding expiring cards
CREATE INDEX idx_gift_cards_read_expires_at ON gift_cards_read(expires_at) WHERE expires_at IS NOT NULL;

-- Comments
COMMENT ON TABLE gift_cards_read IS 'Read Model projection for Gift Cards - optimized for queries';
COMMENT ON COLUMN gift_cards_read.balance_amount IS 'Current balance in smallest currency unit (grosze)';
COMMENT ON COLUMN gift_cards_read.initial_amount IS 'Original amount when card was created';
COMMENT ON COLUMN gift_cards_read.suspension_duration IS 'Total suspension duration in seconds';
