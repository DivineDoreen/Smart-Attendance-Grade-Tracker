<?php
// Generate a password hash for 'adminpass123'
$password = 'adminpass123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Display the hash
echo "Password hash for 'adminpass123': <br>";
echo $hash;
?>