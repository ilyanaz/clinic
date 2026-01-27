<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .login-card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        
        .login-header {
            background: #389B5B;
            color: white;
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .logo {
            width: 100px;
            height: 100px;
            background: transparent;
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: none;
            overflow: hidden;
            box-shadow: none;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .login-header h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .login-header p {
            font-size: 1rem;
            opacity: 0.95;
            margin: 0;
            font-weight: 500;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-control {
            border-radius: 4px;
            border: 1px solid #ced4da;
            padding: 12px 16px;
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background: #ffffff;
        }
        
        .form-control:focus {
            border-color: #389B5B;
            box-shadow: 0 0 0 0.2rem rgba(56, 155, 91, 0.25);
            background: white;
        }
        
        .btn-login {
            background: #389B5B;
            border: none;
            border-radius: 4px;
            padding: 12px 24px;
            font-weight: 500;
            font-size: 1rem;
            width: 100%;
            transition: background-color 0.15s ease-in-out;
            color: white;
        }
        
        .btn-login:hover {
            background: #319755;
            color: white;
        }
        
        .btn-login:active {
            background: #2a7a4a;
        }
        
        .alert {
            border-radius: 4px;
            border: 1px solid transparent;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-weight: 400;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .footer-text {
            text-align: center;
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .footer-text i {
            color: #389B5B;
            margin-right: 0.5rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 10px;
            }
            
            .login-header {
                padding: 2rem 1.5rem 1.5rem;
            }
            
            .login-body {
                padding: 1.5rem;
            }
            
            .logo {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <img src="/clinic/public/medical_surveillance_logo.png" alt="Medical Surveillance Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <i class="fas fa-hospital" style="display: none; font-size: 2rem; color: white;"></i>
                </div>
                <h2>Medical Surveillance</h2>
                <p>System Login</p>
            </div>
            
            <div class="login-body">
                <?php if(session('success')): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

                    </div>
                <?php endif; ?>
                
                <?php if(session('error') || $errors->any()): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <?php echo e(session('error') ?? $errors->first()); ?>

                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo e(route('login')); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Username or Email
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo e(old('username', 'admin')); ?>" 
                               required autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" class="form-control" id="password" name="password" 
                               required autocomplete="current-password" placeholder="Enter your password">
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login to System
                    </button>
                </form>
                
                <div class="footer-text">
                    <i class="fas fa-shield-alt"></i> Secure Medical Surveillance System
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on password field if username is filled
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            if (usernameField.value) {
                passwordField.focus();
            } else {
                usernameField.focus();
            }
            
            // Add loading state to form
            document.querySelector('form').addEventListener('submit', function() {
                const submitBtn = document.querySelector('.btn-login');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\clinic\resources\views/auth/login.blade.php ENDPATH**/ ?>