-- Only needed if you imported database.sql before this update.
-- Skip this if you're setting the app up fresh — it's already in database.sql.
ALTER TABLE transactions ADD COLUMN category VARCHAR(50) DEFAULT NULL AFTER product_id;
