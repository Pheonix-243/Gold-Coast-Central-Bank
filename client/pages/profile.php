<?php
require_once('../includes/auth.php');
// require_once('../includes/db.php');

// Fetch profile data
$account = $_SESSION['client_account'];
$sql = "SELECT * FROM accountsholder WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc($stmt->get_result());

// Fetch account info
$sql = "SELECT * FROM accounts_info WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$accountInfo = mysqli_fetch_assoc($stmt->get_result());

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = false;
    
    // Basic validation
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $postal = trim($_POST['postal'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $houseAddress = trim($_POST['houseaddress'] ?? '');
    
    if (empty($name)) $errors[] = "Full name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($contact) || !preg_match('/^[0-9]{10,15}$/', $contact)) $errors[] = "Valid contact number is required";
    
    if (empty($errors)) {
        // Handle profile picture upload
        $imageData = $profile['image'];
        if (!empty($_FILES['profile_picture']['tmp_name'])) {
            $imageInfo = getimagesize($_FILES['profile_picture']['tmp_name']);
            if ($imageInfo !== false) {
                $imageData = file_get_contents($_FILES['profile_picture']['tmp_name']);
            } else {
                $errors[] = "Invalid image file";
            }
        }
        
        if (empty($errors)) {
            // Update profile in database
            $sql = "UPDATE accountsholder SET 
                    name = ?, 
                    email = ?, 
                    contect = ?, 
                    postal = ?, 
                    city = ?, 
                    houseaddress = ?, 
                    image = ?
                    WHERE account = ?";
            
            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssss", 
                $name, $email, $contact, $postal, $city, $houseAddress, $imageData, $account);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = true;
                // Update session data
                $_SESSION['client_name'] = $name;
                // Refresh profile data
                $profile = array_merge($profile, [
                    'name' => $name,
                    'email' => $email,
                    'contect' => $contact,
                    'postal' => $postal,
                    'city' => $city,
                    'houseaddress' => $houseAddress,
                    'image' => $imageData
                ]);
            } else {
                $errors[] = "Failed to update profile: " . mysqli_error($con);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Profile Management</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../dashboard/style.css">
    <link rel="stylesheet" href="profile.css">
</head>

<body class="light">
    <div class="container">
        <nav class="sidebar" id="sidebar">
            <button id="btn_close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path d="M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z" />
                </svg>
            </button>

            <div class="logo">
                <img src="../../gccb_logos/logo-transparent.svg" alt="">
            </div>

            <div class="nav_links">
                <a href="../dashboard/" class="nav_link" aria-label="overview">
                    <div class="nav_link_icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="nav_link_text">Dashboard</div>
                </a>

                <a href="../transactions/deposit.php" class="nav_link" aria-label="deposit">
                    <div class="nav_link_icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="nav_link_text">Deposit</div>
                </a>

                <a href="../transactions/withdrawal.php" class="nav_link" aria-label="withdraw">
                    <div class="nav_link_icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="nav_link_text">Withdraw</div>
                </a>

                <a href="../transactions/transfer.php" class="nav_link" aria-label="transfer">
                    <div class="nav_link_icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="nav_link_text">Transfer</div>
                </a>

                <a href="../transactions/history.php" class="nav_link" aria-label="history">
                    <div class="nav_link_icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="nav_link_text">History</div>
                </a>

                <a href="../profile/view.php" class="nav_link active" aria-label="profile">
                    <div class="nav_link_icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="nav_link_text">Profile</div>
                </a>

                <a href="../settings/password.php" class="nav_link" aria-label="settings">
                    <div class="nav_link_icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="nav_link_text">Security</div>
                </a>
            </div>

            <div class="profile">
                <div class="img_with_name">
                    <img src="../images/profile_pic3.webp" alt="">
                    <div class="profile_text">
                        <p class="name"><?= htmlspecialchars($_SESSION['client_name']) ?></p>
                        <p class="occupation"><?= htmlspecialchars($_SESSION['client_account_type'] ?? 'Account') ?></p>
                    </div>
                </div>
                <a href="../scripts/logout.php" aria-label="logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <section class="main_content">
            <div class="topbar">
                <button id="menu_btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                        <path fill="none" d="M0 0h24v24H0z" />
                        <path d="M3 4h18v2H3V4zm0 7h12v2H3v-2zm0 7h18v2H3v-2z" /></svg>
                </button>
                <div class="overview_text">
                    <p class="title">My Profile</p>
                    <p class="desc">Update your personal information and account details</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="search" class="topbar_icon">
                        <i class="fas fa-search"></i>
                    </a>
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>

            <div class="profile-container">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success) && $success): ?>
                    <div class="alert alert-success">
                        Profile updated successfully!
                    </div>
                <?php endif; ?>
                
                <!-- <div class="profile-header">
                    <h1>My Profile</h1>
                    <p class="subtitle">Manage your personal information and account details</p>
                </div> -->
                
                <div class="profile-row">
                    <div class="profile-main">
                        <div class="profile-card">
                            <form method="POST" class="profile-form" enctype="multipart/form-data">
                                <div class="profile-picture-container">
                                    <?php if (!empty($profile['image'])): ?>
                                        <img src="data:image/jpeg;base64,<?= base64_encode($profile['image']) ?>" 
                                             alt="Profile Picture" class="profile-picture">
                                    <?php else: ?>
                                        <img src="../images/default-profile.png" 
                                             alt="Profile Picture" class="profile-picture">
                                    <?php endif; ?>
                                    
                                    <div class="profile-picture-upload">
                                        <label for="profile_picture" class="upload-btn">
                                            <i class="fas fa-camera"></i> Change Photo
                                        </label>
                                        <input type="file" id="profile_picture" name="profile_picture" 
                                               accept="image/jpeg,image/png">
                                        <small class="text-muted">Max 2MB (JPEG or PNG)</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= htmlspecialchars($profile['name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($profile['email'] ?? '') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contact">Phone Number</label>
                                    <input type="tel" class="form-control" id="contact" name="contact" 
                                           value="0<?= htmlspecialchars($profile['contect'] ?? '') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="postal">Postal Code</label>
                                    <input type="text" class="form-control" id="postal" name="postal" 
                                           value="<?= htmlspecialchars($profile['postal'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="houseaddress">House Address</label>
                                    <textarea class="form-control" id="houseaddress" name="houseaddress" 
                                              rows="3"><?= htmlspecialchars($profile['houseaddress'] ?? '') ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn-profile">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="profile-sidebar">
                        <div class="account-summary">
                            <h3>Account Summary</h3>
                            <div class="summary-item">
                                <i class="fas fa-user"></i>
                                <div>
                                    <p class="label">Account Holder</p>
                                    <p class="value"><?= htmlspecialchars($profile['name'] ?? '') ?></p>
                                </div>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-id-card"></i>
                                <div>
                                    <p class="label">Account Number</p>
                                    <p class="value"><?= htmlspecialchars($account) ?></p>
                                </div>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-wallet"></i>
                                <div>
                                    <p class="label">Account Type</p>
                                    <p class="value"><?= htmlspecialchars($accountInfo['account_type'] ?? '') ?></p>
                                </div>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-calendar-alt"></i>
                                <div>
                                    <p class="label">Member Since</p>
                                    <p class="value"><?= date('M d, Y', strtotime($accountInfo['registerdate'] ?? '')) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="security-info">
                            <div class="security-item">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <h4>Account Security</h4>
                                    <p>Your account is protected with bank-level security</p>
                                </div>
                            </div>
                            <div class="security-item">
                                <i class="fas fa-lock"></i>
                                <div>
                                    <h4>Last Login</h4>
                                    <p><?= date('M d, Y H:i', strtotime($accountInfo['last_login'] ?? '')) ?></p>
                                </div>
                            </div>
                            <div class="security-item">
                                <i class="fas fa-key"></i>
                                <div>
                                    <h4>Password</h4>
                                    <p>Last changed: <?= date('M d, Y', strtotime('-3 months')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        // Mobile sidebar toggle
        document.getElementById('menu_btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show_sidebar');
        });

        document.getElementById('btn_close').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show_sidebar');
        });

        // Preview profile picture before upload
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.querySelector('.profile-picture').src = event.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>