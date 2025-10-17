<?php
require __DIR__ . '/config_mysqli.php'; 
require __DIR__ . '/csrf.php'; 

// --- Helper Functions ---
function set_flash_message($message) {
    $_SESSION['flash'] = $message;
}
function set_success_flash_message($message) {
    $_SESSION['flash_success'] = $message;
}
function redirect($url) {
    header("Location: $url");
    exit;
}
// ------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

// 1. ตรวจสอบ CSRF
if (!csrf_validate($_POST['csrf'] ?? '')) {
    set_flash_message('CSRF token validation failed. Please try again.');
    redirect('register.php');
}

// 2. รับค่าและตรวจสอบ Validation
$name = trim($_POST['name'] ?? ''); // ค่า $name นี้จะมาจากฟอร์ม (Full Name)
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

if (empty($name) || empty($email) || empty($password) || empty($password_confirm)) {
    set_flash_message('Please fill in all fields.');
    redirect('register.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash_message('Invalid email format.');
    redirect('register.php');
}

if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    set_flash_message('Password must be at least 8 characters long and include uppercase, lowercase, and numbers.');
    redirect('register.php');
}

if ($password !== $password_confirm) {
    set_flash_message('Passwords do not match.');
    redirect('register.php');
}

// 3. ตรวจสอบ Email ซ้ำ
try {
    $stmt = $mysqli->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        set_flash_message('This email address is already taken.');
        $stmt->close();
        redirect('register.php');
    }
    $stmt->close();

    // 4. ถ้าทุกอย่างผ่าน = บันทึกลงฐานข้อมูล
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // [MODIFIED] แก้ไข "full_name" เป็น "display_name" ให้ตรงกับตาราง
    $stmt = $mysqli->prepare("INSERT INTO users (display_name, email, password_hash) VALUES (?, ?, ?)");
    
    // $name (จากฟอร์ม) จะถูกผูกกับ display_name (ในตาราง)
    $stmt->bind_param("sss", $name, $email, $password_hash); 
    
    if ($stmt->execute()) {
        // สำเร็จ
        set_success_flash_message('Registration successful! You can now sign in.');
        $stmt->close();
        $mysqli->close();
        redirect('login.php');
    } else {
        // ไม่สำเร็จ (เช่น ฐานข้อมูลมีปัญหา)
        set_flash_message('An error occurred during registration. Please try again.');
        $stmt->close();
        redirect('register.php');
    }

} catch (mysqli_sql_exception $e) {
    // จัดการข้อผิดพลาด
    set_flash_message('Database error: ' . $e->getMessage());
    redirect('register.php');
}