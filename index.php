<?php
session_start();
include 'db.php';

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $position = $_POST['position'];

    if ($position == 'admin') {
        $query = "SELECT * FROM admin WHERE username='$username' AND password='$password'";
        $result = $conn->query($query);
        if ($result->num_rows > 0) {
            $admin_data = $result->fetch_assoc();
            
            // Unify session key to $_SESSION['user']
            $_SESSION['user'] = [
                'id'          => $admin_data['id'],
                'name'        => $admin_data['name'] ?? $admin_data['username'], // fallback to username if name column doesn't exist
                'position'    => 'admin', // Manually hardcode this so dashboard checks pass flawlessly
                'profile_pic' => $admin_data['profile_pic'] ?? 'default.png'
            ];
            
            header("Location: admin_dashboard.php");
            exit();
        }
    } else {
        $query = "SELECT * FROM user WHERE username='$username' AND password='$password'";
        $result = $conn->query($query);
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            
            // Standard User assignment
            $_SESSION['user'] = [
                'id'          => $user_data['id'],
                'name'        => $user_data['name'],
                'position'    => $user_data['position'], 
                'profile_pic' => $user_data['profile_pic']
            ];
            
            header("Location: user_dashboard.php");
            exit();
        }
    }
    $error = "Invalid Username or Password!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIMS 2.0 - Login</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            margin: 0; 
            padding: 0;
            display: flex; 
            height: 100vh; 
            overflow: hidden;
            background: #fff;
        }

        /* --- LEFT SIDE: 70% Design & Headers --- */
        .design-side {
            width: 70%;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 40px;
            text-align: center;
            overflow: hidden;
        }

        /* --- ACTIVE ARTISTIC NEON BACKGROUNDS --- */
        .neon-light {
            position: absolute;
            border-radius: 50%;
            mix-blend-mode: multiply;
            pointer-events: none;
        }
        
        /* Light 1: Floating Cyan/Teal sphere */
        .neon-1 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(0,240,255,0.45) 0%, rgba(0,240,255,0) 70%);
            filter: blur(60px);
            top: -10%;
            left: -5%;
            animation: driftCyan 22s infinite ease-in-out alternate;
        }
        
        /* Light 2: Drifting Hot Pink sphere */
        .neon-2 {
            width: 550px;
            height: 550px;
            background: radial-gradient(circle, rgba(255,0,127,0.4) 0%, rgba(255,0,127,0) 70%);
            filter: blur(70px);
            bottom: -15%;
            right: -5%;
            animation: driftPink 26s infinite ease-in-out alternate;
        }
        
        /* Light 3: Morphing Purple core center-left */
        .neon-3 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(112,0,255,0.35) 0%, rgba(112,0,255,0) 70%);
            filter: blur(50px);
            top: 30%;
            left: 35%;
            animation: driftPurple 18s infinite ease-in-out alternate;
        }

        /* --- KEYFRAMES FOR ACTIVE MOTION --- */
        @keyframes driftCyan {
            0% { transform: translate(0px, 0px) scale(1) rotate(0deg); }
            33% { transform: translate(120px, 80px) scale(1.15) rotate(45deg); filter: blur(50px); }
            66% { transform: translate(50px, 180px) scale(0.9) rotate(90deg); }
            100% { transform: translate(180px, -20px) scale(1.2) rotate(135deg); filter: blur(75px); }
        }

        @keyframes driftPink {
            0% { transform: translate(0px, 0px) scale(1.1); }
            40% { transform: translate(-140px, -90px) scale(0.85); filter: blur(60px); }
            75% { transform: translate(-60px, -200px) scale(1.25); }
            100% { transform: translate(-200px, 40px) scale(1.0); filter: blur(80px); }
        }

        @keyframes driftPurple {
            0% { transform: translate(0px, 0px) scale(0.9); }
            50% { transform: translate(-90px, 70px) scale(1.3); }
            100% { transform: translate(100px, -60px) scale(0.95); }
        }

        /* Content elements cleanly layered over moving neon */
        .design-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
        }
        .logo {
            width: 150px;
            height: auto;
            margin-bottom: 30px;
            filter: drop-shadow(0 6px 12px rgba(0,0,0,0.08));
        }
        .design-content h1 {
            color: #1a202c;
            font-size: 2.5rem;
            margin: 0 0 15px 0;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .design-content h2 {
            color: #4a5568;
            font-size: 1.3rem;
            font-weight: 400;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* --- RIGHT SIDE: 30% Login Form --- */
        .login-side {
            width: 30%;
            background: #545454;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            border-left: 1px solid #e2e8f0;
            box-shadow: -10px 0 25px rgba(0,0,0,0.02);
            position: relative;
            z-index: 3;
        }
        .login-card { 
            width: 100%;
            max-width: 340px; 
        }
        .login-card h3 { 
            color: #dcdcdc; 
            margin-bottom: 25px; 
            margin-top: 0; 
            font-size: 1.6rem;
            font-weight: 700;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; 
            font-size: 13px; 
            color: #d0d0d0; 
        }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #cbd5e0; 
            border-radius: 6px; 
            font-size: 14px; 
            background-color: #fff;
            color: #a4a4a4;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
        }
        
        /* Show Password Styling */
        .show-password-container {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            cursor: pointer;
            user-select: none;
        }
        .show-password-container input {
            width: auto;
            margin-right: 8px;
            cursor: pointer;
        }
        .show-password-container label {
            font-size: 13px;
            color: #ffffff;
            cursor: pointer;
            font-weight: 500;
        }

        .btn { 
            width: 100%; 
            padding: 12px; 
            background: #007bff; 
            border: none; 
            color: white; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: bold; 
            font-size: 15px; 
            transition: background 0.2s, transform 0.1s; 
        }
        .btn:hover { background: #0056b3; }
        .btn:active { transform: scale(0.98); }
        
        .error { 
            color: #e53e3e; 
            background: #fff5f5; 
            border: 1px solid #fed7d7; 
            padding: 12px; 
            border-radius: 6px; 
            text-align: center; 
            margin-bottom: 20px; 
            font-size: 14px; 
            font-weight: 500;
        }

        /* Responsive design for smaller screens */
        @media (max-width: 1024px) {
            body { flex-direction: column; height: auto; overflow: auto; }
            .design-side { width: 100%; padding: 60px 20px; }
            .login-side { width: 100%; border-left: none; border-top: 1px solid #e2e8f0; padding: 60px 20px; }
        }
    </style>
</head>
<body>

    <div class="design-side">
        <div class="neon-light neon-1"></div>
        <div class="neon-light neon-2"></div>
        <div class="neon-light neon-3"></div>

        <div class="design-content">
            <img src="logo.png" alt="OPPDC Logo" class="logo">
            <h1>Administrative Information Management System<br>(AIMS 2.0)</h1>
            <h2>Office of the Provincial Planning and Development Coordinator (OPPDC)</h2>
        </div>
    </div>

    <div class="login-side">
        <div class="login-card">
            <h3>Account Login</h3>
            
            <?php if($error) echo "<div class='error'>$error</div>"; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="passwordField" name="password" required>
                </div>

                <div class="show-password-container" onclick="togglePassword()">
                    <input type="checkbox" id="showPasswordCheckbox">
                    <label for="showPasswordCheckbox">Show Password</label>
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="position">
                        <option value="user">User</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            var passwordField = document.getElementById("passwordField");
            var checkbox = document.getElementById("showPasswordCheckbox");
            
            if (event.target.id !== 'showPasswordCheckbox') {
                checkbox.checked = !checkbox.checked;
            }

            if (checkbox.checked) {
                passwordField.type = "text";
            } else {
                passwordField.type = "password";
            }
        }
    </script>
</body>
</html>