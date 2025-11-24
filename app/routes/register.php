<?php
$title = 'Register Admin';
$useFullTemplate = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    $errors = [];

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } elseif (empty($username) || empty($password) || empty($password_confirm)) {
        $errors[] = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif (strlen($password) > 80) {
        $errors[] = "Password must not exceed 80 characters.";
    } elseif (strlen($username) > 50) {
        $errors[] = "Username must not exceed 50 characters.";
    } elseif ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    } else {
        $db = Database::getInstance();
        if ($db->createUser($username, $password, 'admin')) {
            header('Location: index.php?page=login');
            exit;
        } else {
            $errors[] = "Failed to create admin user. Please try again.";
        }
    }
}
?>
<div class="card">
    <form method="post" action="index.php" class="form-container">
        <h1>Register</h1>
        <p>Please create your admin account via the form below. Any additional users can be created later from the dashboard.</p>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
        <input type="text" name="username" placeholder="Username" required value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>">
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="password_confirm" placeholder="Confirm Password" required>
        <button type="submit" class="btn-primary">Register</button>
        <?php
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo "<div class='alert alert-danger'>{$error}</div>";
            }
        }
        ?>
    </form>

</div>
