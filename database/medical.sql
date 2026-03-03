-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 03, 2026 at 05:41 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `medical`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time DEFAULT NULL,
  `appointment_type` enum('Consultation','Follow-Up','Health Surveillance','Emergency','Other') NOT NULL,
  `status` enum('Scheduled','Completed','Cancelled') DEFAULT 'Scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audiometric_questionnaire`
--

CREATE TABLE `audiometric_questionnaire` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `audiometric_test_id` int(10) UNSIGNED DEFAULT NULL,
  `patient_name` varchar(255) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `ic_passport_no` varchar(50) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `job` varchar(255) DEFAULT NULL,
  `years_of_service` decimal(5,2) DEFAULT NULL,
  `test_date` date NOT NULL,
  `q1_noise_14hours` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `q2_illness_hearing` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `q3_ear_operation` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `q4_medication_hearing` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `q5_exposed_loud_noise` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `q6_family_hearing_loss` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `q7_night_clubs` varchar(50) DEFAULT NULL COMMENT 'NEVER, ONCE A YEAR, MORE THAN ONCE A YEAR',
  `q8_personal_stereo` varchar(50) DEFAULT NULL COMMENT 'NEVER, LESS THAN 2 HOURS A WEEK, MORE THAN 2 HOURS A WEEK',
  `q9_loud_music_instruments` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `q10_noisy_jobs_past` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `q11_hearing_protectors` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `q12_audiometric_test_before` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `air_right_500` int(11) DEFAULT NULL,
  `air_right_1k` int(11) DEFAULT NULL,
  `air_right_2k` int(11) DEFAULT NULL,
  `air_right_3k` int(11) DEFAULT NULL,
  `air_right_4k` int(11) DEFAULT NULL,
  `air_right_6k` int(11) DEFAULT NULL,
  `air_right_8k` int(11) DEFAULT NULL,
  `air_left_500` int(11) DEFAULT NULL,
  `air_left_1k` int(11) DEFAULT NULL,
  `air_left_2k` int(11) DEFAULT NULL,
  `air_left_3k` int(11) DEFAULT NULL,
  `air_left_4k` int(11) DEFAULT NULL,
  `air_left_6k` int(11) DEFAULT NULL,
  `air_left_8k` int(11) DEFAULT NULL,
  `bone_right_500` int(11) DEFAULT NULL,
  `bone_right_1k` int(11) DEFAULT NULL,
  `bone_right_2k` int(11) DEFAULT NULL,
  `bone_right_3k` int(11) DEFAULT NULL,
  `bone_right_4k` int(11) DEFAULT NULL,
  `bone_right_6k` int(11) DEFAULT NULL,
  `bone_right_8k` int(11) DEFAULT NULL,
  `bone_left_500` int(11) DEFAULT NULL,
  `bone_left_1k` int(11) DEFAULT NULL,
  `bone_left_2k` int(11) DEFAULT NULL,
  `bone_left_3k` int(11) DEFAULT NULL,
  `bone_left_4k` int(11) DEFAULT NULL,
  `bone_left_6k` int(11) DEFAULT NULL,
  `bone_left_8k` int(11) DEFAULT NULL,
  `visual_examination` varchar(10) DEFAULT NULL COMMENT 'Normal or Abnormal',
  `visual_examination_details` text DEFAULT NULL,
  `technician_signature` text DEFAULT NULL COMMENT 'Base64 encoded signature image',
  `technician_name` varchar(255) DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audiometric_questionnaire`
--

INSERT INTO `audiometric_questionnaire` (`id`, `patient_id`, `audiometric_test_id`, `patient_name`, `age`, `ic_passport_no`, `gender`, `company`, `department`, `job`, `years_of_service`, `test_date`, `q1_noise_14hours`, `q2_illness_hearing`, `q3_ear_operation`, `q4_medication_hearing`, `q5_exposed_loud_noise`, `q6_family_hearing_loss`, `q7_night_clubs`, `q8_personal_stereo`, `q9_loud_music_instruments`, `q10_noisy_jobs_past`, `q11_hearing_protectors`, `q12_audiometric_test_before`, `air_right_500`, `air_right_1k`, `air_right_2k`, `air_right_3k`, `air_right_4k`, `air_right_6k`, `air_right_8k`, `air_left_500`, `air_left_1k`, `air_left_2k`, `air_left_3k`, `air_left_4k`, `air_left_6k`, `air_left_8k`, `bone_right_500`, `bone_right_1k`, `bone_right_2k`, `bone_right_3k`, `bone_right_4k`, `bone_right_6k`, `bone_right_8k`, `bone_left_500`, `bone_left_1k`, `bone_left_2k`, `bone_left_3k`, `bone_left_4k`, `bone_left_6k`, `bone_left_8k`, `visual_examination`, `visual_examination_details`, `technician_signature`, `technician_name`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 1, NULL, 'Ahmad Hassan', 36, '900101-01-1234', 'Male', 'Consist College', '', 'Chemical Engineer', 5.00, '2026-01-15', 'NO', 'NO', 'NO', 'NO', 'NO', 'NO', 'NEVER', 'NEVER', 'NO', 'NO', 'NO', 'NO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '7', '2026-01-15 02:18:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audiometric_reports`
--

CREATE TABLE `audiometric_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `surveillance_id` int(11) DEFAULT NULL,
  `audiometric_test_id` int(10) UNSIGNED DEFAULT NULL,
  `audiometric_summary_id` int(10) UNSIGNED DEFAULT NULL,
  `personal_exposure_monitoring_dba` int(11) DEFAULT NULL COMMENT 'Exposure level in dBA',
  `personal_exposure_monitoring_date` date DEFAULT NULL,
  `current_illness_symptoms` text DEFAULT NULL,
  `smoking_status` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `smoking_packs_per_day` int(11) DEFAULT NULL,
  `past_ear_disease` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `past_ear_disease_specify` text DEFAULT NULL,
  `past_head_injury` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `past_head_injury_specify` text DEFAULT NULL,
  `past_medical_history` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `past_medical_history_specify` text DEFAULT NULL,
  `ototoxic_medications` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `ototoxic_medications_specify` text DEFAULT NULL,
  `hobbies_diving` tinyint(1) NOT NULL DEFAULT 0,
  `hobbies_loud_music` tinyint(1) NOT NULL DEFAULT 0,
  `hobbies_musical_instrument` tinyint(1) NOT NULL DEFAULT 0,
  `hobbies_karaoke` tinyint(1) NOT NULL DEFAULT 0,
  `hobbies_shooting` tinyint(1) NOT NULL DEFAULT 0,
  `hobbies_others` tinyint(1) NOT NULL DEFAULT 0,
  `hobbies_others_specify` text DEFAULT NULL,
  `php_ear_plug` tinyint(1) NOT NULL DEFAULT 0,
  `php_earmuff` tinyint(1) NOT NULL DEFAULT 0,
  `php_combination` tinyint(1) NOT NULL DEFAULT 0,
  `php_none` tinyint(1) NOT NULL DEFAULT 0,
  `external_ear_normal` tinyint(1) NOT NULL DEFAULT 0,
  `external_ear_abnormal` tinyint(1) NOT NULL DEFAULT 0,
  `external_ear_specify` text DEFAULT NULL,
  `middle_ear_normal` tinyint(1) NOT NULL DEFAULT 0,
  `middle_ear_abnormal` tinyint(1) NOT NULL DEFAULT 0,
  `middle_ear_specify` text DEFAULT NULL,
  `weber_centralization` tinyint(1) NOT NULL DEFAULT 0,
  `weber_lateralization_left` tinyint(1) NOT NULL DEFAULT 0,
  `weber_lateralization_right` tinyint(1) NOT NULL DEFAULT 0,
  `rinne_right_positive` tinyint(1) NOT NULL DEFAULT 0,
  `rinne_right_negative` tinyint(1) NOT NULL DEFAULT 0,
  `rinne_left_positive` tinyint(1) NOT NULL DEFAULT 0,
  `rinne_left_negative` tinyint(1) NOT NULL DEFAULT 0,
  `impression_conductive` tinyint(1) NOT NULL DEFAULT 0,
  `impression_sensorineural` tinyint(1) NOT NULL DEFAULT 0,
  `impression_mixed` tinyint(1) NOT NULL DEFAULT 0,
  `conclusion_occupational_hearing_impairment` tinyint(1) NOT NULL DEFAULT 0,
  `conclusion_occupational_permanent_standard_threshold_shift` tinyint(1) NOT NULL DEFAULT 0,
  `conclusion_occupational_noise_induced_hearing_loss` tinyint(1) NOT NULL DEFAULT 0,
  `conclusion_age_related_hearing_loss` tinyint(1) NOT NULL DEFAULT 0,
  `conclusion_others` tinyint(1) NOT NULL DEFAULT 0,
  `conclusion_others_specify` text DEFAULT NULL,
  `recommendation_repeat_audiometry` tinyint(1) NOT NULL DEFAULT 0,
  `recommendation_continue_annual_audiometry` tinyint(1) NOT NULL DEFAULT 0,
  `recommendation_provision_php` tinyint(1) NOT NULL DEFAULT 0,
  `recommendation_referral_specialist` tinyint(1) NOT NULL DEFAULT 0,
  `recommendation_notification_dosh` tinyint(1) NOT NULL DEFAULT 0,
  `recommendation_others` tinyint(1) NOT NULL DEFAULT 0,
  `recommendation_others_specify` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `ohd_name_signature_stamp` text DEFAULT NULL COMMENT 'Occupational Health Doctor signature/stamp',
  `employee_acknowledgment` text DEFAULT NULL,
  `employee_signature` text DEFAULT NULL,
  `employee_name` text DEFAULT NULL,
  `employee_ic_passport` text DEFAULT NULL,
  `employee_date` date DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audiometric_summaries`
--

CREATE TABLE `audiometric_summaries` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `surveillance_id` int(11) DEFAULT NULL,
  `audiometric_test_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Links to audiometric_tests.id',
  `right_ear_standard_threshold_shift` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `right_ear_average_2k_3k` decimal(5,2) DEFAULT NULL COMMENT 'Average of 2K, 3K frequencies',
  `right_ear_average_05_1k_2k_3k` decimal(5,2) DEFAULT NULL COMMENT 'Average of 0.5K, 1K, 2K, 3K frequencies',
  `left_ear_standard_threshold_shift` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `left_ear_average_2k_3k` decimal(5,2) DEFAULT NULL COMMENT 'Average of 2K, 3K frequencies',
  `left_ear_average_05_1k_2k_3k` decimal(5,2) DEFAULT NULL COMMENT 'Average of 0.5K, 1K, 2K, 3K frequencies',
  `standard_analysis` text DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `reviewed_by` varchar(255) DEFAULT NULL,
  `done_by` varchar(255) DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audiometric_tests`
--

CREATE TABLE `audiometric_tests` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `surveillance_id` int(11) DEFAULT NULL,
  `examination_date` date NOT NULL,
  `audiometer` varchar(100) DEFAULT NULL,
  `calibration_date` date DEFAULT NULL,
  `jkkp_approval_no` varchar(100) DEFAULT NULL,
  `seg_value` int(11) DEFAULT NULL COMMENT 'SEG value in dB',
  `otoscopy` varchar(50) DEFAULT NULL COMMENT 'NORMAL or ABNORMAL',
  `rinne_right` varchar(50) DEFAULT NULL COMMENT 'POSITIVE or NEGATIVE',
  `rinne_left` varchar(50) DEFAULT NULL COMMENT 'POSITIVE or NEGATIVE',
  `weber_center` varchar(50) DEFAULT NULL COMMENT 'YES or NO',
  `weber_right` varchar(50) DEFAULT NULL COMMENT 'YES or NO',
  `weber_left` varchar(50) DEFAULT NULL COMMENT 'YES or NO',
  `baseline_date` date DEFAULT NULL,
  `right_250` int(11) DEFAULT NULL COMMENT '250 Hz frequency in dB HL',
  `right_500` int(11) DEFAULT NULL COMMENT '500 Hz frequency in dB HL',
  `right_1k` int(11) DEFAULT NULL COMMENT '1000 Hz frequency in dB HL',
  `right_2k` int(11) DEFAULT NULL COMMENT '2000 Hz frequency in dB HL',
  `right_3k` int(11) DEFAULT NULL COMMENT '3000 Hz frequency in dB HL',
  `right_4k` int(11) DEFAULT NULL COMMENT '4000 Hz frequency in dB HL',
  `right_6k` int(11) DEFAULT NULL COMMENT '6000 Hz frequency in dB HL',
  `right_8k` int(11) DEFAULT NULL COMMENT '8000 Hz frequency in dB HL',
  `left_250` int(11) DEFAULT NULL,
  `left_500` int(11) DEFAULT NULL,
  `left_1k` int(11) DEFAULT NULL,
  `left_2k` int(11) DEFAULT NULL,
  `left_3k` int(11) DEFAULT NULL,
  `left_4k` int(11) DEFAULT NULL,
  `left_6k` int(11) DEFAULT NULL,
  `left_8k` int(11) DEFAULT NULL,
  `right_bone_250` int(11) DEFAULT NULL,
  `right_bone_500` int(11) DEFAULT NULL,
  `right_bone_1k` int(11) DEFAULT NULL,
  `right_bone_2k` int(11) DEFAULT NULL,
  `right_bone_3k` int(11) DEFAULT NULL,
  `right_bone_4k` int(11) DEFAULT NULL,
  `right_bone_6k` int(11) DEFAULT NULL,
  `right_bone_8k` int(11) DEFAULT NULL,
  `left_bone_250` int(11) DEFAULT NULL,
  `left_bone_500` int(11) DEFAULT NULL,
  `left_bone_1k` int(11) DEFAULT NULL,
  `left_bone_2k` int(11) DEFAULT NULL,
  `left_bone_3k` int(11) DEFAULT NULL,
  `left_bone_4k` int(11) DEFAULT NULL,
  `left_bone_6k` int(11) DEFAULT NULL,
  `left_bone_8k` int(11) DEFAULT NULL,
  `annual_date` date DEFAULT NULL,
  `annual_right_250` int(11) DEFAULT NULL,
  `annual_right_500` int(11) DEFAULT NULL,
  `annual_right_1k` int(11) DEFAULT NULL,
  `annual_right_2k` int(11) DEFAULT NULL,
  `annual_right_3k` int(11) DEFAULT NULL,
  `annual_right_4k` int(11) DEFAULT NULL,
  `annual_right_6k` int(11) DEFAULT NULL,
  `annual_right_8k` int(11) DEFAULT NULL,
  `annual_left_250` int(11) DEFAULT NULL,
  `annual_left_500` int(11) DEFAULT NULL,
  `annual_left_1k` int(11) DEFAULT NULL,
  `annual_left_2k` int(11) DEFAULT NULL,
  `annual_left_3k` int(11) DEFAULT NULL,
  `annual_left_4k` int(11) DEFAULT NULL,
  `annual_left_6k` int(11) DEFAULT NULL,
  `annual_left_8k` int(11) DEFAULT NULL,
  `annual_right_bone_250` int(11) DEFAULT NULL,
  `annual_right_bone_500` int(11) DEFAULT NULL,
  `annual_right_bone_1k` int(11) DEFAULT NULL,
  `annual_right_bone_2k` int(11) DEFAULT NULL,
  `annual_right_bone_3k` int(11) DEFAULT NULL,
  `annual_right_bone_4k` int(11) DEFAULT NULL,
  `annual_right_bone_6k` int(11) DEFAULT NULL,
  `annual_right_bone_8k` int(11) DEFAULT NULL,
  `annual_left_bone_250` int(11) DEFAULT NULL,
  `annual_left_bone_500` int(11) DEFAULT NULL,
  `annual_left_bone_1k` int(11) DEFAULT NULL,
  `annual_left_bone_2k` int(11) DEFAULT NULL,
  `annual_left_bone_3k` int(11) DEFAULT NULL,
  `annual_left_bone_4k` int(11) DEFAULT NULL,
  `annual_left_bone_6k` int(11) DEFAULT NULL,
  `annual_left_bone_8k` int(11) DEFAULT NULL,
  `ear_infections` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `head_injury` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `ototoxic_drugs` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `previous_ear_surgery` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `previous_noise_exposure` varchar(10) DEFAULT NULL COMMENT 'YES or NO',
  `significant_hobbies` text DEFAULT NULL COMMENT 'Description of hobbies',
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audiometric_tests`
--

INSERT INTO `audiometric_tests` (`id`, `patient_id`, `surveillance_id`, `examination_date`, `audiometer`, `calibration_date`, `jkkp_approval_no`, `seg_value`, `otoscopy`, `rinne_right`, `rinne_left`, `weber_center`, `weber_right`, `weber_left`, `baseline_date`, `right_250`, `right_500`, `right_1k`, `right_2k`, `right_3k`, `right_4k`, `right_6k`, `right_8k`, `left_250`, `left_500`, `left_1k`, `left_2k`, `left_3k`, `left_4k`, `left_6k`, `left_8k`, `right_bone_250`, `right_bone_500`, `right_bone_1k`, `right_bone_2k`, `right_bone_3k`, `right_bone_4k`, `right_bone_6k`, `right_bone_8k`, `left_bone_250`, `left_bone_500`, `left_bone_1k`, `left_bone_2k`, `left_bone_3k`, `left_bone_4k`, `left_bone_6k`, `left_bone_8k`, `annual_date`, `annual_right_250`, `annual_right_500`, `annual_right_1k`, `annual_right_2k`, `annual_right_3k`, `annual_right_4k`, `annual_right_6k`, `annual_right_8k`, `annual_left_250`, `annual_left_500`, `annual_left_1k`, `annual_left_2k`, `annual_left_3k`, `annual_left_4k`, `annual_left_6k`, `annual_left_8k`, `annual_right_bone_250`, `annual_right_bone_500`, `annual_right_bone_1k`, `annual_right_bone_2k`, `annual_right_bone_3k`, `annual_right_bone_4k`, `annual_right_bone_6k`, `annual_right_bone_8k`, `annual_left_bone_250`, `annual_left_bone_500`, `annual_left_bone_1k`, `annual_left_bone_2k`, `annual_left_bone_3k`, `annual_left_bone_4k`, `annual_left_bone_6k`, `annual_left_bone_8k`, `ear_infections`, `head_injury`, `ototoxic_drugs`, `previous_ear_surgery`, `previous_noise_exposure`, `significant_hobbies`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, '2023-01-15', 'Interacoustics AD629', '2022-12-01', 'JKKP-2023-001', 85, 'NORMAL', 'POSITIVE', 'POSITIVE', 'YES', 'NO', 'NO', '2023-01-15', 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'NO', 'NO', 'NO', 'NO', 'NO', NULL, 'system', '2026-01-15 02:30:58', NULL),
(2, 1, NULL, '2024-01-15', 'Interacoustics AD629', '2023-12-01', 'JKKP-2024-001', 85, 'NORMAL', 'POSITIVE', 'POSITIVE', 'YES', 'NO', 'NO', '2023-01-15', 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, '2024-01-15', 10, 10, 10, 12, 17, 17, 22, 22, 10, 10, 10, 12, 17, 17, 22, 22, 10, 10, 10, 12, 17, 17, 22, 22, 10, 10, 10, 12, 17, 17, 22, 22, 'NO', 'NO', 'NO', 'NO', 'NO', NULL, 'system', '2026-01-15 02:30:58', NULL),
(3, 1, NULL, '2025-01-15', 'Interacoustics AD629', '2024-12-01', 'JKKP-2025-001', 85, 'NORMAL', 'POSITIVE', 'POSITIVE', 'YES', 'NO', 'NO', '2023-01-15', 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, '2025-01-15', 10, 10, 10, 14, 19, 19, 24, 24, 10, 10, 10, 14, 19, 19, 24, 24, 10, 10, 10, 14, 19, 19, 24, 24, 10, 10, 10, 14, 19, 19, 24, 24, 'NO', 'NO', 'NO', 'NO', 'NO', NULL, 'system', '2026-01-15 02:30:58', NULL),
(4, 1, NULL, '2026-01-15', 'Interacoustics AD629', '2025-12-01', 'JKKP-2026-001', 85, 'NORMAL', 'POSITIVE', 'POSITIVE', 'YES', 'NO', 'NO', '2023-01-15', 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, 10, 10, 10, 10, 15, 15, 20, 20, '2026-01-15', 10, 10, 10, 16, 21, 21, 26, 26, 10, 10, 10, 16, 21, 21, 26, 26, 10, 10, 10, 16, 21, 21, 26, 26, 10, 10, 10, 16, 21, 21, 26, 26, 'NO', 'NO', 'NO', 'NO', 'NO', NULL, 'system', '2026-01-15 02:30:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `biological_monitoring`
--

CREATE TABLE `biological_monitoring` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `surveillance_id` int(11) DEFAULT NULL,
  `biological_exposure` text DEFAULT NULL,
  `result_baseline` text DEFAULT NULL,
  `result_annual` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `biological_monitoring`
--

INSERT INTO `biological_monitoring` (`id`, `patient_id`, `surveillance_id`, `biological_exposure`, `result_baseline`, `result_annual`) VALUES
(1, 1, 1, '', '', ''),
(2, 1, 2, 'Benzene in blood', '0.5 ppm', '1.2 ppm (Elevated)'),
(4, 14, 3, 'Toluene in blood, Xylene in urine', 'Baseline: 0.3 ppm (blood), 0.2 mg/L (urine)', 'Annual: 1.8 ppm (blood) - Elevated, 1.5 mg/L (urine) - Above BEI'),
(5, 14, 3, 'Toluene in blood, Xylene in urine', 'Baseline: 0.3 ppm (blood), 0.2 mg/L (urine)', 'Annual: 1.8 ppm (blood) - Elevated, 1.5 mg/L (urine) - Above BEI'),
(6, 14, 3, 'Toluene in blood, Xylene in urine', 'Baseline: 0.3 ppm (blood), 0.2 mg/L (urine)', 'Annual: 1.8 ppm (blood) - Elevated, 1.5 mg/L (urine) - Above BEI'),
(7, 14, 3, 'Toluene in blood, Xylene in urine', 'Baseline: 0.3 ppm (blood), 0.2 mg/L (urine)', 'Annual: 1.8 ppm (blood) - Elevated, 1.5 mg/L (urine) - Above BEI'),
(8, 14, 3, 'Toluene in blood, Xylene in urine', 'Baseline: 0.3 ppm (blood), 0.2 mg/L (urine)', 'Annual: 1.8 ppm (blood) - Elevated, 1.5 mg/L (urine) - Above BEI');

-- --------------------------------------------------------

--
-- Table structure for table `chemical_information`
--

CREATE TABLE `chemical_information` (
  `id` int(11) NOT NULL,
  `surveillance_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `workplace` varchar(255) DEFAULT NULL,
  `chemical` text DEFAULT NULL,
  `examination_date` date DEFAULT NULL,
  `examination_type` varchar(100) DEFAULT NULL,
  `final_assessment` varchar(255) DEFAULT NULL,
  `examiner_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chemical_information`
--

INSERT INTO `chemical_information` (`id`, `surveillance_id`, `patient_id`, `workplace`, `chemical`, `examination_date`, `examination_type`, `final_assessment`, `examiner_name`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Consist College', 'BENZENE', '2026-01-13', 'Pre-employment', NULL, 'System Administrator', '2026-01-13 06:35:02', '2026-01-13 06:35:02'),
(5, 2, 1, 'Main Production Floor', 'Benzene, Toluene', '2026-01-13', 'Annual', 'Not Fit for Work', 'Dr. Test Examiner', '2026-01-13 22:33:33', '2026-01-13 22:33:33'),
(7, 3, 14, 'Consist College', 'Toluene, Xylene, Benzene', '2026-01-14', 'Pre-employment', 'Not Fit for Work', 'Dr. Test Examiner', '2026-01-13 23:11:02', '2026-01-15 03:35:25');

-- --------------------------------------------------------

--
-- Table structure for table `clinical_findings`
--

CREATE TABLE `clinical_findings` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `surveillance_id` int(11) DEFAULT NULL,
  `result_clinical_findings` enum('Yes','No') DEFAULT 'No',
  `elaboration` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clinical_findings`
--

INSERT INTO `clinical_findings` (`id`, `patient_id`, `surveillance_id`, `result_clinical_findings`, `elaboration`) VALUES
(1, 1, 1, 'No', ''),
(2, 1, 2, 'Yes', 'Mild respiratory symptoms observed during examination'),
(4, 14, 3, 'Yes', 'Yes');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_info`
--

CREATE TABLE `clinic_info` (
  `id` int(11) NOT NULL,
  `clinic_name` varchar(255) NOT NULL,
  `operating_company` varchar(255) DEFAULT NULL,
  `established_year` varchar(50) DEFAULT NULL,
  `clinic_address` text DEFAULT NULL,
  `clinic_phone` varchar(50) DEFAULT NULL,
  `clinic_email` varchar(100) DEFAULT NULL,
  `clinic_logo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clinic_info`
--

INSERT INTO `clinic_info` (`id`, `clinic_name`, `operating_company`, `established_year`, `clinic_address`, `clinic_phone`, `clinic_email`, `clinic_logo_path`, `created_at`, `updated_at`) VALUES
(1, 'KLINIK HAYDAR & KAMAL', 'OPERATED BY WARISAN COMPANY', 'SINCE 1991', '', '', '', NULL, '2025-11-07 07:55:48', '2025-11-07 07:55:48');

-- --------------------------------------------------------

--
-- Table structure for table `company`
--

CREATE TABLE `company` (
  `id` int(11) NOT NULL,
  `company_id` varchar(10) DEFAULT NULL,
  `company_name` varchar(255) NOT NULL,
  `mykpp_registration_no` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `district` varchar(50) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fax` int(11) DEFAULT NULL,
  `total_workers` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company`
--

INSERT INTO `company` (`id`, `company_id`, `company_name`, `mykpp_registration_no`, `address`, `state`, `district`, `postcode`, `telephone`, `email`, `fax`, `total_workers`) VALUES
(1, 'COMP0001', 'Consist College', 'MYKPP001234', 'Jalan Industri 1, Kawasan Perindustrian Kota Bharu', 'Kelantan', 'Kota Bharu', '15300', '097444451', 'info@consist.com.my', 97444452, 3),
(2, 'COMP0002', 'Tech Solutions Sdn Bhd', 'MYKPP005678', 'No. 25, Jalan Teknologi 3/1, Taman Teknologi Malaysia', 'Kuala Lumpur', 'Kuala Lumpur', '57000', '0321234567', 'contact@techsolutions.com.my', 321234568, 4),
(3, 'COMP0003', 'Test Company Sdn Bhd', 'MYKPP999999', 'Lot 123, Jalan Test Industrial Park', 'Selangor', 'Kuala Selangor', '40000', '0333936301', 'test@gmail.com', 333936302, 1),
(7, 'COMP0004', 'Mumbai Industries Malaysia Sdn Bhd', 'MYKPP008901', 'Lot 789, Jalan Industri Mumbai, Kawasan Perindustrian Selangor', 'Selangor', 'Shah Alam', '40000', '0334567890', 'contact@mumbaiindustries.com.my', 334567891, 2);

-- --------------------------------------------------------

--
-- Table structure for table `conclusion_ms_finding`
--

CREATE TABLE `conclusion_ms_finding` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `surveillance_id` int(11) NOT NULL,
  `history_of_health` enum('Yes','No') DEFAULT 'No',
  `clinical_findings` enum('Yes','No') DEFAULT 'No',
  `target_organ` enum('Yes','No') DEFAULT 'No',
  `biological_monitoring` enum('Yes','No') DEFAULT 'No',
  `pregnancy_breast_feeding` enum('Yes','No') DEFAULT 'No',
  `clinical_work_related` enum('Yes','No') DEFAULT 'No',
  `organ_work_related` enum('Yes','No') DEFAULT 'No',
  `biological_work_related` enum('Yes','No') DEFAULT 'No'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conclusion_ms_finding`
--

INSERT INTO `conclusion_ms_finding` (`id`, `patient_id`, `surveillance_id`, `history_of_health`, `clinical_findings`, `target_organ`, `biological_monitoring`, `pregnancy_breast_feeding`, `clinical_work_related`, `organ_work_related`, `biological_work_related`) VALUES
(1, 1, 1, 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No'),
(2, 1, 2, 'Yes', 'Yes', 'No', 'Yes', 'No', 'Yes', 'No', 'Yes'),
(3, 14, 3, 'Yes', 'Yes', 'Yes', 'Yes', 'No', 'Yes', 'Yes', 'Yes');

-- --------------------------------------------------------

--
-- Table structure for table `declarations`
--

CREATE TABLE `declarations` (
  `declaration_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `employer` varchar(255) DEFAULT NULL,
  `patient_signature` text DEFAULT NULL,
  `doctor_signature` text DEFAULT NULL,
  `patient_date` date DEFAULT NULL,
  `doctor_date` date DEFAULT NULL,
  `surveillance_id` int(11) DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `declarations`
--

INSERT INTO `declarations` (`declaration_id`, `patient_id`, `patient_name`, `employer`, `patient_signature`, `doctor_signature`, `patient_date`, `doctor_date`, `surveillance_id`, `created_by`) VALUES
(1, 1, 'Ahmad Hassan', 'Consist College', 'data:image/png;base64,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', 'data:image/png;base64,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', '2026-01-15', '2026-01-15', NULL, 'admin'),
(2, 1, 'Ahmad Hassan', 'Consist College', 'data:image/png;base64,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', 'data:image/png;base64,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', '2026-01-15', '2026-01-15', NULL, 'admin');
INSERT INTO `declarations` (`declaration_id`, `patient_id`, `patient_name`, `employer`, `patient_signature`, `doctor_signature`, `patient_date`, `doctor_date`, `surveillance_id`, `created_by`) VALUES
(3, 1, 'Ahmad Hassan', 'Consist College', 'data:image/png;base64,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', 'data:image/png;base64,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', '2026-01-15', '2026-01-15', NULL, 'admin'),
(4, 1, 'Ahmad Hassan', 'Consist College', 'data:image/png;base64,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', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAfQAAADICAYAAAAeGRPoAAAQAElEQVR4AeydP68tyVVHLyQQEDhAcoCRcUY4ARkjmfcBkCeDDPwJAMkTAyGCAISIgciyREDoBA2WQCJAAgJLTpA90gQOHDhw4MCSXev67ue+fU/36e5Tf7vXqOt2n66qXXuvGulXu7rPeb/85H8SkIAEJCABCQxPQEEffgoNQAISkIAEJPD0VFbQJSwBCUhAAhKQQBUCCnoVzA4iAQlIQAISKEtgZEEvS0brEpCABCQggYEIKOgDTZauSkACEpCABJYIKOhLZLwvAQlIQAISGIiAgj7QZOmqBCQgAQlIYImAgr5Epux9rUtAAhKQgASyElDQs+LUmAQkIAEJSKANAQW9Dfeyo2pdAhKQgAQuR0BBv9yUG7AEJCABCZyRgIJ+xlktG5PWJSABCUigQwIKeoeToksSkIAEJCCBvQQU9L3EbF+WgNYlIAEJSOAQAQX9EDY7SUACEpCABPoioKD3NR96U5aA1iUgAQmcloCCftqpNTAJSEACErgSAQX9SrNtrGUJaF0CEpBAQwIKekP4Di0BCUhAAhLIRUBBz0VSOxIoS0DrEpCABFYJKOireKyUgAQkIAEJjEFAQR9jnvRSAmUJaF0CEhiegII+/BQagAQkIAEJSODpSUH3/wIJSKA0Ae1LQAIVCCjoFSA7hAQkIAEJSKA0AQW9NGHtS0ACZQloXQISeCagoD9j8I8EJCABCUhgbAIK+tjzp/cSkEBZAlqXwDAEFPRhpkpHJSABCUhAAssEFPRlNtZIQAISKEtA6xLISEBBzwhTUxKQgAQkIIFWBBT0VuQdVwISkEBZAlq/GAEF/WITbrgSkIAEJHBOAgr6OefVqCQgAQmUJaD17ggo6N1NiQ5JQAISkIAE9hNQ0Pczs4cEJCABCZQloPUDBBT0A9DsIgEJSEACEuiNgILe24zojwQkIAEJlCVwUusK+kkn1rAkIAEJSOBaBBT0a8230UpAAhKQQFkCzawr6M3QO7AEJCABCUggHwEFPR9LLUlAAvcJ/F5q8o+pcE4nDwlIYBeBlcYK+gocqyQggewEPkkW/zgVzunkIQEJ5CKgoOciqR0JSOAegamI/+ReY+slIIF9BDII+r4BbS0BCVySwF+kqKfb7P+aPntIQAIZCSjoGWFqSgISuEkAIf/zWc23Z5/9KAEJPEige0F/MD67S0ACbQn8Vhp+utX+rfSZ49/5Y5GABPIRUNDzsdSSBCTwlgAvwMVdRPyn6QNnSrr0kIAEchG4uKDnwqgdCUjgBgGy8+lW+1dTG7bfP5fOHhKQQGYCCnpmoJqTgATeE5iK+V+muwh8Oj3FtjvXZy7sTvAy4JljNLaOCCjoBSdD0xK4MAHEG0EDwffSH4SNe+ny6YdP5//vaylEfkDng3T2kEAVAgp6FcwOIoHLEQgxJ/B/5s+knP35OUL+1yneH6XyUSoeEqhCQEGvgrnEINqUQLcEyMSn2+1k5zjLfc5nFXTeD/huCjAWM7+frj0kUI2Agl4NtQNJ4DIEQtAImGfnnKOw/R7XZzqzaOHreSxaeKTAooVyphiNpXMCCnrnE9TKPceVwEECCNo0O/+niZ0vT67PdMkWOzGzWPmzFBhv8b9LZw8JVCWgoFfF7WASOD2BeXaOyE2DPlvWSlYeMfO1vK+kYOe7EumWhwTKE1DQyzN2hDcEvHFSAmvZOSHzjPlTLk5SyMyJiXDIyFm8wIDtd+5ZJFCVgIJeFbeDSeDUBP50Et2/pGsELp1eHWfJ0BHtyMwRc+Li8/yN/lfB+0ECJQko6CXparsJAQdtRuBPJiN/PLnmksyVM8LHeeSCcPPMnBh4RyBi+qN0g8/p5CGB+gQU9PrMHVECZyQQgk1sZOYUrqOwNT2/F3UjnRFzttrxGSHnuTnXEd8ZYiQey4AEFPQBJ02XWxJw7AUCCF1U3dp2ngp+tBvtjGiHmCPcbLVHDGTsIe5xz7MEqhJQ0KvidjAJnJIAYo2gRXBL285ktNFmxDMijt+cp2KO0HOPQr1FAk0IKOhNsDuoBG4TGPTuNDtH1CjzUPgO+uhvuBPXl1JgFK7T5fNB1m52/ozCPy0JKOgt6Tu2BM5BALGOSG5tt1MXWSzXI5epkBMHb7svxUy9RQLVCCjo1VA7kARaEygyPtvtiHUYR+Dien6ei+G8fsTPvNm+FvOIMenzoAQU9EEnTrcl0AmB6Xb70jNyRB93l+qpG7EQu78KN+LMndRnBf2kE2tYEqhEgAz1eaj0Z2nr+Qup7vupnOlgkcKjhqUXAM8Uq7EMQkBBH2SidFMCHRJA1Ci4xnb6krh9lhr8OJUzHWTnvgh3phk9QSwK+gkm0RAk0IgAohZDL2Xn1Ifoc32wdNWNuFnAdOWUzkhAQff/AQlI4CiBLd89xzaCfiYBJJ6l3QjitUigCQEFvQl2B5XA8AQQtQgCsabE5/mZumn7eX3zzzsc4DvnvtW+A5hN6xFQ0OuxdiQJnIkA284Rz9p2O20Qc0Sd65ELX8/71sgB6Pu5CSjo555fo5NACQII9HS7/SoZKzEf+OpdiSnQpgTeElDQ3zLxjgQksE5gmp1vzbxHz2xZxBDD1njXCVorgQIEFPQCUDUpgZMT4PvXEeKWH1ZBDKP9qGcWMV1m56MC1e/8BBT0/Ey1KIEzE0CceZZMjGSrW972ni4A6Ddi4Qd0FPQRZ+5CPivoF5psQ5VABgJkqmHm3stw0Y7zFuGnXY+FdwS27ET06PuDPtl9JAIK+kizpa8SaE9gmm1vFWmyerL59t4f84DsfGusx0awlwQyEFDQM0DUhAQuQgBhnm63jyzSW6eMHYk9OxFb7dru6elJCHkJKOh5eWpNAmcmgLhFfFtFjkXAyMLPV9XYco+4PUugWwIKerdTo2MS6I7AdLt9q8iR0fN1r+6C2eAQvvsi3AZQfTa5nlcK+vXm3IglcIsAmTQChlB/khp896Vwzb2oT7ef9ogc/UbN0Hl27stwT/43CgEFfZSZ0k8J5CWA0M4FHPFmi5n71FO45t7/TIbfk3GT1e9ZAEyGaXpJ7JRRFyNN4V1h8B5jVNB7nBV9kkB+An+YTJJp84+LTLNvxBrRTtXPBwLGG91kpl9Nd7jm3ufSdRxfTBfTPunj4jGqKPK+wNb3BBaDt0ICNQko6DVpO5YE6hNAUMm8v56GRrwRKu6lj+8PBBsBf5fufCkVhBzxR8y55l66/f7ABjZZHKwJO+Ng+33HQS7wm+124h/EZd08F4Fj0Sjox7jZSwIjEEBsycY5T/1FZBFwxPqXUgWCjYAvbY0jcKnZ8/Fx+htCF8LOGFynqlcH90bMckf1+xV8P1yPgIJ+vTk34msQQKDJoiPaz9IFYox4U6gPYU5VqwcCRwMWAn+TLlgIYCP6I/hk619LddODrfmlRcK0XU/XxEJ2Dp+e/NIXCdwlsFXQ7xqygQQk0AUBBAkhZ3s9HEJ4fzN9QIwR5XS56+DFNjpMs23shLDHS3K/TqNJYWeAdpNb3V+yeJnG2b3DOiiBIKCgBwnPEhifAFkl298IaUQTW+vx+cg57N0SZ+4xJnY/z5+XQp8RhZHFCxxfwvAkgXEI9CHo4/DSUwn0SOBWVo7Q8pLbo+KEbWLGHpk+1/MSwh1tqUfQOY9U8Dl2G0byW18l8ExAQX/G4B8JDEsAwSZDRowiCLJynnHneH7NFjR212xRh+Djw1TU8Y2+oxTeA1hatIwSg35emMAVBP3C02voJyaAcM6flSOqObLyKTa2oPn8KX9WSmTpsQDgxbKV5t1VwTMWJt05p0MS2EJAQd9CyTYS6IsAmW/JrHwaLVk3nxmT81Ihs2VBwct4H6ZGiGM6DXOQnceiZBindVQCUwIK+pTGkWv7SKAugRpZeURE1so1Qs15rdAmBJGvr430LDriHG0RsjYf1l2QgIJ+wUk35GEJIOaRMRNEzmfl2JuX2D4PoZ7Xzz+HIPIzsWTs8/peP5Odw7JX//RLApsIKOibMDVr5MASCAJTMScbzv2sPMaZniNzvbfdHn2iPT8oE/d6P+MzJRYjvfurfxJYJKCgL6KxQgLdEJiLea432O8FyItt37nXaFKPMPLx1/gzSGEXwux8kMnSzXUCCvo6n3PXGt0IBMiOY5udzBwxr+F3iPM3dgwWb8T/w44+LZsSI4uWkR4PtOTl2J0TUNA7nyDduzQBhJy3xoFQU8wZj8yVMwsKzvcK4oi/P0gNR9m+Jsat7weksDwk0DcBBb3v+RnZO31/nAAva4UVfjc9rmucWUiwiNg6FoJO22+nPyMIOv6SnW9dsKSwPCTQNwEFve/50bvrEkDMER0I8AJcTZEk02bcPdkr4kifX+HPAMXsfIBJ0sV9BBT0fbxs3QuBc/uBoCI4RMkLWzXFnDEZn/PW7JWFB/5+ljrteYkuNW9y4C8LkK3xNXHSQSWwl4CCvpeY7SVQngDb3YyCkLcQHcbfs92OmOPvf6Y/I/ygDP7u2X1IYXlIoH8CCnr/c6SH9Qm0HBGxiQyZ7Ly2L4zPmHsE70d0SOU3Uun9jXGz8zRJHuckoKCfc16NalwCPDvHe16CI0PnumaJr57t2RmI753Huaa/e8diwbJnsbLXvu0l0IyAgt4MvQNflsBy4CHmCHmrTBfB27PdTjTRPjJ17vVYzM57nBV9ykZAQc+GUkMSeJgAYooRsnPOtUtk5Xsz2BB0nqHX9nnPePDdG9se+7aVQFMCCnpT/A4ugfcE+HlXPiDmIZB83lseaR+/wR7CvtUWmS9te37DHR99s51ZspyWgIJ+2qk1sIEIIDa8CPdfyedWW+34QAbLdn9y43QHsZmdn25aDWhKQEGf0vBaAvUJIKSRnX9cf/j3IyJ4fFj/2hkt3paPXm612ll4GX71ZHa+isfKMxBQ0M8wi8YwMgFehEPU2Wr/j4aBHN1ub+jy5qF5hGB2vhmXDUcloKCPOnP6fQYCiDlb7Wxzt9pqhyMLCjL0oxn272IkQyllwuy8FFntdkVAQe9qOnTmQgQQUApizm+1twwdQWf8I1ksCxJ+8pX+PRaz8x5nRZ+KEFDQi2DVqARWCSCCZOdkxK3FHEfxhzPix3lPIfv9v5cOxPNy2c2Jn7H9+e5HNy7piATKEFDQy3DVqgSWCJANx0twPDdfalfzPqJ3VIzZZfj8i7NHbbx0z35ioYKY9+ZX9kA1KAEIKOhQsEigHgEyc0bjd9rZbue6ZUH0GP/odjti+avJAOd06uqANZxrOOUYEmhOQEFvPgU6cCECCAwCipAf2d4ugQp/sHvEH/qyEGDXARs9FXyCc48LjZ446cuJCCjoJ5pMQ+maAOLH9jQi08Nz84D1yHZ79EU8exNOFk8sNiLOsc96L4ENBBT0DZBsIoEHCSB4PDdH9HraAsYvQjsifNGXBQo2jvwgDf1KFHyjhG8lxtCmBLojoKB3NyU6dEICiDlh8RJcTyLDjgF+8eIY5z2FvixQ9vSp1Zadg54WTrXiXmDYgAAADrdJREFUPjqO/U5CQEE/yUQaRrcE2PolW0RgehJzgH05/UGUKely18HX1cjseZRAx15igzU+HVmkEIdFAsMSUNCHnTodH4AAwhKZ7JGXzkqGGMKHKO8dh74URJPz3v4l28ObxVPJMbS9h4BtqxFQ0KuhdqCLEUDoYqu9p5fgYhoQPq6PZNb0JaunYINyxA79chd2Dlho5LarPQl0T0BB736KdHBQAmy14zrZ4lT4uNdDiX+M5YgQs1UfmT3XvcTHQiP86oGxPpQn4AgTAgr6BIaXEshEAGFhux2x7G2rPULER/yLz1vP7DxEbFv71GpHdt4r71oMHOfCBBT0C0++oRchgOCRnZO18lZ7kUEeNIqPmDjyVTMWAsQWiwFs8Rl7LUsvfrRk4Ni5CQxmT0EfbMJ0t3sCCB5OsvXbg9Dhy7yEj0eyWbbYiS1sIqQ/iA8Nz3xVbepXQ1ccWgJtCCjobbg76jkJIG4IC0J+RCxrUUGU8XHveMQ33W7nMzb+nz8NC35M/WroikNLYDOB7A0V9OxINXhhAmy1E36vW+34FuJ3JJsls2chMN1ux+Y3+dOw4BcvHzZ0waEl0J6Agt5+DvTgHARCKBGWELweI8NP/DriI5n9dCFAVnzUFv1ylQ+SIb+qliB4XJvAK0G/Ngqjl8BDBOI75z1vtRMgIjzNsrm3pbAQiL7Rnq++YSs+tzjj0/+2GNgxJdAbAQW9txnRnxEJICoIHtl57/7HM/69frKtjXhPM2Fi5t5eWznbE8/Up5y2tSWBoQhUFPShuOisBPYQ4Nk5wtZ7do4AE9eRr6vxHe/pdjt2WMgcsUXfHCXigX0Oe9qQwNAEFPShp0/nOyBA5oqwzMWuA9feuICv3Ny78CA+yvS5O5+x1VJMiWcE7nCySKA4gdMIenFSDiCB2wTY8kXU9orkbWtl7/JSG77uHQXhpN8tQZ/e22v30fbsGrjd/ihF+5+GgIJ+mqk0kAYEEDoy1VGyRLbIj/jKomXeD1uIPKUB+ifGn/v05H8SuDIBBX3T7NtIAjcJIHQI2gjZOQsPgtibUUe/eSZMto+9VgX2c59a+eK4EuiCgILexTToxIAEIjsf4c128OIvi4+9go5w0o+CnSgtM2QWGfhDCX88S+DyBBT0Dv4X0IUhCYTQjZIl8rx57xY1wslCYN6P+0za3sUBfXIUfGr5dn2OGLQhgewEFPTsSDV4AQJkp4jaKNk5vlL2ZrQIJ9M5f6RA/NxvIejEweJklIUUnCwSqEJAQa+CueUgjl2AQHzvfBRRCQHe6++ScCKqexcHuaaBRcZ8xyCXbe1IYGgCCvrQ06fzDQggjghaz/8AyxwL/u7NpulDubW1jdC3ElXGnu8YzOP1swQuSUBBv+S05wv6gpYiO98rkC1R8bz/ljCv+UQfsvB5Vo/IU1rEj5C3WkissbJOAl0QUNC7mAadGITAh8lPxOxdOo9y4C++IoactxT6LG1tcx+hbyHoZudbZs82lyWgoF926kcIvDsfv548+k4qCFo6DXGEAO9xlj60v7UI4PvnLbJkHnW0GBcOFgkMQUBBH2KadLIDAmStX0h+/FUqIx1HBJjt9ltv8MMAYW2xoOFRx3z7f6R50FcJFCegoBdH7AC9EtjpF1krQjaSqIQA7/E5svJbfVoxYBHBdMGfs0UCErhBQEG/AcVbEpgRQBjJWkfb8sXvz1Ise4SQ59S0p6Sur44j2f4rAwc/kJ2P9N7CwTDtJoHHCCjoj/Gz9zUIRGYa2euGqLto8lHy4t9S2XqwAKCsbbfXfhkOf/D/1gKD+xYJSOCFgIL+AsKTBFYIjJidE85X0p89AkwmjHCubbfvsZeGf/j4JFkY6Tv/yV0PCbQhoKC34e6o4xCIrPyWyDWLYsPAZLaUrX7TlmfVS48V2IpfqtvgzqEm7IywgKAcMmAnCVyJgIJ+pdk21iMEEDKyVsqR/q36IM57fEY8aR8LmKnfiD1l6+Jg2veRa3ZGbm3/P2LTvhI4LQEF/bRTa2AZCCBilBFFBb+3Zra0RTxfMvA35ELsEfw3lYVusLDAn5pjFgpFsxKoQ0BBr8PZUcYkgMghKLUz0xy0eCP9042GEGyaIqKc5wUOiOv8fsnP7IyMyL0kE21LYJWAgr6Kx8oLEyBrRehqC1ku5Gy5bxFE4kSwl3YhqMenJbGnblfZ0Jix4M5iakNzm0hAAhBQ0KFgkcBbAog5goK4vK3t+w5iju+Ue54SJ22WxB+x32IHGzkKCwiy8xG554hfGxI4TEBBP4zOjicngJCRJY4YZgj6Ft+JE8GmzNsjrgh+TQ58de6Br6nNQ/CzBK5DQEG/zlwb6XYCkR3GeXvPPlry/HzLP5ca8S0JKGJORNGO65KFhQj2t77MR1uLBCTwQkBBfwHhSQITAmz5Lm1BT5p1eUlWjTBuEUXiJDNfahv1tQLt/kdkaoFwHAkcIaCgH6FmnzMTQBApSy+JjRD7mkiH/2Tda3FSR6m13Y4/MMf38NGzBCSwg4CCvgOWTS9BYO2Z8ggAYpv8nq+RfS/tRIQdhPaerUfrWTjgT42xHvW1YH9NS+AxAgr6Y/zsfS4CCAtCVisrLUGP5+f3/CdOylI76mJhU8LHuU1fhJsT8bMEDhBQ0A9As8tpCSDmbPmOnClueX4eYr0UJxyY5CXBpy5XwQde4Ft6jp9rnMvbEcD5CSjo559jI9xOAKGrIWLbPdrXksyaHmviSBsEey1Otr+xg9hyLlXwhbFKj1PKf+1KoCsCCnpX06EzDQmQ2TL80jNl6novCDU7DGt+smihzZKIIrIU2qzZyVHnVnsOil3Y0IkeCCjoPcyCPvRAgEwREaP04M8RH+49P0eoEf217Jx6xl5rQ/2jJcZZ2014dAz7S+BSBBT0S023wS4Q2CJ0C127us0uw5pAhoiuZedk8AS11Ia6HIVx3uUwpI3zEzDCbQQU9G2cbHVuAveEboToWZTg55KgU4+I8l1v2t0qwaH0LgWLhdI7ALfi854ETk1AQT/19BrcRgJsVS8J4UYTzZshxmtCTD1Orr0jwGMH2pQUWxYWjIOoM5ZFAo0JnGd4Bf08c2kkxwggMGxV89WpYxb66MWiZEmIiZHsnEXLkujThkI0JcWWn3d1qx3KFglkJqCgZwaqueEIROZaUsRqQGFRsiTWEWPr7XYYs+hY8rMGJ8eQQFUCNQdT0GvSdqweCZDZkrn26NtWnyKzXtpOJztHRJfipD9tGA/B5Zy7MIZb7bmpak8CEwIK+gSGl5cjgMiQ2Y6+3U4GjmDfmkCyYu5vyc5pF+25zllYMCz9M605x9GWBC5E4HWoCvprHn66FgEEnYhLiRi2axR2GZYya7JixH4pe8c/2nBeyuCpe6SwaKJ/KfvYtkjg8gQU9Mv/L3BpAAgNYjc6BOK4JZYsWChLYk/c1FO4XmtH/dHCL8Kt7RActWs/CUhgQiC3oE9MeymB7gmQmZYSsVrBhxjfEnSElAXL2g4E2/X4Sru1LJ42Rwpjwxj7R/rbRwIS2EhAQd8IymanI4AQUm4J4UjBIsi3xJLYyNwR06V4aMOzberX2lF/pGCfRROifqS/fSQggR0ExhL0HYHZVAJ3CCCENBld0Jeen0d8a2IabUpxYIfAF+Gga5FABQIKegXIDtEtgVuZbbfOLjhGFj5flJAZk3nfe25N9oxZOMxtcP+Rgg8l7D7ik30lcGoCCvovpteraxFA8EpsM9ekiGgy3lyMI/NeeyZOXwr9S3DgF+HuLSgY2yIBCWQioKBnAqmZoQiEkM2FcKggkrMIN1lwunx/EBuLFcR8Xve+UbqgTTo9H2vb8s8Ndv7BHouEtfF3mrS5BCRwj4CCfo9Qrnrt9EQghHB0Qb/1/JzYYL2WHSP60S636PII4IPkAKKeTh4SkEAtAgp6LdKOI4H8BBDP+aKEzBuRpiyNGGJO/ZrwU7+nsFBgq/1v93SyrQQkkIeAgp6HY2srjr+PAKLHlvC+Xn21RjzxaCrokRX/HRUrhfipRvTZmuc6R+GtdhYIU59y2NWGBCSwgYCCvgGSTU5F4JYQjhggWTaCHL4TF0LNvbUMOUSffjkXNWTm2Jza57NFAhKoREBBrwR66GHO5Tzb1Ije6FnkF9O0TAUZgU+3nu597zu+qkbbXOKLHbiSnWPXIgEJNCCgoDeA7pBNCZDJNnUg0+AIeCxKiInsnM+UpSFoR6E+11Y7Qs7Y75LRtbFTtYcEJFCSgIJekq62txCo3eYP0oDfSGXkI0Q5BJRn18RzL0NGeGlHudeWNvcKfrDVjh+Ue+2tl4AEChJQ0AvC1XSXBH47efXNVEY+yM55bEAMZMgUBJXCvVsF8aUfdfSlcH20YI+FBHbIzo/asZ8EJJCJgIKeCaRmOiXw2i1EiDtrwkf9CCWenyOq+Hvv2XmIOW2jL9dHC+OykLg37lH79pOABHYSUNB3ArP50ARC0IcOIjkfL7bxMhox8TycTDlV3TxoE9vttKPfzYYbb9IfMWfb/gyLo41h20wCfRNQ0PueH73LSwARQtByWW1hB3GmIOIh0gjrmi85s3MYMi5CjrCvjWudBCRQkYCCXhG2QzUncOunUps7tdMBxJxFSYg0Ys7nNTMIcNQ/IsKMzUtw2HKrHQoWCXREQEHvaDJ0pTgBssvig2Qb4LYhYvjvVBUifU+gp/WIf+p6+Agx5yW4e4uIw4PYUQISOEZAQT/GzV7jESC7xGu2ijmPWthl+J0X57cIdDxvpwvb9JyPFMQchvCjHLFhHwlIoCABBb0gXE13RYDMFodGFyPiQFjJkKfZN7HNS7TlPu0pXFP2FMbBFv3Jzvf0ta0EJFCJgIJeCbTDNCcQItjckQcc+HDSd8tXz2Jbnm5bsnnazQtCHnZ8bj6n42cJdERAQe9oMnRFAncI/P1LPZkyWfPLx5snFjCIMZW0P7Ldjg222rHBgmD77gY9LBKQQFUCCnpV3A7WkADPnhsO//DQCPgHL1a2ZMqRVdNlSzZPu3nhx2O4h5AzPtcWCUigUwI/AwAA///4+RsOAAAABklEQVQDAMmEWLTlddl3AAAAAElFTkSuQmCC', '2026-01-15', '2026-01-15', NULL, 'admin');
INSERT INTO `declarations` (`declaration_id`, `patient_id`, `patient_name`, `employer`, `patient_signature`, `doctor_signature`, `patient_date`, `doctor_date`, `surveillance_id`, `created_by`) VALUES
(5, 1, 'Ahmad Hassan', 'Consist College', 'data:image/png;base64,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', 'data:image/png;base64,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', '2026-01-15', '2026-01-15', NULL, 'admin'),
(6, 1, 'Ahmad Hassan', 'Consist College', 'data:image/png;base64,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', 'data:image/png;base64,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', '2026-01-15', '2026-01-15', NULL, 'admin');
INSERT INTO `declarations` (`declaration_id`, `patient_id`, `patient_name`, `employer`, `patient_signature`, `doctor_signature`, `patient_date`, `doctor_date`, `surveillance_id`, `created_by`) VALUES
(7, 1, 'Ahmad Hassan', 'Consist College', 'data:image/png;base64,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', 'data:image/png;base64,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', '2026-01-15', '2026-01-15', NULL, 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `fitness_respirator`
--

CREATE TABLE `fitness_respirator` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `result` enum('Fit','Not Fit') DEFAULT NULL,
  `justification` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fitness_respirator`
--

INSERT INTO `fitness_respirator` (`id`, `patient_id`, `result`, `justification`) VALUES
(1, 1, 'Not Fit', 'Respiratory symptoms present, not suitable for respirator use'),
(2, 14, 'Not Fit', 'Respiratory symptoms and abnormal lung function tests indicate patient is not suitable for respirato');

-- --------------------------------------------------------

--
-- Table structure for table `header_documents`
--

CREATE TABLE `header_documents` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `header_documents`
--

INSERT INTO `header_documents` (`id`, `filename`, `original_name`, `document_type`, `file_path`, `description`, `uploaded_by`, `uploaded_at`, `is_active`) VALUES
(3, 'header_document_1769425842.jpg', 'h&k header.jpg', NULL, 'C:\\xampp\\htdocs\\clinic\\public/uploads/headers/header_document_1769425842.jpg', '', '7', '2026-01-26 11:10:42', 1);

-- --------------------------------------------------------

--
-- Table structure for table `history_of_health`
--

CREATE TABLE `history_of_health` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `surveillance_id` int(11) NOT NULL,
  `breathing_difficulty` enum('Yes','No') DEFAULT 'No',
  `cough` enum('Yes','No') DEFAULT 'No',
  `sore_throat` enum('Yes','No') DEFAULT 'No',
  `sneezing` enum('Yes','No') DEFAULT 'No',
  `chest_pain` enum('Yes','No') DEFAULT 'No',
  `palpitation` enum('Yes','No') DEFAULT 'No',
  `limb_oedema` enum('Yes','No') DEFAULT 'No',
  `drowsiness` enum('Yes','No') DEFAULT 'No',
  `dizziness` enum('Yes','No') DEFAULT 'No',
  `headache` enum('Yes','No') DEFAULT 'No',
  `confusion` enum('Yes','No') DEFAULT 'No',
  `lethargy` enum('Yes','No') DEFAULT 'No',
  `nausea` enum('Yes','No') DEFAULT 'No',
  `vomiting` enum('Yes','No') DEFAULT 'No',
  `eye_irritations` enum('Yes','No') DEFAULT 'No',
  `blurred_vision` enum('Yes','No') DEFAULT 'No',
  `blisters` enum('Yes','No') DEFAULT 'No',
  `burns` enum('Yes','No') DEFAULT 'No',
  `itching` enum('Yes','No') DEFAULT 'No',
  `rash` enum('Yes','No') DEFAULT 'No',
  `redness` enum('Yes','No') DEFAULT 'No',
  `abdominal_pain` enum('Yes','No') DEFAULT 'No',
  `abdominal_mass` enum('Yes','No') DEFAULT 'No',
  `jaundice` enum('Yes','No') DEFAULT 'No',
  `diarrhoea` enum('Yes','No') DEFAULT 'No',
  `loss_of_weight` enum('Yes','No') DEFAULT 'No',
  `loss_of_appetite` enum('Yes','No') DEFAULT 'No',
  `dysuria` enum('Yes','No') DEFAULT 'No',
  `haematuria` enum('Yes','No') DEFAULT 'No',
  `others_symptoms` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `history_of_health`
--

INSERT INTO `history_of_health` (`id`, `patient_id`, `surveillance_id`, `breathing_difficulty`, `cough`, `sore_throat`, `sneezing`, `chest_pain`, `palpitation`, `limb_oedema`, `drowsiness`, `dizziness`, `headache`, `confusion`, `lethargy`, `nausea`, `vomiting`, `eye_irritations`, `blurred_vision`, `blisters`, `burns`, `itching`, `rash`, `redness`, `abdominal_pain`, `abdominal_mass`, `jaundice`, `diarrhoea`, `loss_of_weight`, `loss_of_appetite`, `dysuria`, `haematuria`, `others_symptoms`) VALUES
(1, 1, 1, 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', ''),
(4, 1, 2, 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'Yes', 'Yes', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', 'No', NULL),
(6, 14, 3, 'Yes', 'Yes', 'No', 'No', 'Yes', 'No', 'No', 'Yes', 'Yes', 'Yes', 'No', 'Yes', 'No', 'No', 'Yes', 'No', 'No', 'No', 'Yes', 'Yes', 'No', 'No', 'No', 'No', 'No', 'Yes', 'No', 'No', 'No', 'Patient reports persistent breathing difficulty, frequent headaches, and skin irritation after chemical exposure.');

-- --------------------------------------------------------

--
-- Table structure for table `medical_history`
--

CREATE TABLE `medical_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `diagnosed_history` varchar(100) DEFAULT NULL,
  `medication_history` varchar(100) DEFAULT NULL,
  `admitted_history` varchar(100) DEFAULT NULL,
  `family_history` varchar(100) DEFAULT NULL,
  `others_history` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medical_history`
--

INSERT INTO `medical_history` (`id`, `patient_id`, `diagnosed_history`, `medication_history`, `admitted_history`, `family_history`, `others_history`) VALUES
(1, 1, 'Hypertension, Diabetes Type 2', 'Metformin 500mg daily, Amlodipine 5mg daily', 'Hospital admission in 2020 for chest pain', 'Father: Diabetes, Mother: Hypertension', 'Allergic to penicillin'),
(2, 2, 'None', 'None', 'None', 'Mother: Breast cancer', 'No known allergies'),
(3, 3, 'Asthma', 'Salbutamol inhaler as needed', 'Emergency admission in 2019 for asthma attack', 'Brother: Asthma', 'Allergic to dust mites'),
(4, 4, 'High cholesterol', 'Atorvastatin 20mg daily', 'None', 'Father: Heart disease', 'No known allergies'),
(5, 5, 'Depression, Anxiety', 'Sertraline 50mg daily', 'Psychiatric admission in 2021', 'Mother: Depression', 'Allergic to shellfish'),
(6, 6, 'None', 'None', 'None', 'None', 'No known allergies'),
(7, 7, 'Gastritis', 'Omeprazole 20mg daily', 'None', 'Father: Stomach ulcer', 'No known allergies'),
(8, 8, 'Migraine', 'Sumatriptan as needed', 'None', 'Mother: Migraine', 'Allergic to nuts'),
(9, 9, 'None', 'None', 'None', 'None', 'No known allergies'),
(10, 10, 'Thyroid disorder', 'Levothyroxine 75mcg daily', 'None', 'Sister: Thyroid disorder', 'No known allergies');

-- --------------------------------------------------------

--
-- Table structure for table `medical_removal_protection`
--

CREATE TABLE `medical_removal_protection` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `surveillance_id` int(11) DEFAULT NULL,
  `patient_name` varchar(200) NOT NULL,
  `nric_passport` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `workplace_name_address` text DEFAULT NULL,
  `employment_start_date` date DEFAULT NULL,
  `employment_duration` decimal(5,2) DEFAULT NULL,
  `health_hazard_present` varchar(500) DEFAULT NULL,
  `removal_type` enum('Temporary','Permanent') DEFAULT NULL,
  `examination_date` date DEFAULT NULL,
  `designated_work` varchar(200) DEFAULT NULL,
  `place_of_work` varchar(200) DEFAULT NULL,
  `department_section` varchar(200) DEFAULT NULL,
  `removal_months` int(11) DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `alternative_work_department` varchar(200) DEFAULT NULL,
  `chemical_name` varchar(200) DEFAULT NULL,
  `pregnancy` tinyint(1) DEFAULT 0,
  `breastfeeding` tinyint(1) DEFAULT 0,
  `abnormal_bem_result` tinyint(1) DEFAULT 0,
  `adverse_health_effects` tinyint(1) DEFAULT 0,
  `target_organ_abnormality` tinyint(1) DEFAULT 0,
  `other_reasons` text DEFAULT NULL,
  `ohd_name` varchar(200) DEFAULT NULL,
  `ohd_address` text DEFAULT NULL,
  `ohd_email` varchar(100) DEFAULT NULL,
  `ohd_hp` varchar(20) DEFAULT NULL,
  `ohd_tel` varchar(20) DEFAULT NULL,
  `ohd_fax` varchar(20) DEFAULT NULL,
  `ohd_signature_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_staff`
--

CREATE TABLE `medical_staff` (
  `id` int(11) NOT NULL,
  `doctor_id` varchar(10) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `NRIC` varchar(20) DEFAULT NULL,
  `passport_no` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `district` varchar(50) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `country_code` varchar(10) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employment_status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `qualification` varchar(100) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `years_of_experience` int(11) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telephone_no` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `occupational_history`
--

CREATE TABLE `occupational_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `employment_duration` int(100) DEFAULT NULL,
  `chemical_exposure_duration` varchar(100) DEFAULT NULL,
  `chemical_exposure_incidents` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `occupational_history`
--

INSERT INTO `occupational_history` (`id`, `patient_id`, `job_title`, `company_name`, `employment_duration`, `chemical_exposure_duration`, `chemical_exposure_incidents`) VALUES
(1, 1, 'Chemical Engineer', 'Consist College', 5, '5 years', 'Daily exposure to benzene, toluene, xylene during plant operations'),
(2, 2, 'Laboratory Technician', 'Tech Solutions Sdn Bhd', 3, '3 years', 'Regular exposure to formaldehyde, methanol, acetone in laboratory testing'),
(3, 3, 'Factory Worker', 'Test Company Sdn Bhd', 2, '2 years', 'Exposure to welding fumes, metal dust, and solvents'),
(4, 4, 'Quality Control Inspector', 'Mumbai Industries Malaysia Sdn Bhd', 4, '4 years', 'Exposure to food preservatives, cleaning chemicals, and sanitizers'),
(5, 5, 'Maintenance Technician', 'Consist College', 6, '6 years', 'Exposure to lubricants, coolants, and electrical cleaning solvents'),
(6, 6, 'Research Assistant', 'Tech Solutions Sdn Bhd', 1, '1 year', 'Limited exposure to laboratory chemicals and reagents'),
(7, 7, 'Plant Operator', 'Test Company Sdn Bhd', 3, '3 years', 'Daily exposure to ammonia, phosphoric acid, and other fertilizers'),
(8, 8, 'Pharmaceutical Technician', 'Mumbai Industries Malaysia Sdn Bhd', 2, '2 years', 'Exposure to pharmaceutical powders, solvents, and cleaning agents'),
(9, 9, 'Environmental Monitor', 'Tech Solutions Sdn Bhd', 1, '1 year', 'Minimal exposure to environmental sampling chemicals'),
(10, 10, 'Production Supervisor', 'Consist College', 5, '5 years', 'Exposure to textile dyes, bleaches, and finishing chemicals'),
(12, 14, 'Production Worker', 'Consist College', 4, '4 years', 'Daily exposure to toluene, xylene, and benzene during production operations. Symptoms worsen during ');

-- --------------------------------------------------------

--
-- Table structure for table `patient_information`
--

CREATE TABLE `patient_information` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `NRIC` varchar(20) DEFAULT NULL,
  `passport_no` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `district` varchar(50) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `telephone_no` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ethnicity` varchar(50) DEFAULT NULL,
  `citizenship` varchar(50) DEFAULT NULL,
  `martial_status` enum('Single','Married','Divorced','Widowed') DEFAULT NULL,
  `no_of_children` int(11) DEFAULT 0,
  `years_married` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient_information`
--

INSERT INTO `patient_information` (`id`, `patient_id`, `first_name`, `last_name`, `NRIC`, `passport_no`, `date_of_birth`, `gender`, `address`, `state`, `district`, `postcode`, `telephone_no`, `email`, `ethnicity`, `citizenship`, `martial_status`, `no_of_children`, `years_married`) VALUES
(1, 'PAT0001', 'Ahmad', 'Hassan', '900101-01-1234', NULL, '1990-01-01', 'Male', 'No. 123, Jalan Merdeka, Taman Indah', 'Selangor', 'Petaling Jaya', '47800', '012-3456789', 'ahmad.hassan@email.com', 'Malay', 'Malaysian', 'Married', 2, 5),
(2, 'PAT0002', 'Sarah', 'Lim', '880215-05-5678', NULL, '1988-02-15', 'Female', 'No. 456, Jalan Utama, Taman Sentosa', 'Kuala Lumpur', 'Kuala Lumpur', '50400', '013-9876543', 'sarah.lim@email.com', 'Chinese', 'Malaysian', 'Single', 0, NULL),
(3, 'PAT0003', 'Raj', 'Kumar', '920330-10-9012', NULL, '1992-03-30', 'Male', 'No. 789, Jalan Besar, Taman Maju', 'Penang', 'George Town', '10000', '014-5678901', 'raj.kumar@email.com', 'Indian', 'Malaysian', 'Married', 1, 3),
(4, 'PAT0004', 'Fatimah', 'Ahmad', '850712-08-3456', NULL, '1985-07-12', 'Female', 'No. 321, Jalan Cemerlang, Taman Bahagia', 'Johor', 'Johor Bahru', '80000', '011-2345678', 'fatimah.ahmad@email.com', 'Malay', 'Malaysian', 'Married', 3, 8),
(5, 'PAT0005', 'David', 'Wong', '870925-12-7890', 'A12345678', '1987-09-25', 'Male', 'No. 654, Jalan Harmoni, Taman Damai', 'Selangor', 'Shah Alam', '40000', '016-3456789', 'david.wong@email.com', 'Chinese', 'Malaysian', 'Divorced', 1, NULL),
(6, 'PAT0006', 'Priya', 'Devi', '910418-06-2345', NULL, '1991-04-18', 'Female', 'No. 987, Jalan Sejahtera, Taman Permai', 'Perak', 'Ipoh', '30000', '017-4567890', 'priya.devi@email.com', 'Indian', 'Malaysian', 'Single', 0, NULL),
(7, 'PAT0007', 'Muhammad', 'Ali', '890625-14-6789', NULL, '1989-06-25', 'Male', 'No. 147, Jalan Makmur, Taman Jaya', 'Kedah', 'Alor Setar', '5000', '018-5678901', 'muhammad.ali@email.com', 'Malay', 'Malaysian', 'Married', 2, 4),
(8, 'PAT0008', 'Lisa', 'Tan', '860318-02-4567', NULL, '1986-03-18', 'Female', 'No. 258, Jalan Bahagia, Taman Indah', 'Melaka', 'Melaka', '75000', '019-6789012', 'lisa.tan@email.com', 'Chinese', 'Malaysian', 'Married', 1, 6),
(9, 'PAT0009', 'Suresh', 'Krishnan', '930511-16-8901', NULL, '1993-05-11', 'Male', 'No. 369, Jalan Ceria, Taman Murni', 'Negeri Sembilan', 'Seremban', '70000', '010-7890123', 'suresh.krishnan@email.com', 'Indian', 'Malaysian', 'Single', 0, NULL),
(10, 'PAT0010', 'Nurul', 'Ain', '880907-04-1234', NULL, '1988-09-07', 'Female', 'No. 741, Jalan Damai, Taman Sentosa', 'Terengganu', 'Kuala Terengganu', '20000', '012-8901234', 'nurul.ain@email.com', 'Malay', 'Malaysian', 'Married', 2, 7),
(14, 'PAT0011', 'Siti', 'Rahman', '880202-02-5678', NULL, '1988-02-02', 'Female', 'No. 456, Jalan Industri, Taman Perindustrian', 'Selangor', 'Shah Alam', '40000', '013-9876543', 'siti.rahman@email.com', 'Malay', 'Malaysian', 'Single', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `personal_social_history`
--

CREATE TABLE `personal_social_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `smoking_history` enum('Yes','No') DEFAULT NULL,
  `years_of_smoking` int(100) DEFAULT NULL,
  `no_of_cigarettes` int(100) DEFAULT NULL,
  `vaping_history` enum('Yes','No') DEFAULT NULL,
  `years_of_vaping` int(100) DEFAULT NULL,
  `hobby` varchar(100) DEFAULT NULL,
  `parttime_job` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_social_history`
--

INSERT INTO `personal_social_history` (`id`, `patient_id`, `smoking_history`, `years_of_smoking`, `no_of_cigarettes`, `vaping_history`, `years_of_vaping`, `hobby`, `parttime_job`) VALUES
(1, 1, '', 10, 20, 'No', 0, 'Football, Reading', 'None'),
(2, 2, '', 0, 0, 'No', 0, 'Yoga, Cooking', 'Freelance graphic designer'),
(3, 3, '', 5, 15, 'No', 0, 'Cricket, Photography', 'None'),
(4, 4, '', 0, 0, 'No', 0, 'Gardening, Sewing', 'None'),
(5, 5, '', 8, 10, 'Yes', 2, 'Gaming, Music', 'None'),
(6, 6, '', 0, 0, 'No', 0, 'Dancing, Swimming', 'Part-time tutor'),
(7, 7, '', 12, 25, 'No', 0, 'Fishing, Hiking', 'None'),
(8, 8, '', 0, 0, 'No', 0, 'Painting, Reading', 'None'),
(9, 9, '', 0, 0, 'No', 0, 'Basketball, Gaming', 'Food delivery driver'),
(10, 10, '', 0, 0, 'No', 0, 'Cooking, Traveling', 'None');

-- --------------------------------------------------------

--
-- Table structure for table `physical_examination`
--

CREATE TABLE `physical_examination` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `surveillance_id` int(11) NOT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `BMI` decimal(4,1) DEFAULT NULL,
  `bp_systolic` varchar(20) DEFAULT NULL,
  `bp_distolic` int(20) NOT NULL,
  `pulse_rate` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `general_appearance` enum('Normal','Abnormal') DEFAULT 'Normal',
  `s1_s2` enum('Yes','No') DEFAULT 'No',
  `murmur` enum('Yes','No') DEFAULT 'No',
  `ear_nose_throat` enum('Normal','Abnormal') DEFAULT 'Normal',
  `visual_acuity_left` varchar(20) DEFAULT NULL,
  `visual_acuity_right` varchar(20) DEFAULT NULL,
  `colour_blindness` enum('Yes','No') DEFAULT 'No',
  `tenderness` enum('Yes','No') DEFAULT 'No',
  `abdominal_mass` enum('Yes','No') DEFAULT 'No',
  `lymph_nodes` enum('Palpable','Non-palpable') DEFAULT 'Non-palpable',
  `splenomegaly` enum('Yes','No') DEFAULT 'No',
  `ballottable` enum('Yes','No') DEFAULT 'No',
  `jaundice` enum('Yes','No') DEFAULT 'No',
  `hepatomegaly` enum('Yes','No') DEFAULT 'No',
  `muscle_tone` enum('1','2','3','4','5') DEFAULT '3',
  `muscle_tenderness` enum('Yes','No') DEFAULT 'No',
  `power` enum('1','2','3','4','5') DEFAULT '3',
  `sensation` enum('Normal','Abnormal') DEFAULT 'Normal',
  `sound` enum('Clear','Rhonchi','Crepitus') DEFAULT 'Clear',
  `air_entry` enum('Normal','Abnormal') DEFAULT 'Normal',
  `respiratory_findings` text DEFAULT NULL,
  `reproductive` enum('Normal','Abnormal') DEFAULT 'Normal',
  `skin` enum('Normal','Abnormal') DEFAULT 'Normal',
  `ent` varchar(50) DEFAULT NULL,
  `gi_tenderness` enum('Yes','No') DEFAULT 'No',
  `abdominal_mass_exam` enum('Yes','No') DEFAULT 'No',
  `kidney_tenderness` enum('Yes','No') DEFAULT 'No',
  `ballotable` enum('Yes','No') DEFAULT 'No',
  `liver_jaundice` enum('Yes','No') DEFAULT 'No',
  `others_exam` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `physical_examination`
--

INSERT INTO `physical_examination` (`id`, `patient_id`, `surveillance_id`, `weight`, `height`, `BMI`, `bp_systolic`, `bp_distolic`, `pulse_rate`, `respiratory_rate`, `general_appearance`, `s1_s2`, `murmur`, `ear_nose_throat`, `visual_acuity_left`, `visual_acuity_right`, `colour_blindness`, `tenderness`, `abdominal_mass`, `lymph_nodes`, `splenomegaly`, `ballottable`, `jaundice`, `hepatomegaly`, `muscle_tone`, `muscle_tenderness`, `power`, `sensation`, `sound`, `air_entry`, `respiratory_findings`, `reproductive`, `skin`, `ent`, `gi_tenderness`, `abdominal_mass_exam`, `kidney_tenderness`, `ballotable`, `liver_jaundice`, `others_exam`) VALUES
(1, 1, 1, 0.00, 0.00, 0.0, '', 0, 0, 0, 'Normal', 'No', 'No', 'Normal', '', '', 'No', 'No', 'No', 'Non-palpable', 'No', 'No', 'No', 'No', '3', 'No', '3', 'Normal', '', 'Normal', '', 'Normal', 'Normal', 'Normal', 'No', 'No', 'No', 'No', 'No', ''),
(3, 1, 2, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'Abnormal', 'No', 'No', 'Normal', NULL, NULL, 'No', 'No', 'No', 'Non-palpable', 'No', 'No', 'No', 'No', '3', 'No', '3', 'Normal', 'Clear', 'Normal', NULL, 'Normal', 'Normal', NULL, 'No', 'No', 'No', 'No', 'No', NULL),
(5, 14, 3, 62.50, 158.00, 0.0, '', 0, 82, 18, 'Abnormal', 'Yes', 'No', 'Normal', '6/6', '6/6', 'No', 'No', 'No', 'Non-palpable', 'No', 'No', 'No', 'No', '3', 'Yes', '4', 'Normal', 'Rhonchi', 'Abnormal', '', 'Normal', 'Abnormal', 'Normal', 'No', 'No', 'No', 'No', 'No', 'Mild wheezing on auscultation, skin redness observed on hands and forearms.');

-- --------------------------------------------------------

--
-- Table structure for table `recommendations`
--

CREATE TABLE `recommendations` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `surveillance_id` int(11) NOT NULL,
  `recommendations_type` varchar(255) DEFAULT NULL,
  `date_of_MRP` date DEFAULT NULL,
  `next_review_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recommendations`
--

INSERT INTO `recommendations` (`id`, `patient_id`, `surveillance_id`, `recommendations_type`, `date_of_MRP`, `next_review_date`, `notes`) VALUES
(1, 1, 1, '', '0000-00-00', '0000-00-00', ''),
(2, 14, 3, 'Fit for work with restriction', '2026-01-30', '2026-01-31', '');

-- --------------------------------------------------------

--
-- Table structure for table `target_organ`
--

CREATE TABLE `target_organ` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `surveillance_id` int(11) NOT NULL,
  `blood_count` enum('Normal','Abnormal') DEFAULT 'Normal',
  `renal_function` enum('Normal','Abnormal') DEFAULT 'Normal',
  `liver_function` enum('Normal','Abnormal') DEFAULT 'Normal',
  `chest_xray` enum('Normal','Abnormal') DEFAULT 'Normal',
  `spirometry_fev1` decimal(10,2) DEFAULT NULL,
  `spirometry_fvc2` decimal(10,2) DEFAULT NULL,
  `spirometry_fev_fvc` decimal(10,2) DEFAULT NULL,
  `blood_comment` text DEFAULT NULL,
  `renal_comment` text DEFAULT NULL,
  `liver_comment` text DEFAULT NULL,
  `xray_comment` text DEFAULT NULL,
  `spirometry_comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `target_organ`
--

INSERT INTO `target_organ` (`id`, `patient_id`, `surveillance_id`, `blood_count`, `renal_function`, `liver_function`, `chest_xray`, `spirometry_fev1`, `spirometry_fvc2`, `spirometry_fev_fvc`, `blood_comment`, `renal_comment`, `liver_comment`, `xray_comment`, `spirometry_comment`) VALUES
(1, 1, 1, 'Normal', 'Normal', 'Normal', 'Normal', NULL, NULL, NULL, '', '', '', '', ''),
(2, 1, 2, 'Normal', 'Normal', 'Normal', 'Normal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 14, 3, 'Abnormal', 'Normal', 'Normal', 'Normal', NULL, NULL, NULL, 'Slightly elevated white blood cell count', 'Renal function tests within normal range', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `training_history`
--

CREATE TABLE `training_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `handling_of_chemical` enum('Yes','No') DEFAULT NULL,
  `chemical_comments` varchar(100) DEFAULT NULL,
  `sign_symptoms` enum('Yes','No') DEFAULT NULL,
  `sign_symptoms_comments` varchar(100) DEFAULT NULL,
  `chemical_poisoning` enum('Yes','No') DEFAULT NULL,
  `poisoning_comments` varchar(100) DEFAULT NULL,
  `proper_PPE` enum('Yes','No') DEFAULT NULL,
  `proper_PPE_comments` varchar(100) DEFAULT NULL,
  `PPE_usage` enum('Yes','No') DEFAULT NULL,
  `PPE_usage_comment` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `training_history`
--

INSERT INTO `training_history` (`id`, `patient_id`, `handling_of_chemical`, `chemical_comments`, `sign_symptoms`, `sign_symptoms_comments`, `chemical_poisoning`, `poisoning_comments`, `proper_PPE`, `proper_PPE_comments`, `PPE_usage`, `PPE_usage_comment`) VALUES
(1, 1, 'Yes', 'Completed 40-hour chemical safety training course', 'Yes', 'Trained to recognize symptoms of chemical exposure', 'Yes', 'Emergency response training for chemical incidents', 'Yes', 'Proper PPE selection and maintenance training', 'Yes', 'Always uses appropriate PPE including respirator and protective clothing'),
(2, 2, 'Yes', 'Laboratory safety training and chemical handling protocols', 'Yes', 'Trained to identify early signs of chemical exposure', 'No', 'No specific chemical poisoning training', 'Yes', 'PPE training for laboratory work', 'Yes', 'Consistently uses gloves, lab coat, and safety glasses'),
(3, 3, 'No', 'No formal chemical safety training received', 'No', 'No training on symptom recognition', 'No', 'No chemical poisoning training', 'No', 'No PPE training provided', 'No', 'Does not consistently use provided PPE'),
(4, 4, 'Yes', 'Food safety and chemical handling training', 'Yes', 'Basic training on chemical exposure symptoms', 'No', 'No chemical poisoning specific training', 'Yes', 'Basic PPE training for food industry', 'Yes', 'Uses gloves and protective clothing as required'),
(5, 5, 'Yes', 'Industrial safety training including chemical handling', 'Yes', 'Trained to recognize various chemical exposure symptoms', 'Yes', 'Emergency response training for chemical incidents', 'Yes', 'Comprehensive PPE training', 'Yes', 'Always uses full PPE including respiratory protection'),
(6, 6, 'Yes', 'Laboratory safety training for research work', 'Yes', 'Trained to recognize laboratory chemical exposure symptoms', 'No', 'No chemical poisoning training', 'Yes', 'Laboratory PPE training', 'Yes', 'Always uses laboratory PPE including gloves and safety glasses'),
(7, 7, 'No', 'No formal chemical safety training', 'No', 'No symptom recognition training', 'No', 'No chemical poisoning training', 'No', 'No PPE training provided', 'No', 'Rarely uses provided PPE equipment'),
(8, 8, 'Yes', 'Pharmaceutical industry safety training', 'Yes', 'Trained to recognize pharmaceutical chemical exposure', 'No', 'No chemical poisoning training', 'Yes', 'Pharmaceutical industry PPE training', 'Yes', 'Uses appropriate PPE for pharmaceutical work'),
(9, 9, 'Yes', 'Environmental monitoring safety training', 'Yes', 'Basic training on chemical exposure symptoms', 'No', 'No chemical poisoning training', 'Yes', 'Environmental monitoring PPE training', 'Yes', 'Uses PPE when handling environmental samples'),
(10, 10, 'Yes', 'Textile industry chemical safety training', 'Yes', 'Trained to recognize textile chemical exposure symptoms', 'No', 'No chemical poisoning training', 'Yes', 'Textile industry PPE training', 'Yes', 'Uses appropriate PPE for textile chemical handling');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Doctor','Nurse') DEFAULT 'Doctor',
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `first_name`, `last_name`, `email`, `created_at`, `updated_at`) VALUES
(2, 'doctor', 'doctor123', 'Doctor', 'Dr. Ahmad', 'Hassan', 'khk_44@yahoo.com', '2025-11-07 07:55:48', '2025-11-07 07:55:48'),
(7, 'admin', '$2y$12$Qnk9XH4UF85xBYmhSQ893.mwj4o2F/d2DkrI4Fb4OofyxYyRmU4cq', 'Admin', 'System', 'Administrator', 'admin@clinic.com', '2026-01-13 02:23:48', '2026-03-02 19:34:13');

-- --------------------------------------------------------

--
-- Table structure for table `user_signatures`
--

CREATE TABLE `user_signatures` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `signature_name` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_signatures`
--

INSERT INTO `user_signatures` (`id`, `user_id`, `filename`, `original_name`, `file_path`, `signature_name`, `uploaded_at`) VALUES
(2, 7, 'signature_7_1769425816.png', 'signature_1769425815943.png', 'C:\\xampp\\htdocs\\clinic\\public/uploads/signatures/signature_7_1769425816.png', 'Signature', '2026-01-26 11:10:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_doctor_id` (`doctor_id`),
  ADD KEY `idx_appointment_date` (`appointment_date`);

--
-- Indexes for table `audiometric_questionnaire`
--
ALTER TABLE `audiometric_questionnaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_test_id` (`audiometric_test_id`),
  ADD KEY `idx_test_date` (`test_date`);

--
-- Indexes for table `audiometric_reports`
--
ALTER TABLE `audiometric_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_patient_date` (`patient_id`,`employee_date`),
  ADD KEY `idx_reports_surveillance` (`surveillance_id`),
  ADD KEY `idx_reports_test` (`audiometric_test_id`),
  ADD KEY `idx_reports_summary` (`audiometric_summary_id`);

--
-- Indexes for table `audiometric_summaries`
--
ALTER TABLE `audiometric_summaries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_summaries_patient_date` (`patient_id`,`report_date`),
  ADD KEY `idx_summaries_surveillance` (`surveillance_id`),
  ADD KEY `idx_summaries_test` (`audiometric_test_id`);

--
-- Indexes for table `audiometric_tests`
--
ALTER TABLE `audiometric_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tests_patient_date` (`patient_id`,`examination_date`),
  ADD KEY `idx_tests_surveillance` (`surveillance_id`),
  ADD KEY `idx_tests_date` (`examination_date`),
  ADD KEY `idx_tests_baseline_date` (`baseline_date`),
  ADD KEY `idx_tests_annual_date` (`annual_date`);

--
-- Indexes for table `biological_monitoring`
--
ALTER TABLE `biological_monitoring`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_surveillance_id` (`surveillance_id`);

--
-- Indexes for table `chemical_information`
--
ALTER TABLE `chemical_information`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surveillance_id` (`surveillance_id`),
  ADD KEY `idx_patient_id` (`patient_id`);

--
-- Indexes for table `clinical_findings`
--
ALTER TABLE `clinical_findings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_surveillance_id` (`surveillance_id`);

--
-- Indexes for table `clinic_info`
--
ALTER TABLE `clinic_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company`
--
ALTER TABLE `company`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_company_id` (`company_id`);

--
-- Indexes for table `conclusion_ms_finding`
--
ALTER TABLE `conclusion_ms_finding`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surveillance_id` (`surveillance_id`);

--
-- Indexes for table `declarations`
--
ALTER TABLE `declarations`
  ADD PRIMARY KEY (`declaration_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_surveillance_id` (`surveillance_id`);

--
-- Indexes for table `fitness_respirator`
--
ALTER TABLE `fitness_respirator`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`);

--
-- Indexes for table `header_documents`
--
ALTER TABLE `header_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `history_of_health`
--
ALTER TABLE `history_of_health`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surveillance_id` (`surveillance_id`);

--
-- Indexes for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`);

--
-- Indexes for table `medical_removal_protection`
--
ALTER TABLE `medical_removal_protection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surveillance_id` (`surveillance_id`);

--
-- Indexes for table `medical_staff`
--
ALTER TABLE `medical_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_doctor_id` (`doctor_id`);

--
-- Indexes for table `occupational_history`
--
ALTER TABLE `occupational_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`);

--
-- Indexes for table `patient_information`
--
ALTER TABLE `patient_information`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_patient_id` (`patient_id`),
  ADD KEY `idx_nric` (`NRIC`);

--
-- Indexes for table `personal_social_history`
--
ALTER TABLE `personal_social_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`);

--
-- Indexes for table `physical_examination`
--
ALTER TABLE `physical_examination`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surveillance_id` (`surveillance_id`);

--
-- Indexes for table `recommendations`
--
ALTER TABLE `recommendations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surveillance_id` (`surveillance_id`);

--
-- Indexes for table `target_organ`
--
ALTER TABLE `target_organ`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surveillance_id` (`surveillance_id`);

--
-- Indexes for table `training_history`
--
ALTER TABLE `training_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`username`);

--
-- Indexes for table `user_signatures`
--
ALTER TABLE `user_signatures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audiometric_questionnaire`
--
ALTER TABLE `audiometric_questionnaire`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audiometric_reports`
--
ALTER TABLE `audiometric_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audiometric_summaries`
--
ALTER TABLE `audiometric_summaries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audiometric_tests`
--
ALTER TABLE `audiometric_tests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `biological_monitoring`
--
ALTER TABLE `biological_monitoring`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `chemical_information`
--
ALTER TABLE `chemical_information`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `clinical_findings`
--
ALTER TABLE `clinical_findings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `clinic_info`
--
ALTER TABLE `clinic_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `company`
--
ALTER TABLE `company`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `conclusion_ms_finding`
--
ALTER TABLE `conclusion_ms_finding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `declarations`
--
ALTER TABLE `declarations`
  MODIFY `declaration_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `fitness_respirator`
--
ALTER TABLE `fitness_respirator`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `header_documents`
--
ALTER TABLE `header_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `history_of_health`
--
ALTER TABLE `history_of_health`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `medical_removal_protection`
--
ALTER TABLE `medical_removal_protection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_staff`
--
ALTER TABLE `medical_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `occupational_history`
--
ALTER TABLE `occupational_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `patient_information`
--
ALTER TABLE `patient_information`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `personal_social_history`
--
ALTER TABLE `personal_social_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `physical_examination`
--
ALTER TABLE `physical_examination`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recommendations`
--
ALTER TABLE `recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `target_organ`
--
ALTER TABLE `target_organ`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `training_history`
--
ALTER TABLE `training_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_signatures`
--
ALTER TABLE `user_signatures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audiometric_questionnaire`
--
ALTER TABLE `audiometric_questionnaire`
  ADD CONSTRAINT `fk_questionnaire_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_questionnaire_test` FOREIGN KEY (`audiometric_test_id`) REFERENCES `audiometric_tests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `audiometric_reports`
--
ALTER TABLE `audiometric_reports`
  ADD CONSTRAINT `fk_reports_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reports_summary` FOREIGN KEY (`audiometric_summary_id`) REFERENCES `audiometric_summaries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reports_surveillance` FOREIGN KEY (`surveillance_id`) REFERENCES `chemical_information` (`surveillance_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reports_test` FOREIGN KEY (`audiometric_test_id`) REFERENCES `audiometric_tests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `audiometric_summaries`
--
ALTER TABLE `audiometric_summaries`
  ADD CONSTRAINT `fk_summaries_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_summaries_surveillance` FOREIGN KEY (`surveillance_id`) REFERENCES `chemical_information` (`surveillance_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_summaries_test` FOREIGN KEY (`audiometric_test_id`) REFERENCES `audiometric_tests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `audiometric_tests`
--
ALTER TABLE `audiometric_tests`
  ADD CONSTRAINT `fk_tests_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tests_surveillance` FOREIGN KEY (`surveillance_id`) REFERENCES `chemical_information` (`surveillance_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `chemical_information`
--
ALTER TABLE `chemical_information`
  ADD CONSTRAINT `fk_chemical_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `user_signatures`
--
ALTER TABLE `user_signatures`
  ADD CONSTRAINT `fk_signature_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
