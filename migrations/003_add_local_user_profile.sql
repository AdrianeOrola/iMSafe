USE imsafe_oop_local;

-- Account-profile details are nullable so existing community accounts remain valid.
ALTER TABLE local_users
    ADD COLUMN IF NOT EXISTS region VARCHAR(16) NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS province VARCHAR(16) NULL AFTER region,
    ADD COLUMN IF NOT EXISTS city_municipality VARCHAR(16) NULL AFTER province,
    ADD COLUMN IF NOT EXISTS barangay VARCHAR(16) NULL AFTER city_municipality,
    ADD COLUMN IF NOT EXISTS house_street VARCHAR(160) NULL AFTER barangay,
    ADD COLUMN IF NOT EXISTS nearby_landmark VARCHAR(255) NULL AFTER house_street,
    ADD COLUMN IF NOT EXISTS primary_contact VARCHAR(32) NULL AFTER nearby_landmark,
    ADD COLUMN IF NOT EXISTS alternate_contact VARCHAR(32) NULL AFTER primary_contact;
