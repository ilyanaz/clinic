<?php
/**
 * Lightweight schema synchronisation for the clinic database.
 *
 * This helper ensures the minimum columns required by the upgraded UI exist in
 * the MySQL database that was restored from the legacy `clinic.sql` dump.
 * Missing columns are created on the fly so the application can run without
 * manual SQL patches.
 */

if (!function_exists('ensureClinicSchema')) {
    /**
     * Ensure required columns are present before the application queries them.
     */
    function ensureClinicSchema(PDO $pdo): void
    {
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $columnExists = function (string $table, string $column) use ($pdo): bool {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
                );
                $stmt->execute([$table, $column]);
                return (bool) $stmt->fetchColumn();
            };

            $addColumnIfMissing = function (string $table, string $column, string $definition) use ($pdo, $columnExists): void {
                if ($columnExists($table, $column)) {
                    return;
                }

                $pdo->exec("ALTER TABLE `{$table}` {$definition}");
            };

            // --- chemical_information table ---
            $addColumnIfMissing(
                'chemical_information',
                'created_at',
                "ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `examiner_name`"
            );

            $addColumnIfMissing(
                'chemical_information',
                'updated_at',
                "ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`"
            );

            $addColumnIfMissing(
                'chemical_information',
                'final_assessment',
                "ADD COLUMN `final_assessment` VARCHAR(255) DEFAULT NULL AFTER `examination_type`"
            );

            // --- medical_staff table ---
            $addColumnIfMissing(
                'medical_staff',
                'position',
                "ADD COLUMN `position` VARCHAR(100) DEFAULT NULL AFTER `specialization`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'department',
                "ADD COLUMN `department` VARCHAR(100) DEFAULT NULL AFTER `position`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'employment_status',
                "ADD COLUMN `employment_status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER `department`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'passport_no',
                "ADD COLUMN `passport_no` VARCHAR(20) DEFAULT NULL AFTER `NRIC`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'date_of_birth',
                "ADD COLUMN `date_of_birth` DATE DEFAULT NULL AFTER `passport_no`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'gender',
                "ADD COLUMN `gender` ENUM('Male','Female') DEFAULT NULL AFTER `date_of_birth`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'address',
                "ADD COLUMN `address` TEXT DEFAULT NULL AFTER `gender`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'state',
                "ADD COLUMN `state` VARCHAR(50) DEFAULT NULL AFTER `address`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'district',
                "ADD COLUMN `district` VARCHAR(50) DEFAULT NULL AFTER `state`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'postcode',
                "ADD COLUMN `postcode` VARCHAR(10) DEFAULT NULL AFTER `district`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'country_code',
                "ADD COLUMN `country_code` VARCHAR(10) DEFAULT NULL AFTER `postcode`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'phone',
                "ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `country_code`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'years_of_experience',
                "ADD COLUMN `years_of_experience` INT DEFAULT NULL AFTER `license_number`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'hire_date',
                "ADD COLUMN `hire_date` DATE DEFAULT NULL AFTER `years_of_experience`"
            );

            $addColumnIfMissing(
                'medical_staff',
                'salary',
                "ADD COLUMN `salary` DECIMAL(10,2) DEFAULT NULL AFTER `hire_date`"
            );

            // --- header_documents table ---
            $addColumnIfMissing(
                'header_documents',
                'filename',
                "ADD COLUMN `filename` VARCHAR(255) DEFAULT NULL AFTER `id`"
            );

            $addColumnIfMissing(
                'header_documents',
                'original_name',
                "ADD COLUMN `original_name` VARCHAR(255) DEFAULT NULL AFTER `filename`"
            );

            $addColumnIfMissing(
                'header_documents',
                'description',
                "ADD COLUMN `description` TEXT DEFAULT NULL AFTER `file_path`"
            );

            $dropUniqueIndex = function (string $table, string $index) use ($pdo): void {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND NON_UNIQUE = 0"
                );
                $stmt->execute([$table, $index]);
                if ($stmt->fetchColumn() > 0) {
                    $pdo->exec("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
                }
            };

            $dropUniqueIndex('history_of_health', 'patient_id');
            $dropUniqueIndex('physical_examination', 'patient_id');
            $dropUniqueIndex('conclusion_ms_finding', 'patient_id');
            $dropUniqueIndex('recommendations', 'patient_id');
            $dropUniqueIndex('target_organ', 'patient_id');
            $dropUniqueIndex('medical_removal_protection', 'patient_id');
        } catch (Throwable $e) {
            error_log('[clinic] Schema sync warning: ' . $e->getMessage());
        }
    }
}


