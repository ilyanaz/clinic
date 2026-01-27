<?php
require_once 'config/clinic_database.php';

// Helper functions for clinic database operations

/**
 * Get the base URL path for the application
 * Returns the correct path based on the current directory structure
 */
function getBasePath() {
    // Get the current script directory
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    // Remove trailing slash if not root
    return rtrim($scriptPath, '/') ?: '';
}

/**
 * Get full URL path for redirects and links
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = getBasePath();
    return $protocol . '://' . $host . $path;
}

/**
 * Generate a relative URL path for internal links
 */
function url($path = '') {
    $base = getBasePath();
    $path = ltrim($path, '/');
    return $base . '/' . $path;
}

function sanitizeInput($input) {
    if (is_null($input)) {
        return null;
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function addPatientToClinic($data) {
    global $clinic_pdo;
    
    try {
        $clinic_pdo->beginTransaction();
        
        // Insert into patient_information table
        // Generate next PAT0000 format ID
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM patient_information");
        $count = $stmt->fetch()['count'];
        $next_patient_id = 'PAT' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        $stmt = $clinic_pdo->prepare("INSERT INTO patient_information (
            patient_id, first_name, last_name, NRIC, passport_no, date_of_birth, gender,
            address, state, district, postcode, telephone_no, email,
            ethnicity, citizenship, martial_status, no_of_children, years_married
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $next_patient_id,
            $data['first_name'],
            $data['last_name'],
            $data['NRIC'],
            $data['passport_no'] ?? null,
            $data['date_of_birth'],
            $data['gender'],
            $data['address'],
            $data['state'],
            $data['district'],
            $data['postcode'],
            $data['telephone_no'],
            $data['email'] ?? null,
            $data['ethnicity'] ?? null,
            $data['citizenship'] ?? null,
            $data['martial_status'] ?? null,
            $data['no_of_children'] ?? 0,
            $data['years_married'] ?? null
        ]);
        
        $patient_id = $clinic_pdo->lastInsertId();
        
        // Insert into medical_history table
        if (!empty($data['diagnosed_history']) || !empty($data['medication_history']) || 
            !empty($data['admitted_history']) || !empty($data['family_history']) || 
            !empty($data['others_history'])) {
            
            $stmt = $clinic_pdo->prepare("INSERT INTO medical_history (
                patient_id, diagnosed_history, medication_history, admitted_history,
                family_history, others_history
            ) VALUES (?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $patient_id,
                $data['diagnosed_history'] ?? null,
                $data['medication_history'] ?? null,
                $data['admitted_history'] ?? null,
                $data['family_history'] ?? null,
                $data['others_history'] ?? null
            ]);
        }
        
        // Insert into personal_social_history table
        if (!empty($data['smoking_history']) || !empty($data['vaping_history']) || 
            !empty($data['hobby']) || !empty($data['parttime_job'])) {
            
            $stmt = $clinic_pdo->prepare("INSERT INTO personal_social_history (
                patient_id, smoking_history, years_of_smoking, no_of_cigarettes,
                vaping_history, years_of_vaping, hobby, parttime_job
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $patient_id,
                $data['smoking_history'] ?? 'Non-Smoker',
                $data['years_of_smoking'] ?? 0,
                $data['no_of_cigarettes'] ?? 0,
                $data['vaping_history'] ?? 'No',
                $data['years_of_vaping'] ?? 0,
                $data['hobby'] ?? null,
                $data['parttime_job'] ?? null
            ]);
        }
        
        // Insert into occupational_history table
        if (!empty($data['job_title']) || !empty($data['company_name']) || 
            !empty($data['employment_duration']) || !empty($data['chemical_exposure_duration']) || 
            !empty($data['chemical_exposure_incidents'])) {
            
            $stmt = $clinic_pdo->prepare("INSERT INTO occupational_history (
                patient_id, job_title, company_name, employment_duration,
                chemical_exposure_duration, chemical_exposure_incidents
            ) VALUES (?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $patient_id,
                $data['job_title'] ?? null,
                $data['company_name'] ?? null,
                $data['employment_duration'] ?? null,
                $data['chemical_exposure_duration'] ?? null,
                $data['chemical_exposure_incidents'] ?? null
            ]);
        }
        
        // Insert into training_history table
        if (!empty($data['handling_of_chemical']) || !empty($data['sign_symptoms']) || 
            !empty($data['chemical_poisoning']) || !empty($data['proper_PPE']) || 
            !empty($data['PPE_usage'])) {
            
            $stmt = $clinic_pdo->prepare("INSERT INTO training_history (
                patient_id, handling_of_chemical, chemical_comments, sign_symptoms,
                sign_symptoms_comments, chemical_poisoning, poisoning_comments,
                proper_PPE, proper_PPE_comments, PPE_usage, PPE_usage_comment
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $patient_id,
                $data['handling_of_chemical'] ?? 'No',
                $data['chemical_comments'] ?? null,
                $data['sign_symptoms'] ?? 'No',
                $data['sign_symptoms_comments'] ?? null,
                $data['chemical_poisoning'] ?? 'No',
                $data['poisoning_comments'] ?? null,
                $data['proper_PPE'] ?? 'No',
                $data['proper_PPE_comments'] ?? null,
                $data['PPE_usage'] ?? 'No',
                $data['PPE_usage_comment'] ?? null
            ]);
        }
        
        $clinic_pdo->commit();
        return ['success' => true, 'patient_id' => $patient_id, 'message' => 'Patient information saved successfully to clinic database.'];
        
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        return ['success' => false, 'message' => 'Error saving patient information: ' . $e->getMessage()];
    }
}

function getAllClinicPatients() {
    global $clinic_pdo;
    
    try {
        $sql = "SELECT p.*, 
                       mh.diagnosed_history, mh.medication_history, mh.family_history,
                       psh.smoking_history, psh.vaping_history, psh.hobby,
                       oh.job_title, oh.company_name,
                       th.handling_of_chemical, th.proper_PPE, th.PPE_usage
                FROM patient_information p 
                LEFT JOIN medical_history mh ON p.id = mh.patient_id 
                LEFT JOIN personal_social_history psh ON p.id = psh.patient_id
                LEFT JOIN occupational_history oh ON p.id = oh.patient_id
                LEFT JOIN training_history th ON p.id = th.patient_id
                ORDER BY p.patient_id DESC";
        
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return ['error' => 'Error fetching patients: ' . $e->getMessage()];
    }
}

function getClinicPatientById($id) {
    global $clinic_pdo;
    
    try {
        $sql = "SELECT p.*, 
                       mh.diagnosed_history, mh.medication_history, mh.admitted_history, 
                       mh.family_history, mh.others_history,
                       psh.smoking_history, psh.years_of_smoking, psh.no_of_cigarettes,
                       psh.vaping_history, psh.years_of_vaping, psh.hobby, psh.parttime_job,
                       oh.job_title, oh.company_name, oh.employment_duration,
                       oh.chemical_exposure_duration, oh.chemical_exposure_incidents,
                       th.handling_of_chemical, th.chemical_comments, th.sign_symptoms,
                       th.sign_symptoms_comments, th.chemical_poisoning, th.poisoning_comments,
                       th.proper_PPE, th.proper_PPE_comments, th.PPE_usage, th.PPE_usage_comment
                FROM patient_information p 
                LEFT JOIN medical_history mh ON p.id = mh.patient_id 
                LEFT JOIN personal_social_history psh ON p.id = psh.patient_id
                LEFT JOIN occupational_history oh ON p.id = oh.patient_id
                LEFT JOIN training_history th ON p.id = th.patient_id
                WHERE p.id = ?";
        
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        return ['error' => 'Error fetching patient: ' . $e->getMessage()];
    }
}

function updateClinicPatient($id, $data) {
    global $clinic_pdo;
    
    try {
        $clinic_pdo->beginTransaction();
        
        // Update patient_information table
        $stmt = $clinic_pdo->prepare("UPDATE patient_information SET 
            first_name = ?, last_name = ?, NRIC = ?, passport_no = ?, 
            date_of_birth = ?, gender = ?, address = ?, state = ?, 
            district = ?, postcode = ?, telephone_no = ?, email = ?,
            ethnicity = ?, citizenship = ?, martial_status = ?, 
            no_of_children = ?, years_married = ?
            WHERE id = ?");
        
        $stmt->execute([
            $data['first_name'], $data['last_name'], $data['NRIC'], 
            $data['passport_no'], $data['date_of_birth'], $data['gender'],
            $data['address'], $data['state'], $data['district'], 
            $data['postcode'], $data['telephone_no'], $data['email'],
            $data['ethnicity'], $data['citizenship'], $data['martial_status'],
            $data['no_of_children'], $data['years_married'], $id
        ]);
        
        // Update or insert medical_history
        $stmt = $clinic_pdo->prepare("SELECT id FROM medical_history WHERE patient_id = ?");
        $stmt->execute([$id]);
        $medical_exists = $stmt->fetch();
        
        if ($medical_exists) {
            $stmt = $clinic_pdo->prepare("UPDATE medical_history SET 
                diagnosed_history = ?, medication_history = ?, admitted_history = ?,
                family_history = ?, others_history = ?
                WHERE patient_id = ?");
            $stmt->execute([
                $data['diagnosed_history'], $data['medication_history'], 
                $data['admitted_history'], $data['family_history'], 
                $data['others_history'], $id
            ]);
        } else {
            $stmt = $clinic_pdo->prepare("INSERT INTO medical_history (
                patient_id, diagnosed_history, medication_history, admitted_history,
                family_history, others_history
            ) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id, $data['diagnosed_history'], $data['medication_history'], 
                $data['admitted_history'], $data['family_history'], $data['others_history']
            ]);
        }
        
        // Update or insert personal_social_history
        $stmt = $clinic_pdo->prepare("SELECT id FROM personal_social_history WHERE patient_id = ?");
        $stmt->execute([$id]);
        $personal_exists = $stmt->fetch();
        
        if ($personal_exists) {
            $stmt = $clinic_pdo->prepare("UPDATE personal_social_history SET 
                smoking_history = ?, years_of_smoking = ?, no_of_cigarettes = ?,
                vaping_history = ?, years_of_vaping = ?, hobby = ?, parttime_job = ?
                WHERE patient_id = ?");
            $stmt->execute([
                $data['smoking_history'], $data['years_of_smoking'], $data['no_of_cigarettes'],
                $data['vaping_history'], $data['years_of_vaping'], $data['hobby'], 
                $data['parttime_job'], $id
            ]);
        } else {
            $stmt = $clinic_pdo->prepare("INSERT INTO personal_social_history (
                patient_id, smoking_history, years_of_smoking, no_of_cigarettes,
                vaping_history, years_of_vaping, hobby, parttime_job
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id, $data['smoking_history'], $data['years_of_smoking'], $data['no_of_cigarettes'],
                $data['vaping_history'], $data['years_of_vaping'], $data['hobby'], $data['parttime_job']
            ]);
        }
        
        // Update or insert occupational_history
        $stmt = $clinic_pdo->prepare("SELECT id FROM occupational_history WHERE patient_id = ?");
        $stmt->execute([$id]);
        $occupational_exists = $stmt->fetch();
        
        if ($occupational_exists) {
            $stmt = $clinic_pdo->prepare("UPDATE occupational_history SET 
                job_title = ?, company_name = ?, employment_duration = ?,
                chemical_exposure_duration = ?, chemical_exposure_incidents = ?
                WHERE patient_id = ?");
            $stmt->execute([
                $data['job_title'], $data['company_name'], $data['employment_duration'],
                $data['chemical_exposure_duration'], $data['chemical_exposure_incidents'], $id
            ]);
        } else {
            $stmt = $clinic_pdo->prepare("INSERT INTO occupational_history (
                patient_id, job_title, company_name, employment_duration,
                chemical_exposure_duration, chemical_exposure_incidents
            ) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id, $data['job_title'], $data['company_name'], $data['employment_duration'],
                $data['chemical_exposure_duration'], $data['chemical_exposure_incidents']
            ]);
        }
        
        // Update or insert training_history
        $stmt = $clinic_pdo->prepare("SELECT id FROM training_history WHERE patient_id = ?");
        $stmt->execute([$id]);
        $training_exists = $stmt->fetch();
        
        if ($training_exists) {
            $stmt = $clinic_pdo->prepare("UPDATE training_history SET 
                handling_of_chemical = ?, chemical_comments = ?, sign_symptoms = ?,
                sign_symptoms_comments = ?, chemical_poisoning = ?, poisoning_comments = ?,
                proper_PPE = ?, proper_PPE_comments = ?, PPE_usage = ?, PPE_usage_comment = ?
                WHERE patient_id = ?");
            $stmt->execute([
                $data['handling_of_chemical'], $data['chemical_comments'], $data['sign_symptoms'],
                $data['sign_symptoms_comments'], $data['chemical_poisoning'], $data['poisoning_comments'],
                $data['proper_PPE'], $data['proper_PPE_comments'], $data['PPE_usage'], $data['PPE_usage_comment'], $id
            ]);
        } else {
            $stmt = $clinic_pdo->prepare("INSERT INTO training_history (
                patient_id, handling_of_chemical, chemical_comments, sign_symptoms,
                sign_symptoms_comments, chemical_poisoning, poisoning_comments,
                proper_PPE, proper_PPE_comments, PPE_usage, PPE_usage_comment
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id, $data['handling_of_chemical'], $data['chemical_comments'], $data['sign_symptoms'],
                $data['sign_symptoms_comments'], $data['chemical_poisoning'], $data['poisoning_comments'],
                $data['proper_PPE'], $data['proper_PPE_comments'], $data['PPE_usage'], $data['PPE_usage_comment']
            ]);
        }
        
        $clinic_pdo->commit();
        return ['success' => true, 'message' => 'Patient information updated successfully.'];
        
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        return ['success' => false, 'message' => 'Error updating patient: ' . $e->getMessage()];
    }
}

function deleteClinicPatient($id) {
    global $clinic_pdo;
    
    try {
        $stmt = $clinic_pdo->prepare("DELETE FROM patient_information WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true, 'message' => 'Patient deleted successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting patient: ' . $e->getMessage()];
    }
}

// Health Surveillance Functions
function addHealthSurveillance($data) {
    global $clinic_pdo;
    
    try {
        // Ensure patient_id is an integer
        $patient_id = (int)($data['patient_id'] ?? 0);
        if ($patient_id <= 0) {
            throw new Exception('Invalid patient_id: ' . ($data['patient_id'] ?? 'not provided'));
        }
        
        error_log("addHealthSurveillance: Starting save for patient_id: " . $patient_id);
        
        $clinic_pdo->beginTransaction();

        // Determine next surveillance_id using a reliable method
        // Check ALL tables that have surveillance_id UNIQUE constraints to avoid conflicts
        // Tables with surveillance_id UNIQUE: chemical_information, history_of_health, physical_examination, 
        // clinical_findings, target_organ, biological_monitoring, conclusion_ms_finding, recommendations
        $tables_to_check = [
            'chemical_information',
            'history_of_health', 
            'physical_examination',
            'clinical_findings',
            'target_organ',
            'biological_monitoring',
            'conclusion_ms_finding',
            'recommendations'
        ];
        
        $all_existing_ids = [];
        foreach ($tables_to_check as $table) {
            try {
                $stmt = $clinic_pdo->query("SELECT DISTINCT surveillance_id FROM $table WHERE surveillance_id IS NOT NULL ORDER BY surveillance_id ASC");
                $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                $all_existing_ids = array_merge($all_existing_ids, $ids);
                error_log("addHealthSurveillance: Found " . count($ids) . " surveillance_ids in $table");
            } catch (Exception $e) {
                // Table might not exist or have surveillance_id column, skip it
                error_log("addHealthSurveillance: Could not check $table: " . $e->getMessage());
            }
        }
        
        // Remove duplicates and sort
        $all_existing_ids = array_unique($all_existing_ids);
        sort($all_existing_ids);
        
        $next_surveillance_id = 1;
        
        if (!empty($all_existing_ids)) {
            // Find the maximum ID and try max + 1
            $max_id = max($all_existing_ids);
            $next_surveillance_id = $max_id + 1;
            
            // If max+1 already exists (shouldn't happen), find first gap
            if (in_array($next_surveillance_id, $all_existing_ids, true)) {
                error_log("addHealthSurveillance: Max+1 (" . $next_surveillance_id . ") already exists, finding gap");
                // Find first gap starting from 1
                for ($i = 1; $i <= $max_id + 100; $i++) {
                    if (!in_array($i, $all_existing_ids, true)) {
                        $next_surveillance_id = $i;
                        break;
                    }
                }
            }
        }
        
        // Final verification: check if the selected ID exists in ANY table (race condition protection)
        $id_exists = false;
        foreach ($tables_to_check as $table) {
            try {
                $verify_stmt = $clinic_pdo->prepare("SELECT COUNT(*) FROM $table WHERE surveillance_id = ?");
                $verify_stmt->execute([$next_surveillance_id]);
                if ((int)$verify_stmt->fetchColumn() > 0) {
                    $id_exists = true;
                    error_log("addHealthSurveillance: surveillance_id $next_surveillance_id exists in $table");
                    break;
                }
            } catch (Exception $e) {
                // Skip if table doesn't exist
            }
        }
        
        if ($id_exists) {
            // ID exists in some table, get max from all tables and use max+1
            $max_from_all = 0;
            foreach ($tables_to_check as $table) {
                try {
                    $stmt = $clinic_pdo->query("SELECT COALESCE(MAX(surveillance_id), 0) AS max_id FROM $table");
                    $table_max = (int)$stmt->fetchColumn();
                    if ($table_max > $max_from_all) {
                        $max_from_all = $table_max;
                    }
                } catch (Exception $e) {
                    // Skip if table doesn't exist
                }
            }
            $next_surveillance_id = $max_from_all + 1;
            error_log("addHealthSurveillance: ID exists, using max+1 from all tables: " . $next_surveillance_id);
        }
        
        error_log("addHealthSurveillance: Next surveillance_id: " . $next_surveillance_id . " (existing in all tables: " . (empty($all_existing_ids) ? 'none' : implode(',', $all_existing_ids)) . ")");
        
        // 1. Insert into chemical_information (only basic columns)
        $executeParams = [
            $next_surveillance_id,
            $patient_id,
            $data['workplace'] ?? null,
            $data['chemical'] ?? null,
            $data['examination_date'] ?? null,
            $data['examination_type'] ?? null,
            $data['examiner_name'] ?? null,
            $data['final_assessment'] ?? null
        ];
        
        error_log("addHealthSurveillance: Inserting into chemical_information with params: " . json_encode($executeParams));
        
        // Try to insert, and if we get a duplicate key error, retry with next ID
        $insert_attempts = 0;
        $max_insert_attempts = 20;
        $insert_success = false;
        $last_error = null;
        
        while ($insert_attempts < $max_insert_attempts && !$insert_success) {
            try {
                // Re-prepare statement for each attempt to ensure fresh state
                $stmt = $clinic_pdo->prepare("INSERT INTO chemical_information (
                    surveillance_id, patient_id, workplace, chemical, examination_date, examination_type, examiner_name, final_assessment
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                $executeParams[0] = $next_surveillance_id; // Ensure we use the current ID
                
                error_log("addHealthSurveillance: Attempt " . ($insert_attempts + 1) . " - Trying to insert with surveillance_id: " . $next_surveillance_id);
                
                $stmt->execute($executeParams);
                $insert_success = true;
                error_log("addHealthSurveillance: Successfully inserted into chemical_information with surveillance_id: " . $next_surveillance_id);
            } catch (PDOException $e) {
                $last_error = $e;
                // Check if it's a duplicate key error for surveillance_id
                $error_code = $e->getCode();
                $error_message = $e->getMessage();
                
                if ($error_code == 23000 && (strpos($error_message, 'Duplicate entry') !== false) && (strpos($error_message, 'surveillance_id') !== false || strpos($error_message, 'surveillance_id') !== false)) {
                    error_log("addHealthSurveillance: Duplicate surveillance_id " . $next_surveillance_id . " detected (attempt " . ($insert_attempts + 1) . "), trying next ID");
                    
                    // Get max surveillance_id from ALL tables that have this constraint
                    $max_from_all = 0;
                    foreach ($tables_to_check as $table) {
                        try {
                            $check_stmt = $clinic_pdo->query("SELECT COALESCE(MAX(surveillance_id), 0) AS max_id FROM $table");
                            $table_max = (int)$check_stmt->fetchColumn();
                            if ($table_max > $max_from_all) {
                                $max_from_all = $table_max;
                            }
                        } catch (Exception $ex) {
                            // Skip if table doesn't exist
                        }
                    }
                    
                    $next_surveillance_id = $max_from_all + 1;
                    
                    // Double-check this new ID doesn't exist in ANY table
                    $id_still_exists = true;
                    $check_attempts = 0;
                    while ($id_still_exists && $check_attempts < 100) {
                        $id_still_exists = false;
                        foreach ($tables_to_check as $table) {
                            try {
                                $verify_stmt = $clinic_pdo->prepare("SELECT COUNT(*) FROM $table WHERE surveillance_id = ?");
                                $verify_stmt->execute([$next_surveillance_id]);
                                if ((int)$verify_stmt->fetchColumn() > 0) {
                                    $id_still_exists = true;
                                    break;
                                }
                            } catch (Exception $ex) {
                                // Skip if table doesn't exist
                            }
                        }
                        
                        if ($id_still_exists) {
                            $next_surveillance_id++;
                            $check_attempts++;
                        }
                    }
                    
                    error_log("addHealthSurveillance: New surveillance_id after duplicate detection: " . $next_surveillance_id);
                    $insert_attempts++;
                } else {
                    // Different error, log it and re-throw
                    error_log("addHealthSurveillance: Non-duplicate error: " . $error_message);
                    throw $e;
                }
            }
        }
        
        if (!$insert_success) {
            error_log("addHealthSurveillance: Failed to insert after " . $max_insert_attempts . " attempts. Last error: " . ($last_error ? $last_error->getMessage() : 'Unknown'));
            throw new Exception("Failed to insert surveillance record after " . $max_insert_attempts . " attempts. Last error: " . ($last_error ? $last_error->getMessage() : 'Unknown'));
        }
        
        // Use the surveillance_id we inserted, not lastInsertId (which returns the auto-increment id)
        $surveillance_id = $next_surveillance_id;
        
        // 2. Insert into history_of_health
        $stmt = $clinic_pdo->prepare("INSERT INTO history_of_health (
            patient_id, surveillance_id, breathing_difficulty, cough, sore_throat, sneezing, chest_pain, palpitation, limb_oedema,
            drowsiness, dizziness, headache, confusion, lethargy, nausea, vomiting,
            eye_irritations, blurred_vision, blisters, burns, itching, rash, redness,
            abdominal_pain, abdominal_mass, jaundice, diarrhoea, loss_of_weight, loss_of_appetite, dysuria, haematuria,
            others_symptoms
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        error_log("addHealthSurveillance: Inserting into history_of_health");
        
        $stmt->execute([
            $patient_id,
            $next_surveillance_id,
            $data['breathing_difficulty'] ?? 'No',
            $data['cough'] ?? 'No',
            $data['sore_throat'] ?? 'No',
            $data['sneezing'] ?? 'No',
            $data['chest_pain'] ?? 'No',
            $data['palpitation'] ?? 'No',
            $data['limb_oedema'] ?? 'No',
            $data['drowsiness'] ?? 'No',
            $data['dizziness'] ?? 'No',
            $data['headache'] ?? 'No',
            $data['confusion'] ?? 'No',
            $data['lethargy'] ?? 'No',
            $data['nausea'] ?? 'No',
            $data['vomiting'] ?? 'No',
            $data['eye_irritations'] ?? 'No',
            $data['blurred_vision'] ?? 'No',
            $data['blisters'] ?? 'No',
            $data['burns'] ?? 'No',
            $data['itching'] ?? 'No',
            $data['rash'] ?? 'No',
            $data['redness'] ?? 'No',
            $data['abdominal_pain'] ?? 'No',
            $data['abdominal_mass'] ?? 'No',
            $data['jaundice'] ?? 'No',
            $data['diarrhoea'] ?? 'No',
            $data['loss_of_weight'] ?? 'No',
            $data['loss_of_appetite'] ?? 'No',
            $data['dysuria'] ?? 'No',
            $data['haematuria'] ?? 'No',
            $data['others_symptoms'] ?? null
        ]);
        
        $health_id = $clinic_pdo->lastInsertId();
        
        // 3. Insert into clinical_findings
        error_log("addHealthSurveillance: Inserting into clinical_findings");
        $stmt = $clinic_pdo->prepare("INSERT INTO clinical_findings (patient_id, surveillance_id, result_clinical_findings, elaboration) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $patient_id, 
            $next_surveillance_id,
            $data['has_clinical_findings'] ?? 'No',
            $data['clinical_elaboration'] ?? null
        ]);
        
        // 4. Insert into physical_examination
        error_log("addHealthSurveillance: Inserting into physical_examination");
        $stmt = $clinic_pdo->prepare("INSERT INTO physical_examination (
            patient_id, surveillance_id, weight, height, BMI, bp_systolic, bp_distolic, pulse_rate, respiratory_rate,
            general_appearance, s1_s2, murmur, ear_nose_throat, visual_acuity_left, visual_acuity_right,
            colour_blindness, tenderness, abdominal_mass, lymph_nodes, splenomegaly, 
            ballottable, jaundice, hepatomegaly, muscle_tone, muscle_tenderness, 
            power, sensation, sound, air_entry, respiratory_findings, reproductive, skin, ent, gi_tenderness, abdominal_mass_exam, kidney_tenderness, ballotable, liver_jaundice, others_exam
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $patient_id,
            $next_surveillance_id,
            $data['weight'] ?? null,
            $data['height'] ?? null,
            $data['BMI'] ?? null,
            $data['blood_pressure_systolic'] ?? null,
            $data['blood_pressure_diastolic'] ?? null,
            $data['pulse_rate'] ?? null,
            $data['respiratory_rate'] ?? null,
            $data['general_appearance'] ?? 'Normal',
            $data['s1_s2'] ?? 'No',
            $data['murmur'] ?? 'No',
            $data['ent'] ?? 'Normal', // ear_nose_throat
            $data['visual_acuity_left'] ?? null,
            $data['visual_acuity_right'] ?? null,
            $data['colour_blindness'] ?? 'No',
            $data['gi_tenderness'] ?? 'No', // tenderness
            $data['abdominal_mass_exam'] ?? 'No', // abdominal_mass
            $data['lymph_nodes'] ?? 'Non-palpable',
            $data['splenomegaly'] ?? 'No',
            $data['kidney_tenderness'] ?? 'No', // ballottable
            $data['liver_jaundice'] ?? 'No', // jaundice
            $data['hepatomegaly'] ?? 'No',
            $data['muscle_tone'] ?? '3',
            $data['muscle_tenderness'] ?? 'No',
            $data['power'] ?? '3',
            $data['sensation'] ?? 'Normal',
            $data['respiratory_findings'] ?? 'Clear', // sound
            $data['air_entry'] ?? 'Normal',
            $data['respiratory_findings'] ?? null,
            $data['reproductive'] ?? 'Normal',
            $data['skin'] ?? 'Normal',
            $data['ent'] ?? 'Normal', // ent
            $data['gi_tenderness'] ?? 'No', // gi_tenderness
            $data['abdominal_mass_exam'] ?? 'No', // abdominal_mass_exam
            $data['kidney_tenderness'] ?? 'No', // kidney_tenderness
            $data['kidney_tenderness'] ?? 'No', // ballotable
            $data['liver_jaundice'] ?? 'No', // liver_jaundice
            $data['others_exam'] ?? null
        ]);
        
        $clinic_pdo->commit();
        
        error_log("addHealthSurveillance: Transaction committed successfully. surveillance_id: " . $next_surveillance_id);
        
        return [
            'success' => true,
            'message' => 'Health surveillance data saved successfully!',
            'health_id' => $health_id,
            'surveillance_id' => $next_surveillance_id
        ];
        
    } catch (PDOException $e) {
        $clinic_pdo->rollBack();
        error_log("addHealthSurveillance PDO Error: " . $e->getMessage());
        error_log("addHealthSurveillance PDO Error Trace: " . $e->getTraceAsString());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        error_log("addHealthSurveillance General Error: " . $e->getMessage());
        error_log("addHealthSurveillance General Error Trace: " . $e->getTraceAsString());
        return ['success' => false, 'message' => 'General error: ' . $e->getMessage()];
    }
}

function getAllHealthSurveillance() {
    global $clinic_pdo;
    try {
        // First, get all surveillance metadata records
        $sql = "SELECT sm.surveillance_id, sm.patient_id, sm.workplace, sm.chemical, sm.examination_date, sm.examination_type, sm.examiner_name, sm.created_at,
                       p.patient_id as patient_code, p.first_name, p.last_name
                FROM chemical_information sm
                LEFT JOIN patient_information p ON sm.patient_id = p.id
                ORDER BY sm.examination_date DESC";
        
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return ['error' => 'Error fetching health surveillance: ' . $e->getMessage()];
    }
}

function getHealthSurveillanceById($id) {
    global $clinic_pdo;
    try {
        // Get surveillance metadata
        $sql = "SELECT sm.*, p.patient_id as patient_code, p.first_name, p.last_name, p.NRIC, p.date_of_birth, p.gender, p.telephone_no, p.email
                FROM chemical_information sm
                LEFT JOIN patient_information p ON sm.patient_id = p.id
                WHERE sm.surveillance_id = ?";
        
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$id]);
        $surveillance = $stmt->fetch();
        
        if (!$surveillance) {
            return ['error' => 'Surveillance record not found'];
        }
        
        // Get health history
        $sql = "SELECT * FROM history_of_health WHERE surveillance_id = ?";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$surveillance['surveillance_id']]);
        $health_history = $stmt->fetch() ?: [];
        
        // Get clinical findings
        $sql = "SELECT * FROM clinical_findings WHERE surveillance_id = ?";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$surveillance['surveillance_id']]);
        $clinical_findings = $stmt->fetch() ?: [];
        
        // Get physical examination
        $sql = "SELECT * FROM physical_examination WHERE surveillance_id = ?";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$surveillance['surveillance_id']]);
        $physical_exam = $stmt->fetch() ?: [];
        
        // Get target organ
        $sql = "SELECT * FROM target_organ WHERE surveillance_id = ?";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$surveillance['surveillance_id']]);
        $target_organ = $stmt->fetch() ?: [];
        
        // Get biological monitoring
        $sql = "SELECT * FROM biological_monitoring WHERE surveillance_id = ?";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$surveillance['surveillance_id']]);
        $biological_monitoring = $stmt->fetch() ?: [];
        
        // Get conclusion MS findings
        $sql = "SELECT * FROM conclusion_ms_finding WHERE surveillance_id = ?";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$surveillance['surveillance_id']]);
        $conclusion_ms = $stmt->fetch() ?: [];
        
        // Get recommendations
        $sql = "SELECT * FROM recommendations WHERE surveillance_id = ?";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$surveillance['surveillance_id']]);
        $recommendations = $stmt->fetch() ?: [];
        
        // Get fitness respirator (by patient_id, not surveillance_id)
        $sql = "SELECT * FROM fitness_respirator WHERE patient_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$surveillance['patient_id']]);
        $fitness_respirator = $stmt->fetch() ?: [];
        
        // Merge biological monitoring data into surveillance for easier access
        if (!empty($biological_monitoring)) {
            $surveillance['biological_exposure'] = $biological_monitoring['biological_exposure'] ?? '';
            $surveillance['result_baseline'] = $biological_monitoring['result_baseline'] ?? '';
            $surveillance['result_annual'] = $biological_monitoring['result_annual'] ?? '';
        }
        
        // Merge clinical findings elaboration into surveillance
        if (!empty($clinical_findings)) {
            $surveillance['clinical_findings'] = $clinical_findings['elaboration'] ?? '';
            $surveillance['has_clinical_findings'] = $clinical_findings['result_clinical_findings'] ?? 'No';
        }
        
        // Merge conclusion MS findings into surveillance
        if (!empty($conclusion_ms)) {
            $surveillance['history_of_health'] = $conclusion_ms['history_of_health'] ?? '';
            $surveillance['clinical_findings'] = $conclusion_ms['clinical_findings'] ?? '';
            $surveillance['target_organ'] = $conclusion_ms['target_organ'] ?? '';
            $surveillance['biological_monitoring'] = $conclusion_ms['biological_monitoring'] ?? '';
            $surveillance['pregnancy_breast_feeding'] = $conclusion_ms['pregnancy_breast_feeding'] ?? '';
            $surveillance['clinical_work_related'] = $conclusion_ms['clinical_work_related'] ?? '';
            $surveillance['organ_work_related'] = $conclusion_ms['organ_work_related'] ?? '';
            $surveillance['biological_work_related'] = $conclusion_ms['biological_work_related'] ?? '';
        }
        
        // Merge recommendations into surveillance
        if (!empty($recommendations)) {
            $surveillance['recommendations_type'] = $recommendations['recommendations_type'] ?? '';
            $surveillance['date_of_MRP'] = $recommendations['date_of_MRP'] ?? '';
            $surveillance['next_review_date'] = $recommendations['next_review_date'] ?? '';
            $surveillance['recommendations_notes'] = $recommendations['notes'] ?? '';
        }
        
        // Merge fitness respirator into surveillance
        if (!empty($fitness_respirator)) {
            $surveillance['respirator_result'] = $fitness_respirator['result'] ?? '';
            $surveillance['respirator_justification'] = $fitness_respirator['justification'] ?? '';
        }
        
        // Format blood pressure from physical exam
        if (!empty($physical_exam) && !empty($physical_exam['bp_systolic']) && !empty($physical_exam['bp_distolic'])) {
            $physical_exam['blood_pressure'] = $physical_exam['bp_systolic'] . '/' . $physical_exam['bp_distolic'];
        }
        
        return [
            'surveillance' => $surveillance,
            'health_history' => $health_history,
            'clinical_findings' => $clinical_findings,
            'physical_exam' => $physical_exam,
            'target_organ' => $target_organ,
            'biological_monitoring' => $biological_monitoring,
            'conclusion_ms' => $conclusion_ms,
            'recommendations' => $recommendations,
            'fitness_respirator' => $fitness_respirator
        ];
        
    } catch (Exception $e) {
        return ['error' => 'Error fetching surveillance details: ' . $e->getMessage()];
    }
}

function updateHealthSurveillance($data) {
    global $clinic_pdo;
    
    try {
        $clinic_pdo->beginTransaction();
        
        // 1. Update chemical_information (only basic columns)
        $stmt = $clinic_pdo->prepare("UPDATE chemical_information SET 
            workplace = ?, chemical = ?, examination_date = ?, examination_type = ?, examiner_name = ?,
            final_assessment = ?,
            updated_at = NOW()
            WHERE surveillance_id = ?");
        
        $stmt->execute([
            $data['workplace'] ?? null,
            $data['chemical'] ?? null,
            $data['examination_date'] ?? null,
            $data['examination_type'] ?? null,
            $data['examiner_name'] ?? null,
            $data['final_assessment'] ?? null,
            $data['surveillance_id']
        ]);
        
        // 2. Update history_of_health
        $stmt = $clinic_pdo->prepare("UPDATE history_of_health SET 
            breathing_difficulty = ?, cough = ?, sore_throat = ?, sneezing = ?, chest_pain = ?, palpitation = ?, limb_oedema = ?,
            drowsiness = ?, dizziness = ?, headache = ?, confusion = ?, lethargy = ?, nausea = ?, vomiting = ?,
            eye_irritations = ?, blurred_vision = ?, blisters = ?, burns = ?, itching = ?, rash = ?, redness = ?,
            abdominal_pain = ?, abdominal_mass = ?, jaundice = ?, diarrhoea = ?, loss_of_weight = ?, loss_of_appetite = ?, dysuria = ?, haematuria = ?,
            others_symptoms = ?
            WHERE surveillance_id = ?");
        
        $stmt->execute([
            $data['breathing_difficulty'] ?? 'No',
            $data['cough'] ?? 'No',
            $data['sore_throat'] ?? 'No',
            $data['sneezing'] ?? 'No',
            $data['chest_pain'] ?? 'No',
            $data['palpitation'] ?? 'No',
            $data['limb_oedema'] ?? 'No',
            $data['drowsiness'] ?? 'No',
            $data['dizziness'] ?? 'No',
            $data['headache'] ?? 'No',
            $data['confusion'] ?? 'No',
            $data['lethargy'] ?? 'No',
            $data['nausea'] ?? 'No',
            $data['vomiting'] ?? 'No',
            $data['eye_irritations'] ?? 'No',
            $data['blurred_vision'] ?? 'No',
            $data['blisters'] ?? 'No',
            $data['burns'] ?? 'No',
            $data['itching'] ?? 'No',
            $data['rash'] ?? 'No',
            $data['redness'] ?? 'No',
            $data['abdominal_pain'] ?? 'No',
            $data['abdominal_mass'] ?? 'No',
            $data['jaundice'] ?? 'No',
            $data['diarrhoea'] ?? 'No',
            $data['loss_of_weight'] ?? 'No',
            $data['loss_of_appetite'] ?? 'No',
            $data['dysuria'] ?? 'No',
            $data['haematuria'] ?? 'No',
            $data['others_symptoms'] ?? '',
            $data['surveillance_id']
        ]);
        
        // 3. Update physical_examination
        $stmt = $clinic_pdo->prepare("UPDATE physical_examination SET 
            weight = ?, height = ?, BMI = ?, bp_systolic = ?, bp_distolic = ?, pulse_rate = ?, respiratory_rate = ?, general_appearance = ?,
            s1_s2 = ?, murmur = ?, ent = ?, visual_acuity_left = ?, visual_acuity_right = ?, colour_blindness = ?,
            gi_tenderness = ?, abdominal_mass_exam = ?, lymph_nodes = ?, splenomegaly = ?, kidney_tenderness = ?, 
            ballotable = ?, liver_jaundice = ?, hepatomegaly = ?, muscle_tone = ?, muscle_tenderness = ?, power = ?, 
            sensation = ?, respiratory_findings = ?, air_entry = ?, reproductive = ?, skin = ?, others_exam = ?
            WHERE surveillance_id = ?");
        
        $stmt->execute([
            $data['weight'] ?? '0.00',
            $data['height'] ?? '0.00',
            $data['BMI'] ?? '0.0',
            $data['blood_pressure_systolic'] ?? '',
            $data['blood_pressure_diastolic'] ?? '',
            $data['pulse_rate'] ?? '0',
            $data['respiratory_rate'] ?? '0',
            $data['general_appearance'] ?? 'Normal',
            $data['s1_s2'] ?? 'No',
            $data['murmur'] ?? 'No',
            $data['ent'] ?? 'Normal',
            $data['visual_acuity_left'] ?? '',
            $data['visual_acuity_right'] ?? '',
            $data['colour_blindness'] ?? 'No',
            $data['gi_tenderness'] ?? 'No',
            $data['abdominal_mass_exam'] ?? 'No',
            $data['lymph_nodes'] ?? 'Non-palpable',
            $data['splenomegaly'] ?? 'No',
            $data['kidney_tenderness'] ?? 'No',
            $data['ballotable'] ?? 'No',
            $data['liver_jaundice'] ?? 'No',
            $data['hepatomegaly'] ?? 'No',
            $data['muscle_tone'] ?? '3',
            $data['muscle_tenderness'] ?? 'No',
            $data['power'] ?? '3',
            $data['sensation'] ?? 'Normal',
            $data['respiratory_findings'] ?? '',
            $data['air_entry'] ?? 'Normal',
            $data['reproductive'] ?? 'Normal',
            $data['skin'] ?? 'Normal',
            $data['others_exam'] ?? '',
            $data['surveillance_id']
        ]);
        
        // 4. Update clinical_findings
        $stmt = $clinic_pdo->prepare("UPDATE clinical_findings SET 
            result_clinical_findings = ?, elaboration = ?
            WHERE surveillance_id = ?");
        
        $stmt->execute([
            $data['clinical_findings'] ?? 'No',
            $data['clinical_elaboration'] ?? null,
            $data['surveillance_id']
        ]);
        
        // 5. Update or Insert target_organ (has UNIQUE KEY on surveillance_id)
        $stmt = $clinic_pdo->prepare("INSERT INTO target_organ (
            patient_id, surveillance_id, blood_count, renal_function, liver_function, chest_xray,
            spirometry_fev1, spirometry_fvc2, spirometry_fev_fvc,
            blood_comment, renal_comment, liver_comment, xray_comment, spirometry_comment
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            blood_count = VALUES(blood_count),
            renal_function = VALUES(renal_function),
            liver_function = VALUES(liver_function),
            chest_xray = VALUES(chest_xray),
            spirometry_fev1 = VALUES(spirometry_fev1),
            spirometry_fvc2 = VALUES(spirometry_fvc2),
            spirometry_fev_fvc = VALUES(spirometry_fev_fvc),
            blood_comment = VALUES(blood_comment),
            renal_comment = VALUES(renal_comment),
            liver_comment = VALUES(liver_comment),
            xray_comment = VALUES(xray_comment),
            spirometry_comment = VALUES(spirometry_comment)");
        
        $stmt->execute([
            $data['patient_id'],
            $data['surveillance_id'],
            $data['blood_count'] ?? 'Normal',
            $data['renal_function'] ?? 'Normal',
            $data['liver_function'] ?? 'Normal',
            $data['chest_xray'] ?? 'Normal',
            $data['spirometry_fev1'] ?? null,
            $data['spirometry_fvc2'] ?? null,
            $data['spirometry_fev_fvc'] ?? null,
            $data['blood_comment'] ?? '',
            $data['renal_comment'] ?? '',
            $data['liver_comment'] ?? '',
            $data['xray_comment'] ?? '',
            $data['spirometry_comment'] ?? ''
        ]);
        
        // 6. Update or Insert biological_monitoring (has UNIQUE KEY on surveillance_id)
        $stmt = $clinic_pdo->prepare("INSERT INTO biological_monitoring (
            patient_id, surveillance_id, biological_exposure, result_baseline, result_annual
        ) VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            biological_exposure = VALUES(biological_exposure),
            result_baseline = VALUES(result_baseline),
            result_annual = VALUES(result_annual)");
        
        $stmt->execute([
            $data['patient_id'],
            $data['surveillance_id'],
            $data['biological_exposure'] ?? '',
            $data['result_baseline'] ?? '',
            $data['result_annual'] ?? ''
        ]);
        
        // 7. Update or Insert conclusion_ms_finding (has UNIQUE KEY on surveillance_id)
        $stmt = $clinic_pdo->prepare("INSERT INTO conclusion_ms_finding (
            patient_id, surveillance_id, history_of_health, clinical_findings, target_organ,
            biological_monitoring, pregnancy_breast_feeding, clinical_work_related,
            organ_work_related, biological_work_related
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            history_of_health = VALUES(history_of_health),
            clinical_findings = VALUES(clinical_findings),
            target_organ = VALUES(target_organ),
            biological_monitoring = VALUES(biological_monitoring),
            pregnancy_breast_feeding = VALUES(pregnancy_breast_feeding),
            clinical_work_related = VALUES(clinical_work_related),
            organ_work_related = VALUES(organ_work_related),
            biological_work_related = VALUES(biological_work_related)");
        
        $stmt->execute([
            $data['patient_id'],
            $data['surveillance_id'],
            $data['history_of_health'] ?? 'No',
            $data['clinical_findings'] ?? 'No',
            $data['target_organ'] ?? 'No',
            $data['biological_monitoring'] ?? 'No',
            $data['pregnancy_breast_feeding'] ?? 'No',
            $data['clinical_work_related'] ?? 'No',
            $data['organ_work_related'] ?? 'No',
            $data['biological_work_related'] ?? 'No'
        ]);
        
        // 8. Update or Insert recommendations (has UNIQUE KEY on surveillance_id)
        $stmt = $clinic_pdo->prepare("INSERT INTO recommendations (
            patient_id, surveillance_id, recommendations_type, date_of_MRP, next_review_date, notes
        ) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            recommendations_type = VALUES(recommendations_type),
            date_of_MRP = VALUES(date_of_MRP),
            next_review_date = VALUES(next_review_date),
            notes = VALUES(notes)");
        
        $stmt->execute([
            $data['patient_id'],
            $data['surveillance_id'],
            $data['recommendations_type'] ?? '',
            $data['date_of_MRP'] ?? null,
            $data['next_review_date'] ?? null,
            $data['recommendations_notes'] ?? ''
        ]);
        
        // 9. Update or Insert fitness_respirator (check if record exists first)
        $stmt = $clinic_pdo->prepare("SELECT id FROM fitness_respirator WHERE patient_id = ? LIMIT 1");
        $stmt->execute([$data['patient_id']]);
        $existing_fitness = $stmt->fetch();
        
        if ($existing_fitness) {
            $stmt = $clinic_pdo->prepare("UPDATE fitness_respirator SET 
                result = ?, justification = ?
                WHERE patient_id = ?");
            $stmt->execute([
                $data['respirator_result'] ?? 'Fit',
                $data['respirator_justification'] ?? '',
                $data['patient_id']
            ]);
        } else {
            $stmt = $clinic_pdo->prepare("INSERT INTO fitness_respirator (
                patient_id, result, justification
            ) VALUES (?, ?, ?)");
            $stmt->execute([
                $data['patient_id'],
                $data['respirator_result'] ?? 'Fit',
                $data['respirator_justification'] ?? ''
            ]);
        }
        
        $clinic_pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Surveillance record updated successfully'
        ];
        
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        return [
            'success' => false,
            'message' => 'Error updating surveillance record: ' . $e->getMessage()
        ];
    }
}

// Save Target Organ data
function saveTargetOrganData($data) {
    global $clinic_pdo;
    
    try {
        error_log("saveTargetOrganData: Starting save for patient_id: " . ($data['patient_id'] ?? 'N/A') . ", surveillance_id: " . ($data['surveillance_id'] ?? 'N/A'));
        
        $stmt = $clinic_pdo->prepare("INSERT INTO target_organ (
            patient_id, surveillance_id, blood_count, renal_function, liver_function, chest_xray,
            spirometry_fev1, spirometry_fvc2, spirometry_fev_fvc,
            blood_comment, renal_comment, liver_comment, xray_comment, spirometry_comment
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            (int)($data['patient_id'] ?? 0), 
            (int)($data['surveillance_id'] ?? 0), 
            $data['blood_count'] ?? 'Normal', 
            $data['renal_function'] ?? 'Normal', 
            $data['liver_function'] ?? 'Normal', 
            $data['chest_xray'] ?? 'Normal', 
            $data['spirometry_fev1'] ?? null,
            $data['spirometry_fvc2'] ?? null, 
            $data['spirometry_fev_fvc'] ?? null, 
            $data['blood_comment'] ?? '', 
            $data['renal_comment'] ?? '', 
            $data['liver_comment'] ?? '', 
            $data['xray_comment'] ?? '', 
            $data['spirometry_comment'] ?? ''
        ]);
        
        error_log("saveTargetOrganData: Successfully saved");
        return ['success' => true];
    } catch (Exception $e) {
        error_log("saveTargetOrganData Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Save Biological Monitoring data
function saveBiologicalMonitoringData($data) {
    global $clinic_pdo;
    
    try {
        error_log("saveBiologicalMonitoringData: Starting save for patient_id: " . ($data['patient_id'] ?? 'N/A') . ", surveillance_id: " . ($data['surveillance_id'] ?? 'N/A'));
        
        $stmt = $clinic_pdo->prepare("INSERT INTO biological_monitoring (
            patient_id, surveillance_id, biological_exposure, result_baseline, result_annual
        ) VALUES (?, ?, ?, ?, ?)");
        
        $stmt->execute([
            (int)($data['patient_id'] ?? 0), 
            (int)($data['surveillance_id'] ?? 0), 
            $data['biological_exposure'] ?? '', 
            $data['result_baseline'] ?? '', 
            $data['result_annual'] ?? ''
        ]);
        
        error_log("saveBiologicalMonitoringData: Successfully saved");
        return ['success' => true];
    } catch (Exception $e) {
        error_log("saveBiologicalMonitoringData Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Save Fitness Respirator data
function saveFitnessRespiratorData($data) {
    global $clinic_pdo;
    
    try {
        error_log("saveFitnessRespiratorData: Starting save for patient_id: " . ($data['patient_id'] ?? 'N/A'));
        
        // Check if record exists first
        $checkStmt = $clinic_pdo->prepare("SELECT id FROM fitness_respirator WHERE patient_id = ? LIMIT 1");
        $checkStmt->execute([(int)($data['patient_id'] ?? 0)]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            $stmt = $clinic_pdo->prepare("UPDATE fitness_respirator SET result = ?, justification = ? WHERE patient_id = ?");
            $stmt->execute([
                $data['result'] ?? 'Fit', 
                $data['justification'] ?? '', 
                (int)($data['patient_id'] ?? 0)
            ]);
            error_log("saveFitnessRespiratorData: Updated existing record");
        } else {
            $stmt = $clinic_pdo->prepare("INSERT INTO fitness_respirator (
                patient_id, result, justification
            ) VALUES (?, ?, ?)");
            
            $stmt->execute([
                (int)($data['patient_id'] ?? 0), 
                $data['result'] ?? 'Fit', 
                $data['justification'] ?? ''
            ]);
            error_log("saveFitnessRespiratorData: Inserted new record");
        }
        
        error_log("saveFitnessRespiratorData: Successfully saved");
        return ['success' => true];
    } catch (Exception $e) {
        error_log("saveFitnessRespiratorData Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Save Conclusion MS Finding data
function saveConclusionMSFindingData($data) {
    global $clinic_pdo;
    
    try {
        error_log("saveConclusionMSFindingData: Starting save for patient_id: " . ($data['patient_id'] ?? 'N/A') . ", surveillance_id: " . ($data['surveillance_id'] ?? 'N/A'));
        
        $stmt = $clinic_pdo->prepare("INSERT INTO conclusion_ms_finding (
            patient_id, surveillance_id, history_of_health, clinical_findings, target_organ,
            biological_monitoring, pregnancy_breast_feeding, clinical_work_related,
            organ_work_related, biological_work_related
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            (int)($data['patient_id'] ?? 0), 
            (int)($data['surveillance_id'] ?? 0), 
            $data['history_of_health'] ?? 'No', 
            $data['clinical_findings'] ?? 'No',
            $data['target_organ'] ?? 'No', 
            $data['biological_monitoring'] ?? 'No', 
            $data['pregnancy_breast_feeding'] ?? 'No',
            $data['clinical_work_related'] ?? 'No', 
            $data['organ_work_related'] ?? 'No', 
            $data['biological_work_related'] ?? 'No'
        ]);
        
        error_log("saveConclusionMSFindingData: Successfully saved");
        return ['success' => true];
    } catch (Exception $e) {
        error_log("saveConclusionMSFindingData Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Save Recommendations data
function saveRecommendationsData($data) {
    global $clinic_pdo;
    
    try {
        error_log("saveRecommendationsData: Starting save for patient_id: " . ($data['patient_id'] ?? 'N/A') . ", surveillance_id: " . ($data['surveillance_id'] ?? 'N/A'));
        
        $stmt = $clinic_pdo->prepare("INSERT INTO recommendations (
            patient_id, surveillance_id, recommendations_type, date_of_MRP, next_review_date, notes
        ) VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            (int)($data['patient_id'] ?? 0), 
            (int)($data['surveillance_id'] ?? 0), 
            $data['recommendations_type'] ?? '', 
            $data['date_of_MRP'] ?? null,
            $data['next_review_date'] ?? null, 
            $data['notes'] ?? ''
        ]);
        
        error_log("saveRecommendationsData: Successfully saved");
        return ['success' => true];
    } catch (Exception $e) {
        error_log("saveRecommendationsData Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Doctor Management Functions

/**
 * Normalise doctor records returned from the database so the UI always has a
 * consistent set of keys (and sensible default values).
 */
function normalizeDoctorRecord(array $doctor): array {
    // Ensure employment status has a default.
    if (empty($doctor['employment_status'])) {
        $doctor['employment_status'] = 'Active';
    }

    // Normalise country code formatting.
    if (isset($doctor['country_code'])) {
        $doctor['country_code'] = trim((string) $doctor['country_code']);
    }

    // Build a user-friendly phone string.
    $combinedPhone = '';
    $rawPhone = trim((string) ($doctor['phone'] ?? ''));
    $telephoneNo = trim((string) ($doctor['telephone_no'] ?? ''));
    $countryCode = $doctor['country_code'] ?? '';

    if ($rawPhone === '' && $telephoneNo !== '') {
        $combinedPhone = $telephoneNo;
    } else {
        $combinedPhone = trim(sprintf('%s %s', $countryCode, $rawPhone));
    }

    if ($combinedPhone !== '') {
        // Collapse multiple spaces for nicer display.
        $doctor['phone'] = preg_replace('/\s+/', ' ', $combinedPhone);
    }

    return $doctor;
}

function addDoctor($data) {
    global $clinic_pdo;
    
    try {
        $clinic_pdo->beginTransaction();
        
        // Generate next DOC0000 format ID
        $stmt = $clinic_pdo->query("SELECT MAX(CAST(SUBSTRING(doctor_id, 4) AS UNSIGNED)) as max_num FROM medical_staff WHERE doctor_id LIKE 'DOC%'");
        $result = $stmt->fetch();
        $max_num = $result['max_num'] ?? 0;
        $next_doctor_id = 'DOC' . str_pad($max_num + 1, 4, '0', STR_PAD_LEFT);
        
        $countryCode = $data['country_code'] ?? null;
        $localPhone = $data['phone'] ?? '';
        $combinedPhone = trim(sprintf('%s %s', $countryCode ?? '', $localPhone));
        
        $stmt = $clinic_pdo->prepare("INSERT INTO medical_staff (
            doctor_id, first_name, last_name, NRIC, passport_no, date_of_birth, gender,
            address, state, district, postcode, country_code, phone, telephone_no,
            email, specialization, position, department, employment_status,
            qualification, license_number, years_of_experience, hire_date, salary
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $next_doctor_id,
            $data['first_name'],
            $data['last_name'],
            $data['nric'] ?? null,
            $data['passport_no'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? null,
            $data['address'] ?? null,
            $data['state'] ?? null,
            $data['district'] ?? null,
            $data['postcode'] ?? null,
            $countryCode,
            $localPhone !== '' ? $localPhone : null,
            $combinedPhone !== '' ? $combinedPhone : null,
            $data['email'] ?? null,
            $data['specialization'] ?? null,
            $data['position'] ?? 'Doctor',
            $data['department'] ?? null,
            $data['employment_status'] ?? 'Active',
            $data['qualification'] ?? null,
            $data['license_number'] ?? null,
            isset($data['years_of_experience']) && $data['years_of_experience'] !== '' ? (int)$data['years_of_experience'] : null,
            $data['hire_date'] ?? null,
            isset($data['salary']) && $data['salary'] !== '' ? $data['salary'] : null
        ]);
        
        $doctor_id = $clinic_pdo->lastInsertId();
        $clinic_pdo->commit();
        
        return [
            'success' => true,
            'doctor_id' => $doctor_id,
            'message' => 'Doctor added successfully.'
        ];
        
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        return ['success' => false, 'message' => 'Error adding doctor: ' . $e->getMessage()];
    }
}

function getAllDoctors() {
    global $clinic_pdo;
    
    try {
        $sql = "SELECT * FROM medical_staff ORDER BY id ASC";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute();
        $doctors = $stmt->fetchAll();
        
        foreach ($doctors as &$doctor) {
            $doctor = normalizeDoctorRecord($doctor);
        }
        unset($doctor);
        
        return $doctors;
        
    } catch (Exception $e) {
        return ['error' => 'Error fetching doctors: ' . $e->getMessage()];
    }
}

function getDoctorById($id) {
    global $clinic_pdo;
    
    try {
        $sql = "SELECT * FROM medical_staff WHERE id = ?";
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute([$id]);
        $doctor = $stmt->fetch();
        if ($doctor) {
            $doctor = normalizeDoctorRecord($doctor);
        }
        return $doctor;
        
    } catch (Exception $e) {
        return ['error' => 'Error fetching doctor: ' . $e->getMessage()];
    }
}

function updateDoctor($id, $data) {
    global $clinic_pdo;
    
    try {
        $clinic_pdo->beginTransaction();
        
        // Ensure phone values are aligned before update.
        if (isset($data['phone'])) {
            $countryCode = $data['country_code'] ?? null;
            $combinedPhone = trim(sprintf('%s %s', $countryCode ?? '', $data['phone']));

            $data['telephone_no'] = $combinedPhone !== '' ? $combinedPhone : null;
            $data['phone'] = $data['phone'] !== '' ? $data['phone'] : null;
        }

        // Build dynamic SQL query based on provided fields
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'doctor_id', 'first_name', 'last_name', 'specialization', 'qualification',
            'department', 'position', 'employment_status', 'country_code', 'phone',
            'telephone_no', 'address', 'years_of_experience', 'hire_date', 'salary',
            'email', 'license_number', 'passport_no', 'date_of_birth', 'gender',
            'state', 'district', 'postcode'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        // employment_status may be provided as "status" key by UI.
        if (isset($data['status']) && $data['status'] !== null) {
            $fields[] = "employment_status = ?";
            $values[] = $data['status'];
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No fields to update.'];
        }
        
        $values[] = $id;
        $sql = "UPDATE medical_staff SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute($values);
        
        $clinic_pdo->commit();
        return ['success' => true, 'message' => 'Doctor updated successfully.'];
        
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        return ['success' => false, 'message' => 'Error updating doctor: ' . $e->getMessage()];
    }
}

function deleteDoctor($id) {
    global $clinic_pdo;
    
    try {
        $clinic_pdo->beginTransaction();
        
        $stmt = $clinic_pdo->prepare("DELETE FROM medical_staff WHERE id = ?");
        $stmt->execute([$id]);
        
        $clinic_pdo->commit();
        return ['success' => true, 'message' => 'Doctor deleted successfully.'];
        
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        return ['success' => false, 'message' => 'Error deleting doctor: ' . $e->getMessage()];
    }
}

function getDashboardStats($date) {
    global $clinic_pdo;
    
    try {
        $stats = [];
        
        // Total patients
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM patient_information");
        $stats['total_patients'] = $stmt->fetch()['count'];
        
        // Total doctors/staff
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM medical_staff");
        $stats['total_staff'] = $stmt->fetch()['count'];
        
        // Appointments today (using surveillance records as proxy)
        $stmt = $clinic_pdo->prepare("SELECT COUNT(*) as count FROM chemical_information WHERE DATE(examination_date) = ?");
        $stmt->execute([$date]);
        $stats['appointments_today'] = $stmt->fetch()['count'];
        
        // Surveillance records
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM chemical_information");
        $stats['surveillance_records'] = $stmt->fetch()['count'];
        
        // Pending reviews (surveillance records without final assessment)
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM chemical_information WHERE final_assessment IS NULL OR final_assessment = ''");
        $stats['pending_reviews'] = $stmt->fetch()['count'];
        
        // Completed this month
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM chemical_information WHERE MONTH(examination_date) = MONTH(CURDATE()) AND YEAR(examination_date) = YEAR(CURDATE())");
        $stats['completed_this_month'] = $stmt->fetch()['count'];
        
        // Total appointments (using surveillance records)
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM chemical_information");
        $stats['total_appointments'] = $stmt->fetch()['count'];
        
        // Abnormal findings (surveillance with abnormal results)
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM chemical_information WHERE final_assessment LIKE '%abnormal%' OR final_assessment LIKE '%unfit%'");
        $stats['abnormal_findings'] = $stmt->fetch()['count'];
        
        // Fit for work
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM chemical_information WHERE final_assessment LIKE '%fit%' OR final_assessment LIKE '%normal%'");
        $stats['fit_for_work'] = $stmt->fetch()['count'];
        
        return $stats;
        
    } catch (Exception $e) {
        return [
            'total_patients' => 0,
            'total_staff' => 0,
            'appointments_today' => 0,
            'surveillance_records' => 0,
            'pending_reviews' => 0,
            'completed_this_month' => 0,
            'total_appointments' => 0,
            'abnormal_findings' => 0,
            'fit_for_work' => 0
        ];
    }
}

function getRecentAppointments($limit = 5) {
    global $clinic_pdo;
    
    try {
        $stmt = $clinic_pdo->prepare("
            SELECT 
                sm.surveillance_id,
                CONCAT(pi.first_name, ' ', pi.last_name) as patient_name,
                sm.examination_date as appointment_date,
                sm.examination_type as appointment_type,
                CASE 
                    WHEN sm.final_assessment IS NULL OR sm.final_assessment = '' THEN 'Scheduled'
                    WHEN sm.final_assessment LIKE '%fit%' OR sm.final_assessment LIKE '%normal%' THEN 'Completed'
                    ELSE 'Pending Review'
                END as status
            FROM chemical_information sm
            JOIN patient_information pi ON sm.patient_id = pi.id
            ORDER BY sm.examination_date DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

function addAppointment($data) {
    global $clinic_pdo;
    
    try {
        $clinic_pdo->beginTransaction();
        
        // Generate next APT0000 format ID
        $stmt = $clinic_pdo->query("SELECT COUNT(*) as count FROM appointments");
        $count = $stmt->fetch()['count'];
        $next_appointment_id = 'APT' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        $stmt = $clinic_pdo->prepare("INSERT INTO appointments (
            patient_id, doctor_id, appointment_date, appointment_time, appointment_type, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $data['patient_id'],
            $data['doctor_id'] ?? null,
            $data['appointment_date'],
            $data['appointment_time'],
            $data['appointment_type'] ?? 'Consultation',
            $data['status'] ?? 'Scheduled',
            $data['notes'] ?? null
        ]);
        
        $appointment_id = $clinic_pdo->lastInsertId();
        $clinic_pdo->commit();
        
        return ['success' => true, 'appointment_id' => $appointment_id, 'message' => 'Appointment added successfully.'];
        
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        return ['success' => false, 'message' => 'Error adding appointment: ' . $e->getMessage()];
    }
}

function getAllAppointments() {
    global $clinic_pdo;
    
    try {
        $stmt = $clinic_pdo->query("
            SELECT 
                a.*,
                CONCAT(pi.first_name, ' ', pi.last_name) as patient_name,
                pi.patient_id as patient_code,
                CONCAT(di.first_name, ' ', di.last_name) as doctor_name,
                di.doctor_id as doctor_code
            FROM appointments a
            LEFT JOIN patient_information pi ON a.patient_id = pi.id
            LEFT JOIN medical_staff di ON a.doctor_id = di.id
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

function getAppointmentById($id) {
    global $clinic_pdo;
    
    try {
        $stmt = $clinic_pdo->prepare("
            SELECT 
                a.*,
                CONCAT(pi.first_name, ' ', pi.last_name) as patient_name,
                pi.patient_id as patient_code,
                CONCAT(di.first_name, ' ', di.last_name) as doctor_name,
                di.doctor_id as doctor_code
            FROM appointments a
            LEFT JOIN patient_information pi ON a.patient_id = pi.id
            LEFT JOIN medical_staff di ON a.doctor_id = di.id
            WHERE a.id = ?
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return ['error' => 'Appointment not found'];
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['error' => 'Error fetching appointment: ' . $e->getMessage()];
    }
}

function updateAppointment($id, $data) {
    global $clinic_pdo;
    
    try {
        $clinic_pdo->beginTransaction();
        
        // Build dynamic SQL query based on provided fields
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'patient_id', 'doctor_id', 'appointment_date', 'appointment_time',
            'appointment_type', 'status', 'notes'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return ['success' => false, 'message' => 'No fields to update.'];
        }
        
        $values[] = $id;
        $sql = "UPDATE appointments SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $clinic_pdo->prepare($sql);
        $stmt->execute($values);
        
        $clinic_pdo->commit();
        return ['success' => true, 'message' => 'Appointment updated successfully.'];
        
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        return ['success' => false, 'message' => 'Error updating appointment: ' . $e->getMessage()];
    }
}

function deleteAppointment($id) {
    global $clinic_pdo;
    
    try {
        $clinic_pdo->beginTransaction();
        
        $stmt = $clinic_pdo->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->execute([$id]);
        
        $clinic_pdo->commit();
        return ['success' => true, 'message' => 'Appointment deleted successfully.'];
        
    } catch (Exception $e) {
        $clinic_pdo->rollBack();
        return ['success' => false, 'message' => 'Error deleting appointment: ' . $e->getMessage()];
    }
}

// Authentication Functions
function loginUser($username, $password) {
    global $clinic_pdo;
    
    try {
        // Trim whitespace from inputs
        $username = trim($username);
        $password = trim($password);
        
        // First, find the user by username (case-insensitive)
        $stmt = $clinic_pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check password - support both plain text and hashed passwords
            $stored_password = $user['password'];
            $password_match = false;
            
            // Check if password is plain text (direct match)
            if ($stored_password === $password) {
                $password_match = true;
            }
            // Check if password is hashed (using password_verify)
            elseif (password_verify($password, $stored_password)) {
                $password_match = true;
            }
            // Also check with md5 (legacy support)
            elseif (md5($password) === $stored_password) {
                $password_match = true;
            }
            
            if ($password_match) {
                // Start session if not already started
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'] ?? '';
                $_SESSION['last_name'] = $user['last_name'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';
                
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($required_role) {
    requireLogin();
    if ($_SESSION['role'] !== $required_role) {
        header('Location: index.php');
        exit();
    }
}

// Function to get logged-in user's medical staff information
function getLoggedInUserMedicalStaffInfo() {
    global $clinic_pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        // First try to match by email
        $stmt = $clinic_pdo->prepare("SELECT * FROM medical_staff WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $staff = $stmt->fetch();
        
        // If no match by email, try to match by first_name and last_name
        if (!$staff && isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
            $stmt = $clinic_pdo->prepare("SELECT * FROM medical_staff WHERE first_name = ? AND last_name = ?");
            $stmt->execute([$_SESSION['first_name'], $_SESSION['last_name']]);
            $staff = $stmt->fetch();
        }
        
        return $staff;
        
    } catch (Exception $e) {
        return null;
    }
}
?>
