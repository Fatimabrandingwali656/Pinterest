<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create upload directories if they don't exist
$dirs = ['uploads', 'uploads/profiles'];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0777, true)) {
            die("Failed to create directory: $dir");
        }
    }
}

// Database connection with error handling
try {
    $db = new mysqli('localhost', 'udwzwna8gnxab', 'm5hgzfxvejfa', 'dbrkuqmurjxzr9');
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Create tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        bio TEXT,
        profile_picture VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS boards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS pins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        board_id INT,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        image_url VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS follows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        follower_id INT,
        followed_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        pin_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (pin_id) REFERENCES pins(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $sql) {
    try {
        $db->query($sql);
    } catch (Exception $e) {
        die("Table creation error: " . $e->getMessage());
    }
}

function sanitize($data) {
    global $db;
    return mysqli_real_escape_string($db, $data);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['signup'])) {
        $username = sanitize($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = sanitize($_POST['email']);
        
        $stmt = $db->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $password, $email);
        
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['username'] = $username;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
        exit;
    } elseif (isset($_POST['login'])) {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid password']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
        $stmt->close();
        exit;
    } elseif (isset($_POST['upload'])) {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'User not logged in']);
            exit;
        }

        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $board_id = sanitize($_POST['board_id']);

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'File upload failed']);
            exit;
        }

        $image = $_FILES['image'];
        $target_dir = "uploads/";
        $target_file = $target_dir . uniqid() . '_' . basename($image["name"]);
        
        if (move_uploaded_file($image["tmp_name"], $target_file)) {
            $stmt = $db->prepare("INSERT INTO pins (user_id, board_id, title, description, image_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $_SESSION['user_id'], $board_id, $title, $description, $target_file);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
        }
        exit;
    } elseif (isset($_POST['create_board'])) {
        $board_name = sanitize($_POST['board_name']);
        $description = sanitize($_POST['description']);
        
        $stmt = $db->prepare("INSERT INTO boards (user_id, name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $_SESSION['user_id'], $board_name, $description);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
        exit;
    } elseif (isset($_POST['follow'])) {
        $follower_id = $_SESSION['user_id'];
        $followed_id = sanitize($_POST['followed_id']);
        
        $stmt = $db->prepare("INSERT INTO follows (follower_id, followed_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $follower_id, $followed_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
        exit;
    } elseif (isset($_POST['like'])) {
        $user_id = $_SESSION['user_id'];
        $pin_id = sanitize($_POST['pin_id']);
        
        $stmt = $db->prepare("INSERT INTO likes (user_id, pin_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $pin_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
        exit;
    } elseif (isset($_POST['update_profile'])) {
        $bio = sanitize($_POST['bio']);
        $user_id = $_SESSION['user_id'];
        
        if ($_FILES['profile_picture']['name']) {
            $profile_pic = $_FILES['profile_picture'];
            $target_dir = "uploads/profiles/";
            $target_file = $target_dir . uniqid() . '_' . basename($profile_pic["name"]);
            
            if (move_uploaded_file($profile_pic["tmp_name"], $target_file)) {
                $stmt = $db->prepare("UPDATE users SET bio = ?, profile_picture = ? WHERE id = ?");
                $stmt->bind_param("ssi", $bio, $target_file, $user_id);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to upload profile picture']);
                exit;
            }
        } else {
            $stmt = $db->prepare("UPDATE users SET bio = ? WHERE id = ?");
            $stmt->bind_param("si", $bio, $user_id);
        }
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
        exit;
    } elseif (isset($_POST['logout'])) {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }
}

$pins = $db->query("SELECT p.*, u.username, b.name as board_name FROM pins p JOIN users u ON p.user_id = u.id LEFT JOIN boards b ON p.board_id = b.id ORDER BY p.created_at DESC");

if (isset($_SESSION['user_id'])) {
    $user_boards = $db->query("SELECT * FROM boards WHERE user_id = " . $_SESSION['user_id']);
    $user_info = $db->query("SELECT * FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PinClone - Your Visual Discovery</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        .container {
            width: 90%;
            margin: auto;
            overflow: hidden;
            padding: 20px 0;
        }
        header {
            background: #fff;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #e60023;
        }
        .nav-links {
            display: flex;
            list-style: none;
        }
        .nav-links li {
            margin-left: 20px;
        }
        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #e60023;
            color: #fff;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: #ad081b;
        }
        .search-bar {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            position: relative;
        }
        .search-bar input {
            width: 100%;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .search-bar button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
        }
        .pin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }
        .pin {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .pin:hover {
            transform: translateY(-5px);
        }
        .pin img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .pin-content {
            padding: 15px;
        }
        .pin-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .pin-user {
            font-size: 0.9rem;
            color: #666;
        }
        .pin-board {
            font-size: 0.8rem;
            color: #0066cc;
            margin-top: 5px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 10px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        form input,
        form textarea,
        form select {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        form button {
            align-self: flex-start;
        }
        .profile {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
        }
        .profile h2 {
            margin-bottom: 10px;
        }
        .profile p {
            color: #666;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">PinClone</div>
            <ul class="nav-links">
                <li><a href="#" onclick="showHome()">Home</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="#" onclick="showUploadForm()">Upload</a></li>
                    <li><a href="#" onclick="showCreateBoardForm()">Create Board</a></li>
                    <li><a href="#" onclick="showProfile()">Profile</a></li>
                    <li><a href="#" onclick="logout()">Logout</a></li>
                <?php else: ?>
                    <li><a href="#" onclick="showLoginForm()">Login</a></li>
                    <li><a href="#" onclick="showSignupForm()">Signup</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div class="container">
        <div class="search-bar">
            <input type="text" placeholder="Search...">
            <button>🔍</button>
        </div>

        <div id="content">
            <div class="pin-grid">
                <?php while($pin = $pins->fetch_assoc()): ?>
                    <div class="pin">
                        <img src="<?php echo htmlspecialchars($pin['image_url']); ?>" alt="<?php echo htmlspecialchars($pin['title']); ?>">
                        <div class="pin-content">
                            <div class="pin-title"><?php echo htmlspecialchars($pin['title']); ?></div>
                            <div class="pin-user">by <?php echo htmlspecialchars($pin['username']); ?></div>
                            <div class="pin-board">Board: <?php echo htmlspecialchars($pin['board_name']); ?></div>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="btn" onclick="likePin(<?php echo $pin['id']; ?>)">Like</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('loginModal')">&times;</span>
            <h2>Login</h2>
            <form id="loginForm">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="btn">Login</button>
            </form>
        </div>
    </div>

    <div id="signupModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('signupModal')">&times;</span>
            <h2>Sign Up</h2>
            <form id="signupForm">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="btn">Sign Up</button>
            </form>
        </div>
    </div>

    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('uploadModal')">&times;</span>
            <h2>Upload Pin</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="text" name="title" placeholder="Title" required>
                <textarea name="description" placeholder="Description" required></textarea>
                <select name="board_id" required>
                    <?php while($board = $user_boards->fetch_assoc()): ?>
                        <option value="<?php echo $board['id']; ?>"><?php echo htmlspecialchars($board['name']); ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="file" name="image" accept="image/*" required>
                <button type="submit" class="btn">Upload</button>
            </form>
        </div>
    </div>

    <div id="createBoardModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('createBoardModal')">&times;</span>
            <h2>Create Board</h2>
            <form id="createBoardForm">
                <input type="text" name="board_name" placeholder="Board Name" required>
                <textarea name="description" placeholder="Board Description"></textarea>
                <button type="submit" class="btn">Create Board</button>
            </form>
        </div>
    </div>

    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('profileModal')">&times;</span>
            <h2>Your Profile</h2>
            <div class="profile">
                <img src="<?php echo $user_info['profile_picture'] ?? 'default_profile.jpg'; ?>" alt="Profile Picture" class="profile-picture">
                <h2><?php echo htmlspecialchars($user_info['username']); ?></h2>
                <p><?php echo htmlspecialchars($user_info['bio'] ?? 'No bio yet.'); ?></p>
            </div>
            <form id="updateProfileForm" enctype="multipart/form-data">
                <textarea name="bio" placeholder="Update your bio"><?php echo htmlspecialchars($user_info['bio'] ?? ''); ?></textarea>
                <input type="file" name="profile_picture" accept="image/*">
                <button type="submit" class="btn">Update Profile</button>
            </form>
        </div>
    </div>

    <script>
        function showLoginForm() {
            document.getElementById('loginModal').style.display = 'block';
        }

        function showSignupForm() {
            document.getElementById('signupModal').style.display = 'block';
        }

        function showUploadForm() {
            document.getElementById('uploadModal').style.display = 'block';
        }

        function showCreateBoardForm() {
            document.getElementById('createBoardModal').style.display = 'block';
        }

        function showProfile() {
            document.getElementById('profileModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showHome() {
            location.reload();
        }

        function logout() {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'logout=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during logout. Please try again.');
            });
        }

        function likePin(pinId) {
            const formData = new FormData();
            formData.append('like', '1');
            formData.append('pin_id', pinId);

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pin liked!');
                } else {
                    alert('Failed to like pin: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while liking the pin. Please try again.');
            });
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        document.getElementById('loginForm').onsubmit = function(e) {
            e.preventDefault();
            submitForm(this, 'login');
        };

        document.getElementById('signupForm').onsubmit = function(e) {
            e.preventDefault();
            submitForm(this, 'signup');
        };

        document.getElementById('uploadForm').onsubmit = function(e) {
            e.preventDefault();
            submitForm(this, 'upload');
        };

        document.getElementById('createBoardForm').onsubmit = function(e) {
            e.preventDefault();
            submitForm(this, 'create_board');
        };

        document.getElementById('updateProfileForm').onsubmit = function(e) {
            e.preventDefault();
            submitForm(this, 'update_profile');
        };

        function submitForm(form, action) {
            const formData = new FormData(form);
            formData.append(action, '1');

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal(action + 'Modal');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'An unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    </script>
</body>
</html>

