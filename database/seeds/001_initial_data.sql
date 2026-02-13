-- Facewatch Subscription Management System
-- Initial Seed Data
-- Created: 2026-02-13

-- ============================================================================
-- 1. CREATE DEFAULT ADMIN USER
-- ============================================================================
-- Password: changeme123 (MUST be changed on first login)
-- Password hash generated with: password_hash('changeme123', PASSWORD_BCRYPT)

INSERT INTO users (username, email, password_hash, full_name, role, is_active)
VALUES (
    'admin',
    'simon.gordon@facewatch.co.uk',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Simon Gordon',
    'admin',
    TRUE
);

-- ============================================================================
-- 2. INFLATION RATES (2024-2029)
-- ============================================================================

INSERT INTO inflation_rates (effective_date, inflation_percentage, notes) VALUES
('2024-01-01', 0.00, '2024 - No inflation applied'),
('2025-01-01', 4.00, '2025 - 4% inflation'),
('2026-01-01', 3.00, '2026 - 3% inflation'),
('2027-01-01', 3.00, '2027 - 3% inflation'),
('2028-01-01', 3.00, '2028 - 3% inflation'),
('2029-01-01', 3.00, '2029 - 3% inflation');

-- ============================================================================
-- 3. PRICING TIERS - 2024 BASE PRICING
-- ============================================================================

INSERT INTO pricing_tiers (effective_date, min_cameras, max_cameras, annual_price_per_camera, monthly_price_per_camera, tier_name) VALUES
('2024-01-01', 1, 49, 3540.00, 295.00, 'Tier 1: 1-49 cameras'),
('2024-01-01', 50, 149, 3417.00, 285.00, 'Tier 2: 50-149 cameras'),
('2024-01-01', 150, 249, 3298.00, 275.00, 'Tier 3: 150-249 cameras'),
('2024-01-01', 250, 349, 3178.00, 265.00, 'Tier 4: 250-349 cameras'),
('2024-01-01', 350, 499, 2997.00, 250.00, 'Tier 5: 350-499 cameras'),
('2024-01-01', 500, NULL, 2759.00, 230.00, 'Tier 6: 500+ cameras');

-- ============================================================================
-- 4. PRICING TIERS - 2025 (4% INFLATION)
-- ============================================================================

INSERT INTO pricing_tiers (effective_date, min_cameras, max_cameras, annual_price_per_camera, monthly_price_per_camera, tier_name) VALUES
('2025-01-01', 1, 49, 3680.00, 307.00, 'Tier 1: 1-49 cameras'),
('2025-01-01', 50, 149, 3552.00, 296.00, 'Tier 2: 50-149 cameras'),
('2025-01-01', 150, 249, 3428.00, 286.00, 'Tier 3: 150-249 cameras'),
('2025-01-01', 250, 349, 3303.00, 275.00, 'Tier 4: 250-349 cameras'),
('2025-01-01', 350, 499, 3117.00, 260.00, 'Tier 5: 350-499 cameras'),
('2025-01-01', 500, NULL, 2867.00, 239.00, 'Tier 6: 500+ cameras');

-- ============================================================================
-- 5. PRICING TIERS - 2026 (3% INFLATION)
-- ============================================================================

INSERT INTO pricing_tiers (effective_date, min_cameras, max_cameras, annual_price_per_camera, monthly_price_per_camera, tier_name) VALUES
('2026-01-01', 1, 49, 3790.00, 316.00, 'Tier 1: 1-49 cameras'),
('2026-01-01', 50, 149, 3659.00, 305.00, 'Tier 2: 50-149 cameras'),
('2026-01-01', 150, 249, 3531.00, 294.00, 'Tier 3: 150-249 cameras'),
('2026-01-01', 250, 349, 3402.00, 284.00, 'Tier 4: 250-349 cameras'),
('2026-01-01', 350, 499, 3210.00, 268.00, 'Tier 5: 350-499 cameras'),
('2026-01-01', 500, NULL, 2953.00, 246.00, 'Tier 6: 500+ cameras');

-- ============================================================================
-- 6. PRICING TIERS - 2027 (3% INFLATION)
-- ============================================================================

INSERT INTO pricing_tiers (effective_date, min_cameras, max_cameras, annual_price_per_camera, monthly_price_per_camera, tier_name) VALUES
('2027-01-01', 1, 49, 3904.00, 325.00, 'Tier 1: 1-49 cameras'),
('2027-01-01', 50, 149, 3769.00, 314.00, 'Tier 2: 50-149 cameras'),
('2027-01-01', 150, 249, 3637.00, 303.00, 'Tier 3: 150-249 cameras'),
('2027-01-01', 250, 349, 3504.00, 292.00, 'Tier 4: 250-349 cameras'),
('2027-01-01', 350, 499, 3306.00, 276.00, 'Tier 5: 350-499 cameras'),
('2027-01-01', 500, NULL, 3042.00, 254.00, 'Tier 6: 500+ cameras');

-- ============================================================================
-- 7. PRICING TIERS - 2028 (3% INFLATION)
-- ============================================================================

INSERT INTO pricing_tiers (effective_date, min_cameras, max_cameras, annual_price_per_camera, monthly_price_per_camera, tier_name) VALUES
('2028-01-01', 1, 49, 4021.00, 335.00, 'Tier 1: 1-49 cameras'),
('2028-01-01', 50, 149, 3882.00, 324.00, 'Tier 2: 50-149 cameras'),
('2028-01-01', 150, 249, 3746.00, 312.00, 'Tier 3: 150-249 cameras'),
('2028-01-01', 250, 349, 3609.00, 301.00, 'Tier 4: 250-349 cameras'),
('2028-01-01', 350, 499, 3405.00, 284.00, 'Tier 5: 350-499 cameras'),
('2028-01-01', 500, NULL, 3133.00, 261.00, 'Tier 6: 500+ cameras');

-- ============================================================================
-- 8. PRICING TIERS - 2029 (3% INFLATION)
-- ============================================================================

INSERT INTO pricing_tiers (effective_date, min_cameras, max_cameras, annual_price_per_camera, monthly_price_per_camera, tier_name) VALUES
('2029-01-01', 1, 49, 4021.00, 335.00, 'Tier 1: 1-49 cameras'),
('2029-01-01', 50, 149, 3882.00, 324.00, 'Tier 2: 50-149 cameras'),
('2029-01-01', 150, 249, 3746.00, 312.00, 'Tier 3: 150-249 cameras'),
('2029-01-01', 250, 349, 3609.00, 301.00, 'Tier 4: 250-349 cameras'),
('2029-01-01', 350, 499, 3405.00, 284.00, 'Tier 5: 350-499 cameras'),
('2029-01-01', 500, NULL, 3133.00, 261.00, 'Tier 6: 500+ cameras');

-- ============================================================================
-- NOTES
-- ============================================================================
-- 1. Default admin password is 'changeme123' - MUST be changed on first login
-- 2. Pricing tiers are loaded for 2024-2029 based on provided inflation schedule
-- 3. 2029 pricing is same as 2028 (no inflation data provided beyond 2028)
-- 4. Monthly prices are calculated as Annual / 12 (rounded)

