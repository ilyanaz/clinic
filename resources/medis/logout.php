<?php
session_start();
require_once __DIR__ . '/../../app/Services/clinic_functions.php';

// Clear all session data
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .logout-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .logout-card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        
        .logout-header {
            background: #389B5B;
            color: white;
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .logo {
            width: 300px;
            height: 300px;
            background: transparent;
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            border: none;
            box-shadow: none;
        }
        
        .logout-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .logout-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .logout-body {
            padding: 2rem;
            text-align: center;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(56, 155, 91, 0.3);
            border-radius: 50%;
            border-top-color: #389B5B;
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .logout-message {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        
        .redirect-link {
            color: #389B5B;
            text-decoration: none;
            font-weight: 500;
        }
        
        .redirect-link:hover {
            color: #319755;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-card">
            <div class="logout-header">
                <div class="logo">
                    <i class="fas fa-hospital"></i>
                </div>
                <h2>Medical Surveillance</h2>
                <p>System Logout</p>
            </div>
            
            <div class="logout-body">
                <div class="logout-message">
                    <div class="spinner"></div>
                    Logging out...
                </div>
                <p>You have been successfully logged out.</p>
                <p><a href="/login" class="redirect-link">Click here to login again</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto redirect to login page after 3 seconds
        setTimeout(function() {
            window.location.href = '/login';
        }, 3000);
    </script>
</body>
</html>