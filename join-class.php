<?php
include("includes/auth.php");
include("config/db.php");
include("includes/lang.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"] ?? 0;
$message = "";

if (!$user_id) {
    echo "<p>Unauthorized.</p>";
    include("includes/footer.php");
    exit();
}

if (isset($_POST["code"])) {
    $code = trim($_POST["code"]);

    $stmt = $conn->prepare("SELECT * FROM classes WHERE join_code = ? LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();

    $result = $stmt->get_result();
    $class = $result->fetch_assoc();

    if ($class) {
        $class_id = (int)$class["id"];

        $check = $conn->prepare("
            SELECT *
            FROM class_members
            WHERE user_id = ?
            AND class_id = ?
            LIMIT 1
        ");

        $check->bind_param("ii", $user_id, $class_id);
        $check->execute();

        $exists = $check->get_result();

        if ($exists->num_rows === 0) {
            $stmt = $conn->prepare("
                INSERT INTO class_members (user_id, class_id)
                VALUES (?, ?)
            ");

            $stmt->bind_param("ii", $user_id, $class_id);
            $stmt->execute();

            $_SESSION["class_id"] = $class_id;

            $message = "✅ " . t("joined_successfully");
        } else {
            $_SESSION["class_id"] = $class_id;
            $message = "⚠️ " . t("already_joined");
        }
    } else {
        $message = "❌ " . t("invalid_class_code");
    }
}
?>

<form method="POST">
    <input
        type="text"
        name="code"
        placeholder="<?= t('enter_class_code') ?>"
        required
    >

    <button type="submit">
        <?= t("join_class") ?>
    </button>
</form>

<?php if ($message): ?>
    <p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php include("includes/footer.php"); ?>