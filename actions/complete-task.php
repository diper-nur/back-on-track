<?php
include("../includes/auth.php");
include("../config/db.php");

$user_id = $_SESSION["user_id"] ?? 0;
$class_id = $_SESSION["class_id"] ?? 0;

if (!$user_id || !$class_id) {
    die("Unauthorized.");
}

/* =========================
   SAFE REDIRECT BACK
========================= */
function redirectBack() {
    $fallback = "../tasks.php";
    $redirect = $_SERVER["HTTP_REFERER"] ?? $fallback;

    $currentHost = $_SERVER["HTTP_HOST"] ?? "";
    $redirectHost = parse_url($redirect, PHP_URL_HOST);

    if ($redirectHost && $currentHost && strtolower($redirectHost) !== strtolower($currentHost)) {
        $redirect = $fallback;
    }

    header("Location: " . $redirect);
    exit();
}

/* =========================
   COMPLETE TASK
========================= */
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {
    redirectBack();
}

$stmt = $conn->prepare("
    UPDATE tasks
    SET status = 'completed'
    WHERE id = ?
    AND user_id = ?
    AND class_id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("iii", $id, $user_id, $class_id);
$stmt->execute();

redirectBack();
?>
