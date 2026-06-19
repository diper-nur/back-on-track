<?php
include("../includes/auth.php");
include("../config/db.php");

$user_id = $_SESSION["user_id"] ?? 0;
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if (!$user_id || $id <= 0) {
    header("Location: ../task.php");
    exit();
}

$stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();

header("Location: ../task.php");
exit();
?>