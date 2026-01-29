<?php
// Company management functions

/**
 * Get all companies with their worker counts
 */
function getAllCompanies() {
    global $clinic_pdo;
    
    $query = "
        SELECT 
            c.*,
            COALESCE(worker_count.total_workers, 0) as total_workers
        FROM company c
        LEFT JOIN (
            SELECT 
                oh.company_name,
                COUNT(DISTINCT oh.patient_id) as total_workers
            FROM occupational_history oh
            INNER JOIN patient_information pi ON oh.patient_id = pi.id
            GROUP BY oh.company_name
        ) worker_count ON TRIM(LOWER(c.company_name)) = TRIM(LOWER(worker_count.company_name))
        ORDER BY c.company_id
    ";
    
    $stmt = $clinic_pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get a single company by ID (accepts both numeric ID and formatted company_id)
 */
function getCompanyById($id) {
    global $clinic_pdo;
    
    // Check if it's a formatted ID (starts with COMP) or numeric ID
    if (strpos($id, 'COMP') === 0) {
        $query = "SELECT * FROM company WHERE company_id = ?";
    } else {
        $query = "SELECT * FROM company WHERE id = ?";
    }
    
    $stmt = $clinic_pdo->prepare($query);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get a single company by formatted company_id
 */
function getCompanyByCompanyId($companyId) {
    global $clinic_pdo;
    
    $query = "SELECT * FROM company WHERE company_id = ?";
    $stmt = $clinic_pdo->prepare($query);
    $stmt->execute([$companyId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get a company by name
 */
function getCompanyByName($name) {
    global $clinic_pdo;
    
    $query = "SELECT * FROM company WHERE company_name = ?";
    $stmt = $clinic_pdo->prepare($query);
    $stmt->execute([$name]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Add a new company
 */
function addCompany($data) {
    global $clinic_pdo;
    
    $query = "
        INSERT INTO company (company_name, address, district, state, postcode, telephone, fax, email, mykpp_registration_no)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $clinic_pdo->prepare($query);
    $result = $stmt->execute([
        $data['company_name'],
        $data['address'],
        $data['district'],
        $data['state'],
        $data['postcode'],
        $data['telephone'],
        $data['fax'],
        $data['email'],
        $data['mykpp_registration_no']
    ]);
    
    if ($result) {
        $companyId = $clinic_pdo->lastInsertId();
        updateCompanyWorkerCount($data['company_name']);
        return $companyId;
    }
    
    return false;
}

/**
 * Update an existing company
 */
function updateCompany($id, $data) {
    global $clinic_pdo;
    
    $query = "
        UPDATE company 
        SET company_name = ?, address = ?, district = ?, state = ?, postcode = ?, telephone = ?, fax = ?, email = ?, mykpp_registration_no = ?
        WHERE id = ?
    ";
    
    $stmt = $clinic_pdo->prepare($query);
    $result = $stmt->execute([
        $data['company_name'],
        $data['address'],
        $data['district'],
        $data['state'],
        $data['postcode'],
        $data['telephone'],
        $data['fax'],
        $data['email'],
        $data['mykpp_registration_no'],
        $id
    ]);
    
    if ($result) {
        updateCompanyWorkerCount($data['company_name']);
    }
    
    return $result;
}

/**
 * Delete a company
 */
function deleteCompany($id) {
    global $clinic_pdo;
    
    $query = "DELETE FROM company WHERE id = ?";
    $stmt = $clinic_pdo->prepare($query);
    return $stmt->execute([$id]);
}

/**
 * Update worker count for a specific company
 */
function updateCompanyWorkerCount($companyName) {
    global $clinic_pdo;
    
    $query = "
        UPDATE company 
        SET total_workers = (
            SELECT COUNT(DISTINCT oh.patient_id)
            FROM occupational_history oh
            WHERE TRIM(LOWER(oh.company_name)) = TRIM(LOWER(?))
        )
        WHERE TRIM(LOWER(company_name)) = TRIM(LOWER(?))
    ";
    
    $stmt = $clinic_pdo->prepare($query);
    return $stmt->execute([$companyName, $companyName]);
}

/**
 * Update all company worker counts
 */
function updateAllCompanyWorkerCounts() {
    global $clinic_pdo;
    
    $query = "
        UPDATE company c
        SET total_workers = (
            SELECT COUNT(DISTINCT oh.patient_id)
            FROM occupational_history oh
            WHERE TRIM(LOWER(oh.company_name)) = TRIM(LOWER(c.company_name))
        )
    ";
    
    return $clinic_pdo->exec($query);
}

/**
 * Get companies for dropdown
 */
function getCompaniesForDropdown() {
    global $clinic_pdo;
    
    $query = "SELECT company_name FROM company ORDER BY company_name";
    $stmt = $clinic_pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get worker count for a company
 */
function getWorkerCountByCompany($companyName) {
    global $clinic_pdo;
    
    $query = "
        SELECT COUNT(DISTINCT oh.patient_id) as worker_count
        FROM occupational_history oh
        WHERE TRIM(LOWER(oh.company_name)) = TRIM(LOWER(?))
    ";
    
    $stmt = $clinic_pdo->prepare($query);
    $stmt->execute([$companyName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['worker_count'] : 0;
}

/**
 * Get company worker count (alias for getWorkerCountByCompany)
 */
function getCompanyWorkerCount($companyName) {
    return getWorkerCountByCompany($companyName);
}
?>
