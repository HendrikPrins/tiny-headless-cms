<?php
$useFullTemplate = true;
?>
<div class="card">
    <form method="post" action="admin.php?page=register" class="form-container">
        <h1>Register</h1>
        <p>Please create your admin account via the form below. Any additional users can be created later from the dashboard.</p>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="password_confirm" placeholder="Confirm Password" required>
        <button type="submit" class="btn-primary">Register</button>
    </form>

</div>
