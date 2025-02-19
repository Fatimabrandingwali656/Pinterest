<div class="profile">
    <img src="<?php echo $user['profile_picture'] ?? 'default_profile.jpg'; ?>" alt="Profile Picture" class="profile-picture">
    <h2><?php echo htmlspecialchars($user['username']); ?></h2>
    <p><?php echo htmlspecialchars($user['bio'] ?? 'No bio yet.'); ?></p>
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user['id']): ?>
        <button class="btn" onclick="toggleFollow(<?php echo $user['id']; ?>)">
            <?php echo $is_following ? 'Unfollow' : 'Follow'; ?>
        </button>
    <?php endif; ?>
</div>

<h3>User's Pins</h3>
<div class="pin-grid">
    <?php while($pin = $pins->fetch_assoc()): ?>
        <div class="pin">
            <img src="<?php echo htmlspecialchars($pin['image_url']); ?>" alt="<?php echo htmlspecialchars($pin['title']); ?>">
            <div class="pin-content">
                <div class="pin-title"><?php echo htmlspecialchars($pin['title']); ?></div>
                <div class="pin-board">Board: <?php echo htmlspecialchars($pin['board_name']); ?></div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

