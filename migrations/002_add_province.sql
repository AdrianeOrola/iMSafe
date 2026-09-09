-- Additive migration: old locations remain valid with NULL province fields.
ALTER TABLE incident_locations
    ADD COLUMN IF NOT EXISTS province_code VARCHAR(16) NULL AFTER region_name,
    ADD COLUMN IF NOT EXISTS province_name VARCHAR(160) NULL AFTER province_code;
