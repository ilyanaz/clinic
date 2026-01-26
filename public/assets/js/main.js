// Medical Surveillance System JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Search functionality
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Auto-calculate BMI
    const weightInput = document.getElementById('weight_kg');
    const heightInput = document.getElementById('height_cm');
    const bmiInput = document.getElementById('bmi');

    if (weightInput && heightInput && bmiInput) {
        function calculateBMI() {
            const weight = parseFloat(weightInput.value);
            const height = parseFloat(heightInput.value) / 100; // Convert cm to m
            
            if (weight && height) {
                const bmi = weight / (height * height);
                bmiInput.value = bmi.toFixed(1);
                
                // Color code BMI
                if (bmi < 18.5) {
                    bmiInput.className = 'form-control text-info';
                } else if (bmi >= 18.5 && bmi < 25) {
                    bmiInput.className = 'form-control text-success';
                } else if (bmi >= 25 && bmi < 30) {
                    bmiInput.className = 'form-control text-warning';
                } else {
                    bmiInput.className = 'form-control text-danger';
                }
            }
        }

        weightInput.addEventListener('input', calculateBMI);
        heightInput.addEventListener('input', calculateBMI);
    }

    // Date picker enhancements
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        // Set max date to today for birth date inputs
        if (input.name === 'dateOfBirth') {
            input.max = new Date().toISOString().split('T')[0];
        }
        
        // Set min date to today for appointment dates
        if (input.name === 'appointment_date') {
            input.min = new Date().toISOString().split('T')[0];
        }
    });

    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = value.slice(0, 3) + '-' + value.slice(3);
                } else if (value.length <= 10) {
                    value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6);
                } else {
                    value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6, 10);
                }
            }
            this.value = value;
        });
    });

    // NRIC validation
    const nricInput = document.getElementById('NRIC');
    if (nricInput) {
        nricInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 12) {
                value = value.slice(0, 12);
            }
            this.value = value;
        });
    }

    // Real-time form validation
    const requiredFields = document.querySelectorAll('input[required], select[required], textarea[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    });

    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    });

    // Direct form submission - no loading states

    // Table row selection
    const selectAllCheckbox = document.querySelector('#selectAll');
    const rowCheckboxes = document.querySelectorAll('input[name="selected_rows[]"]');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Bulk actions
    const bulkActionSelect = document.querySelector('#bulkAction');
    const bulkActionButton = document.querySelector('#bulkActionButton');
    
    if (bulkActionSelect && bulkActionButton) {
        bulkActionButton.addEventListener('click', function() {
            const selectedRows = document.querySelectorAll('input[name="selected_rows[]"]:checked');
            const action = bulkActionSelect.value;
            
            if (selectedRows.length === 0) {
                alert('Please select at least one item.');
                return;
            }
            
            if (action === '') {
                alert('Please select an action.');
                return;
            }
            
            if (confirm(`Are you sure you want to ${action} ${selectedRows.length} item(s)?`)) {
                // Submit form with selected action
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="bulk_action" value="${action}">
                    ${Array.from(selectedRows).map(checkbox => 
                        `<input type="hidden" name="selected_ids[]" value="${checkbox.value}">`
                    ).join('')}
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Print functionality
    const printButtons = document.querySelectorAll('.print-btn');
    printButtons.forEach(button => {
        button.addEventListener('click', function() {
            window.print();
        });
    });

    // Export functionality
    const exportButtons = document.querySelectorAll('.export-btn');
    exportButtons.forEach(button => {
        button.addEventListener('click', function() {
            const format = this.dataset.format;
            const table = this.closest('.card').querySelector('table');
            
            if (format === 'csv') {
                exportTableToCSV(table, 'export.csv');
            } else if (format === 'excel') {
                exportTableToExcel(table, 'export.xlsx');
            }
        });
    });

    // Dashboard charts (if Chart.js is loaded)
    if (typeof Chart !== 'undefined') {
        // Sample chart data
        const ctx = document.getElementById('dashboardChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Appointments',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        }
    }
});

// Utility functions
function exportTableToCSV(table, filename) {
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }
        
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}

function exportTableToExcel(table, filename) {
    // This would require a library like SheetJS for full Excel export
    // For now, we'll export as CSV
    exportTableToCSV(table, filename.replace('.xlsx', '.csv'));
}

// AJAX helper functions
function makeAjaxRequest(url, method = 'GET', data = null) {
    return fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: data ? JSON.stringify(data) : null
    })
    .then(response => response.json())
    .catch(error => {
        console.error('AJAX Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

