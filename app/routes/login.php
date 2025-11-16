<?php
$useFullTemplate = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $errors = [];

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } elseif ($username === '' || $password === '') {
        $errors[] = 'All fields are required.';
    } else {
        $db = Database::getInstance();
        $user = $db->getUserByUsername($username);

        // Use a valid dummy hash to mitigate timing-based user enumeration
        // This is a bcrypt hash of a random dummy string; generate once and hardcode.
        $dummyHash = '$2y$12$Q1dQ8m4qP7k1o7fQZf2XQeYb0h2a5zVJq1v5xjv0pVqYcYj4vGm2u';

        $hashToCheck = $user['password'] ?? $dummyHash;
        $pwVerified = password_verify($password, $hashToCheck);

        if ($user && $pwVerified) {
            // Prevent session fixation
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: admin.php?page=dashboard', true, 303);
            exit;
        } else {
            $errors[] = 'Invalid username or password.';
        }
    }
}
?>
<div class="card">
    <form method="post" action="admin.php?page=login" class="form-container">
        <h1>Sign In</h1>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
        <input type="text" name="username" placeholder="Username" required value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>">
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" class="btn-primary">Sign In</button>
        <?php
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo "<div class='alert alert-danger'>{$error}</div>";
            }
        }
        ?>
    </form>
</div>