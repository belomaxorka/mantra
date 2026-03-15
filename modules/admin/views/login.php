<div class="login-form">
    <h1>Login to Mantra CMS</h1>
    
    <?php if (isset($error)): ?>
        <p class="error"><?php echo $this->escape($error); ?></p>
    <?php endif; ?>
    
    <form method="POST" action="<?php echo base_url('/admin/login'); ?>">
        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" required>
        </div>
        
        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        
        <button type="submit">Login</button>
    </form>
</div>

<style>
.login-form {
    max-width: 400px;
    margin: 50px auto;
    padding: 30px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}
.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
button {
    width: 100%;
    padding: 12px;
    background: #2c3e50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
button:hover {
    background: #34495e;
}
.error {
    color: #e74c3c;
    padding: 10px;
    background: #fadbd8;
    border-radius: 4px;
}
</style>
